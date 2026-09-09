<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PbrV2ContributionApprovalService
{
    public function __construct(
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ContributionReadinessService $readiness,
        private readonly PbrV2CanonicalWorkService $work,
        private readonly PbrV2CanonicalWorkflowService $workflow,
    ) {}

    /**
     * Approves the reviewed agreement only. No delivery, acceptance or publication.
     * Every approver must agree to the same persisted subject and agreed value.
     */
    public function approveItem(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        string $itemId,
        string $agreedValue,
        string $evidenceReference,
        string $notes = '',
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $actor, $workspace, $artifact, $expectedVersion,
            $itemId, $agreedValue, $evidenceReference, $notes,
        ) {
            $this->access->currentContext($actor, $workspace);
            $workspace = PartnershipWorkspace::query()->whereKey($workspace->getKey())
                ->lockForUpdate()->firstOrFail();
            $locked = WorkspaceV2CanonicalWorkArtifact::query()
                ->where('workspace_id', $workspace->id)->whereKey($artifact->getKey())
                ->lockForUpdate()->firstOrFail();

            if ($locked->target_domain_key !== 'contribution'
                || $locked->target_component_key !== 'accepted_contribution_register'
                || $locked->producer_domain_key !== 'contribution'
                || $locked->producer_component_key !== 'partner_contributions_workflow'
                || $locked->state !== 'working_draft' || $locked->isConsumed()) {
                $this->invalid('work_artifact', 'An unconsumed contribution working draft is required.');
            }
            $payload = $locked->payload;
            if (! is_array($payload)
                || ($payload['schema_version'] ?? null) !== 'contribution-register-v1'
                || ! is_int($payload['edit_version'] ?? null)
                || $expectedVersion < 1
                || $payload['edit_version'] !== $expectedVersion
                || $expectedVersion >= PHP_INT_MAX) {
                $this->invalid('edit_version', 'This draft has changed or has an invalid version.');
            }
            foreach (['setup', 'items', 'event_history'] as $key) {
                if (! is_array($payload[$key] ?? null)
                    || ($key !== 'setup' && ! array_is_list($payload[$key]))) {
                    $this->invalid('payload', 'Invalid stored contribution structure.');
                }
            }
            $setup = $payload['setup'];
            if (! $this->readiness->assessSetup($setup)['passed']
                || $setup['currency_code'] !== $workspace->currency_code) {
                $this->invalid('setup', 'Valid setup matching the workspace currency is required.');
            }
            $required = $setup['approver_user_ids'];
            $actor = $this->access->approvalContext($actor, $workspace, $required)['actor'];

            $index = null;
            foreach ($payload['items'] as $position => $candidate) {
                if (is_array($candidate) && ($candidate['id'] ?? null) === $itemId) {
                    if ($index !== null) {
                        $this->invalid('item_id', 'Duplicate stored item identity.');
                    }
                    $index = $position;
                }
            }
            if ($index === null) {
                $this->invalid('item_id', 'Contribution item does not exist in this draft.');
            }
            $item = $payload['items'][$index];
            if (($item['status'] ?? null) !== 'reviewed') {
                $this->invalid('status', 'Only a reviewed item can receive agreement approval.');
            }
            foreach ([$agreedValue, $item['reviewed_value'] ?? null] as $value) {
                if (! is_string($value)
                    || ! preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $value)) {
                    $this->invalid('valuation', 'Reviewed and agreed values must be valid decimal strings.');
                }
            }
            if (trim($evidenceReference) === '') {
                $this->invalid('evidence_reference', 'Approval evidence is required.');
            }
            if (! $this->readiness->assessItemForReview($item)['passed']) {
                $this->invalid('item', 'Stored contribution evidence is incomplete.');
            }

            $records = $item['approval_records'] ?? [];
            $history = $item['event_history'] ?? [];
            if (! is_array($records) || ! array_is_list($records)
                || ! is_array($history) || ! array_is_list($history)) {
                $this->invalid('approval_records', 'Invalid stored approval or history structure.');
            }
            if ($records !== [] && ($item['agreed_value'] ?? null) !== $agreedValue) {
                $this->invalid('agreed_value', 'Existing approvals bind a different agreed value. Revise and review first.');
            }
            $item['agreed_value'] = $agreedValue;
            $fingerprint = $this->readiness->agreementFingerprint(
                (int) $workspace->id, (int) $locked->id, $setup, $item,
            );
            $recordIds = [];
            foreach ($records as $record) {
                $id = is_array($record) ? ($record['user_id'] ?? null) : null;
                if (! is_int($id) || ! in_array($id, $required, true)) {
                    $this->invalid('approval_records', 'Stored approval has an invalid approver.');
                }
                $recordIds[] = $id;
            }
            if ($records !== [] && ! $this->readiness->assessApprovalRecords(
                $records, $recordIds, $fingerprint,
            )['passed']) {
                $this->invalid('approval_records', 'Stored approvals are stale or invalid.');
            }
            if (in_array((int) $actor->id, $recordIds, true)) {
                $this->invalid('approval_records', 'This approver has already approved the item.');
            }

            // Current eligibility is rechecked for every designated approver.
            foreach ($required as $id) {
                $person = User::query()->findOrFail($id);
                $this->access->approvalContext($person, $workspace, $required);
            }

            $record = [
                'user_id' => (int) $actor->id,
                'decision' => 'approved',
                'approved_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'evidence_reference' => trim($evidenceReference),
                'notes' => $notes,
                'fingerprint' => $fingerprint,
            ];
            $records[] = $record;
            $complete = $this->readiness->assessApprovalRecords($records, $required, $fingerprint)['passed'];
            $item['approval_records'] = $records;
            $item['status'] = $complete ? 'approved' : 'reviewed';
            $history[] = [
                'action' => 'agreement_approved',
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $record['approved_at'],
                'approval_record' => $record,
            ];
            $item['event_history'] = $history;
            $payload['items'][$index] = $item;
            $payload['edit_version']++;
            $payload['event_history'][] = [
                'action' => 'agreement_approved',
                'item_id' => $itemId,
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $record['approved_at'],
            ];

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Approves acceptance of an already delivered contribution.
     * Uses a fresh acceptance fingerprint and never publishes the draft.
     */
    public function acceptItem(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        string $itemId,
        string $evidenceReference,
        string $notes = '',
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $actor, $workspace, $artifact, $expectedVersion,
            $itemId, $evidenceReference, $notes,
        ) {
            $this->access->currentContext($actor, $workspace);

            $workspace = PartnershipWorkspace::query()
                ->whereKey($workspace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked = WorkspaceV2CanonicalWorkArtifact::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($artifact->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->target_domain_key !== 'contribution'
                || $locked->target_component_key !== 'accepted_contribution_register'
                || $locked->producer_domain_key !== 'contribution'
                || $locked->producer_component_key !== 'partner_contributions_workflow'
                || $locked->state !== 'working_draft'
                || $locked->isConsumed()) {
                $this->invalid('work_artifact', 'An unconsumed contribution working draft is required.');
            }

            $payload = $locked->payload;
            if (! is_array($payload)
                || ($payload['schema_version'] ?? null) !== 'contribution-register-v1'
                || ! is_int($payload['edit_version'] ?? null)
                || $expectedVersion < 1
                || $payload['edit_version'] !== $expectedVersion
                || $expectedVersion >= PHP_INT_MAX) {
                $this->invalid('edit_version', 'This draft has changed or has an invalid version.');
            }

            foreach (['setup', 'items', 'event_history'] as $key) {
                if (! is_array($payload[$key] ?? null)
                    || ($key !== 'setup' && ! array_is_list($payload[$key]))) {
                    $this->invalid('payload', 'Invalid stored contribution structure.');
                }
            }

            $setup = $payload['setup'];
            if (! $this->readiness->assessSetup($setup)['passed']
                || ($setup['currency_code'] ?? null) !== $workspace->currency_code) {
                $this->invalid('setup', 'Valid setup matching the workspace currency is required.');
            }

            $required = $setup['approver_user_ids'];
            $actor = $this->access->approvalContext($actor, $workspace, $required)['actor'];

            $index = null;
            foreach ($payload['items'] as $position => $candidate) {
                if (is_array($candidate) && ($candidate['id'] ?? null) === $itemId) {
                    if ($index !== null) {
                        $this->invalid('item_id', 'Duplicate stored item identity.');
                    }
                    $index = $position;
                }
            }

            if ($index === null) {
                $this->invalid('item_id', 'Contribution item does not exist in this draft.');
            }

            $item = $payload['items'][$index];
            if (($item['status'] ?? null) !== 'delivered') {
                $this->invalid('status', 'Only a delivered item can receive acceptance approval.');
            }

            if (trim($evidenceReference) === '') {
                $this->invalid('evidence_reference', 'Acceptance evidence is required.');
            }

            if (! $this->readiness->assessItemDelivery($item)['passed']) {
                $this->invalid('item', 'Stored delivery is incomplete or invalid.');
            }

            $records = $item['approval_records'] ?? [];
            $history = $item['event_history'] ?? [];

            if (! is_array($records) || ! array_is_list($records)
                || ! is_array($history) || ! array_is_list($history)) {
                $this->invalid('approval_records', 'Invalid stored acceptance approval or history structure.');
            }

            try {
                $fingerprint = $this->readiness->acceptanceFingerprint(
                    (int) $workspace->id,
                    (int) $locked->id,
                    $setup,
                    $item,
                );
            } catch (\InvalidArgumentException|\JsonException $exception) {
                $this->invalid('item', 'Stored acceptance subject is invalid.');
            }

            $recordIds = [];
            foreach ($records as $record) {
                $id = is_array($record) ? ($record['user_id'] ?? null) : null;
                if (! is_int($id) || ! in_array($id, $required, true)) {
                    $this->invalid('approval_records', 'Stored acceptance approval has an invalid approver.');
                }
                $recordIds[] = $id;
            }

            if ($records !== [] && ! $this->readiness->assessApprovalRecords(
                $records,
                $recordIds,
                $fingerprint,
            )['passed']) {
                $this->invalid('approval_records', 'Stored acceptance approvals are stale or invalid.');
            }

            if (in_array((int) $actor->id, $recordIds, true)) {
                $this->invalid('approval_records', 'This approver has already accepted the delivered item.');
            }

            foreach ($required as $id) {
                $person = User::query()->findOrFail($id);
                $this->access->approvalContext($person, $workspace, $required);
            }

            $record = [
                'user_id' => (int) $actor->id,
                'decision' => 'approved',
                'approved_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'evidence_reference' => trim($evidenceReference),
                'notes' => $notes,
                'fingerprint' => $fingerprint,
            ];

            $records[] = $record;
            $complete = $this->readiness->assessApprovalRecords(
                $records,
                $required,
                $fingerprint,
            )['passed'];

            $item['approval_records'] = $records;
            $item['status'] = $complete ? 'accepted' : 'delivered';

            $history[] = [
                'action' => 'acceptance_approved',
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $record['approved_at'],
                'approval_record' => $record,
            ];
            $item['event_history'] = $history;

            if ($complete && ! $this->readiness->assessAcceptedItem(
                (int) $workspace->id,
                (int) $locked->id,
                $setup,
                $item,
            )['passed']) {
                $this->invalid('acceptance', 'Accepted contribution failed final consistency checks.');
            }

            $payload['items'][$index] = $item;
            $payload['edit_version']++;
            $payload['event_history'][] = [
                'action' => 'acceptance_approved',
                'item_id' => $itemId,
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $record['approved_at'],
                'accepted' => $complete,
            ];

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Validates the locked working draft and enters the canonical proposal
     * state. Browser edit_version protection is enforced before submission.
     */
    public function submitForPublication(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $artifact,
            $expectedVersion,
        ): array {
            $context = $this->managementContext(
                $actor,
                $workspace
            );

            $actor = $context['actor'];
            $workspace = $context['workspace'];

            $locked = $this->lockedPublicationArtifact(
                $workspace,
                $artifact,
                'working_draft'
            );

            $payload = $locked->payload;

            if (! is_array($payload)
                || ! is_int($payload['edit_version'] ?? null)
                || $expectedVersion < 1
                || ($payload['edit_version'] ?? null) !== $expectedVersion) {
                $this->invalid(
                    'edit_version',
                    'This contribution draft changed. Reload before submission.'
                );
            }

            $assessment = $this->publicationAssessment(
                $workspace,
                $locked,
                $payload
            );

            $this->assertAmendmentBoundary(
                $workspace,
                $locked
            );

            if ($this->work
                ->sources($workspace, $locked)
                ->isNotEmpty()) {
                $this->invalid(
                    'source_canonical_revision_ids',
                    'The accepted contribution register is a root canonical register and cannot pin upstream sources.'
                );
            }

            $proposal = $this->work->submitProposal(
                $workspace,
                $locked,
                $actor
            );

            return [
                'readiness' => $assessment,
                'work_artifact' => $proposal,
            ];
        });
    }

    /**
     * Revalidates the persisted proposed register and delegates canonical
     * publication only after Chapter 2 authorization and readiness succeed.
     */
    public function approveAndPublish(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $artifact,
        ): array {
            $context = $this->managementContext(
                $actor,
                $workspace
            );

            $actor = $context['actor'];
            $workspace = $context['workspace'];

            $locked = $this->lockedPublicationArtifact(
                $workspace,
                $artifact,
                'proposed'
            );

            $payload = $locked->payload;
            if (! is_array($payload)) {
                $this->invalid(
                    'payload',
                    'Persisted contribution proposal payload is invalid.'
                );
            }

            $assessment = $this->publicationAssessment(
                $workspace,
                $locked,
                $payload
            );

            $this->assertAmendmentBoundary(
                $workspace,
                $locked
            );

            if ($this->work
                ->sources($workspace, $locked)
                ->isNotEmpty()) {
                $this->invalid(
                    'source_canonical_revision_ids',
                    'The accepted contribution register is a root canonical register and cannot pin upstream sources.'
                );
            }

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

    private function managementContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $this->access->managementContext(
            $actor,
            $workspace
        );

        $lockedWorkspace = PartnershipWorkspace::query()
            ->whereKey($workspace->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $this->access->managementContext(
            $actor,
            $lockedWorkspace
        );
    }

    private function lockedPublicationArtifact(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        string $requiredState
    ): WorkspaceV2CanonicalWorkArtifact {
        $locked = WorkspaceV2CanonicalWorkArtifact::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($artifact->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            $this->invalid(
                'work_artifact',
                'Contribution publication artifact does not exist in this workspace.'
            );
        }

        if ($locked->target_domain_key !== 'contribution'
            || $locked->target_component_key !== 'accepted_contribution_register'
            || $locked->producer_domain_key !== 'contribution'
            || $locked->producer_component_key !== 'partner_contributions_workflow') {
            $this->invalid(
                'target',
                'Contribution publication requires contribution / accepted_contribution_register.'
            );
        }

        if ($locked->state !== $requiredState) {
            $this->invalid(
                'state',
                'Contribution publication artifact must be '.$requiredState.'.'
            );
        }

        if ($locked->isConsumed()) {
            $this->invalid(
                'work_artifact',
                'Consumed contribution publication artifacts are terminal.'
            );
        }

        return $locked;
    }

    private function publicationAssessment(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        array $payload
    ): array {
        $assessment = $this->readiness->assessPublication(
            (int) $workspace->id,
            (int) $artifact->id,
            $payload
        );

        if (! $assessment['passed']) {
            $messages = [];

            foreach ($assessment['errors'] as $error) {
                $path = $error['path'] === ''
                    ? 'payload'
                    : 'payload.'.$error['path'];

                $messages[$path][] =
                    $error['code'].': '.$error['message'];
            }

            throw ValidationException::withMessages(
                $messages
            );
        }

        if (($payload['setup']['currency_code'] ?? null)
            !== $workspace->currency_code) {
            $this->invalid(
                'payload.setup.currency_code',
                'Contribution setup currency must match the current workspace currency.'
            );
        }

        foreach ($payload['setup']['approver_user_ids'] as $id) {
            $person = User::query()->find($id);

            if (! $person) {
                $this->invalid(
                    'payload.setup.approver_user_ids',
                    'A designated contribution approver no longer exists.'
                );
            }

            try {
                $this->access->approvalContext(
                    $person,
                    $workspace,
                    $payload['setup']['approver_user_ids']
                );
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                $this->invalid(
                    'payload.setup.approver_user_ids',
                    'Every designated contribution approver must still have current workspace approval access.'
                );
            }
        }

        return $assessment;
    }

    /**
     * A new root draft must never silently supersede an approved register.
     * Once a current revision exists, only canonical amendment work targeting
     * that exact current revision may proceed.
     */
    private function assertAmendmentBoundary(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): void {
        $currents =
            \App\Models\WorkspaceV2CanonicalRevision::query()
                ->where('workspace_id', $workspace->id)
                ->where('domain_key', 'contribution')
                ->where(
                    'component_key',
                    'accepted_contribution_register'
                )
                ->where('state', 'approved_current')
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->get();

        if ($currents->count() > 1) {
            $this->invalid(
                'amendment',
                'Canonical integrity violation: multiple current contribution registers exist.'
            );
        }

        $current = $currents->first();

        $reviewCaseId =
            $artifact->amendment_review_case_id;

        $targetRevisionId =
            $artifact
                ->amendment_target_canonical_revision_id;

        if (! $current) {
            if ($reviewCaseId !== null
                || $targetRevisionId !== null) {
                $this->invalid(
                    'amendment',
                    'Initial contribution publication cannot claim an amendment target.'
                );
            }

            return;
        }

        if ($reviewCaseId === null
            || $targetRevisionId === null) {
            $this->invalid(
                'amendment',
                'An approved contribution register may only be replaced through amendment review.'
            );
        }

        if ((int) $targetRevisionId
            !== (int) $current->id) {
            $this->invalid(
                'amendment',
                'Contribution amendment target is no longer the current approved register.'
            );
        }

        $case =
            \App\Models\WorkspaceV2ReviewCase::query()
                ->whereKey($reviewCaseId)
                ->lockForUpdate()
                ->first();

        if (! $case
            || (int) $case->workspace_id
                !== (int) $workspace->id
            || $case->status !== 'resolved'
            || $case->resolution
                !== 'amendment_required'
            || (int) $case
                ->target_canonical_revision_id
                !== (int) $current->id
            || (int) $case->target_revision
                !== (int) $current->revision
            || $case->target_domain_key
                !== 'contribution'
            || $case->target_component_key
                !== 'accepted_contribution_register') {
            $this->invalid(
                'amendment',
                'Contribution amendment requires the exact resolved amendment review for the current register.'
            );
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
