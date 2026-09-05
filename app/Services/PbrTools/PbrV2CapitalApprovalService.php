<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PbrV2CapitalApprovalService
{
    public function __construct(
        private readonly PbrV2CapitalPlanReadinessService $readiness,
        private readonly PbrV2CanonicalWorkflowService $workflow
    ) {
    }

    public function approveAndPublish(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): array {
        return DB::transaction(function () use ($actor, $workspace, $artifact): array {
            $actor = User::query()->findOrFail($actor->getKey());
            $workspace = PartnershipWorkspace::query()
                ->whereKey($workspace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! (
                $actor->isAdmin()
                || (
                    (int) $workspace->owner_user_id === (int) $actor->id
                    && $actor->isStudent()
                )
            )) {
                throw new AuthorizationException('V2 workspace management is not permitted.');
            }

            $locked = WorkspaceV2CanonicalWorkArtifact::query()
                ->whereKey($artifact->getKey())
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'work_artifact' => 'V2 target does not exist in this workspace.',
                ]);
            }

            if ($locked->target_domain_key !== 'capital'
                || $locked->target_component_key !== 'approved_capital_plan') {
                throw ValidationException::withMessages([
                    'target' => 'Capital approval requires capital / approved_capital_plan.',
                ]);
            }

            if ($locked->state !== 'proposed') {
                throw ValidationException::withMessages([
                    'state' => 'V2 canonical workflow requires a proposed artifact.',
                ]);
            }

            if ($locked->consumed_at !== null
                || $locked->published_canonical_revision_id !== null) {
                throw ValidationException::withMessages([
                    'work_artifact' => 'Consumed V2 canonical proposal is terminal.',
                ]);
            }

            $assessment = $this->readiness->assess(
                $locked->target_domain_key,
                $locked->target_component_key,
                $locked->payload
            );

            if (! $assessment['passed']) {
                $messages = [];
                foreach ($assessment['errors'] as $error) {
                    $path = $error['path'] === '' ? 'payload' : 'payload.'.$error['path'];
                    $messages[$path][] = $error['code'].': '.$error['message'];
                }

                throw ValidationException::withMessages($messages);
            }

            $messages = [];
            foreach (['business_stage', 'currency_code'] as $field) {
                if ($locked->payload[$field] !== $workspace->getAttribute($field)) {
                    $messages['payload.'.$field] = 'Capital plan must match the current workspace '.$field.'.';
                }
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }

            // Keep the assessed proposal locked until F11 consumes that same proposal.
            // Readiness evidence is returned, not inserted into the frozen payload.
            $workflow = $this->workflow->approveAndPublish(
                $workspace,
                $locked,
                true,
                $actor
            );

            return [
                'readiness' => $assessment,
                'workflow' => $workflow,
            ];
        });
    }
}
