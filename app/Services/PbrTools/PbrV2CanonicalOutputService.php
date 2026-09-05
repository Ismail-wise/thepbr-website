<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PbrV2CanonicalOutputService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $components,
        private readonly PbrV2LifecycleService $lifecycle
    ) {
    }

    public function states(): array
    {
        return $this->configArray('states');
    }

    public function publicationContract(
        string $domainKey,
        string $componentKey
    ): array {
        $component = $this->component(
            $domainKey,
            $componentKey
        );

        $this->assertCanonicalContract(
            $componentKey,
            $component
        );

        $publish = $this->lifecycle->transitionDecision(
            $componentKey,
            'proposed',
            'approved_current'
        );

        $supersede = $this->lifecycle->transitionDecision(
            $componentKey,
            'approved_current',
            'superseded'
        );

        if (
            ($publish['decision'] ?? null) !== 'reserved'
            || ($publish['authority'] ?? null)
                !== 'approval_engine'
            || ($publish['publication_owner'] ?? null)
                !== 'canonical_output_foundation'
        ) {
            throw new LogicException(
                'F7 publication boundary does not match the frozen '
                .'F5/F6 lifecycle contract.'
            );
        }

        if (
            ($supersede['decision'] ?? null) !== 'reserved'
            || ($supersede['authority'] ?? null)
                !== 'canonical_output_foundation'
            || ($supersede['publication_owner'] ?? null)
                !== 'canonical_output_foundation'
        ) {
            throw new LogicException(
                'F7 supersession boundary does not match the frozen '
                .'canonical lifecycle contract.'
            );
        }

        return [
            'domain_key' => $domainKey,
            'component_key' => $componentKey,
            'component_type' => $component['component_type'],
            'canonical_capability' =>
                $component['canonical_capability'],
            'write_mode' => $component['write_mode'],
            'approval_required' =>
                $component['approval_required'],
            'publish_authority_contract' =>
                $component['publish_authority'],
            'stale_source_guard' =>
                $component['stale_source_guard'],
            'approval_authority' => 'approval_engine',
            'publication_owner' =>
                'canonical_output_foundation',
            'proposal_state' => 'proposed',
            'approved_target_state' =>
                'approved_current',
            'superseded_state' => 'superseded',
        ];
    }

    public function current(
        PartnershipWorkspace $workspace,
        string $domainKey,
        string $componentKey
    ): ?WorkspaceV2CanonicalRevision {
        $this->publicationContract(
            $domainKey,
            $componentKey
        );

        return WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', $domainKey)
            ->where('component_key', $componentKey)
            ->where('state', 'approved_current')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();
    }

    public function history(
        PartnershipWorkspace $workspace,
        string $domainKey,
        string $componentKey
    ): Collection {
        $this->publicationContract(
            $domainKey,
            $componentKey
        );

        return WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', $domainKey)
            ->where('component_key', $componentKey)
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get();
    }

    public function publish(
        PartnershipWorkspace $workspace,
        string $domainKey,
        string $componentKey,
        array $payload,
        array $approvalDecision,
        ?User $actor = null
    ): WorkspaceV2CanonicalRevision {
        $contract = $this->publicationContract(
            $domainKey,
            $componentKey
        );

        $this->assertApprovedHandoff(
            $contract,
            $approvalDecision
        );

        return DB::transaction(
            function () use (
                $workspace,
                $domainKey,
                $componentKey,
                $payload,
                $approvalDecision,
                $actor,
                $contract
            ): WorkspaceV2CanonicalRevision {
                $identity = WorkspaceV2CanonicalRevision::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('domain_key', $domainKey)
                    ->where('component_key', $componentKey);

                $latest = (clone $identity)
                    ->orderByDesc('revision')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $currents = (clone $identity)
                    ->where('state', 'approved_current')
                    ->orderByDesc('revision')
                    ->lockForUpdate()
                    ->get();

                if ($currents->count() > 1) {
                    throw new LogicException(
                        'Canonical integrity violation: more than one '
                        .'approved_current revision exists for the same '
                        .'stable identity.'
                    );
                }

                $now = now();

                $current = $currents->first();

                if ($current) {
                    $current->forceFill([
                        'state' => 'superseded',
                        'superseded_at' => $now,
                    ])->save();
                }

                $revision = ((int) ($latest?->revision ?? 0)) + 1;

                return WorkspaceV2CanonicalRevision::create([
                    'workspace_id' => $workspace->id,
                    'domain_key' => $domainKey,
                    'component_key' => $componentKey,
                    'component_type' =>
                        $contract['component_type'],
                    'revision' => $revision,
                    'state' => 'approved_current',
                    'payload' => $payload,
                    'approval_decision' =>
                        $approvalDecision,
                    'published_by_user_id' =>
                        $actor?->id,
                    'published_at' => $now,
                    'superseded_at' => null,
                ]);
            }
        );
    }

    private function component(
        string $domainKey,
        string $componentKey
    ): array {
        $domainKey = trim($domainKey);
        $componentKey = trim($componentKey);

        if ($domainKey === '') {
            throw new InvalidArgumentException(
                'V2 domain key is required.'
            );
        }

        if ($componentKey === '') {
            throw new InvalidArgumentException(
                'V2 component key is required.'
            );
        }

        $component = $this->components->find(
            $componentKey
        );

        if (! is_array($component)) {
            throw new InvalidArgumentException(
                "Unknown V2 component [{$componentKey}]."
            );
        }

        if (
            ($component['domain_key'] ?? null)
            !== $domainKey
        ) {
            throw new DomainException(
                'V2 canonical publication identity mismatch: '
                .$componentKey
                .' does not belong to '
                .$domainKey
                .'.'
            );
        }

        return $component;
    }

    private function assertCanonicalContract(
        string $componentKey,
        array $component
    ): void {
        if (
            ! $this->lifecycle
                ->canEnterCanonicalLifecycle(
                    $componentKey
                )
        ) {
            throw new DomainException(
                "V2 component [{$componentKey}] is not canonical."
            );
        }

        $type = $component['component_type'] ?? null;

        if (
            ! in_array(
                $type,
                ['rule', 'register', 'plan'],
                true
            )
        ) {
            throw new DomainException(
                'Unsupported V2 canonical component type.'
            );
        }

        if (
            ($component['canonical_capability'] ?? null)
            !== $type
            || ($component['approval_required'] ?? null)
                !== true
            || ($component['publish_authority'] ?? null)
                !== 'approval_engine_only'
            || ($component['write_mode'] ?? null)
                !== 'revisioned_current'
            || ($component['stale_source_guard'] ?? null)
                !== true
        ) {
            throw new DomainException(
                'V2 canonical publication contract is incomplete.'
            );
        }
    }

    private function assertApprovedHandoff(
        array $contract,
        array $decision
    ): void {
        $required = $this->configArray(
            'required_approval_decision'
        );

        foreach ($required as $key => $expected) {
            if (
                ! array_key_exists($key, $decision)
                || $decision[$key] !== $expected
            ) {
                throw new DomainException(
                    'Invalid F6 approval handoff at ['
                    .$key
                    .'].'
                );
            }
        }

        $identity = [
            'domain_key' =>
                $contract['domain_key'],
            'component_key' =>
                $contract['component_key'],
            'component_type' =>
                $contract['component_type'],
            'canonical_capability' =>
                $contract['canonical_capability'],
        ];

        foreach ($identity as $key => $expected) {
            if (
                ! array_key_exists($key, $decision)
                || $decision[$key] !== $expected
            ) {
                throw new DomainException(
                    'F6/F7 publication identity mismatch at ['
                    .$key
                    .'].'
                );
            }
        }
    }

    private function configArray(
        string $key
    ): array {
        $value = config(
            'pbr_v2_canonical_output.'.$key,
            []
        );

        return is_array($value)
            ? $value
            : [];
    }
}
