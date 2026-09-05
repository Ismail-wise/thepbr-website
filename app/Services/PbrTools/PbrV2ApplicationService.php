<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2OperatingRecord;
use App\Models\WorkspaceV2ReviewCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PbrV2ApplicationService
{
    public function __construct(
        private readonly PbrV2CanonicalWorkService $work,
        private readonly PbrV2CanonicalWorkflowService $workflow,
        private readonly PbrV2ReviewAuditService $review,
        private readonly PbrV2ExecutionService $execution
    ) {
    }

    public function createWorkingDraft(
        User $actor,
        PartnershipWorkspace $workspace,
        string $targetDomainKey,
        string $targetComponentKey,
        string $producerDomainKey,
        string $producerComponentKey,
        array $payload,
        array $sourceCanonicalRevisionIds = []
    ): WorkspaceV2CanonicalWorkArtifact {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);

        return $this->work->createWorkingDraft(
            $workspace,
            $targetDomainKey,
            $targetComponentKey,
            $producerDomainKey,
            $producerComponentKey,
            $payload,
            $sourceCanonicalRevisionIds,
            $actor
        );
    }

    public function updateWorkingDraftPayload(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $payload
    ): WorkspaceV2CanonicalWorkArtifact {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $artifact = $this->workspaceTarget($workspace, $artifact, 'work_artifact');

        return $this->work->updateWorkingDraftPayload($workspace, $artifact, $payload);
    }

    public function refreshWorkingDraftSources(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $sourceCanonicalRevisionIds
    ): WorkspaceV2CanonicalWorkArtifact {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $artifact = $this->workspaceTarget($workspace, $artifact, 'work_artifact');

        return $this->work->refreshWorkingDraftSources(
            $workspace,
            $artifact,
            $sourceCanonicalRevisionIds
        );
    }

    public function submitProposal(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): WorkspaceV2CanonicalWorkArtifact {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $artifact = $this->workspaceTarget($workspace, $artifact, 'work_artifact');

        return $this->work->submitProposal($workspace, $artifact, $actor);
    }

    public function rejectAndReturnToWorkingDraft(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        string $reason
    ): array {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $artifact = $this->workspaceTarget($workspace, $artifact, 'work_artifact');

        return $this->workflow->rejectAndReturnToWorkingDraft(
            $workspace,
            $artifact,
            $reason
        );
    }

    public function openReview(
        User $actor,
        PartnershipWorkspace $workspace,
        string $producerDomainKey,
        string $producerComponentKey,
        string $targetDomainKey,
        string $targetComponentKey,
        int $targetRevision,
        string $triggerType,
        array $triggerContext = []
    ): WorkspaceV2ReviewCase {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);

        return $this->review->openReview(
            $workspace,
            $producerDomainKey,
            $producerComponentKey,
            $targetDomainKey,
            $targetComponentKey,
            $targetRevision,
            $triggerType,
            $triggerContext,
            $actor
        );
    }

    public function resolveReview(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        string $resolution,
        ?string $resolutionNote = null
    ): WorkspaceV2ReviewCase {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $case = $this->workspaceTarget($workspace, $case, 'review_case');

        return $this->review->resolveReview(
            $workspace,
            $case,
            $resolution,
            $resolutionNote,
            $actor
        );
    }

    public function requestAmendmentAndCreateWorkingDraft(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        string $producerDomainKey,
        string $producerComponentKey,
        array $payload,
        array $sourceCanonicalRevisionIds = [],
        ?string $reason = null,
        array $amendmentContext = []
    ): array {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);

        return DB::transaction(function () use (
            $actor,
            $workspace,
            $case,
            $producerDomainKey,
            $producerComponentKey,
            $payload,
            $sourceCanonicalRevisionIds,
            $reason,
            $amendmentContext
        ): array {
            $case = $this->workspaceTarget($workspace, $case, 'review_case');

            $handoff = $this->review->requestAmendment(
                $workspace,
                $case,
                $reason,
                $amendmentContext,
                $actor
            );

            $artifact = $this->work->createAmendmentWorkingDraft(
                $workspace,
                $case,
                $producerDomainKey,
                $producerComponentKey,
                $payload,
                $sourceCanonicalRevisionIds,
                $actor
            );

            return [
                'review_handoff' => $handoff,
                'work_artifact' => $artifact,
            ];
        });
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
        [$actor, $workspace] = $this->managementContext($actor, $workspace);

        return $this->execution->createAction(
            $actor,
            $workspace,
            $producerDomainKey,
            $producerComponentKey,
            $sourceCanonicalRevisionIds,
            $attributes,
            $producerCanonicalRevisionId,
            $producerOperatingRecordId
        );
    }

    public function transitionAction(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2Action $action,
        string $toState
    ): WorkspaceV2Action {
        [$actor, $workspace] = $this->managementContext($actor, $workspace);
        $action = $this->workspaceTarget($workspace, $action, 'action');

        return $this->execution->transitionAction($actor, $workspace, $action, $toState);
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
        [$actor, $workspace] = $this->managementContext($actor, $workspace);

        return $this->execution->appendRecord(
            $actor,
            $workspace,
            $domainKey,
            $componentKey,
            $sourceCanonicalRevisionIds,
            $payload,
            $attributes
        );
    }

    private function managementContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $actor = User::query()->findOrFail($actor->getKey());
        $workspace = PartnershipWorkspace::query()->findOrFail($workspace->getKey());

        if (! (
            $actor->isAdmin()
            || (
                (int) $workspace->owner_user_id === (int) $actor->id
                && $actor->isStudent()
            )
        )) {
            throw new AuthorizationException('V2 workspace management is not permitted.');
        }

        return [$actor, $workspace];
    }

    private function workspaceTarget(
        PartnershipWorkspace $workspace,
        Model $target,
        string $errorKey
    ): Model {
        $class = match ($errorKey) {
            'work_artifact' => WorkspaceV2CanonicalWorkArtifact::class,
            'review_case' => WorkspaceV2ReviewCase::class,
            'action' => WorkspaceV2Action::class,
        };

        $fresh = $class::query()
            ->whereKey($target->getKey())
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $fresh) {
            throw ValidationException::withMessages([
                $errorKey => 'V2 target does not exist in this workspace.',
            ]);
        }

        return $fresh;
    }
}
