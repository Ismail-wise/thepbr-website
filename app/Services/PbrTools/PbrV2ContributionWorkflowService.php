<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2ReviewCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PbrV2ContributionWorkflowService
{
    public function __construct(
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2CanonicalWorkService $work,
        private readonly PbrV2ContributionReadinessService $readiness,
        private readonly PbrV2ReviewAuditService $review,
    ) {}

    public function createDraft(User $actor, PartnershipWorkspace $workspace): WorkspaceV2CanonicalWorkArtifact
    {
        return DB::transaction(function () use ($actor, $workspace) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];

            return $this->work->createWorkingDraft(
                $workspace,
                'contribution',
                'accepted_contribution_register',
                'contribution',
                'partner_contributions_workflow',
                [
                    'schema_version' => 'contribution-register-v1',
                    'edit_version' => 1,
                    'setup' => [],
                    'partners' => [],
                    'items' => [],
                    'event_history' => [$this->event($actor, 'draft_created')],
                ],
                [],
                $actor,
            );
        });
    }

    public function saveSetup(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        array $setup,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use ($actor, $workspace, $artifact, $expectedVersion, $setup) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);

            $allowed = [
                'valuation_date', 'currency_code', 'contribution_period',
                'valuation_owner_reference', 'approver_user_ids',
            ];
            if (array_diff(array_keys($setup), $allowed) !== []
                || (isset($setup['contribution_period'])
                    && is_array($setup['contribution_period'])
                    && array_diff(array_keys($setup['contribution_period']), ['start_date', 'end_date']) !== [])) {
                $this->invalid('setup', 'Unexpected setup fields.');
            }

            $assessment = $this->readiness->assessSetup($setup);
            if (! $assessment['passed']) {
                $this->invalid('setup', 'Complete a valid contribution setup before saving.');
            }
            if ($setup['currency_code'] !== $workspace->currency_code) {
                $this->invalid('setup.currency_code', 'Setup currency must match the workspace.');
            }

            foreach ($setup['approver_user_ids'] as $id) {
                $approver = User::query()->findOrFail($id);
                $this->access->approvalContext($approver, $workspace, $setup['approver_user_ids']);
            }

            $payload = $locked->payload;
            if ($payload['setup'] === $setup) {
                return $locked;
            }

            foreach ($payload['items'] as &$item) {
                if (! is_array($item)) {
                    $this->invalid('payload', 'Invalid stored contribution item.');
                }
                $history = $item['event_history'] ?? [];
                if (! is_array($history) || ! array_is_list($history)) {
                    $this->invalid('payload', 'Invalid stored item history.');
                }
                $history[] = array_merge($this->event($actor, 'setup_changed'), [
                    'previous_status' => $item['status'] ?? null,
                    'previous_approval_records' => $item['approval_records'] ?? [],
                ]);
                $item['event_history'] = $history;
                $item['approval_records'] = [];
                if (! in_array($item['status'] ?? null, ['rejected', 'cancelled', 'defaulted'], true)) {
                    $item['status'] = 'proposed';
                }
            }
            unset($item);

            $previousSetup = $payload['setup'];
            $payload['setup'] = $setup;
            $payload['edit_version']++;
            $payload['event_history'][] = array_merge($this->event($actor, 'setup_saved'), [
                'previous_setup' => $previousSetup,
                'setup' => $setup,
            ]);

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    public function savePartners(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        array $profileIds,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use ($actor, $workspace, $artifact, $expectedVersion, $profileIds) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);

            if (! array_is_list($profileIds) || $profileIds === []) {
                $this->invalid('partners', 'Select a nonempty list of partner profiles.');
            }
            $seen = [];
            foreach ($profileIds as $id) {
                if (! is_int($id) || $id < 1 || isset($seen[$id])) {
                    $this->invalid('partners', 'Partner profile IDs must be unique positive integers.');
                }
                $seen[$id] = true;
            }

            $payload = $locked->payload;
            $existing = [];
            foreach ($payload['partners'] as $snapshot) {
                if (! is_array($snapshot) || ! is_string($snapshot['partner_key'] ?? null)) {
                    $this->invalid('partners', 'Invalid stored partner snapshot.');
                }
                $existing[$snapshot['partner_key']] = $snapshot;
            }

            $snapshots = [];
            $keys = [];
            foreach ($profileIds as $id) {
                $profile = \App\Models\WorkspacePartnerProfile::query()
                    ->where('workspace_id', $workspace->id)->whereKey($id)
                    ->lockForUpdate()->firstOrFail();

                $key = $profile->partner_key;
                if (! is_string($key) || trim($key) === '' || isset($keys[$key])
                    || ! in_array($profile->status, ['active', 'planned'], true)) {
                    $this->invalid('partners', 'Partner profile is unavailable or duplicated.');
                }
                $keys[$key] = true;
                $name = $profile->display_name;

                if ($profile->user_id !== null) {
                    $person = User::query()->findOrFail($profile->user_id);
                    $this->access->currentContext($person, $workspace);
                    $name = $person->name;
                }
                if (! is_string($name) || trim($name) === '') {
                    $this->invalid('partners', 'Partner display name is required.');
                }

                // Explicit selection does not silently rewrite historical snapshots.
                $snapshots[] = $existing[$key] ?? [
                    'profile_id' => (int) $profile->id,
                    'partner_key' => $key,
                    'user_id' => $profile->user_id === null ? null : (int) $profile->user_id,
                    'display_name' => $name,
                    'status' => $profile->status,
                ];
            }

            foreach ($payload['items'] as $item) {
                if (! is_array($item) || ! isset($keys[$item['partner_key'] ?? ''])) {
                    $this->invalid('partners', 'A partner referenced by an item cannot be removed.');
                }
            }
            if ($payload['partners'] === $snapshots) {
                return $locked;
            }
            $previous = $payload['partners'];
            $payload['partners'] = $snapshots;
            $payload['edit_version']++;
            $payload['event_history'][] = array_merge($this->event($actor, 'partners_saved'), [
                'previous_partners' => $previous,
                'partners' => $snapshots,
            ]);

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Saves editable item data, not review/approval/acceptance decisions.
     * Existing items are replaced, so omitted editable fields are removed.
     */
    public function saveItem(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        array $input,
        ?string $itemId = null,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use ($actor, $workspace, $artifact, $expectedVersion, $input, $itemId) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);
            $payload = $locked->payload;
            $type = $input['contribution_type'] ?? null;
            if (! is_string($type) || ! in_array($type, config('pbr_v2_contribution.contribution_types'), true)) {
                $this->invalid('contribution_type', 'Choose a supported contribution type.');
            }

            $serverFields = ['id', 'partner_snapshot', 'status', 'approval_records', 'event_history'];
            $allowed = array_merge(
                array_values(array_diff(config('pbr_v2_contribution.common_item_fields'), $serverFields)),
                config('pbr_v2_contribution.type_fields.'.$type),
                config('pbr_v2_contribution.partial_delivery_fields'),
                ['delivery_extent'],
            );
            if (array_diff(array_keys($input), $allowed) !== []) {
                $this->invalid('item', 'Unexpected or server-controlled item fields.');
            }
            // Reuse the strict JSON value check without treating this as an approval.
            try {
                $this->readiness->acceptanceFingerprint((int) $workspace->id, (int) $locked->id, [], $input);
            } catch (\InvalidArgumentException|\JsonException $exception) {
                $this->invalid('item', 'Use supported JSON values and decimal strings.');
            }

            $key = $input['partner_key'] ?? null;
            if (! is_string($key) || trim($key) === '') {
                $this->invalid('partner_key', 'Select a partner.');
            }
            $snapshot = null;
            foreach ($payload['partners'] as $candidate) {
                if (($candidate['partner_key'] ?? null) === $key) {
                    $snapshot = $candidate;
                    break;
                }
            }
            if ($snapshot === null) {
                $this->invalid('partner_key', 'Partner is not selected in this draft.');
            }

            $index = null;
            $previous = null;
            if ($itemId !== null) {
                foreach ($payload['items'] as $position => $candidate) {
                    if (($candidate['id'] ?? null) === $itemId) {
                        $index = $position;
                        $previous = $candidate;
                        break;
                    }
                }
                if ($index === null) {
                    $this->invalid('item_id', 'Contribution item does not exist in this draft.');
                }
            }
            $history = $previous['event_history'] ?? [];
            if (! is_array($history) || ! array_is_list($history)) {
                $this->invalid('item', 'Invalid stored item history.');
            }
            $previousData = $previous;
            if ($previousData !== null) {
                unset($previousData['event_history']);
            }
            $history[] = array_merge($this->event($actor, $previous === null ? 'item_created' : 'item_revised'), [
                'previous_item' => $previousData,
            ]);
            $item = array_merge($input, [
                'id' => $itemId ?? (string) \Illuminate\Support\Str::uuid(),
                'partner_snapshot' => $snapshot,
                'status' => 'proposed',
                'approval_records' => [],
                'event_history' => $history,
            ]);
            if ($index === null) {
                $payload['items'][] = $item;
            } else {
                $payload['items'][$index] = $item;
            }
            $payload['edit_version']++;
            $payload['event_history'][] = array_merge($this->event($actor, 'item_saved'), [
                'item_id' => $item['id'],
            ]);

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    public function reviewItem(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        string $itemId,
        string $reviewedValue,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use ($actor, $workspace, $artifact, $expectedVersion, $itemId, $reviewedValue) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);
            $payload = $locked->payload;
            $index = $this->itemIndex($payload['items'], $itemId);
            $item = $payload['items'][$index];

            if (($item['status'] ?? null) !== 'proposed') {
                $this->invalid('status', 'Only a proposed item may enter review.');
            }
            if (! $this->readiness->assessSetup($payload['setup'])['passed']
                || ($payload['setup']['currency_code'] ?? null) !== $workspace->currency_code) {
                $this->invalid('setup', 'A valid setup matching the workspace currency is required.');
            }
            if (! preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $reviewedValue)) {
                $this->invalid('reviewed_value', 'Provide a nonnegative decimal string with at most two decimal places.');
            }

            $item['reviewed_value'] = $reviewedValue;
            $assessment = $this->readiness->assessItemForReview($item);
            if (! $assessment['passed']) {
                $errors = [];
                foreach ($assessment['errors'] as $error) {
                    $errors['item.'.$error['path']][] = $error['message'];
                }
                throw ValidationException::withMessages($errors);
            }
            $history = $item['event_history'] ?? [];
            if (! is_array($history) || ! array_is_list($history)) {
                $this->invalid('item', 'Invalid stored item history.');
            }
            $history[] = array_merge($this->event($actor, 'item_reviewed'), [
                'previous_status' => 'proposed',
                'reviewed_value' => $reviewedValue,
            ]);
            $item['event_history'] = $history;
            $item['status'] = 'reviewed';
            $item['approval_records'] = [];
            $payload['items'][$index] = $item;
            $payload['edit_version']++;
            $payload['event_history'][] = array_merge($this->event($actor, 'item_reviewed'), [
                'item_id' => $itemId,
            ]);

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Records delivery facts on a fully agreement-approved item.
     * Agreement approvals remain in history but are cleared from the current
     * approval set so acceptance requires fresh approvals.
     */
    public function recordDelivery(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        string $itemId,
        array $delivery,
        string $evidenceReference,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use (
            $actor, $workspace, $artifact, $expectedVersion,
            $itemId, $delivery, $evidenceReference,
        ) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);

            $payload = $locked->payload;
            $index = $this->itemIndex($payload['items'], $itemId);
            $item = $payload['items'][$index];

            if (($item['status'] ?? null) !== 'approved') {
                $this->invalid('status', 'Only an agreement-approved item may be recorded as delivered.');
            }

            if (! $this->readiness->assessSetup($payload['setup'])['passed']
                || ($payload['setup']['currency_code'] ?? null) !== $workspace->currency_code) {
                $this->invalid('setup', 'A valid setup matching the workspace currency is required.');
            }

            $proof = trim($evidenceReference);
            if ($proof === '') {
                $this->invalid('evidence_reference', 'Delivery evidence is required.');
            }

            $allowed = [
                'accepted_value',
                'committed_date',
                'due_date',
                'delivered_date',
                'delivery_extent',
                'accepted_scope',
                'adjustment_basis',
            ];

            if (($item['contribution_type'] ?? null) === 'cash') {
                $allowed[] = 'amount_received';
                $allowed[] = 'payment_date';
            }

            if (array_diff(array_keys($delivery), $allowed) !== []) {
                $this->invalid('delivery', 'Unexpected delivery fields.');
            }

            $requiredDeliveryFields = [
                'accepted_value',
                'committed_date',
                'due_date',
                'delivered_date',
                'delivery_extent',
            ];

            if (array_diff($requiredDeliveryFields, array_keys($delivery)) !== []) {
                $this->invalid('delivery', 'Complete the required delivery fields.');
            }

            if (($item['contribution_type'] ?? null) === 'cash'
                && array_diff(['amount_received', 'payment_date'], array_keys($delivery)) !== []) {
                $this->invalid('delivery', 'Cash delivery must record amount received and payment date.');
            }

            $agreementRecords = $item['approval_records'] ?? null;
            $history = $item['event_history'] ?? null;

            if (! is_array($agreementRecords) || ! array_is_list($agreementRecords)
                || ! is_array($history) || ! array_is_list($history)) {
                $this->invalid('approval_records', 'Invalid stored agreement approval or history structure.');
            }

            $setup = $payload['setup'];
            $requiredApprovers = $setup['approver_user_ids'];

            try {
                $agreementFingerprint = $this->readiness->agreementFingerprint(
                    (int) $workspace->id,
                    (int) $locked->id,
                    $setup,
                    $item,
                );
            } catch (\InvalidArgumentException|\JsonException $exception) {
                $this->invalid('item', 'Stored agreement subject is invalid.');
            }

            if (! $this->readiness->assessApprovalRecords(
                $agreementRecords,
                $requiredApprovers,
                $agreementFingerprint,
            )['passed']) {
                $this->invalid('approval_records', 'A complete current agreement approval set is required before delivery.');
            }

            $deliveredItem = array_merge($item, $delivery);

            if (($deliveredItem['delivery_extent'] ?? null) === 'full') {
                unset($deliveredItem['accepted_scope'], $deliveredItem['adjustment_basis']);
            }

            $evidence = $deliveredItem['evidence_references'] ?? null;
            if (! is_array($evidence) || ! array_is_list($evidence)) {
                $this->invalid('evidence_references', 'Stored contribution evidence must be a list.');
            }

            if (! in_array($proof, $evidence, true)) {
                $evidence[] = $proof;
            }
            $deliveredItem['evidence_references'] = $evidence;

            $assessment = $this->readiness->assessItemDelivery($deliveredItem);
            if (! $assessment['passed']) {
                $errors = [];
                foreach ($assessment['errors'] as $error) {
                    $errors['delivery.'.$error['path']][] = $error['message'];
                }
                throw ValidationException::withMessages($errors);
            }

            $occurredAt = now()->utc()->format('Y-m-d\TH:i:s\Z');

            $history[] = [
                'action' => 'item_delivered',
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $occurredAt,
                'previous_status' => 'approved',
                'delivery_evidence_reference' => $proof,
                'delivery_fields' => $delivery,
                'previous_approval_records' => $agreementRecords,
            ];

            $deliveredItem['event_history'] = $history;
            $deliveredItem['status'] = 'delivered';
            $deliveredItem['approval_records'] = [];

            $payload['items'][$index] = $deliveredItem;
            $payload['edit_version']++;
            $payload['event_history'][] = [
                'action' => 'item_delivered',
                'item_id' => $itemId,
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $occurredAt,
                'evidence_reference' => $proof,
            ];

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Chapter 2 implementation policy: closure does not grant accepted value.
     * Reopening requires an explicit item revision and fresh review/approval.
     */
    public function closeItem(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
        string $itemId,
        string $targetStatus,
        string $reason,
        string $evidenceReference,
    ): WorkspaceV2CanonicalWorkArtifact {
        return DB::transaction(function () use ($actor, $workspace, $artifact, $expectedVersion, $itemId, $targetStatus, $reason, $evidenceReference) {
            $context = $this->managementContext($actor, $workspace);
            $actor = $context['actor'];
            $workspace = $context['workspace'];
            $locked = $this->lockedDraft($workspace, $artifact, $expectedVersion);
            $payload = $locked->payload;
            $index = $this->itemIndex($payload['items'], $itemId);
            $item = $payload['items'][$index];
            $allowedFrom = [
                'rejected' => ['proposed', 'reviewed'],
                'cancelled' => ['proposed', 'reviewed', 'approved', 'delivered'],
                'defaulted' => ['approved', 'delivered'],
            ];
            if (! isset($allowedFrom[$targetStatus])
                || ! in_array($item['status'] ?? null, $allowedFrom[$targetStatus], true)) {
                $this->invalid('status', 'This item closure transition is not permitted.');
            }
            if (trim($reason) === '' || trim($evidenceReference) === '') {
                $this->invalid('closure', 'A reason and evidence reference are required.');
            }
            $history = $item['event_history'] ?? [];
            if (! is_array($history) || ! array_is_list($history)) {
                $this->invalid('item', 'Invalid stored item history.');
            }
            $history[] = array_merge($this->event($actor, 'item_closed'), [
                'previous_status' => $item['status'],
                'status' => $targetStatus,
                'reason' => trim($reason),
                'evidence_reference' => trim($evidenceReference),
                'previous_approval_records' => $item['approval_records'] ?? [],
            ]);
            $item['event_history'] = $history;
            $item['status'] = $targetStatus;
            $item['approval_records'] = [];
            $payload['items'][$index] = $item;
            $payload['edit_version']++;
            $payload['event_history'][] = array_merge($this->event($actor, 'item_closed'), [
                'item_id' => $itemId,
                'status' => $targetStatus,
                'reason' => trim($reason),
                'evidence_reference' => trim($evidenceReference),
            ]);

            return $this->work->updateWorkingDraftPayload($workspace, $locked, $payload);
        });
    }

    /**
     * Opens a structured review against the exact current Chapter 2 register.
     * Opening review never changes the canonical current state.
     */
    public function openReview(
        User $actor,
        PartnershipWorkspace $workspace,
        string $triggerType = 'manual_review',
        array $triggerContext = [],
    ): WorkspaceV2ReviewCase {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $triggerType,
            $triggerContext,
        ): WorkspaceV2ReviewCase {
            $context = $this->managementContext(
                $actor,
                $workspace
            );

            $actor = $context['actor'];
            $workspace = $context['workspace'];

            $current = $this->lockedCurrentRevision(
                $workspace
            );

            return $this->review->openReview(
                $workspace,
                'contribution',
                'partner_contributions_workflow',
                'contribution',
                'accepted_contribution_register',
                (int) $current->revision,
                $triggerType,
                $triggerContext,
                $actor
            );
        });
    }

    /**
     * Resolves a Chapter 2 review without creating amendment work.
     * Direct resolutions remain limited by the frozen review service.
     */
    public function resolveReview(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        string $resolution,
        ?string $resolutionNote = null,
    ): WorkspaceV2ReviewCase {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $case,
            $resolution,
            $resolutionNote,
        ): WorkspaceV2ReviewCase {
            $context = $this->managementContext(
                $actor,
                $workspace
            );

            $actor = $context['actor'];
            $workspace = $context['workspace'];

            $lockedCase = $this->lockedContributionReviewCase(
                $workspace,
                $case
            );

            return $this->review->resolveReview(
                $workspace,
                $lockedCase,
                $resolution,
                $resolutionNote,
                $actor
            );
        });
    }

    /**
     * Resolves an open review as amendment_required and creates one new
     * Chapter 2 working draft from the authoritative current register.
     *
     * Previously accepted rows retain their delivery facts but are returned
     * to delivered with no active acceptance approvals. The former approvals
     * remain explicit history and the immutable prior canonical revision is
     * left untouched.
     */
    public function requestAmendmentAndCreateDraft(
        User $actor,
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case,
        ?string $reason = null,
        array $amendmentContext = [],
    ): array {
        return DB::transaction(function () use (
            $actor,
            $workspace,
            $case,
            $reason,
            $amendmentContext,
        ): array {
            $context = $this->managementContext(
                $actor,
                $workspace
            );

            $actor = $context['actor'];
            $workspace = $context['workspace'];

            $lockedCase = $this->lockedContributionReviewCase(
                $workspace,
                $case
            );

            $current = $this->lockedCurrentRevision(
                $workspace
            );

            if ((int) $lockedCase->target_canonical_revision_id
                    !== (int) $current->id
                || (int) $lockedCase->target_revision
                    !== (int) $current->revision) {
                $this->invalid(
                    'review_case',
                    'The review no longer targets the current approved contribution register.'
                );
            }

            $sourceArtifact =
                $this->lockedPublishedSourceArtifact(
                    $workspace,
                    $current
                );

            $payload = $this->prepareAmendmentPayload(
                $actor,
                $current,
                $sourceArtifact
            );

            $handoff = $this->review->requestAmendment(
                $workspace,
                $lockedCase,
                $reason,
                $amendmentContext,
                $actor
            );

            $draft = $this->work
                ->createAmendmentWorkingDraft(
                    $workspace,
                    $lockedCase,
                    'contribution',
                    'partner_contributions_workflow',
                    $payload,
                    [],
                    $actor
                );

            return [
                'review_handoff' => $handoff,
                'work_artifact' => $draft,
            ];
        });
    }

    private function lockedCurrentRevision(
        PartnershipWorkspace $workspace
    ): WorkspaceV2CanonicalRevision {
        $currents = WorkspaceV2CanonicalRevision::query()
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

        if ($currents->count() !== 1) {
            $this->invalid(
                'canonical_revision',
                'Exactly one current approved contribution register is required.'
            );
        }

        return $currents->first();
    }

    private function lockedContributionReviewCase(
        PartnershipWorkspace $workspace,
        WorkspaceV2ReviewCase $case
    ): WorkspaceV2ReviewCase {
        $locked = WorkspaceV2ReviewCase::query()
            ->whereKey($case->getKey())
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            $this->invalid(
                'review_case',
                'Contribution review case does not exist in this workspace.'
            );
        }

        if ($locked->target_domain_key !== 'contribution'
            || $locked->target_component_key
                !== 'accepted_contribution_register') {
            $this->invalid(
                'review_case',
                'Review case does not target the Chapter 2 accepted contribution register.'
            );
        }

        return $locked;
    }

    private function lockedPublishedSourceArtifact(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalRevision $revision
    ): WorkspaceV2CanonicalWorkArtifact {
        $artifacts = WorkspaceV2CanonicalWorkArtifact::query()
            ->where('workspace_id', $workspace->id)
            ->where(
                'published_canonical_revision_id',
                $revision->id
            )
            ->whereNotNull('consumed_at')
            ->lockForUpdate()
            ->get();

        if ($artifacts->count() !== 1) {
            $this->invalid(
                'source_revision',
                'Current contribution register must have exactly one consumed Chapter 2 publication artifact.'
            );
        }

        $artifact = $artifacts->first();

        if ($artifact->target_domain_key !== 'contribution'
            || $artifact->target_component_key
                !== 'accepted_contribution_register'
            || $artifact->producer_domain_key
                !== 'contribution'
            || $artifact->producer_component_key
                !== 'partner_contributions_workflow') {
            $this->invalid(
                'source_revision',
                'Current contribution register publication provenance is invalid.'
            );
        }

        $payload = $revision->payload;

        if (! is_array($payload)
            || $artifact->payload !== $payload) {
            $this->invalid(
                'source_revision',
                'Canonical contribution register does not match its consumed publication artifact.'
            );
        }

        $assessment = $this->readiness
            ->assessPublication(
                (int) $workspace->id,
                (int) $artifact->id,
                $payload
            );

        if (! $assessment['passed']) {
            $this->invalid(
                'source_revision',
                'Current canonical contribution register failed Chapter 2 source consistency checks.'
            );
        }

        return $artifact;
    }

    private function prepareAmendmentPayload(
        User $actor,
        WorkspaceV2CanonicalRevision $revision,
        WorkspaceV2CanonicalWorkArtifact $sourceArtifact
    ): array {
        $payload = $revision->payload;

        if (! is_array($payload)
            || ($payload['schema_version'] ?? null)
                !== 'contribution-register-v1'
            || ! is_array($payload['items'] ?? null)
            || ! array_is_list($payload['items'])
            || ! is_array($payload['event_history'] ?? null)
            || ! array_is_list($payload['event_history'])) {
            $this->invalid(
                'source_revision',
                'Canonical contribution payload is not amendment-safe.'
            );
        }

        $occurredAt =
            now()->utc()->format('Y-m-d\TH:i:s\Z');

        foreach ($payload['items'] as $index => $item) {
            if (! is_array($item)) {
                $this->invalid(
                    'source_revision',
                    'Canonical contribution item is invalid.'
                );
            }

            if (($item['status'] ?? null) !== 'accepted') {
                continue;
            }

            $history = $item['event_history'] ?? null;
            $approvals = $item['approval_records'] ?? null;

            if (! is_array($history)
                || ! array_is_list($history)
                || ! is_array($approvals)
                || ! array_is_list($approvals)) {
                $this->invalid(
                    'source_revision',
                    'Accepted canonical contribution lacks valid approval history.'
                );
            }

            $history[] = [
                'action' =>
                    'amendment_reopened_for_acceptance',
                'actor_user_id' => (int) $actor->id,
                'occurred_at' => $occurredAt,
                'source_canonical_revision_id' =>
                    (int) $revision->id,
                'source_revision' =>
                    (int) $revision->revision,
                'source_work_artifact_id' =>
                    (int) $sourceArtifact->id,
                'previous_status' => 'accepted',
                'previous_approval_records' =>
                    $approvals,
            ];

            $item['event_history'] = $history;
            $item['approval_records'] = [];
            $item['status'] = 'delivered';

            $payload['items'][$index] = $item;
        }

        $payload['edit_version'] = 1;

        $payload['event_history'][] = [
            'action' => 'amendment_draft_created',
            'actor_user_id' => (int) $actor->id,
            'occurred_at' => $occurredAt,
            'source_canonical_revision_id' =>
                (int) $revision->id,
            'source_revision' =>
                (int) $revision->revision,
            'source_work_artifact_id' =>
                (int) $sourceArtifact->id,
        ];

        return $payload;
    }

    private function itemIndex(array $items, string $itemId): int
    {
        $index = null;
        foreach ($items as $position => $item) {
            if (is_array($item) && ($item['id'] ?? null) === $itemId) {
                if ($index !== null) {
                    $this->invalid('item_id', 'Duplicate stored contribution identity.');
                }
                $index = $position;
            }
        }
        if ($index === null) {
            $this->invalid('item_id', 'Contribution item does not exist in this draft.');
        }

        return $index;
    }

    private function managementContext(User $actor, PartnershipWorkspace $workspace): array
    {
        $this->access->managementContext($actor, $workspace);
        $locked = PartnershipWorkspace::query()->whereKey($workspace->getKey())
            ->lockForUpdate()->firstOrFail();

        return $this->access->managementContext($actor, $locked);
    }

    private function lockedDraft(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalWorkArtifact $artifact,
        int $expectedVersion,
    ): WorkspaceV2CanonicalWorkArtifact {
        $locked = WorkspaceV2CanonicalWorkArtifact::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($artifact->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->target_domain_key !== 'contribution'
            || $locked->target_component_key !== 'accepted_contribution_register'
            || $locked->producer_domain_key !== 'contribution'
            || $locked->producer_component_key !== 'partner_contributions_workflow') {
            $this->invalid('work_artifact', 'Not a Chapter 2 contribution draft.');
        }
        if ($locked->state !== 'working_draft' || $locked->isConsumed()) {
            $this->invalid('state', 'Only an unconsumed working draft may be edited.');
        }

        $payload = $locked->payload;
        if (! is_array($payload)
            || ($payload['schema_version'] ?? null) !== 'contribution-register-v1'
            || ! is_int($payload['edit_version'] ?? null)
            || $payload['edit_version'] < 1
            || $payload['edit_version'] >= PHP_INT_MAX) {
            $this->invalid('payload', 'Invalid contribution payload version.');
        }
        foreach (['setup', 'partners', 'items', 'event_history'] as $key) {
            if (! is_array($payload[$key] ?? null)
                || ($key !== 'setup' && ! array_is_list($payload[$key]))) {
                $this->invalid('payload', 'Invalid stored contribution structure.');
            }
        }
        if ($expectedVersion < 1 || $payload['edit_version'] !== $expectedVersion) {
            $this->invalid('edit_version', 'This draft has changed. Reload before saving.');
        }

        return $locked;
    }

    private function event(User $actor, string $action): array
    {
        return [
            'action' => $action,
            'actor_user_id' => (int) $actor->id,
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
