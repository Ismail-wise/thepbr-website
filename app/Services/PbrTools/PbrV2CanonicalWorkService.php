<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2CanonicalWorkSource;
use App\Models\WorkspaceV2ReviewCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PbrV2CanonicalWorkService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $registry,
        private readonly PbrV2LifecycleService $lifecycle
    ) {
    }

    public function states(): array
    {
        return $this->configArray('states');
    }

    public function createWorkingDraft(
        PartnershipWorkspace $workspace,
        string $targetDomainKey,
        string $targetComponentKey,
        string $producerDomainKey,
        string $producerComponentKey,
        array $payload,
        array $sourceCanonicalRevisionIds = [],
        ?User $actor = null
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $targetDomainKey,
            $targetComponentKey,
            $producerDomainKey,
            $producerComponentKey,
            $payload,
            $sourceCanonicalRevisionIds,
            $actor
        ): WorkspaceV2CanonicalWorkArtifact {
            [, $target] = $this->validatedContracts(
                $producerDomainKey,
                $producerComponentKey,
                $targetDomainKey,
                $targetComponentKey
            );

            [$sources, $sourceIds] =
                $this->validatedNewSources(
                    $workspace,
                    $target,
                    $sourceCanonicalRevisionIds,
                    'required_source_state_on_pin'
                );

            $generation =
                $this->initialSourceGeneration();

            $artifact =
                WorkspaceV2CanonicalWorkArtifact::create([
                    'workspace_id' =>
                        $workspace->id,
                    'target_domain_key' =>
                        $targetDomainKey,
                    'target_component_key' =>
                        $targetComponentKey,
                    'producer_domain_key' =>
                        $producerDomainKey,
                    'producer_component_key' =>
                        $producerComponentKey,
                    'state' =>
                        $this->initialState(),
                    'payload' =>
                        $payload,
                    'source_generation' =>
                        $generation,
                    'created_by_user_id' =>
                        $actor?->id,
                    'submitted_by_user_id' =>
                        null,
                    'submitted_at' =>
                        null,
                    'amendment_review_case_id' =>
                        null,
                    'amendment_target_canonical_revision_id' =>
                        null,
                    'published_canonical_revision_id' =>
                        null,
                    'consumed_at' =>
                        null,
                ]);

            $this->persistSourceGeneration(
                $workspace,
                $artifact,
                $generation,
                $sources,
                $sourceIds
            );

            return $artifact->fresh();
        });
    }

    public function updateWorkingDraftPayload(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $payload
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $payload
        ): WorkspaceV2CanonicalWorkArtifact {
            $locked = $this->lockedArtifact(
                $workspace,
                $artifact
            );

            $this->assertWorkingDraft($locked);
            $this->assertNotConsumed($locked);

            DB::table(
                'workspace_v2_canonical_work_artifacts'
            )
                ->where('id', $locked->id)
                ->update([
                    'payload' => json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR
                    ),
                    'updated_at' => now(),
                ]);

            return $this->freshArtifact($locked->id);
        });
    }

    public function refreshWorkingDraftSources(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $sourceCanonicalRevisionIds
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $sourceCanonicalRevisionIds
        ): WorkspaceV2CanonicalWorkArtifact {
            $locked = $this->lockedArtifact(
                $workspace,
                $artifact
            );

            $this->assertWorkingDraft($locked);
            $this->assertNotConsumed($locked);

            [, $target] = $this->validatedContracts(
                $locked->producer_domain_key,
                $locked->producer_component_key,
                $locked->target_domain_key,
                $locked->target_component_key
            );

            [$sources, $sourceIds] =
                $this->validatedNewSources(
                    $workspace,
                    $target,
                    $sourceCanonicalRevisionIds,
                    'required_source_state_on_pin'
                );

            $nextGeneration =
                ((int) $locked->source_generation) + 1;

            $this->persistSourceGeneration(
                $workspace,
                $locked,
                $nextGeneration,
                $sources,
                $sourceIds
            );

            DB::table(
                'workspace_v2_canonical_work_artifacts'
            )
                ->where('id', $locked->id)
                ->update([
                    'source_generation' =>
                        $nextGeneration,
                    'updated_at' =>
                        now(),
                ]);

            return $this->freshArtifact($locked->id);
        });
    }

    public function submitProposal(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        ?User $actor = null
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $actor
        ): WorkspaceV2CanonicalWorkArtifact {
            $locked = $this->lockedArtifact(
                $workspace,
                $artifact
            );

            $this->assertWorkingDraft($locked);
            $this->assertNotConsumed($locked);

            [, $target] = $this->validatedContracts(
                $locked->producer_domain_key,
                $locked->producer_component_key,
                $locked->target_domain_key,
                $locked->target_component_key
            );

            $this->lifecycle->assertTransitionAllowed(
                $locked->target_component_key,
                'working_draft',
                'proposed'
            );

            $this->validateExistingGeneration(
                $workspace,
                $locked,
                $target,
                (int) $locked->source_generation,
                'required_source_state_on_submit'
            );

            DB::table(
                'workspace_v2_canonical_work_artifacts'
            )
                ->where('id', $locked->id)
                ->update([
                    'state' =>
                        'proposed',
                    'submitted_by_user_id' =>
                        $actor?->id,
                    'submitted_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

            return $this->freshArtifact($locked->id);
        });
    }

    public function returnProposalToWorkingDraft(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $artifact
        ): WorkspaceV2CanonicalWorkArtifact {
            $locked = $this->lockedArtifact(
                $workspace,
                $artifact
            );

            $this->assertProposed($locked);
            $this->assertNotConsumed($locked);

            $this->validatedContracts(
                $locked->producer_domain_key,
                $locked->producer_component_key,
                $locked->target_domain_key,
                $locked->target_component_key
            );

            $this->lifecycle->assertTransitionAllowed(
                $locked->target_component_key,
                'proposed',
                'working_draft'
            );

            DB::table(
                'workspace_v2_canonical_work_artifacts'
            )
                ->where('id', $locked->id)
                ->update([
                    'state' =>
                        'working_draft',
                    'submitted_by_user_id' =>
                        null,
                    'submitted_at' =>
                        null,
                    'updated_at' =>
                        now(),
                ]);

            return $this->freshArtifact($locked->id);
        });
    }

    public function createAmendmentWorkingDraft(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $reviewCase,
        string $producerDomainKey,
        string $producerComponentKey,
        array $payload,
        array $sourceCanonicalRevisionIds = [],
        ?User $actor = null
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $reviewCase,
            $producerDomainKey,
            $producerComponentKey,
            $payload,
            $sourceCanonicalRevisionIds,
            $actor
        ): WorkspaceV2CanonicalWorkArtifact {
            $case = WorkspaceV2ReviewCase::query()
                ->whereKey($reviewCase->id)
                ->lockForUpdate()
                ->first();

            if (! $case) {
                $this->validationError(
                    'review_case',
                    'V2 review case does not exist.'
                );
            }

            if (
                (int) $case->workspace_id
                !== (int) $workspace->id
            ) {
                $this->validationError(
                    'review_case',
                    'V2 review case belongs to another workspace.'
                );
            }

            if (
                $case->status
                !== $this->configString(
                    'amendment_required_review_status'
                )
                || $case->resolution
                !== $this->configString(
                    'amendment_required_resolution'
                )
            ) {
                $this->validationError(
                    'review_case',
                    'V2 review case does not authorize amendment work.'
                );
            }

            $targetRevision =
                WorkspaceV2CanonicalRevision::query()
                    ->whereKey(
                        $case->target_canonical_revision_id
                    )
                    ->lockForUpdate()
                    ->first();

            if (
                ! $targetRevision
                || (int) $targetRevision->workspace_id
                    !== (int) $workspace->id
                || $targetRevision->domain_key
                    !== $case->target_domain_key
                || $targetRevision->component_key
                    !== $case->target_component_key
                || (int) $targetRevision->revision
                    !== (int) $case->target_revision
            ) {
                $this->validationError(
                    'review_case',
                    'V2 amendment target revision identity mismatch.'
                );
            }

            [, $target] = $this->validatedContracts(
                $producerDomainKey,
                $producerComponentKey,
                $case->target_domain_key,
                $case->target_component_key
            );

            [$sources, $sourceIds] =
                $this->validatedNewSources(
                    $workspace,
                    $target,
                    $sourceCanonicalRevisionIds,
                    'required_source_state_on_pin'
                );

            $generation =
                $this->initialSourceGeneration();

            $artifact =
                WorkspaceV2CanonicalWorkArtifact::create([
                    'workspace_id' =>
                        $workspace->id,
                    'target_domain_key' =>
                        $case->target_domain_key,
                    'target_component_key' =>
                        $case->target_component_key,
                    'producer_domain_key' =>
                        $producerDomainKey,
                    'producer_component_key' =>
                        $producerComponentKey,
                    'state' =>
                        $this->initialState(),
                    'payload' =>
                        $payload,
                    'source_generation' =>
                        $generation,
                    'created_by_user_id' =>
                        $actor?->id,
                    'submitted_by_user_id' =>
                        null,
                    'submitted_at' =>
                        null,
                    'amendment_review_case_id' =>
                        $case->id,
                    'amendment_target_canonical_revision_id' =>
                        $targetRevision->id,
                    'published_canonical_revision_id' =>
                        null,
                    'consumed_at' =>
                        null,
                ]);

            $this->persistSourceGeneration(
                $workspace,
                $artifact,
                $generation,
                $sources,
                $sourceIds
            );

            return $artifact->fresh();
        });
    }

    public function recordPublicationConsumption(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        WorkspaceV2CanonicalRevision $revision
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $revision
        ): WorkspaceV2CanonicalWorkArtifact {
            $locked = $this->lockedArtifact(
                $workspace,
                $artifact
            );

            $this->assertProposed($locked);
            $this->assertNotConsumed($locked);

            $canonical =
                WorkspaceV2CanonicalRevision::query()
                    ->whereKey($revision->id)
                    ->lockForUpdate()
                    ->first();

            if (
                ! $canonical
                || (int) $canonical->workspace_id
                    !== (int) $workspace->id
                || $canonical->domain_key
                    !== $locked->target_domain_key
                || $canonical->component_key
                    !== $locked->target_component_key
                || $canonical->state
                    !== $this->configString(
                        'consumption_requires_revision_state'
                    )
            ) {
                $this->validationError(
                    'published_canonical_revision',
                    'Canonical publication does not match the '
                    .'V2 work artifact.'
                );
            }

            $now = now();

            DB::table(
                'workspace_v2_canonical_work_artifacts'
            )
                ->where('id', $locked->id)
                ->update([
                    'published_canonical_revision_id' =>
                        $canonical->id,
                    'consumed_at' =>
                        $now,
                    'updated_at' =>
                        $now,
                ]);

            return $this->freshArtifact($locked->id);
        });
    }

    public function sources(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        ?int $sourceGeneration = null
    ): Collection {
        $current = WorkspaceV2CanonicalWorkArtifact::query()
            ->whereKey($artifact->id)
            ->first();

        if (
            ! $current
            || (int) $current->workspace_id
                !== (int) $workspace->id
        ) {
            $this->validationError(
                'work_artifact',
                'V2 canonical work artifact belongs to another workspace.'
            );
        }

        $generation =
            $sourceGeneration
            ?? (int) $current->source_generation;

        if (
            $generation < 1
            || $generation > (int) $current->source_generation
        ) {
            $this->validationError(
                'source_generation',
                'Unknown V2 canonical work source generation.'
            );
        }

        return WorkspaceV2CanonicalWorkSource::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'work_artifact_id',
                $current->id
            )
            ->where(
                'source_generation',
                $generation
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validatedContracts(
        string $producerDomainKey,
        string $producerComponentKey,
        string $targetDomainKey,
        string $targetComponentKey
    ): array {
        $producer = $this->component(
            $producerDomainKey,
            $producerComponentKey
        );

        $target = $this->component(
            $targetDomainKey,
            $targetComponentKey
        );

        if (
            ($producer['can_create_proposal'] ?? false)
            !== true
        ) {
            $this->validationError(
                'producer_component_key',
                'V2 component cannot create proposal work.'
            );
        }

        if (
            ($producer['cross_domain_write'] ?? null)
            !== false
        ) {
            $this->validationError(
                'producer_component_key',
                'V2 proposal producer cannot write across domains.'
            );
        }

        if (
            $producerDomainKey
            !== $targetDomainKey
        ) {
            $this->validationError(
                'target_domain_key',
                'V2 proposal producer and canonical target '
                .'must share the same domain.'
            );
        }

        if (
            ($target['canonical_capability'] ?? 'none')
            === 'none'
            || ($target['approval_required'] ?? false)
                !== true
            || ($target['publish_authority'] ?? null)
                !== 'approval_engine_only'
            || ($target['write_mode'] ?? null)
                !== 'revisioned_current'
            || ($target['source_policy'] ?? null)
                !== $this->configString(
                    'required_target_source_policy'
                )
        ) {
            $this->validationError(
                'target_component_key',
                'V2 target does not satisfy the frozen '
                .'canonical work contract.'
            );
        }

        return [$producer, $target];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(
        string $domainKey,
        string $componentKey
    ): array {
        $component =
            $this->registry->find($componentKey);

        if (
            ! is_array($component)
            || ($component['domain_key'] ?? null)
                !== $domainKey
        ) {
            $this->validationError(
                'component_key',
                'Unknown or mismatched V2 component identity.'
            );
        }

        return $component;
    }

    /**
     * @return array{0: Collection<int, WorkspaceV2CanonicalRevision>, 1: array<int, int>}
     */
    private function validatedNewSources(
        PartnershipWorkspace $workspace,
        array $target,
        array $sourceIds,
        string $requiredStateConfigKey
    ): array {
        $normalized = array_map(
            static fn ($id): int =>
                (int) $id,
            $sourceIds
        );

        if (
            in_array(0, $normalized, true)
            || count(array_unique($normalized))
                !== count($normalized)
        ) {
            $this->validationError(
                'source_canonical_revision_ids',
                'Source revision IDs must be unique positive IDs.'
            );
        }

        $allowedDomains =
            is_array($target['reads_from'] ?? null)
                ? $target['reads_from']
                : [];

        if (
            $allowedDomains === []
            && $normalized !== []
        ) {
            $this->validationError(
                'source_canonical_revision_ids',
                'Canonical target declares no upstream '
                .'source domains.'
            );
        }

        if ($normalized === []) {
            return [collect(), []];
        }

        $sources =
            WorkspaceV2CanonicalRevision::query()
                ->whereIn('id', $normalized)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

        if (
            $sources->count()
            !== count($normalized)
        ) {
            $this->validationError(
                'source_canonical_revision_ids',
                'One or more canonical source revisions '
                .'do not exist.'
            );
        }

        $requiredState =
            $this->configString(
                $requiredStateConfigKey
            );

        foreach ($normalized as $id) {
            $source = $sources->get($id);

            if (
                (int) $source->workspace_id
                !== (int) $workspace->id
            ) {
                $this->validationError(
                    'source_canonical_revision_ids',
                    'Canonical source belongs to another workspace.'
                );
            }

            if ($source->state !== $requiredState) {
                $this->validationError(
                    'source_canonical_revision_ids',
                    'Canonical source is not approved_current.'
                );
            }

            if (
                ! in_array(
                    $source->domain_key,
                    $allowedDomains,
                    true
                )
            ) {
                $this->validationError(
                    'source_canonical_revision_ids',
                    'Canonical source domain is outside the '
                    .'target reads_from boundary.'
                );
            }
        }

        return [$sources, $normalized];
    }

    private function validateExistingGeneration(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $target,
        int $generation,
        string $requiredStateConfigKey
    ): void {
        $rows =
            WorkspaceV2CanonicalWorkSource::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'work_artifact_id',
                    $artifact->id
                )
                ->where(
                    'source_generation',
                    $generation
                )
                ->lockForUpdate()
                ->get();

        $allowedDomains =
            is_array($target['reads_from'] ?? null)
                ? $target['reads_from']
                : [];

        if (
            $allowedDomains === []
            && $rows->isNotEmpty()
        ) {
            $this->validationError(
                'source_generation',
                'Root canonical target cannot contain '
                .'upstream source evidence.'
            );
        }

        if ($rows->isEmpty()) {
            return;
        }

        $revisionIds = $rows
            ->pluck('canonical_revision_id')
            ->map(
                static fn ($id): int =>
                    (int) $id
            )
            ->all();

        $revisions =
            WorkspaceV2CanonicalRevision::query()
                ->whereIn('id', $revisionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

        if (
            $revisions->count()
            !== count($revisionIds)
        ) {
            $this->validationError(
                'source_generation',
                'One or more pinned canonical revisions '
                .'no longer exist.'
            );
        }

        $requiredState =
            $this->configString(
                $requiredStateConfigKey
            );

        foreach ($rows as $row) {
            $revision = $revisions->get(
                (int) $row->canonical_revision_id
            );

            if (
                ! $revision
                || (int) $row->workspace_id
                    !== (int) $workspace->id
                || (int) $revision->workspace_id
                    !== (int) $workspace->id
                || $revision->state
                    !== $requiredState
                || $row->source_domain_key
                    !== $revision->domain_key
                || $row->source_component_key
                    !== $revision->component_key
                || (int) $row->source_revision
                    !== (int) $revision->revision
                || ! in_array(
                    $revision->domain_key,
                    $allowedDomains,
                    true
                )
            ) {
                $this->validationError(
                    'source_generation',
                    'Pinned canonical source evidence is stale '
                    .'or no longer valid.'
                );
            }
        }
    }

    private function persistSourceGeneration(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $generation,
        Collection $sources,
        array $sourceIds
    ): void {
        $now = now();

        foreach ($sourceIds as $id) {
            $source = $sources->get((int) $id);

            WorkspaceV2CanonicalWorkSource::create([
                'workspace_id' =>
                    $workspace->id,
                'work_artifact_id' =>
                    $artifact->id,
                'source_generation' =>
                    $generation,
                'canonical_revision_id' =>
                    $source->id,
                'source_domain_key' =>
                    $source->domain_key,
                'source_component_key' =>
                    $source->component_key,
                'source_revision' =>
                    $source->revision,
                'pinned_at' =>
                    $now,
            ]);
        }
    }

    private function lockedArtifact(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): WorkspaceV2CanonicalWorkArtifact {
        $locked =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->whereKey($artifact->id)
                ->lockForUpdate()
                ->first();

        if (
            ! $locked
            || (int) $locked->workspace_id
                !== (int) $workspace->id
        ) {
            $this->validationError(
                'work_artifact',
                'V2 canonical work artifact belongs to '
                .'another workspace.'
            );
        }

        return $locked;
    }

    private function assertWorkingDraft(
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): void {
        if ($artifact->state !== 'working_draft') {
            $this->validationError(
                'state',
                'V2 canonical work artifact is not a working draft.'
            );
        }
    }

    private function assertProposed(
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): void {
        if ($artifact->state !== 'proposed') {
            $this->validationError(
                'state',
                'V2 canonical work artifact is not proposed.'
            );
        }
    }

    private function assertNotConsumed(
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): void {
        if (
            $artifact->consumed_at !== null
            || $artifact->published_canonical_revision_id
                !== null
        ) {
            $this->validationError(
                'work_artifact',
                'Consumed V2 canonical proposal is terminal.'
            );
        }
    }

    private function freshArtifact(
        int $id
    ): WorkspaceV2CanonicalWorkArtifact {
        $artifact =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->whereKey($id)
                ->first();

        if (! $artifact) {
            throw new LogicException(
                'V2 canonical work artifact disappeared '
                .'during persistence.'
            );
        }

        return $artifact;
    }

    private function initialState(): string
    {
        return $this->configString(
            'initial_state'
        );
    }

    private function initialSourceGeneration(): int
    {
        $generation = (int) config(
            'pbr_v2_canonical_work.initial_source_generation',
            1
        );

        if ($generation < 1) {
            throw new LogicException(
                'Invalid V2 canonical work initial '
                .'source generation.'
            );
        }

        return $generation;
    }

    /**
     * @return array<int, string>
     */
    private function configArray(
        string $key
    ): array {
        $value = config(
            'pbr_v2_canonical_work.'.$key,
            []
        );

        if (! is_array($value)) {
            throw new LogicException(
                'Invalid V2 canonical work config: '.$key
            );
        }

        return $value;
    }

    private function configString(
        string $key
    ): string {
        $value = config(
            'pbr_v2_canonical_work.'.$key
        );

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new LogicException(
                'Invalid V2 canonical work config: '.$key
            );
        }

        return $value;
    }

    private function validationError(
        string $key,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $key => $message,
        ]);
    }
}
