<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2CanonicalWorkSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PbrV2CanonicalWorkflowService
{
    public function __construct(
        private readonly PbrV2CanonicalWorkService $work,
        private readonly PbrV2ApprovalEngineService $approval,
        private readonly PbrV2CanonicalOutputService $canonical
    ) {
    }

    public function approveAndPublish(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        bool $readinessPassed,
        ?User $actor = null
    ): array {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $readinessPassed,
            $actor
        ): array {
            $locked = $this->lockedProposal(
                $workspace,
                $artifact
            );

            $sourcesFresh = $this->currentSourcesFresh(
                $workspace,
                $locked
            );

            $decision = $this->approval->approve(
                $locked->target_domain_key,
                $locked->target_component_key,
                'proposed',
                $readinessPassed,
                $sourcesFresh
            );

            $this->assertApprovedDecision(
                $locked,
                $decision
            );

            $revision = $this->canonical->publish(
                $workspace,
                $locked->target_domain_key,
                $locked->target_component_key,
                $locked->payload,
                $decision,
                $actor
            );

            $this->assertPublishedRevision(
                $workspace,
                $locked,
                $decision,
                $revision
            );

            $consumed = $this->work
                ->recordPublicationConsumption(
                    $workspace,
                    $locked,
                    $revision
                );

            $this->assertConsumedArtifact(
                $workspace,
                $locked,
                $revision,
                $consumed
            );

            return [
                'decision' => $decision,
                'canonical_revision' => $revision,
                'work_artifact' => $consumed,
            ];
        });
    }

    public function rejectAndReturnToWorkingDraft(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        string $reason
    ): array {
        return DB::transaction(function () use (
            $workspace,
            $artifact,
            $reason
        ): array {
            $locked = $this->lockedProposal(
                $workspace,
                $artifact
            );

            $decision = $this->approval->reject(
                $locked->target_domain_key,
                $locked->target_component_key,
                'proposed',
                $reason
            );

            $this->assertRejectedDecision(
                $locked,
                $reason,
                $decision
            );

            $returned = $this->work
                ->returnProposalToWorkingDraft(
                    $workspace,
                    $locked
                );

            $this->assertReturnedWorkingDraft(
                $workspace,
                $locked,
                $returned
            );

            return [
                'decision' => $decision,
                'work_artifact' => $returned,
            ];
        });
    }

    private function lockedProposal(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): WorkspaceV2CanonicalWorkArtifact {
        $locked = WorkspaceV2CanonicalWorkArtifact::query()
            ->whereKey($artifact->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            $this->validationError(
                'work_artifact',
                'V2 canonical proposal does not exist.'
            );
        }

        if (
            (int) $locked->workspace_id
            !== (int) $workspace->id
        ) {
            $this->validationError(
                'work_artifact',
                'V2 canonical proposal belongs to another workspace.'
            );
        }

        if ($locked->state !== 'proposed') {
            $this->validationError(
                'state',
                'V2 canonical workflow requires a proposed artifact.'
            );
        }

        if (
            $locked->consumed_at !== null
            || $locked->published_canonical_revision_id !== null
        ) {
            $this->validationError(
                'work_artifact',
                'Consumed V2 canonical proposal is terminal.'
            );
        }

        return $locked;
    }

    private function currentSourcesFresh(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): bool {
        $rows = WorkspaceV2CanonicalWorkSource::query()
            ->where(
                'work_artifact_id',
                $artifact->id
            )
            ->where(
                'source_generation',
                (int) $artifact->source_generation
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($rows->isEmpty()) {
            return true;
        }

        $revisionIds = $rows
            ->pluck('canonical_revision_id')
            ->map(
                static fn ($id): int =>
                    (int) $id
            )
            ->all();

        $revisions = WorkspaceV2CanonicalRevision::query()
            ->whereIn('id', $revisionIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($revisions->count() !== count($revisionIds)) {
            return false;
        }

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
                || $row->source_domain_key
                    !== $revision->domain_key
                || $row->source_component_key
                    !== $revision->component_key
                || (int) $row->source_revision
                    !== (int) $revision->revision
                || $revision->state
                    !== 'approved_current'
            ) {
                return false;
            }
        }

        return true;
    }

    private function assertApprovedDecision(
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $decision
    ): void {
        $expected = [
            'decision' => 'approved',
            'decision_authority' => 'approval_engine',
            'publication_authorized' => true,
            'canonical_published' => false,
            'canonical_revision_created' => false,
            'current_state_changed' => false,
            'readiness_passed' => true,
            'sources_fresh' => true,
            'domain_key' => $artifact->target_domain_key,
            'component_key' => $artifact->target_component_key,
        ];

        foreach ($expected as $key => $value) {
            if (
                ! array_key_exists($key, $decision)
                || $decision[$key] !== $value
            ) {
                throw new \LogicException(
                    'F6 approval result does not match the frozen '
                    .'F11 orchestration contract.'
                );
            }
        }
    }

    private function assertPublishedRevision(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $decision,
        WorkspaceV2CanonicalRevision $revision
    ): void {
        if (
            (int) $revision->workspace_id
                !== (int) $workspace->id
            || $revision->domain_key
                !== $artifact->target_domain_key
            || $revision->component_key
                !== $artifact->target_component_key
            || $revision->state
                !== 'approved_current'
            || $revision->payload
                !== $artifact->payload
            || $revision->approval_decision
                !== $decision
        ) {
            throw new \LogicException(
                'F7 publication result does not match the frozen '
                .'F11 orchestration contract.'
            );
        }
    }

    private function assertConsumedArtifact(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $locked,
        WorkspaceV2CanonicalRevision $revision,
        WorkspaceV2CanonicalWorkArtifact $consumed
    ): void {
        if (
            (int) $consumed->id
                !== (int) $locked->id
            || (int) $consumed->workspace_id
                !== (int) $workspace->id
            || $consumed->state
                !== 'proposed'
            || (int) $consumed->published_canonical_revision_id
                !== (int) $revision->id
            || $consumed->consumed_at === null
        ) {
            throw new \LogicException(
                'F10 consumption result does not match the frozen '
                .'F11 orchestration contract.'
            );
        }
    }

    private function assertRejectedDecision(
        WorkspaceV2CanonicalWorkArtifact $artifact,
        string $reason,
        array $decision
    ): void {
        $expected = [
            'decision' => 'rejected',
            'decision_authority' => 'approval_engine',
            'publication_authorized' => false,
            'canonical_published' => false,
            'canonical_revision_created' => false,
            'current_state_changed' => false,
            'proposal_state_after_decision' => 'proposed',
            'revision_path' => 'working_draft',
            'domain_key' => $artifact->target_domain_key,
            'component_key' => $artifact->target_component_key,
            'reason' => trim($reason),
        ];

        foreach ($expected as $key => $value) {
            if (
                ! array_key_exists($key, $decision)
                || $decision[$key] !== $value
            ) {
                throw new \LogicException(
                    'F6 rejection result does not match the frozen '
                    .'F11 orchestration contract.'
                );
            }
        }
    }

    private function assertReturnedWorkingDraft(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $locked,
        WorkspaceV2CanonicalWorkArtifact $returned
    ): void {
        if (
            (int) $returned->id
                !== (int) $locked->id
            || (int) $returned->workspace_id
                !== (int) $workspace->id
            || $returned->state
                !== 'working_draft'
            || $returned->submitted_by_user_id !== null
            || $returned->submitted_at !== null
            || $returned->published_canonical_revision_id !== null
            || $returned->consumed_at !== null
        ) {
            throw new \LogicException(
                'F10 returned draft does not match the frozen '
                .'F11 orchestration contract.'
            );
        }
    }

    private function validationError(
        string $key,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $key => [$message],
        ]);
    }
}
