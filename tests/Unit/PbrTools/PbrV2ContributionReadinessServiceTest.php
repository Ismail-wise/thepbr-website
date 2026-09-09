<?php

use App\Services\PbrTools\PbrV2ContributionReadinessService;

function contributionSetupFixture(): array
{
    return [
        'valuation_date' => '2026-09-08',
        'currency_code' => 'THB',
        'contribution_period' => [
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ],
        'valuation_owner_reference' => 'Partner A',
        'approver_user_ids' => [1, 2],
    ];
}

test('complete contribution setup passes without claiming publication readiness', function () {
    $setup = contributionSetupFixture();
    $before = $setup;
    $result = (new PbrV2ContributionReadinessService())->assessSetup($setup);

    expect($result)->toBe([
        'scope' => 'setup',
        'passed' => true,
        'errors' => [],
    ])->and($setup)->toBe($before);
});

test('incomplete contribution setup reports all missing sections', function () {
    $result = (new PbrV2ContributionReadinessService())->assessSetup([]);

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'path'))->toBe([
            'valuation_date',
            'currency_code',
            'contribution_period',
            'valuation_owner_reference',
            'approver_user_ids',
        ]);
});

test('contribution setup rejects invalid fields with stable error codes', function (
    array $replacement,
    string $path,
    string $code
) {
    $result = (new PbrV2ContributionReadinessService())->assessSetup(
        array_replace(contributionSetupFixture(), $replacement)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code', 'path')[$path] ?? null)->toBe($code);
})->with([
    'impossible date' => [['valuation_date' => '2026-02-30'], 'valuation_date', 'invalid_date'],
    'non leap date' => [['valuation_date' => '2025-02-29'], 'valuation_date', 'invalid_date'],
    'trailing newline' => [['valuation_date' => "2026-09-08\n"], 'valuation_date', 'invalid_date'],
    'unsupported currency' => [['currency_code' => 'EUR'], 'currency_code', 'invalid_currency'],
    'blank owner' => [['valuation_owner_reference' => '   '], 'valuation_owner_reference', 'required'],
    'no approvers' => [['approver_user_ids' => []], 'approver_user_ids', 'invalid_approvers'],
    'duplicate approver' => [['approver_user_ids' => [1, 1]], 'approver_user_ids.1', 'duplicate_approver'],
    'string ID' => [['approver_user_ids' => ['1']], 'approver_user_ids.0', 'invalid_user_id'],
    'zero ID' => [['approver_user_ids' => [0]], 'approver_user_ids.0', 'invalid_user_id'],
    'boolean ID' => [['approver_user_ids' => [true]], 'approver_user_ids.0', 'invalid_user_id'],
    'associative IDs' => [['approver_user_ids' => ['owner' => 1]], 'approver_user_ids', 'invalid_approvers'],
    'missing period end' => [[
        'contribution_period' => ['start_date' => '2026-09-01'],
    ], 'contribution_period.end_date', 'invalid_date'],
    'reversed period' => [[
        'contribution_period' => ['start_date' => '2026-10-01', 'end_date' => '2026-09-01'],
    ], 'contribution_period.end_date', 'reversed_period'],
]);

test('contribution setup permits a single day and valid leap date', function () {
    $setup = contributionSetupFixture();
    $setup['valuation_date'] = '2024-02-29';
    $setup['contribution_period'] = [
        'start_date' => '2024-02-29',
        'end_date' => '2024-02-29',
    ];

    expect((new PbrV2ContributionReadinessService())->assessSetup($setup)['passed'])->toBeTrue();
});

function contributionReviewFixture(string $type): array
{
    $base = [
        'id' => 'item-1',
        'partner_key' => 'partner-1',
        'description' => 'Partner contribution',
        'owner_reference' => 'Partner A',
        'proposed_value' => '100.00',
        'valuation_method' => 'Documented market comparison',
        'evidence_references' => ['evidence-001'],
        'conditions' => '',
        'contribution_type' => $type,
    ];

    return array_merge($base, match ($type) {
        'cash' => [
            'amount_committed' => '100.00',
            'amount_received' => '0.00',
            'payment_date' => null,
        ],
        'time_skill' => [
            'role_work' => 'Technical work',
            'hours_per_month' => '10',
            'fair_hourly_rate' => '10',
            'number_of_months' => '1',
            'cash_compensation_received' => '0',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'performance_condition' => 'Deliver agreed work',
            'vesting_rule' => 'Recognize delivered work monthly',
        ],
        'property_asset' => [
            'asset_description' => 'Equipment use',
            'asset_owner_reference' => 'Partner A',
            'ownership_transferred' => false,
            'usage_period' => 'September 2026',
            'market_value' => '1000',
            'fair_rental_use_value' => '100',
        ],
        'ip_intangible' => [
            'subtype' => 'software_system',
            'legal_beneficial_owner_reference' => 'Partner A',
            'contribution_form' => 'Non-exclusive licence',
            'period' => 'September 2026',
        ],
    });
}

test('all four contribution types can become review ready without acceptance', function (string $type) {
    $item = contributionReviewFixture($type);
    $before = $item;
    $result = (new PbrV2ContributionReadinessService())->assessItemForReview($item);

    expect($result['scope'])->toBe('item_review')
        ->and($result['passed'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and(array_key_exists('accepted_value', $result))->toBeFalse()
        ->and($item)->toBe($before);
})->with(['cash', 'time_skill', 'property_asset', 'ip_intangible']);

test('review readiness blocks incomplete contribution evidence and valuation', function (
    string $type,
    array $changes,
    string $errorPath
) {
    $result = (new PbrV2ContributionReadinessService())->assessItemForReview(
        array_replace(contributionReviewFixture($type), $changes)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'path'))->toContain($errorPath);
})->with([
    'missing evidence' => ['cash', ['evidence_references' => []], 'evidence_references'],
    'blank reference' => ['cash', ['evidence_references' => ['']], 'evidence_references.0'],
    'no valuation basis' => ['cash', ['valuation_method' => ''], 'valuation_method'],
    'float value' => ['cash', ['proposed_value' => 100.0], 'proposed_value'],
    'received without date' => ['cash', ['amount_received' => '1'], 'payment_date'],
    'invalid optional date' => ['cash', ['payment_date' => 'invalid'], 'payment_date'],
    'missing committed' => ['cash', ['amount_committed' => null], 'amount_committed'],
    'negative received' => ['cash', ['amount_received' => '-1'], 'amount_received'],
    'work overpaid' => ['time_skill', ['cash_compensation_received' => '101'], 'cash_compensation_received'],
    'work dates reversed' => ['time_skill', ['end_date' => '2026-08-31'], 'end_date'],
    'vesting omitted' => ['time_skill', ['vesting_rule' => ''], 'vesting_rule'],
    'transfer unspecified' => ['property_asset', ['ownership_transferred' => null], 'ownership_transferred'],
    'string transfer flag' => ['property_asset', ['ownership_transferred' => 'false'], 'ownership_transferred'],
    'use period absent' => ['property_asset', ['usage_period' => ''], 'usage_period'],
    'intangible owner absent' => ['ip_intangible', ['legal_beneficial_owner_reference' => ''], 'legal_beneficial_owner_reference'],
    'intangible subtype unknown' => ['ip_intangible', ['subtype' => 'shares'], 'subtype'],
    'unknown type' => ['cash', ['contribution_type' => 'equity'], 'contribution_type'],
]);

test('cash receipt is separate from commitment and does not imply acceptance', function () {
    $item = contributionReviewFixture('cash');
    $item['amount_received'] = '50.00';
    $item['payment_date'] = '2026-09-08';
    $result = (new PbrV2ContributionReadinessService())->assessItemForReview($item);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations'])->toBe([]);
});

test('transferred asset does not require a right to use period', function () {
    $item = contributionReviewFixture('property_asset');
    $item['ownership_transferred'] = true;
    unset($item['usage_period']);

    expect((new PbrV2ContributionReadinessService())->assessItemForReview($item)['passed'])
        ->toBeTrue();
});

function contributionDeliveryFixture(string $type = 'cash'): array
{
    return array_replace(contributionReviewFixture($type), [
        'reviewed_value' => '100.00',
        'agreed_value' => '100.00',
        'accepted_value' => '100.00',
        'committed_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'delivered_date' => '2026-09-30',
        'delivery_extent' => 'full',
    ], $type === 'cash' ? [
        'amount_received' => '100.00',
        'payment_date' => '2026-09-30',
    ] : []);
}

test('delivery readiness supports all contribution types without authorizing publication', function (string $type) {
    $item = contributionDeliveryFixture($type);
    $before = $item;
    $result = (new PbrV2ContributionReadinessService())->assessItemDelivery($item);

    expect($result['passed'])->toBeTrue()
        ->and($result['scope'])->toBe('item_delivery')
        ->and($item)->toBe($before)
        ->and(array_key_exists('publication_authorized', $result))->toBeFalse();
})->with(['cash', 'time_skill', 'property_asset', 'ip_intangible']);

test('delivery readiness rejects unsupported acceptance and incomplete delivery', function (
    array $changes,
    string $code
) {
    $result = (new PbrV2ContributionReadinessService())->assessItemDelivery(
        array_replace(contributionDeliveryFixture(), $changes)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain($code);
})->with([
    'above agreement' => [['accepted_value' => '100.01'], 'exceeds_agreed_value'],
    'above cash received' => [['amount_received' => '99.99'], 'exceeds_received_cash'],
    'negative accepted' => [['accepted_value' => '-1'], 'invalid_decimal'],
    'missing reviewed' => [['reviewed_value' => null], 'invalid_decimal'],
    'missing agreed' => [['agreed_value' => null], 'invalid_decimal'],
    'float accepted' => [['accepted_value' => 100.0], 'invalid_decimal'],
    'no delivered date' => [['delivered_date' => null], 'invalid_date'],
    'delivery before commitment' => [['delivered_date' => '2026-08-31'], 'precedes_commitment'],
    'due before commitment' => [['due_date' => '2026-08-31'], 'precedes_commitment'],
    'extent absent' => [['delivery_extent' => null], 'invalid_delivery_extent'],
    'partial without basis' => [['delivery_extent' => 'partial'], 'partial_delivery_basis_required'],
    'evidence absent' => [['evidence_references' => []], 'required_evidence'],
]);

test('partial delivery uses explicit accepted value instead of automatic proration', function () {
    $item = contributionDeliveryFixture();
    $item['amount_received'] = '60';
    $item['accepted_value'] = '50';
    $item['delivery_extent'] = 'partial';
    $item['accepted_scope'] = 'First documented delivery';
    $item['adjustment_basis'] = 'Accept 50 pending evidence for the remaining 10';
    $result = (new PbrV2ContributionReadinessService())->assessItemDelivery($item);

    expect($result['passed'])->toBeTrue()
        ->and($item['accepted_value'])->toBe('50');
});

test('late delivery remains assessable', function () {
    $item = contributionDeliveryFixture();
    $item['delivered_date'] = '2026-10-02';
    $item['payment_date'] = '2026-10-02';

    expect((new PbrV2ContributionReadinessService())->assessItemDelivery($item)['passed'])
        ->toBeTrue();
});

test('accepted cash comparison is exact at large decimal values', function () {
    $item = contributionDeliveryFixture();
    $item['agreed_value'] = '999999999999.99';
    $item['amount_received'] = '999999999999.98';
    $item['accepted_value'] = '999999999999.99';
    $result = (new PbrV2ContributionReadinessService())->assessItemDelivery($item);

    expect(array_column($result['errors'], 'code'))->toContain('exceeds_received_cash');
});

function contributionApprovalFixture(int $id = 1): array
{
    return [
        'user_id' => $id,
        'decision' => 'approved',
        'approved_at' => '2026-09-08T04:00:00Z',
        'evidence_reference' => 'review-evidence-001',
        'notes' => '',
        'fingerprint' => str_repeat('a', 64),
    ];
}

test('complete current approval set passes consistency checks', function () {
    $records = [contributionApprovalFixture(2), contributionApprovalFixture(1)];
    $before = $records;
    $result = (new PbrV2ContributionReadinessService())->assessApprovalRecords(
        $records, [1, 2], str_repeat('a', 64)
    );

    expect($result)->toBe(['scope' => 'approval_records', 'passed' => true, 'errors' => []])
        ->and($records)->toBe($before);
});

test('approval consistency rejects invalid or stale records', function (array $changes, string $code) {
    $record = array_replace(contributionApprovalFixture(), $changes);
    $result = (new PbrV2ContributionReadinessService())->assessApprovalRecords(
        [$record], [1], str_repeat('a', 64)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain($code);
})->with([
    'different subject' => [['fingerprint' => str_repeat('b', 64)], 'stale_approval'],
    'missing fingerprint' => [['fingerprint' => null], 'stale_approval'],
    'unassigned user' => [['user_id' => 2], 'unexpected_approver'],
    'string identity' => [['user_id' => '1'], 'invalid_user_id'],
    'rejected decision' => [['decision' => 'rejected'], 'not_approved'],
    'no timestamp' => [['approved_at' => null], 'invalid_timestamp'],
    'invalid calendar date' => [['approved_at' => '2026-02-30T04:00:00Z'], 'invalid_timestamp'],
    'invalid hour' => [['approved_at' => '2026-09-08T25:00:00Z'], 'invalid_timestamp'],
    'not UTC format' => [['approved_at' => '2026-09-08 04:00:00'], 'invalid_timestamp'],
    'blank evidence' => [['evidence_reference' => ' '], 'required_evidence'],
    'invalid notes' => [['notes' => []], 'invalid_notes'],
]);

test('missing approval cannot be replaced by a duplicate', function () {
    $result = (new PbrV2ContributionReadinessService())->assessApprovalRecords(
        [contributionApprovalFixture(), contributionApprovalFixture()],
        [1, 2],
        str_repeat('a', 64)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))
        ->toContain('duplicate_approval', 'missing_approval');
});

test('approval validator rejects malformed approval requirements', function (array $ids, string $hash) {
    $result = (new PbrV2ContributionReadinessService())->assessApprovalRecords([], $ids, $hash);
    expect($result['passed'])->toBeFalse();
})->with([
    'empty requirements' => [[], str_repeat('a', 64)],
    'duplicate requirements' => [[1, 1], str_repeat('a', 64)],
    'invalid identity' => [[0], str_repeat('a', 64)],
    'invalid fingerprint' => [[1], 'not-a-hash'],
]);

test('approval validator handles malformed record rows', function () {
    $result = (new PbrV2ContributionReadinessService())->assessApprovalRecords(
        [null], [1], str_repeat('a', 64)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('invalid_record', 'missing_approval');
});

test('acceptance fingerprint ignores map insertion order without mutating input', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $before = $item;
    $reordered = array_reverse($setup, true);
    $reordered['contribution_period'] = array_reverse($setup['contribution_period'], true);

    $hash = $service->acceptanceFingerprint(1, 10, $setup, $item);

    expect($hash)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($service->acceptanceFingerprint(1, 10, $reordered, array_reverse($item, true)))
        ->toBe($hash)
        ->and($item)->toBe($before);
});

test('acceptance fingerprint binds workspace and draft identity', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $hash = $service->acceptanceFingerprint(1, 10, $setup, $item);

    expect($service->acceptanceFingerprint(2, 10, $setup, $item))->not->toBe($hash)
        ->and($service->acceptanceFingerprint(1, 11, $setup, $item))->not->toBe($hash);
});

test('material contribution changes invalidate acceptance fingerprints', function (string $field, mixed $value) {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $hash = $service->acceptanceFingerprint(1, 10, $setup, $item);
    $item[$field] = $value;

    expect($service->acceptanceFingerprint(1, 10, $setup, $item))->not->toBe($hash);
})->with([
    'accepted value' => ['accepted_value', '99.00'],
    'partner identity' => ['partner_key', 'partner-2'],
    'evidence' => ['evidence_references', ['replacement-evidence']],
    'conditions' => ['conditions', 'Additional condition'],
    'delivery date' => ['delivered_date', '2026-10-01'],
    'delivery extent' => ['delivery_extent', 'partial'],
    'adjustment basis' => ['adjustment_basis', 'Revised acceptance basis'],
]);

test('setup and approver changes invalidate acceptance fingerprints', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $hash = $service->acceptanceFingerprint(1, 10, $setup, $item);

    $changed = $setup;
    $changed['approver_user_ids'] = [1, 3];
    expect($service->acceptanceFingerprint(1, 10, $changed, $item))->not->toBe($hash);

    $changed = $setup;
    $changed['currency_code'] = 'MMK';
    expect($service->acceptanceFingerprint(1, 10, $changed, $item))->not->toBe($hash);
});

test('recording approvals and status does not recursively alter the acceptance subject', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $hash = $service->acceptanceFingerprint(1, 10, $setup, $item);
    $item['status'] = 'accepted';
    $item['approval_records'] = [contributionApprovalFixture()];
    $item['event_history'] = [['event' => 'accepted']];

    expect($service->acceptanceFingerprint(1, 10, $setup, $item))->toBe($hash);
});

test('approval consistency rejects an approval after the bound value changes', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();
    $record = contributionApprovalFixture();
    $record['fingerprint'] = $service->acceptanceFingerprint(1, 10, $setup, $item);
    $item['accepted_value'] = '99.00';
    $result = $service->assessApprovalRecords(
        [$record], [1], $service->acceptanceFingerprint(1, 10, $setup, $item)
    );

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('stale_approval');
});

test('fingerprint rejects invalid identity', function () {
    expect(fn () => (new PbrV2ContributionReadinessService())->acceptanceFingerprint(
        0, 10, contributionSetupFixture(), contributionDeliveryFixture()
    ))->toThrow(InvalidArgumentException::class);
});

test('fingerprint rejects floating point subject values', function () {
    $item = contributionDeliveryFixture();
    $item['accepted_value'] = 100.0;

    expect(fn () => (new PbrV2ContributionReadinessService())->acceptanceFingerprint(
        1, 10, contributionSetupFixture(), $item
    ))->toThrow(InvalidArgumentException::class);
});

function contributionAcceptedFixture(string $type = 'cash'): array
{
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture($type);
    $item['status'] = 'accepted';
    $hash = (new PbrV2ContributionReadinessService())->acceptanceFingerprint(1, 10, $setup, $item);
    $item['approval_records'] = array_map(
        static fn (int $id): array => array_replace(contributionApprovalFixture($id), [
            'approved_at' => '2026-10-01T04:00:00Z',
            'fingerprint' => $hash,
        ]),
        $setup['approver_user_ids']
    );

    return [$setup, $item];
}

test('accepted item combines setup delivery and approval consistency', function (string $type) {
    [$setup, $item] = contributionAcceptedFixture($type);
    $before = $item;
    $result = (new PbrV2ContributionReadinessService())->assessAcceptedItem(1, 10, $setup, $item);

    expect($result)->toBe([
        'scope' => 'accepted_item',
        'passed' => true,
        'errors' => [],
        'accepted_value' => '100.00',
    ])->and($item)->toBe($before);
})->with(['cash', 'time_skill', 'property_asset', 'ip_intangible']);

test('non accepted statuses never expose an eligible value', function (string $status) {
    [$setup, $item] = contributionAcceptedFixture();
    $item['status'] = $status;
    $result = (new PbrV2ContributionReadinessService())->assessAcceptedItem(1, 10, $setup, $item);

    expect($result['passed'])->toBeFalse()
        ->and($result['accepted_value'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))->toContain('not_accepted');
})->with(['proposed', 'reviewed', 'approved', 'delivered', 'rejected', 'cancelled', 'defaulted']);

test('accepted item fails closed on incomplete or stale data', function (array $changes, string $code) {
    [$setup, $item] = contributionAcceptedFixture();
    $item = array_replace($item, $changes);
    $result = (new PbrV2ContributionReadinessService())->assessAcceptedItem(1, 10, $setup, $item);

    expect($result['passed'])->toBeFalse()
        ->and($result['accepted_value'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))->toContain($code);
})->with([
    'no approvals' => [['approval_records' => []], 'missing_approval'],
    'malformed approvals' => [['approval_records' => 'approved'], 'invalid_records'],
    'changed value' => [['accepted_value' => '99'], 'stale_approval'],
    'over received' => [['amount_received' => '50'], 'exceeds_received_cash'],
    'missing evidence' => [['evidence_references' => []], 'required_evidence'],
    'partial without basis' => [['delivery_extent' => 'partial'], 'partial_delivery_basis_required'],
]);

test('accepted item rejects copied approvals from another workspace or draft', function () {
    [$setup, $item] = contributionAcceptedFixture();
    $service = new PbrV2ContributionReadinessService();

    foreach ([[2, 10], [1, 11]] as [$workspace, $artifact]) {
        $result = $service->assessAcceptedItem($workspace, $artifact, $setup, $item);
        expect($result['passed'])->toBeFalse()
            ->and($result['accepted_value'])->toBeNull()
            ->and(array_column($result['errors'], 'code'))->toContain('stale_approval');
    }
});

test('accepted item rejects invalid setup before exposing a value', function () {
    [$setup, $item] = contributionAcceptedFixture();
    $setup['approver_user_ids'] = [];
    $result = (new PbrV2ContributionReadinessService())->assessAcceptedItem(1, 10, $setup, $item);

    expect($result['accepted_value'])->toBeNull()
        ->and($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'path'))->toContain('setup.approver_user_ids');
});

test('agreement and acceptance fingerprints are domain separated for the same subject', function () {
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();
    $item = contributionDeliveryFixture();

    $agreement = $service->agreementFingerprint(1, 10, $setup, $item);
    $acceptance = $service->acceptanceFingerprint(1, 10, $setup, $item);

    expect($agreement)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($acceptance)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($agreement)->not->toBe($acceptance);
});

function contributionPublicationPayloadFixture(): array
{
    $service = new PbrV2ContributionReadinessService();
    $setup = contributionSetupFixture();

    $accepted = contributionDeliveryFixture();
    $accepted['id'] = 'accepted-1';
    $accepted['partner_snapshot'] = [
        'profile_id' => 1,
        'partner_key' => 'partner-1',
        'user_id' => null,
        'display_name' => 'Partner One',
        'status' => 'planned',
    ];
    $accepted['status'] = 'accepted';
    $accepted['event_history'] = [];

    $fingerprint = $service->acceptanceFingerprint(
        1,
        10,
        $setup,
        $accepted
    );

    $accepted['approval_records'] = array_map(
        static fn (int $id): array => [
            'user_id' => $id,
            'decision' => 'approved',
            'approved_at' => '2026-10-01T04:00:00Z',
            'evidence_reference' => 'acceptance-'.$id,
            'notes' => '',
            'fingerprint' => $fingerprint,
        ],
        $setup['approver_user_ids']
    );

    $closed = $accepted;
    $closed['id'] = 'cancelled-1';
    $closed['status'] = 'cancelled';
    $closed['accepted_value'] = '999.00';
    $closed['approval_records'] = [];

    return [
        'schema_version' => 'contribution-register-v1',
        'edit_version' => 7,
        'setup' => $setup,
        'partners' => [[
            'profile_id' => 1,
            'partner_key' => 'partner-1',
            'user_id' => null,
            'display_name' => 'Partner One',
            'status' => 'planned',
        ]],
        'items' => [$accepted, $closed],
        'event_history' => [],
    ];
}

test('publication readiness calculates accepted total only and preserves resolved exclusions', function () {
    $payload = contributionPublicationPayloadFixture();
    $before = $payload;

    $result = (new PbrV2ContributionReadinessService())
        ->assessPublication(1, 10, $payload);

    expect($result)->toBe([
        'scope' => 'publication',
        'passed' => true,
        'errors' => [],
        'calculations' => [
            'accepted_item_count' => 1,
            'excluded_item_count' => 1,
            'total_accepted_contribution_value' => '100.00',
        ],
    ])->and($payload)->toBe($before);
});

test('publication readiness blocks unresolved contribution states', function () {
    $payload = contributionPublicationPayloadFixture();
    $payload['items'][1]['status'] = 'delivered';

    $result = (new PbrV2ContributionReadinessService())
        ->assessPublication(1, 10, $payload);

    expect($result['passed'])->toBeFalse()
        ->and($result['calculations'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))
        ->toContain('unresolved_status');
});

test('publication readiness rejects stale accepted proof and unknown partner references', function () {
    $payload = contributionPublicationPayloadFixture();
    $payload['items'][0]['accepted_value'] = '90.00';
    $payload['items'][1]['partner_key'] = 'missing-partner';

    $result = (new PbrV2ContributionReadinessService())
        ->assessPublication(1, 10, $payload);

    expect($result['passed'])->toBeFalse()
        ->and($result['calculations'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))
        ->toContain('stale_approval', 'unknown_partner');
});

test('publication readiness rejects empty malformed and injected register structures', function () {
    $payload = contributionPublicationPayloadFixture();
    $payload['items'] = [];
    $payload['unexpected'] = true;

    $result = (new PbrV2ContributionReadinessService())
        ->assessPublication(1, 10, $payload);

    expect($result['passed'])->toBeFalse()
        ->and($result['calculations'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))
        ->toContain('no_items', 'unknown_field');
});

