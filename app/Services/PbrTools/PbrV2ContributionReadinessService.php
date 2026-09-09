<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;

final class PbrV2ContributionReadinessService
{
    /**
     * Setup shape and completeness only.
     * Does not authorize actors or establish publication readiness.
     *
     * @return array{scope: string, passed: bool, errors: array}
     */
    public function assessSetup(array $setup): array
    {
        $errors = [];
        $add = static function (string $path, string $code, string $message) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };

        if (! $this->validDate($setup['valuation_date'] ?? null)) {
            $add('valuation_date', 'invalid_date', 'A valid YYYY-MM-DD valuation date is required.');
        }

        $currency = $setup['currency_code'] ?? null;
        if (! is_string($currency)
            || ! array_key_exists($currency, PartnershipWorkspace::CURRENCIES)) {
            $add('currency_code', 'invalid_currency', 'A supported currency code is required.');
        }

        $period = $setup['contribution_period'] ?? null;
        if (! is_array($period)) {
            $add('contribution_period', 'invalid_period', 'Contribution period must contain start_date and end_date.');
        } else {
            $start = $period['start_date'] ?? null;
            $end = $period['end_date'] ?? null;
            $startValid = $this->validDate($start);
            $endValid = $this->validDate($end);

            if (! $startValid) {
                $add('contribution_period.start_date', 'invalid_date', 'A valid period start date is required.');
            }
            if (! $endValid) {
                $add('contribution_period.end_date', 'invalid_date', 'A valid period end date is required.');
            }
            if ($startValid && $endValid && strcmp($start, $end) > 0) {
                $add('contribution_period.end_date', 'reversed_period', 'Period end date must not precede start date.');
            }
        }

        $owner = $setup['valuation_owner_reference'] ?? null;
        if (! is_string($owner) || trim($owner) === '') {
            $add('valuation_owner_reference', 'required', 'A valuation owner reference is required.');
        }

        $approvers = $setup['approver_user_ids'] ?? null;
        if (! is_array($approvers) || ! array_is_list($approvers) || $approvers === []) {
            $add('approver_user_ids', 'invalid_approvers', 'A non-empty list of approver user IDs is required.');
        } else {
            $seen = [];
            foreach ($approvers as $index => $id) {
                if (! is_int($id) || $id < 1) {
                    $add('approver_user_ids.'.$index, 'invalid_user_id', 'Approver user ID must be a positive integer.');
                    continue;
                }
                if (isset($seen[$id])) {
                    $add('approver_user_ids.'.$index, 'duplicate_approver', 'Approver user IDs must not repeat.');
                }
                $seen[$id] = true;
            }
        }

        return [
            'scope' => 'setup',
            'passed' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Review completeness only. Does not approve or accept a contribution.
     * Numeric inputs are decimal strings; partner membership is checked elsewhere.
     */
    public function assessItemForReview(array $item): array
    {
        $errors = [];
        $calculations = [];
        $add = static function (string $path, string $code, string $message) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };
        $text = static function (string $field) use ($item, $add): void {
            if (! is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                $add($field, 'required', 'A non-empty '.$field.' is required.');
            }
        };
        $money = static function (string $field) use ($item, $add): bool {
            $value = $item[$field] ?? null;
            $valid = is_string($value)
                && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $value) === 1;
            if (! $valid) {
                $add($field, 'invalid_decimal', 'A non-negative decimal string is required.');
            }
            return $valid;
        };

        foreach (['id', 'partner_key', 'description', 'owner_reference', 'valuation_method'] as $field) {
            $text($field);
        }
        $money('proposed_value');

        if (! is_string($item['conditions'] ?? null)) {
            $add('conditions', 'invalid_conditions', 'Conditions must be recorded as text; an empty string means none.');
        }

        $evidence = $item['evidence_references'] ?? null;
        if (! is_array($evidence) || ! array_is_list($evidence) || $evidence === []) {
            $add('evidence_references', 'required_evidence', 'At least one evidence reference is required.');
        } else {
            foreach ($evidence as $index => $reference) {
                if (! is_string($reference) || trim($reference) === '') {
                    $add('evidence_references.'.$index, 'invalid_evidence', 'Evidence reference must be non-empty text.');
                }
            }
        }

        $type = $item['contribution_type'] ?? null;
        if (! in_array($type, ['cash', 'time_skill', 'property_asset', 'ip_intangible'], true)) {
            $add('contribution_type', 'invalid_type', 'A supported contribution type is required.');
        } elseif ($type === 'cash') {
            $money('amount_committed');
            $receivedValid = $money('amount_received');
            if ($receivedValid && preg_match('/[1-9]/', $item['amount_received']) === 1) {
                if (! $this->validDate($item['payment_date'] ?? null)) {
                    $add('payment_date', 'invalid_date', 'Received cash requires a valid payment date.');
                }
            } elseif (($item['payment_date'] ?? null) !== null
                && ($item['payment_date'] ?? '') !== ''
                && ! $this->validDate($item['payment_date'])) {
                $add('payment_date', 'invalid_date', 'Payment date must be valid when supplied.');
            }
        } elseif ($type === 'time_skill') {
            foreach (['role_work', 'performance_condition', 'vesting_rule'] as $field) {
                $text($field);
            }
            foreach (['start_date', 'end_date'] as $field) {
                if (! $this->validDate($item[$field] ?? null)) {
                    $add($field, 'invalid_date', 'A valid work date is required.');
                }
            }
            if ($this->validDate($item['start_date'] ?? null)
                && $this->validDate($item['end_date'] ?? null)
                && strcmp($item['start_date'], $item['end_date']) > 0) {
                $add('end_date', 'reversed_period', 'Work end date must not precede start date.');
            }
            try {
                $calculations = (new PbrV2ContributionValuationService())->assessTimeSkill($item);
                foreach ($calculations['discrepancies'] as $code) {
                    $add('cash_compensation_received', $code, 'Cash compensation exceeds calculated fair work value.');
                }
            } catch (\InvalidArgumentException $exception) {
                $add('time_skill', 'invalid_calculation', $exception->getMessage());
            }
        } elseif ($type === 'property_asset') {
            $text('asset_description');
            $text('asset_owner_reference');
            if (! is_bool($item['ownership_transferred'] ?? null)) {
                $add('ownership_transferred', 'required_boolean', 'Explicit ownership transfer choice is required.');
            } elseif ($item['ownership_transferred'] === false) {
                $text('usage_period');
            }
            $money('market_value');
            $money('fair_rental_use_value');
        } elseif ($type === 'ip_intangible') {
            if (! in_array($item['subtype'] ?? null, [
                'ip', 'brand', 'software_system', 'customer_database',
                'network_introductions', 'customer_access', 'know_how', 'business_process',
            ], true)) {
                $add('subtype', 'invalid_subtype', 'A supported intangible subtype is required.');
            }
            foreach (['legal_beneficial_owner_reference', 'contribution_form', 'period'] as $field) {
                $text($field);
            }
        }

        return [
            'scope' => 'item_review',
            'passed' => $errors === [],
            'errors' => $errors,
            'calculations' => $calculations,
        ];
    }

    /**
     * Delivery/value completeness only; not approval or publication authority.
     * Reuses review checks without mutating item state or accepted value.
     */
    public function assessItemDelivery(array $item): array
    {
        $review = $this->assessItemForReview($item);
        $errors = $review['errors'];
        $add = static function (string $path, string $code, string $message) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };

        $values = [];
        foreach (['reviewed_value', 'agreed_value', 'accepted_value'] as $field) {
            $value = $item[$field] ?? null;
            if (! is_string($value)
                || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $value) !== 1) {
                $add($field, 'invalid_decimal', 'A non-negative decimal string is required.');
                continue;
            }
            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            $values[$field] = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        }

        if (isset($values['accepted_value'], $values['agreed_value'])
            && $values['accepted_value'] > $values['agreed_value']) {
            $add('accepted_value', 'exceeds_agreed_value', 'Accepted value must not exceed the agreed value.');
        }

        if (($item['contribution_type'] ?? null) === 'cash'
            && isset($values['accepted_value'])
            && is_string($item['amount_received'] ?? null)
            && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $item['amount_received']) === 1) {
            [$whole, $fraction] = array_pad(explode('.', $item['amount_received'], 2), 2, '');
            $received = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
            if ($values['accepted_value'] > $received) {
                $add('accepted_value', 'exceeds_received_cash', 'Accepted cash must not exceed received cash.');
            }
        }

        foreach (['committed_date', 'due_date', 'delivered_date'] as $field) {
            if (! $this->validDate($item[$field] ?? null)) {
                $add($field, 'invalid_date', 'A valid '.$field.' is required.');
            }
        }
        foreach (['due_date', 'delivered_date'] as $field) {
            if ($this->validDate($item['committed_date'] ?? null)
                && $this->validDate($item[$field] ?? null)
                && strcmp($item[$field], $item['committed_date']) < 0) {
                $add($field, 'precedes_commitment', 'Date must not precede the recorded commitment.');
            }
        }

        $extent = $item['delivery_extent'] ?? null;
        if (! in_array($extent, ['full', 'partial'], true)) {
            $add('delivery_extent', 'invalid_delivery_extent', 'Delivery extent must be full or partial.');
        }
        if ($extent === 'partial') {
            foreach (['accepted_scope', 'adjustment_basis'] as $field) {
                if (! is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                    $add($field, 'partial_delivery_basis_required', 'Partial delivery requires '.$field.'.');
                }
            }
        }

        return [
            'scope' => 'item_delivery',
            'passed' => $errors === [],
            'errors' => $errors,
            'calculations' => $review['calculations'],
        ];
    }

    /**
     * Validate the current approval set, not historical events.
     * This checks consistency only; callers must establish trusted provenance,
     * current workspace authorization and the expected fingerprint themselves.
     */
    public function assessApprovalRecords(
        array $records,
        array $requiredApproverIds,
        string $expectedFingerprint
    ): array {
        $errors = [];
        $add = static function (string $path, string $code, string $message) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };

        $required = [];
        if (! array_is_list($requiredApproverIds) || $requiredApproverIds === []) {
            $add('required_approver_ids', 'invalid_approvers', 'A non-empty approver list is required.');
        }
        foreach ($requiredApproverIds as $id) {
            if (! is_int($id) || $id < 1) {
                $add('required_approver_ids', 'invalid_user_id', 'Approver IDs must be positive integers.');
                continue;
            }
            if (isset($required[$id])) {
                $add('required_approver_ids', 'duplicate_approver', 'Required approvers must not repeat.');
            }
            $required[$id] = true;
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedFingerprint) !== 1) {
            $add('expected_fingerprint', 'invalid_fingerprint', 'A lowercase SHA-256 fingerprint is required.');
        }
        if (! array_is_list($records)) {
            $add('approval_records', 'invalid_records', 'Approval records must be a list.');
        }

        $seen = [];
        foreach ($records as $index => $record) {
            $path = 'approval_records.'.$index;
            if (! is_array($record)) {
                $add($path, 'invalid_record', 'Approval record must be an array.');
                continue;
            }
            $id = $record['user_id'] ?? null;
            if (! is_int($id) || $id < 1) {
                $add($path.'.user_id', 'invalid_user_id', 'A positive integer user ID is required.');
            } else {
                if (! isset($required[$id])) {
                    $add($path.'.user_id', 'unexpected_approver', 'User is not in the required approval set.');
                }
                if (isset($seen[$id])) {
                    $add($path.'.user_id', 'duplicate_approval', 'Only one current approval per user is allowed.');
                }
                $seen[$id] = true;
            }

            if (($record['decision'] ?? null) !== 'approved') {
                $add($path.'.decision', 'not_approved', 'An approved decision is required.');
            }
            $fingerprint = $record['fingerprint'] ?? null;
            if (! is_string($fingerprint) || ! hash_equals($expectedFingerprint, $fingerprint)) {
                $add($path.'.fingerprint', 'stale_approval', 'Approval does not match the current approval subject.');
            }

            $stamp = $record['approved_at'] ?? null;
            $validStamp = false;
            if (is_string($stamp)
                && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/', $stamp) === 1) {
                $date = \DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i:s\Z', $stamp, new \DateTimeZone('UTC')
                );
                $validStamp = $this->validDate(substr($stamp, 0, 10))
                    && $date !== false
                    && $date->format('Y-m-d\TH:i:s\Z') === $stamp;
            }
            if (! $validStamp) {
                $add($path.'.approved_at', 'invalid_timestamp', 'A valid UTC approval timestamp is required.');
            }
            if (! is_string($record['evidence_reference'] ?? null)
                || trim($record['evidence_reference']) === '') {
                $add($path.'.evidence_reference', 'required_evidence', 'Approval evidence reference is required.');
            }
            if (! is_string($record['notes'] ?? null)) {
                $add($path.'.notes', 'invalid_notes', 'Notes must be text; empty text is allowed.');
            }
        }

        foreach ($required as $id => $_) {
            if (! isset($seen[$id])) {
                $add('approval_records', 'missing_approval', 'Missing approval for user '.$id.'.');
            }
        }

        return [
            'scope' => 'approval_records',
            'passed' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Agreement subject consistency hash, not proof of authorization.
     * Agreement and acceptance use separate hash domains intentionally.
     */
    public function agreementFingerprint(
        int $workspaceId,
        int $artifactId,
        array $setup,
        array $item
    ): string {
        return $this->subjectFingerprint(
            'contribution-agreement-fingerprint-v1',
            $workspaceId,
            $artifactId,
            $setup,
            $item,
        );
    }

    /**
     * Acceptance subject consistency hash, not proof of authorization.
     * Call with trusted persisted identity and normalized stored data.
     */
    public function acceptanceFingerprint(
        int $workspaceId,
        int $artifactId,
        array $setup,
        array $item
    ): string {
        return $this->subjectFingerprint(
            'contribution-acceptance-fingerprint-v1',
            $workspaceId,
            $artifactId,
            $setup,
            $item,
        );
    }

    private function subjectFingerprint(
        string $contract,
        int $workspaceId,
        int $artifactId,
        array $setup,
        array $item
    ): string {
        if ($workspaceId < 1 || $artifactId < 1) {
            throw new \InvalidArgumentException('Positive workspace and artifact IDs are required.');
        }

        unset($item['status'], $item['approval_records'], $item['event_history']);

        $subject = [
            'contract' => $contract,
            'workspace_id' => $workspaceId,
            'artifact_id' => $artifactId,
            'setup' => $setup,
            'item' => $item,
        ];

        return hash('sha256', json_encode(
            $this->fingerprintValue($subject),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function fingerprintValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            throw new \InvalidArgumentException('Fingerprint subject nesting exceeds the supported limit.');
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $child) {
                $value[$key] = $this->fingerprintValue($child, $depth + 1);
            }
            return $value;
        }

        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(
            'Fingerprint subjects support arrays, strings, integers, booleans and null only.'
        );
    }

    /**
     * Data consistency only. Caller must verify authorization and provenance.
     * Never returns an eligible accepted value when any check fails.
     */
    public function assessAcceptedItem(
        int $workspaceId,
        int $artifactId,
        array $setup,
        array $item
    ): array {
        $errors = [];
        $merge = static function (array $assessment, string $prefix) use (&$errors): void {
            foreach ($assessment['errors'] as $error) {
                $error['path'] = $prefix.$error['path'];
                $errors[] = $error;
            }
        };
        $add = static function (string $path, string $code, string $message) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };

        $setupAssessment = $this->assessSetup($setup);
        $merge($setupAssessment, 'setup.');
        $merge($this->assessItemDelivery($item), 'item.');

        if (($item['status'] ?? null) !== 'accepted') {
            $add('item.status', 'not_accepted', 'Only accepted items qualify for an accepted value.');
        }

        $fingerprint = null;
        try {
            $fingerprint = $this->acceptanceFingerprint($workspaceId, $artifactId, $setup, $item);
        } catch (\InvalidArgumentException | \JsonException $exception) {
            $add('item.fingerprint', 'invalid_subject', $exception->getMessage());
        }

        $records = $item['approval_records'] ?? null;
        if (! is_array($records)) {
            $add('item.approval_records', 'invalid_records', 'Current approval records must be an array.');
        } elseif ($setupAssessment['passed'] && $fingerprint !== null) {
            $merge($this->assessApprovalRecords(
                $records,
                $setup['approver_user_ids'],
                $fingerprint
            ), 'item.');
        }

        $acceptedValue = null;
        if ($errors === []) {
            [$whole, $fraction] = array_pad(explode('.', $item['accepted_value'], 2), 2, '');
            $acceptedValue = $whole.'.'.str_pad($fraction, 2, '0');
        }

        return [
            'scope' => 'accepted_item',
            'passed' => $errors === [],
            'errors' => $errors,
            'accepted_value' => $acceptedValue,
        ];
    }

    /**
     * Final Chapter 2 publication readiness.
     *
     * The canonical register preserves resolved contribution rows, including
     * rejected/cancelled/defaulted audit outcomes. Only status=accepted rows
     * contribute to Total Accepted Contribution Value.
     */
    public function assessPublication(
        int $workspaceId,
        int $artifactId,
        array $payload
    ): array {
        $errors = [];

        $add = static function (
            string $path,
            string $code,
            string $message
        ) use (&$errors): void {
            $errors[] = compact('path', 'code', 'message');
        };

        if ($workspaceId < 1 || $artifactId < 1) {
            $add(
                'identity',
                'invalid_identity',
                'Positive workspace and artifact IDs are required.'
            );
        }

        $requiredTop = [
            'schema_version',
            'edit_version',
            'setup',
            'partners',
            'items',
            'event_history',
        ];

        $missing = array_values(array_diff(
            $requiredTop,
            array_keys($payload)
        ));
        sort($missing, SORT_STRING);

        foreach ($missing as $key) {
            $add(
                (string) $key,
                'required_field',
                'Required publication field is missing.'
            );
        }

        $unknown = array_values(array_diff(
            array_keys($payload),
            $requiredTop
        ));
        sort($unknown, SORT_STRING);

        foreach ($unknown as $key) {
            $add(
                (string) $key,
                'unknown_field',
                'Unknown publication field.'
            );
        }

        if (($payload['schema_version'] ?? null)
            !== 'contribution-register-v1') {
            $add(
                'schema_version',
                'unsupported_schema',
                'Unsupported contribution register schema.'
            );
        }

        $editVersion = $payload['edit_version'] ?? null;
        if (! is_int($editVersion)
            || $editVersion < 1
            || $editVersion >= PHP_INT_MAX) {
            $add(
                'edit_version',
                'invalid_version',
                'A positive bounded integer edit version is required.'
            );
        }

        $setup = $payload['setup'] ?? null;
        $setupPassed = false;

        if (! is_array($setup)
            || ($setup !== [] && array_is_list($setup))) {
            $add(
                'setup',
                'invalid_setup',
                'Contribution setup must be an associative array.'
            );
        } else {
            $setupAssessment = $this->assessSetup($setup);
            $setupPassed = $setupAssessment['passed'];

            foreach ($setupAssessment['errors'] as $error) {
                $add(
                    'setup.'.$error['path'],
                    $error['code'],
                    $error['message']
                );
            }
        }

        $partners = $payload['partners'] ?? null;
        $partnerKeys = [];

        if (! is_array($partners) || ! array_is_list($partners)) {
            $add(
                'partners',
                'invalid_partners',
                'Partner snapshots must be a sequential list.'
            );
        } else {
            foreach ($partners as $index => $snapshot) {
                $path = 'partners.'.$index;

                if (! is_array($snapshot)
                    || ($snapshot !== [] && array_is_list($snapshot))) {
                    $add(
                        $path,
                        'invalid_partner_snapshot',
                        'Partner snapshot must be an associative array.'
                    );
                    continue;
                }

                $profileId = $snapshot['profile_id'] ?? null;
                $partnerKey = $snapshot['partner_key'] ?? null;
                $userId = $snapshot['user_id'] ?? null;
                $displayName = $snapshot['display_name'] ?? null;
                $status = $snapshot['status'] ?? null;

                if (! is_int($profileId) || $profileId < 1) {
                    $add(
                        $path.'.profile_id',
                        'invalid_profile_id',
                        'Partner snapshot requires a positive profile ID.'
                    );
                }

                if (! is_string($partnerKey)
                    || trim($partnerKey) === '') {
                    $add(
                        $path.'.partner_key',
                        'invalid_partner_key',
                        'Partner snapshot requires a non-blank partner key.'
                    );
                } elseif (isset($partnerKeys[$partnerKey])) {
                    $add(
                        $path.'.partner_key',
                        'duplicate_partner_key',
                        'Partner snapshot keys must be unique.'
                    );
                } else {
                    $partnerKeys[$partnerKey] = true;
                }

                if ($userId !== null
                    && (! is_int($userId) || $userId < 1)) {
                    $add(
                        $path.'.user_id',
                        'invalid_user_id',
                        'Partner snapshot user ID must be null or positive.'
                    );
                }

                if (! is_string($displayName)
                    || trim($displayName) === '') {
                    $add(
                        $path.'.display_name',
                        'invalid_display_name',
                        'Partner snapshot requires a display name.'
                    );
                }

                if (! in_array($status, ['active', 'planned'], true)) {
                    $add(
                        $path.'.status',
                        'invalid_partner_status',
                        'Partner snapshot status must be active or planned.'
                    );
                }
            }
        }

        $eventHistory = $payload['event_history'] ?? null;
        if (! is_array($eventHistory)
            || ! array_is_list($eventHistory)) {
            $add(
                'event_history',
                'invalid_event_history',
                'Contribution event history must be a sequential list.'
            );
        }

        $items = $payload['items'] ?? null;
        $acceptedUnits = 0;
        $acceptedCount = 0;
        $excludedCount = 0;
        $itemIds = [];

        if (! is_array($items) || ! array_is_list($items)) {
            $add(
                'items',
                'invalid_items',
                'Contribution items must be a sequential list.'
            );
        } elseif ($items === []) {
            $add(
                'items',
                'no_items',
                'At least one contribution outcome is required before publication.'
            );
        } else {
            foreach ($items as $index => $item) {
                $path = 'items.'.$index;

                if (! is_array($item)
                    || ($item !== [] && array_is_list($item))) {
                    $add(
                        $path,
                        'invalid_item',
                        'Contribution row must be an associative array.'
                    );
                    continue;
                }

                $id = $item['id'] ?? null;
                if (! is_string($id) || trim($id) === '') {
                    $add(
                        $path.'.id',
                        'invalid_item_id',
                        'Contribution row requires a non-blank item ID.'
                    );
                } elseif (isset($itemIds[$id])) {
                    $add(
                        $path.'.id',
                        'duplicate_item_id',
                        'Contribution item IDs must be unique.'
                    );
                } else {
                    $itemIds[$id] = true;
                }

                $partnerKey = $item['partner_key'] ?? null;
                if (! is_string($partnerKey)
                    || ! isset($partnerKeys[$partnerKey])) {
                    $add(
                        $path.'.partner_key',
                        'unknown_partner',
                        'Contribution row must reference a saved partner snapshot.'
                    );
                }

                $status = $item['status'] ?? null;

                if (in_array(
                    $status,
                    ['proposed', 'reviewed', 'approved', 'delivered'],
                    true
                )) {
                    $add(
                        $path.'.status',
                        'unresolved_status',
                        'All contribution rows must be resolved before publication.'
                    );
                    continue;
                }

                if (! in_array(
                    $status,
                    ['accepted', 'rejected', 'cancelled', 'defaulted'],
                    true
                )) {
                    $add(
                        $path.'.status',
                        'invalid_status',
                        'Contribution row has an unsupported publication status.'
                    );
                    continue;
                }

                if ($status !== 'accepted') {
                    $excludedCount++;
                    continue;
                }

                if (! $setupPassed
                    || $workspaceId < 1
                    || $artifactId < 1) {
                    continue;
                }

                $assessment = $this->assessAcceptedItem(
                    $workspaceId,
                    $artifactId,
                    $setup,
                    $item
                );

                if (! $assessment['passed']) {
                    foreach ($assessment['errors'] as $error) {
                        $relative = $error['path'];

                        if (str_starts_with($relative, 'item.')) {
                            $relative = substr($relative, 5);
                        } elseif (str_starts_with($relative, 'setup.')) {
                            $relative = 'acceptance_'.$relative;
                        }

                        $add(
                            $path.'.'.$relative,
                            $error['code'],
                            $error['message']
                        );
                    }

                    continue;
                }

                $value = $assessment['accepted_value'];
                $units = $this->publicationMoneyUnits($value);

                if ($units > PHP_INT_MAX - $acceptedUnits) {
                    $add(
                        'items',
                        'accepted_total_overflow',
                        'Total Accepted Contribution Value exceeds the supported integer range.'
                    );
                    continue;
                }

                $acceptedUnits += $units;
                $acceptedCount++;
            }
        }

        return [
            'scope' => 'publication',
            'passed' => $errors === [],
            'errors' => $errors,
            'calculations' => $errors === []
                ? [
                    'accepted_item_count' => $acceptedCount,
                    'excluded_item_count' => $excludedCount,
                    'total_accepted_contribution_value' =>
                        $this->publicationMoneyDecimal($acceptedUnits),
                ]
                : null,
        ];
    }

    private function publicationMoneyUnits(string $value): int
    {
        if (preg_match(
            '/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/D',
            $value
        ) !== 1) {
            throw new \InvalidArgumentException(
                'Publication money must use exactly two decimal places.'
            );
        }

        [$whole, $fraction] = explode('.', $value);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function publicationMoneyDecimal(int $units): string
    {
        return intdiv($units, 100)
            .'.'
            .str_pad(
                (string) ($units % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private function validDate(mixed $value): bool
    {
        if (! is_string($value)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
