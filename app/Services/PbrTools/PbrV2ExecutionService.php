<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2ActionCanonicalSource;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Models\WorkspaceV2OperatingRecordCanonicalSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PbrV2ExecutionService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $registry,
        private readonly PbrV2LifecycleService $lifecycle
    ) {
    }

    public function actionStates(): array
    {
        return config('pbr_v2_execution.action_states', []);
    }

    public function recordState(): string
    {
        return (string) config(
            'pbr_v2_execution.record_state',
            'recorded'
        );
    }

    public function priorities(): array
    {
        return config('pbr_v2_execution.priorities', []);
    }

    public function createAction(
        User $actor,
        PartnershipWorkspace $workspace,
        string $producerDomainKey,
        string $producerComponentKey,
        array $sourceCanonicalRevisionIds,
        array $attributes = [],
        ?int $producerCanonicalRevisionId = null,
        ?int $producerOperatingRecordId = null
    ): WorkspaceV2Action {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $producerDomainKey,
            $producerComponentKey,
            $sourceCanonicalRevisionIds,
            $attributes,
            $producerCanonicalRevisionId,
            $producerOperatingRecordId
        ): WorkspaceV2Action {
            $producer = $this->component(
                $producerDomainKey,
                $producerComponentKey
            );

            if (
                ($producer['can_create_actions'] ?? false)
                !== true
            ) {
                throw ValidationException::withMessages([
                    'producer_component_key' =>
                        'V2 component cannot create actions.',
                ]);
            }

            if (
                ($producer['source_policy'] ?? null)
                !== 'approved_upstream_only'
            ) {
                throw ValidationException::withMessages([
                    'producer_component_key' =>
                        'Unsupported V2 execution source policy.',
                ]);
            }

            if (
                ($producer['cross_domain_write'] ?? null)
                !== false
            ) {
                throw ValidationException::withMessages([
                    'producer_component_key' =>
                        'Cross-domain action write is forbidden.',
                ]);
            }

            $actionComponent = $this->actionComponentForDomain(
                $producerDomainKey
            );

            $sources = $this->validatedCanonicalSources(
                $workspace,
                $producer,
                $sourceCanonicalRevisionIds
            );

            $producerType = $this->producerType($producer);

            $this->validateProducerInstance(
                $workspace,
                $producer,
                $producerType,
                $sources,
                $producerCanonicalRevisionId,
                $producerOperatingRecordId
            );

            $title = trim(
                (string) ($attributes['title'] ?? '')
            );

            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => 'V2 action title is required.',
                ]);
            }

            $priority = (string) (
                $attributes['priority'] ?? 'normal'
            );

            if (! in_array(
                $priority,
                $this->priorities(),
                true
            )) {
                throw ValidationException::withMessages([
                    'priority' => 'Invalid V2 action priority.',
                ]);
            }

            $status = (string) (
                $attributes['status'] ?? 'open'
            );

            if (! in_array(
                $status,
                $this->actionStates(),
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid V2 action state.',
                ]);
            }

            $terminal = in_array(
                $status,
                ['completed', 'superseded'],
                true
            );

            $action = WorkspaceV2Action::create([
                'workspace_id' => $workspace->id,
                'action_domain_key' => $producerDomainKey,
                'action_component_key' =>
                    $actionComponent['component_key'],
                'producer_domain_key' => $producerDomainKey,
                'producer_component_key' =>
                    $producerComponentKey,
                'producer_canonical_revision_id' =>
                    $producerCanonicalRevisionId,
                'producer_operating_record_id' =>
                    $producerOperatingRecordId,
                'created_by_user_id' => $actor->id,
                'title' => Str::limit($title, 180, ''),
                'description' => filled(
                    $attributes['description'] ?? null
                )
                    ? (string) $attributes['description']
                    : null,
                'owner_name' => filled(
                    $attributes['owner_name'] ?? null
                )
                    ? Str::limit(
                        trim(
                            (string) $attributes['owner_name']
                        ),
                        160,
                        ''
                    )
                    : null,
                'priority' => $priority,
                'status' => $status,
                'due_date' =>
                    $attributes['due_date'] ?? null,
                'completed_at' => $terminal ? now() : null,
                'metadata' => is_array(
                    $attributes['metadata'] ?? null
                )
                    ? $attributes['metadata']
                    : null,
            ]);

            $this->persistActionSources(
                $workspace,
                $action,
                $sources,
                $sourceCanonicalRevisionIds
            );

            return $action->fresh([
                'canonicalSources',
                'producerCanonicalRevision',
                'producerOperatingRecord',
            ]);
        });
    }

    public function transitionAction(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2Action $action,
        string $toState
    ): WorkspaceV2Action {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $action,
            $toState
        ): WorkspaceV2Action {
            $current = WorkspaceV2Action::query()
                ->whereKey($action->id)
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'action' =>
                        'V2 action does not belong to workspace.',
                ]);
            }

            if (! $actor->exists) {
                throw ValidationException::withMessages([
                    'actor' => 'Valid actor is required.',
                ]);
            }

            if (! in_array(
                $toState,
                $this->actionStates(),
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Unknown V2 action state.',
                ]);
            }

            $matrix = $this->lifecycle
                ->transitionRules()['actions']
                ?? [];

            $allowed = $matrix[$current->status] ?? [];

            if (! in_array($toState, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' =>
                        'V2 action transition is forbidden.',
                ]);
            }

            $current->status = $toState;

            $current->completed_at = in_array(
                $toState,
                ['completed', 'superseded'],
                true
            ) ? now() : null;

            $current->save();

            return $current->fresh();
        });
    }

    public function appendRecord(
        User $actor,
        PartnershipWorkspace $workspace,
        string $domainKey,
        string $componentKey,
        array $sourceCanonicalRevisionIds,
        array $payload,
        array $attributes = []
    ): WorkspaceV2OperatingRecord {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $domainKey,
            $componentKey,
            $sourceCanonicalRevisionIds,
            $payload,
            $attributes
        ): WorkspaceV2OperatingRecord {
            $recordComponent = $this->component(
                $domainKey,
                $componentKey
            );

            if (
                ($recordComponent['component_type'] ?? null)
                !== 'record'
                || ($recordComponent['write_mode'] ?? null)
                !== 'append_only_record'
                || (
                    $recordComponent['canonical_capability']
                    ?? null
                ) !== 'none'
            ) {
                throw ValidationException::withMessages([
                    'component_key' =>
                        'Component is not a V2 append-only record.',
                ]);
            }

            if (
                ($recordComponent['source_policy'] ?? null)
                !== 'approved_upstream_only'
            ) {
                throw ValidationException::withMessages([
                    'component_key' =>
                        'Unsupported V2 record source policy.',
                ]);
            }

            $sources = $this->validatedCanonicalSources(
                $workspace,
                $recordComponent,
                $sourceCanonicalRevisionIds
            );

            $record = WorkspaceV2OperatingRecord::create([
                'workspace_id' => $workspace->id,
                'domain_key' => $domainKey,
                'component_key' => $componentKey,
                'recorded_by_user_id' => $actor->id,
                'state' => $this->recordState(),
                'title' => filled(
                    $attributes['title'] ?? null
                )
                    ? Str::limit(
                        trim((string) $attributes['title']),
                        180,
                        ''
                    )
                    : null,
                'record_date' =>
                    $attributes['record_date'] ?? null,
                'effective_at' =>
                    $attributes['effective_at'] ?? null,
                'payload' => $payload,
                'metadata' => is_array(
                    $attributes['metadata'] ?? null
                )
                    ? $attributes['metadata']
                    : null,
                'recorded_at' => now(),
            ]);

            $this->persistRecordSources(
                $workspace,
                $record,
                $sources,
                $sourceCanonicalRevisionIds
            );

            return $record->fresh([
                'canonicalSources',
            ]);
        });
    }

    public function actionSources(
        PartnershipWorkspace $workspace,
        WorkspaceV2Action $action
    ): Collection {
        if (
            (int) $action->workspace_id
            !== (int) $workspace->id
        ) {
            throw ValidationException::withMessages([
                'action' =>
                    'V2 action does not belong to workspace.',
            ]);
        }

        return WorkspaceV2ActionCanonicalSource::query()
            ->where('workspace_id', $workspace->id)
            ->where('action_id', $action->id)
            ->orderBy('id')
            ->get();
    }

    public function recordSources(
        PartnershipWorkspace $workspace,
        WorkspaceV2OperatingRecord $record
    ): Collection {
        if (
            (int) $record->workspace_id
            !== (int) $workspace->id
        ) {
            throw ValidationException::withMessages([
                'record' =>
                    'V2 operating record does not belong to workspace.',
            ]);
        }

        return WorkspaceV2OperatingRecordCanonicalSource::query()
            ->where('workspace_id', $workspace->id)
            ->where('operating_record_id', $record->id)
            ->orderBy('id')
            ->get();
    }

    private function component(
        string $domainKey,
        string $componentKey
    ): array {
        $component = $this->registry->find($componentKey);

        if (
            ! is_array($component)
            || ($component['domain_key'] ?? null)
                !== $domainKey
        ) {
            throw ValidationException::withMessages([
                'component_key' =>
                    'Unknown or mismatched V2 component identity.',
            ]);
        }

        return $component;
    }

    private function actionComponentForDomain(
        string $domainKey
    ): array {
        $matches = [];

        foreach ($this->registry->all() as $key => $component) {
            if (
                ($component['domain_key'] ?? null)
                    === $domainKey
                && ($component['component_type'] ?? null)
                    === 'action'
            ) {
                $matches[] = [
                    'component_key' => $key,
                    'definition' => $component,
                ];
            }
        }

        if (count($matches) !== 1) {
            throw ValidationException::withMessages([
                'action_component' =>
                    'V2 domain must expose exactly one action component.',
            ]);
        }

        return $matches[0];
    }

    private function producerType(array $producer): string
    {
        if (
            ($producer['canonical_capability'] ?? 'none')
            !== 'none'
        ) {
            return 'canonical';
        }

        if (
            ($producer['component_type'] ?? null)
            === 'record'
        ) {
            return 'record';
        }

        if (
            ($producer['component_type'] ?? null)
            === 'monitor'
        ) {
            return 'monitor';
        }

        throw ValidationException::withMessages([
            'producer_component_key' =>
                'Unsupported V2 action producer type.',
        ]);
    }

    private function validatedCanonicalSources(
        PartnershipWorkspace $workspace,
        array $producer,
        array $sourceIds
    ): Collection {
        if ($sourceIds === []) {
            throw ValidationException::withMessages([
                'source_canonical_revision_ids' =>
                    'At least one approved canonical source is required.',
            ]);
        }

        $normalized = array_map(
            static fn ($id): int => (int) $id,
            $sourceIds
        );

        if (
            in_array(0, $normalized, true)
            || count(array_unique($normalized))
                !== count($normalized)
        ) {
            throw ValidationException::withMessages([
                'source_canonical_revision_ids' =>
                    'Source revision IDs must be unique positive IDs.',
            ]);
        }

        $sources = WorkspaceV2CanonicalRevision::query()
            ->whereIn('id', $normalized)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($sources->count() !== count($normalized)) {
            throw ValidationException::withMessages([
                'source_canonical_revision_ids' =>
                    'One or more canonical source revisions do not exist.',
            ]);
        }

        $allowedDomains = array_values(array_unique(
            array_merge(
                [(string) $producer['domain_key']],
                is_array($producer['reads_from'] ?? null)
                    ? $producer['reads_from']
                    : []
            )
        ));

        $requiredState = (string) config(
            'pbr_v2_execution.source_state_required_on_pin',
            'approved_current'
        );

        foreach ($normalized as $id) {
            $source = $sources->get($id);

            if (
                (int) $source->workspace_id
                !== (int) $workspace->id
            ) {
                throw ValidationException::withMessages([
                    'source_canonical_revision_ids' =>
                        'Canonical source belongs to another workspace.',
                ]);
            }

            if ($source->state !== $requiredState) {
                throw ValidationException::withMessages([
                    'source_canonical_revision_ids' =>
                        'Canonical source must be approved_current when pinned.',
                ]);
            }

            if (! in_array(
                $source->domain_key,
                $allowedDomains,
                true
            )) {
                throw ValidationException::withMessages([
                    'source_canonical_revision_ids' =>
                        'Canonical source domain is outside producer authority.',
                ]);
            }
        }

        return $sources;
    }

    private function validateProducerInstance(
        PartnershipWorkspace $workspace,
        array $producer,
        string $producerType,
        Collection $sources,
        ?int $producerCanonicalRevisionId,
        ?int $producerOperatingRecordId
    ): void {
        if ($producerType === 'canonical') {
            if (
                $producerCanonicalRevisionId === null
                || $producerOperatingRecordId !== null
            ) {
                throw ValidationException::withMessages([
                    'producer' =>
                        'Canonical producer requires exact canonical revision only.',
                ]);
            }

            $revision = $sources->get(
                $producerCanonicalRevisionId
            );

            if (! $revision) {
                throw ValidationException::withMessages([
                    'producer_canonical_revision_id' =>
                        'Canonical producer revision must be included in source evidence.',
                ]);
            }

            if (
                (int) $revision->workspace_id
                    !== (int) $workspace->id
                || $revision->domain_key
                    !== $producer['domain_key']
                || $revision->component_key
                    !== $producer['component_key']
            ) {
                throw ValidationException::withMessages([
                    'producer_canonical_revision_id' =>
                        'Canonical producer revision identity mismatch.',
                ]);
            }

            return;
        }

        if ($producerType === 'record') {
            if (
                $producerCanonicalRevisionId !== null
                || $producerOperatingRecordId === null
            ) {
                throw ValidationException::withMessages([
                    'producer' =>
                        'Record producer requires exact operating-record instance only.',
                ]);
            }

            $record = WorkspaceV2OperatingRecord::query()
                ->whereKey($producerOperatingRecordId)
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (
                ! $record
                || $record->domain_key
                    !== $producer['domain_key']
                || $record->component_key
                    !== $producer['component_key']
                || $record->state !== $this->recordState()
            ) {
                throw ValidationException::withMessages([
                    'producer_operating_record_id' =>
                        'Operating-record producer identity mismatch.',
                ]);
            }

            return;
        }

        if (
            $producerCanonicalRevisionId !== null
            || $producerOperatingRecordId !== null
        ) {
            throw ValidationException::withMessages([
                'producer' =>
                    'Monitor producer has no persistent producer-instance row.',
            ]);
        }
    }

    private function persistActionSources(
        PartnershipWorkspace $workspace,
        WorkspaceV2Action $action,
        Collection $sources,
        array $sourceIds
    ): void {
        foreach ($sourceIds as $id) {
            $source = $sources->get((int) $id);

            WorkspaceV2ActionCanonicalSource::create([
                'workspace_id' => $workspace->id,
                'action_id' => $action->id,
                'canonical_revision_id' => $source->id,
                'source_domain_key' =>
                    $source->domain_key,
                'source_component_key' =>
                    $source->component_key,
                'source_revision' =>
                    $source->revision,
                'pinned_at' => now(),
            ]);
        }
    }

    private function persistRecordSources(
        PartnershipWorkspace $workspace,
        WorkspaceV2OperatingRecord $record,
        Collection $sources,
        array $sourceIds
    ): void {
        foreach ($sourceIds as $id) {
            $source = $sources->get((int) $id);

            WorkspaceV2OperatingRecordCanonicalSource::create([
                'workspace_id' => $workspace->id,
                'operating_record_id' => $record->id,
                'canonical_revision_id' => $source->id,
                'source_domain_key' =>
                    $source->domain_key,
                'source_component_key' =>
                    $source->component_key,
                'source_revision' =>
                    $source->revision,
                'pinned_at' => now(),
            ]);
        }
    }
}
