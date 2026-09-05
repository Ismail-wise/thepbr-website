<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2ReviewAuditEvent;
use App\Models\WorkspaceV2ReviewCase;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PbrV2ReviewAuditService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $components,
        private readonly PbrV2CanonicalOutputService $canonical
    ) {
    }

    public function statuses(): array
    {
        return $this->configArray('case_statuses');
    }

    public function resolutions(): array
    {
        return $this->configArray('resolutions');
    }

    public function triggerTypes(): array
    {
        return $this->configArray('trigger_types');
    }

    public function auditEventTypes(): array
    {
        return $this->configArray('audit_event_types');
    }

    public function openReview(
        PartnershipWorkspace $workspace,
        string $producerDomainKey,
        string $producerComponentKey,
        string $targetDomainKey,
        string $targetComponentKey,
        int $targetRevision,
        string $triggerType,
        array $triggerContext = [],
        ?User $actor = null
    ): WorkspaceV2ReviewCase {
        $producer = $this->producer(
            $producerDomainKey,
            $producerComponentKey
        );

        $this->assertProducerMayReviewTarget(
            $producer,
            $targetDomainKey
        );

        $this->canonical->publicationContract(
            $targetDomainKey,
            $targetComponentKey
        );

        $triggerType = trim($triggerType);

        if (
            ! in_array(
                $triggerType,
                $this->triggerTypes(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown V2 review trigger type.'
            );
        }

        if ($targetRevision < 1) {
            throw new InvalidArgumentException(
                'Target canonical revision must be positive.'
            );
        }

        return DB::transaction(
            function () use (
                $workspace,
                $producerDomainKey,
                $producerComponentKey,
                $targetDomainKey,
                $targetComponentKey,
                $targetRevision,
                $triggerType,
                $triggerContext,
                $actor
            ): WorkspaceV2ReviewCase {
                $target = WorkspaceV2CanonicalRevision::query()
                    ->where(
                        'workspace_id',
                        $workspace->id
                    )
                    ->where(
                        'domain_key',
                        $targetDomainKey
                    )
                    ->where(
                        'component_key',
                        $targetComponentKey
                    )
                    ->where(
                        'revision',
                        $targetRevision
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $target) {
                    throw new DomainException(
                        'Target V2 canonical revision was not found.'
                    );
                }

                if (
                    $target->state
                    !== config(
                        'pbr_v2_review_audit.'
                        .'target_state_required_on_open'
                    )
                ) {
                    throw new DomainException(
                        'A V2 review may open only against '
                        .'approved_current canonical revision.'
                    );
                }

                $now = now();

                $case = WorkspaceV2ReviewCase::create([
                    'workspace_id' => $workspace->id,
                    'target_canonical_revision_id' =>
                        $target->id,
                    'target_domain_key' =>
                        $targetDomainKey,
                    'target_component_key' =>
                        $targetComponentKey,
                    'target_revision' =>
                        $target->revision,
                    'producer_domain_key' =>
                        $producerDomainKey,
                    'producer_component_key' =>
                        $producerComponentKey,
                    'trigger_type' => $triggerType,
                    'trigger_context' =>
                        $triggerContext,
                    'status' => 'open',
                    'resolution' => null,
                    'resolution_note' => null,
                    'opened_by_user_id' => $actor?->id,
                    'resolved_by_user_id' => null,
                    'opened_at' => $now,
                    'resolved_at' => null,
                ]);

                $this->appendAuditEvent(
                    $workspace,
                    $case,
                    'review_opened',
                    $actor,
                    [
                        'target_domain_key' =>
                            $targetDomainKey,
                        'target_component_key' =>
                            $targetComponentKey,
                        'target_revision' =>
                            $target->revision,
                        'producer_domain_key' =>
                            $producerDomainKey,
                        'producer_component_key' =>
                            $producerComponentKey,
                        'trigger_type' =>
                            $triggerType,
                        'trigger_context' =>
                            $triggerContext,
                    ],
                    $now
                );

                return $case;
            }
        );
    }

    public function resolveReview(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        string $resolution,
        ?string $resolutionNote = null,
        ?User $actor = null
    ): WorkspaceV2ReviewCase {
        $resolution = trim($resolution);

        if (
            ! in_array(
                $resolution,
                $this->configArray(
                    'direct_resolutions'
                ),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Direct review resolution must be '
                .'no_change or dismissed.'
            );
        }

        return DB::transaction(
            function () use (
                $workspace,
                $case,
                $resolution,
                $resolutionNote,
                $actor
            ): WorkspaceV2ReviewCase {
                $locked = $this->lockCase(
                    $workspace,
                    $case
                );

                $this->assertCaseOpen($locked);

                $now = now();

                $note = $this->normalizeNote(
                    $resolutionNote
                );

                $locked->forceFill([
                    'status' => 'resolved',
                    'resolution' => $resolution,
                    'resolution_note' => $note,
                    'resolved_by_user_id' =>
                        $actor?->id,
                    'resolved_at' => $now,
                ])->save();

                $this->appendAuditEvent(
                    $workspace,
                    $locked,
                    'review_resolved',
                    $actor,
                    [
                        'resolution' => $resolution,
                        'resolution_note' => $note,
                    ],
                    $now
                );

                return $locked->fresh();
            }
        );
    }

    public function requestAmendment(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        ?string $reason = null,
        array $amendmentContext = [],
        ?User $actor = null
    ): array {
        return DB::transaction(
            function () use (
                $workspace,
                $case,
                $reason,
                $amendmentContext,
                $actor
            ): array {
                $locked = $this->lockCase(
                    $workspace,
                    $case
                );

                $this->assertCaseOpen($locked);

                $now = now();

                $reason = $this->normalizeNote($reason);

                $locked->forceFill([
                    'status' => 'resolved',
                    'resolution' =>
                        'amendment_required',
                    'resolution_note' => $reason,
                    'resolved_by_user_id' =>
                        $actor?->id,
                    'resolved_at' => $now,
                ])->save();

                $this->appendAuditEvent(
                    $workspace,
                    $locked,
                    'review_resolved',
                    $actor,
                    [
                        'resolution' =>
                            'amendment_required',
                        'resolution_note' =>
                            $reason,
                    ],
                    $now
                );

                $this->appendAuditEvent(
                    $workspace,
                    $locked,
                    'amendment_requested',
                    $actor,
                    [
                        'reason' => $reason,
                        'amendment_context' =>
                            $amendmentContext,
                        'target_domain_key' =>
                            $locked->target_domain_key,
                        'target_component_key' =>
                            $locked->target_component_key,
                        'target_revision' =>
                            $locked->target_revision,
                    ],
                    $now
                );

                return array_merge(
                    [
                        'review_case_id' =>
                            $locked->id,
                        'workspace_id' =>
                            $workspace->id,
                        'target_canonical_revision_id' =>
                            $locked
                                ->target_canonical_revision_id,
                        'target_domain_key' =>
                            $locked->target_domain_key,
                        'target_component_key' =>
                            $locked->target_component_key,
                        'target_revision' =>
                            $locked->target_revision,
                        'resolution' =>
                            'amendment_required',
                        'reason' => $reason,
                    ],
                    $this->configArray(
                        'amendment_handoff'
                    )
                );
            }
        );
    }

    public function events(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case
    ): Collection {
        $this->assertCaseWorkspace(
            $workspace,
            $case
        );

        return WorkspaceV2ReviewAuditEvent::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'review_case_id',
                $case->id
            )
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    private function producer(
        string $domainKey,
        string $componentKey
    ): array {
        $domainKey = trim($domainKey);
        $componentKey = trim($componentKey);

        if (
            $domainKey === ''
            || $componentKey === ''
        ) {
            throw new InvalidArgumentException(
                'V2 review producer identity is required.'
            );
        }

        $component = $this->components->find(
            $componentKey
        );

        if (! is_array($component)) {
            throw new InvalidArgumentException(
                'Unknown V2 review producer.'
            );
        }

        if (
            ($component['domain_key'] ?? null)
            !== $domainKey
        ) {
            throw new DomainException(
                'V2 review producer identity mismatch.'
            );
        }

        if (
            ($component['can_trigger_review'] ?? null)
            !== true
        ) {
            throw new DomainException(
                'V2 component cannot trigger review.'
            );
        }

        if (
            ($component['cross_domain_write'] ?? null)
            !== false
        ) {
            throw new LogicException(
                'V2 review producer cannot have '
                .'cross-domain write authority.'
            );
        }

        return $component;
    }

    private function assertProducerMayReviewTarget(
        array $producer,
        string $targetDomainKey
    ): void {
        $targetDomainKey = trim($targetDomainKey);

        if ($targetDomainKey === '') {
            throw new InvalidArgumentException(
                'V2 review target domain is required.'
            );
        }

        $producerDomain =
            $producer['domain_key'] ?? null;

        $readsFrom = is_array(
            $producer['reads_from'] ?? null
        )
            ? $producer['reads_from']
            : [];

        if (
            $targetDomainKey !== $producerDomain
            && ! in_array(
                $targetDomainKey,
                $readsFrom,
                true
            )
        ) {
            throw new DomainException(
                'V2 review producer may review only its '
                .'own domain or an approved reads_from domain.'
            );
        }
    }

    private function lockCase(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case
    ): WorkspaceV2ReviewCase {
        $locked = WorkspaceV2ReviewCase::query()
            ->where('id', $case->id)
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            throw new DomainException(
                'V2 review case does not belong to workspace.'
            );
        }

        return $locked;
    }

    private function assertCaseWorkspace(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case
    ): void {
        if (
            (int) $case->workspace_id
            !== (int) $workspace->id
        ) {
            throw new DomainException(
                'V2 review case does not belong to workspace.'
            );
        }
    }

    private function assertCaseOpen(
        WorkspaceV2ReviewCase $case
    ): void {
        if ($case->status !== 'open') {
            throw new DomainException(
                'V2 review case is already resolved.'
            );
        }
    }

    private function appendAuditEvent(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        string $eventType,
        ?User $actor,
        array $metadata,
        mixed $occurredAt
    ): WorkspaceV2ReviewAuditEvent {
        if (
            ! in_array(
                $eventType,
                $this->auditEventTypes(),
                true
            )
        ) {
            throw new LogicException(
                'Unknown V2 review audit event type.'
            );
        }

        return WorkspaceV2ReviewAuditEvent::create([
            'workspace_id' => $workspace->id,
            'review_case_id' => $case->id,
            'event_type' => $eventType,
            'actor_user_id' => $actor?->id,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function normalizeNote(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function configArray(
        string $key
    ): array {
        $value = config(
            'pbr_v2_review_audit.'.$key,
            []
        );

        if (! is_array($value)) {
            throw new LogicException(
                'PBR V2 review/audit config '
                .$key
                .' must be an array.'
            );
        }

        return $value;
    }
}
