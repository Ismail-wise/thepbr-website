<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2ContributionApprovalService;
use App\Services\PbrTools\PbrV2ContributionReadinessService;
use App\Services\PbrTools\PbrV2ContributionReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function c2ReadOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2ReadPartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function c2ReadWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Contribution Read Workspace',
        'business_name' =>
            'Contribution Read Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function c2ReadSnapshot(
    int $number
): array {
    return [
        'profile_id' => $number,
        'partner_key' => 'partner-'.$number,
        'user_id' => null,
        'display_name' =>
            'Partner '.$number,
        'status' => 'planned',
    ];
}

function c2ReadItem(
    int $number,
    array $snapshot,
    string $type,
    string $status = 'accepted',
    string $value = '100.00'
): array {
    $base = [
        'id' => 'read-item-'.$number,
        'partner_key' =>
            $snapshot['partner_key'],
        'partner_snapshot' => $snapshot,
        'description' =>
            'Published contribution '.$number,
        'owner_reference' =>
            $snapshot['display_name'],
        'proposed_value' => $value,
        'valuation_method' =>
            'Documented market comparison',
        'evidence_references' => [
            'read-evidence-'.$number,
        ],
        'conditions' => '',
        'contribution_type' => $type,
        'reviewed_value' => $value,
        'agreed_value' => $value,
        'accepted_value' => $value,
        'committed_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'delivered_date' => '2026-09-30',
        'delivery_extent' => 'full',
        'status' => $status,
        'approval_records' => [],
        'event_history' => [],
        'notes' => '',
    ];

    $specific = match ($type) {
        'cash' => [
            'amount_committed' => $value,
            'amount_received' => $value,
            'payment_date' => '2026-09-30',
        ],
        'time_skill' => [
            'role_work' => 'Technical work',
            'hours_per_month' => '10',
            'fair_hourly_rate' => '10',
            'number_of_months' => '1',
            'cash_compensation_received' => '0',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'performance_condition' =>
                'Deliver agreed work',
            'vesting_rule' =>
                'Recognize delivered work monthly',
        ],
        'property_asset' => [
            'asset_description' =>
                'Equipment use',
            'asset_owner_reference' =>
                $snapshot['display_name'],
            'ownership_transferred' => false,
            'usage_period' =>
                'September 2026',
            'market_value' => '1000',
            'fair_rental_use_value' => $value,
        ],
        'ip_intangible' => [
            'subtype' => 'software_system',
            'legal_beneficial_owner_reference' =>
                $snapshot['display_name'],
            'contribution_form' =>
                'Non-exclusive licence',
            'period' => 'September 2026',
        ],
        default => throw new RuntimeException(
            'Unsupported test contribution type.'
        ),
    };

    return array_merge(
        $base,
        $specific
    );
}

function c2ReadPublishedRegister(): array
{
    $owner = c2ReadOwner();
    $workspace = c2ReadWorkspace($owner);

    $partnerOne = c2ReadSnapshot(1);
    $partnerTwo = c2ReadSnapshot(2);

    $setup = [
        'valuation_date' => '2026-09-09',
        'currency_code' => 'THB',
        'contribution_period' => [
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ],
        'valuation_owner_reference' =>
            'Contribution Owner',
        'approver_user_ids' => [
            (int) $owner->id,
        ],
    ];

    $items = [
        c2ReadItem(
            1,
            $partnerOne,
            'cash'
        ),
        c2ReadItem(
            2,
            $partnerOne,
            'time_skill'
        ),
        c2ReadItem(
            3,
            $partnerTwo,
            'property_asset'
        ),
        c2ReadItem(
            4,
            $partnerTwo,
            'ip_intangible'
        ),
        c2ReadItem(
            5,
            $partnerTwo,
            'cash',
            'cancelled',
            '900.00'
        ),
    ];

    $payload = [
        'schema_version' =>
            'contribution-register-v1',
        'edit_version' => 1,
        'setup' => $setup,
        'partners' => [
            $partnerOne,
            $partnerTwo,
        ],
        'items' => $items,
        'event_history' => [],
    ];

    $work = app(
        PbrV2CanonicalWorkService::class
    );

    $draft = $work->createWorkingDraft(
        $workspace,
        'contribution',
        'accepted_contribution_register',
        'contribution',
        'partner_contributions_workflow',
        $payload,
        [],
        $owner
    );

    $readiness = app(
        PbrV2ContributionReadinessService::class
    );

    $payload = $draft->payload;

    foreach ($payload['items'] as $index => $item) {
        if (($item['status'] ?? null)
            !== 'accepted') {
            continue;
        }

        $fingerprint =
            $readiness->acceptanceFingerprint(
                (int) $workspace->id,
                (int) $draft->id,
                $setup,
                $item
            );

        $payload['items'][$index][
            'approval_records'
        ] = [[
            'user_id' => (int) $owner->id,
            'decision' => 'approved',
            'approved_at' =>
                '2026-10-01T04:00:00Z',
            'evidence_reference' =>
                'acceptance-proof-'.$index,
            'notes' => '',
            'fingerprint' => $fingerprint,
        ]];
    }

    $draft = $work->updateWorkingDraftPayload(
        $workspace,
        $draft,
        $payload
    );

    $approval = app(
        PbrV2ContributionApprovalService::class
    );

    $proposal = $approval
        ->submitForPublication(
            $owner,
            $workspace,
            $draft,
            1
        )['work_artifact'];

    $revision = $approval
        ->approveAndPublish(
            $owner,
            $workspace,
            $proposal
        )['workflow']['canonical_revision'];

    return [
        $owner,
        $workspace,
        $proposal,
        $revision,
    ];
}

function c2ReadDraft(
    User $owner,
    PartnershipWorkspace $workspace,
    array $payload = ['draft' => true]
): WorkspaceV2CanonicalWorkArtifact {
    return app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'contribution',
        'accepted_contribution_register',
        'contribution',
        'partner_contributions_workflow',
        $payload,
        [],
        $owner
    );
}

function c2ReadExpectValidation(
    callable $operation,
    string $key
): void {
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey($key);

        return;
    }

    throw new RuntimeException(
        'Expected validation failure for '.$key
    );
}

test('contribution read exposes the exact agreed public surface', function () {
    $class = new ReflectionClass(
        PbrV2ContributionReadService::class
    );

    expect($class->isFinal())->toBeTrue();

    $public = array_map(
        fn ($method) => $method->getName(),
        $class->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
    );

    sort($public);

    expect($public)->toBe([
        '__construct',
        'current',
        'history',
        'workArtifact',
        'workArtifacts',
    ])->and($class->getProperties())
        ->toHaveCount(3);

    $types = $class->getConstant('TYPES');

    expect($types)->toBe([
        'cash',
        'time_skill',
        'property_asset',
        'ip_intangible',
    ]);
});

test('contribution current projects the accepted matrix register total and partner equity handoff', function () {
    [
        $owner,
        $workspace,
        $proposal,
        $revision,
    ] = c2ReadPublishedRegister();

    $service = app(
        PbrV2ContributionReadService::class
    );

    $result = $service->current(
        $owner,
        $workspace
    );

    expect(array_keys($result))->toBe([
        'canonical_revision',
        'payload',
        'currency_code',
        'contribution_matrix',
        'contribution_register',
        'accepted_item_count',
        'excluded_item_count',
        'total_accepted_contribution_value',
        'equity_handoff',
    ])
        ->and($result['currency_code'])
        ->toBe('THB')
        ->and($result['accepted_item_count'])
        ->toBe(4)
        ->and($result['excluded_item_count'])
        ->toBe(1)
        ->and(
            $result[
                'total_accepted_contribution_value'
            ]
        )->toBe('400.00')
        ->and($result['payload'])
        ->toBe($revision->payload)
        ->and(
            $result['canonical_revision'][
                'source_work_artifact_id'
            ]
        )->toBe((int) $proposal->id)
        ->and(
            $result['canonical_revision']['state']
        )->toBe('approved_current');

    expect(
        $result['contribution_matrix']['partners']
    )->toBe([
        [
            'partner_key' => 'partner-1',
            'partner_snapshot' =>
                c2ReadSnapshot(1),
            'cash' => '100.00',
            'time_skill' => '100.00',
            'property_asset' => '0.00',
            'ip_intangible' => '0.00',
            'accepted_total' => '200.00',
        ],
        [
            'partner_key' => 'partner-2',
            'partner_snapshot' =>
                c2ReadSnapshot(2),
            'cash' => '0.00',
            'time_skill' => '0.00',
            'property_asset' => '100.00',
            'ip_intangible' => '100.00',
            'accepted_total' => '200.00',
        ],
    ])->and(
        $result['contribution_matrix']['totals']
    )->toBe([
        'cash' => '100.00',
        'time_skill' => '100.00',
        'property_asset' => '100.00',
        'ip_intangible' => '100.00',
        'accepted_total' => '400.00',
    ]);

    expect($result['contribution_register'])
        ->toHaveCount(5)
        ->and(array_column(
            $result['contribution_register'],
            'approval_status'
        ))
        ->toBe([
            'accepted',
            'accepted',
            'accepted',
            'accepted',
            'cancelled',
        ]);

    expect(array_keys(
        $result['contribution_register'][0]
    ))->toBe([
        'item_id',
        'partner_key',
        'partner_snapshot',
        'contribution_type',
        'description',
        'agreed_value',
        'accepted_value',
        'evidence_references',
        'delivery_date',
        'condition',
        'owner_reference',
        'approval_status',
    ]);

    expect($result['equity_handoff'])->toBe([
        [
            'workspace_id' =>
                (int) $workspace->id,
            'canonical_revision_id' =>
                (int) $revision->id,
            'revision' => 1,
            'currency_code' => 'THB',
            'partner_key' => 'partner-1',
            'partner_snapshot' =>
                c2ReadSnapshot(1),
            'accepted_contribution_value' =>
                '200.00',
            'contribution_status' =>
                'accepted',
        ],
        [
            'workspace_id' =>
                (int) $workspace->id,
            'canonical_revision_id' =>
                (int) $revision->id,
            'revision' => 1,
            'currency_code' => 'THB',
            'partner_key' => 'partner-2',
            'partner_snapshot' =>
                c2ReadSnapshot(2),
            'accepted_contribution_value' =>
                '200.00',
            'contribution_status' =>
                'accepted',
        ],
    ]);

    $result['payload']['setup'][
        'currency_code'
    ] = 'USD';

    expect(
        $revision->fresh()->payload['setup'][
            'currency_code'
        ]
    )->toBe('THB');
});

test('accepted contribution partner may read current but not drafts or canonical history', function () {
    [
        $owner,
        $workspace,
        $proposal,
        $revision,
    ] = c2ReadPublishedRegister();

    $partner = c2ReadPartner();

    WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);

    $draft = c2ReadDraft(
        $owner,
        $workspace,
        ['manager_only' => true]
    );

    $service = app(
        PbrV2ContributionReadService::class
    );

    expect(
        $service->current(
            $partner,
            $workspace
        )['total_accepted_contribution_value']
    )->toBe('400.00');

    expect(fn () =>
        $service->history(
            $partner,
            $workspace
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect(fn () =>
        $service->workArtifacts(
            $partner,
            $workspace
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect(fn () =>
        $service->workArtifact(
            $partner,
            $workspace,
            $draft->id
        )
    )->toThrow(
        AuthorizationException::class
    );
});

test('contribution manager reads active exact-target work artifacts and validates cursor', function () {
    $owner = c2ReadOwner();
    $workspace = c2ReadWorkspace($owner);

    $first = c2ReadDraft(
        $owner,
        $workspace,
        ['sequence' => 1]
    );

    $second = c2ReadDraft(
        $owner,
        $workspace,
        ['sequence' => 2]
    );

    $service = app(
        PbrV2ContributionReadService::class
    );

    $page = $service->workArtifacts(
        $owner,
        $workspace
    );

    expect(array_keys($page))->toBe([
        'items',
        'next_before_id',
    ])->and(array_column(
        $page['items'],
        'id'
    ))->toBe([
        (int) $second->id,
        (int) $first->id,
    ])->and($page['next_before_id'])
        ->toBeNull();

    $single = $service->workArtifact(
        $owner,
        $workspace,
        $first->id
    );

    expect(array_keys($single))->toBe([
        'id',
        'workspace_id',
        'target_domain_key',
        'target_component_key',
        'producer_domain_key',
        'producer_component_key',
        'state',
        'payload',
        'source_generation',
        'amendment_review_case_id',
        'amendment_target_canonical_revision_id',
        'submitted_at',
    ])
        ->and($single['payload'])
        ->toBe(['sequence' => 1])
        ->and($single['state'])
        ->toBe('working_draft')
        ->and($single['producer_component_key'])
        ->toBe(
            'partner_contributions_workflow'
        );

    c2ReadExpectValidation(
        fn () => $service->workArtifacts(
            $owner,
            $workspace,
            0
        ),
        'before_id'
    );

    c2ReadExpectValidation(
        fn () => $service->workArtifact(
            $owner,
            $workspace,
            0
        ),
        'work_artifact'
    );
});

test('contribution history returns canonical projections for managers', function () {
    [
        $owner,
        $workspace,
        $proposal,
        $revision,
    ] = c2ReadPublishedRegister();

    $history = app(
        PbrV2ContributionReadService::class
    )->history(
        $owner,
        $workspace
    );

    expect($history)->toHaveCount(1)
        ->and(
            $history[0]['canonical_revision'][
                'id'
            ]
        )->toBe((int) $revision->id)
        ->and(
            $history[0][
                'total_accepted_contribution_value'
            ]
        )->toBe('400.00')
        ->and(
            $history[0][
                'contribution_matrix'
            ]['totals']['accepted_total']
        )->toBe('400.00');
});

test('contribution current fails closed when approved current lacks Chapter 2 publication provenance', function () {
    [
        $owner,
        $workspace,
        $proposal,
        $first,
    ] = c2ReadPublishedRegister();

    $decision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        'contribution',
        'accepted_contribution_register',
        'proposed',
        true,
        true
    );

    $rogue = app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        'contribution',
        'accepted_contribution_register',
        $first->payload,
        $decision,
        $owner
    );

    expect($rogue->state)
        ->toBe('approved_current')
        ->and($first->fresh()->state)
        ->toBe('superseded');

    c2ReadExpectValidation(
        fn () => app(
            PbrV2ContributionReadService::class
        )->current(
            $owner,
            $workspace
        ),
        'canonical_revision'
    );
});

test('contribution read execution performs no database writes', function () {
    [
        $owner,
        $workspace,
        $proposal,
        $revision,
    ] = c2ReadPublishedRegister();

    $draft = c2ReadDraft(
        $owner,
        $workspace,
        ['read_only' => true]
    );

    $writes = [];

    DB::listen(
        function ($query) use (&$writes): void {
            if (preg_match(
                '/^\s*(insert|update|delete|replace|alter|create|drop)\b/i',
                $query->sql
            )) {
                $writes[] = $query->sql;
            }
        }
    );

    $service = app(
        PbrV2ContributionReadService::class
    );

    $service->current(
        $owner,
        $workspace
    );

    $service->history(
        $owner,
        $workspace
    );

    $service->workArtifacts(
        $owner,
        $workspace
    );

    $service->workArtifact(
        $owner,
        $workspace,
        $draft->id
    );

    expect($writes)->toBe([]);
});
