<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2ContributionActionPlanService;
use App\Services\PbrTools\PbrV2ContributionApprovalService;
use App\Services\PbrTools\PbrV2ContributionDecisionRecordService;
use App\Services\PbrTools\PbrV2ContributionReadService;
use App\Services\PbrTools\PbrV2ContributionReadinessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function c2F9Owner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2F9Partner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function c2F9Workspace(
    User $owner,
    string $name = 'Contribution Execution Workspace'
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function c2F9DecisionInput(): array
{
    return [
        'owner_reference' =>
            'Contribution Owner',
        'status' => 'approved',
        'effective_date' => '2026-09-09',
        'review_date' => '2027-03-09',
        'decision_summary' =>
            'Accepted contribution values confirmed for operating use.',
        'evidence_references' => [
            'contribution-approval-minutes-001',
            'accepted-register-review-001',
        ],
    ];
}

function c2F9PublishedRegister(): array
{
    $owner = c2F9Owner();
    $workspace = c2F9Workspace($owner);

    $snapshot = [
        'profile_id' => 1,
        'partner_key' => 'partner-1',
        'user_id' => null,
        'display_name' => 'Partner One',
        'status' => 'planned',
    ];

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

    $item = [
        'id' => 'c2-f9-item-1',
        'partner_key' => 'partner-1',
        'partner_snapshot' => $snapshot,
        'description' =>
            'Accepted cash contribution',
        'owner_reference' => 'Partner One',
        'proposed_value' => '500.00',
        'valuation_method' =>
            'Cash received evidence',
        'evidence_references' => [
            'bank-proof-c2-f9-001',
        ],
        'conditions' => '',
        'contribution_type' => 'cash',
        'reviewed_value' => '500.00',
        'agreed_value' => '500.00',
        'accepted_value' => '500.00',
        'committed_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'delivered_date' => '2026-09-09',
        'delivery_extent' => 'full',
        'status' => 'accepted',
        'approval_records' => [],
        'event_history' => [],
        'notes' => '',
        'amount_committed' => '500.00',
        'amount_received' => '500.00',
        'payment_date' => '2026-09-09',
    ];

    $payload = [
        'schema_version' =>
            'contribution-register-v1',
        'edit_version' => 1,
        'setup' => $setup,
        'partners' => [$snapshot],
        'items' => [$item],
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

    $fingerprint =
        $readiness->acceptanceFingerprint(
            (int) $workspace->id,
            (int) $draft->id,
            $setup,
            $payload['items'][0]
        );

    $payload['items'][0][
        'approval_records'
    ] = [[
        'user_id' => (int) $owner->id,
        'decision' => 'approved',
        'approved_at' =>
            '2026-09-09T07:00:00Z',
        'evidence_reference' =>
            'acceptance-proof-c2-f9-001',
        'notes' => '',
        'fingerprint' => $fingerprint,
    ]];

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
        $revision,
    ];
}

function c2F9ExpectValidation(
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

test('contribution execution exposes the exact chapter specific service surfaces', function () {
    $decision = new ReflectionClass(
        PbrV2ContributionDecisionRecordService::class
    );

    $action = new ReflectionClass(
        PbrV2ContributionActionPlanService::class
    );

    expect($decision->isFinal())
        ->toBeTrue()
        ->and($action->isFinal())
        ->toBeTrue();

    $decisionPublic = array_map(
        fn ($method) => $method->getName(),
        $decision->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
    );

    $actionPublic = array_map(
        fn ($method) => $method->getName(),
        $action->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
    );

    sort($decisionPublic);
    sort($actionPublic);

    expect($decisionPublic)->toBe([
        '__construct',
        'recordCurrent',
        'records',
    ])->and($actionPublic)->toBe([
        '__construct',
        'actionStates',
        'actions',
        'createFromDecisionRecord',
        'priorities',
        'transition',
    ])->and($decision->getProperties())
        ->toHaveCount(3)
        ->and($action->getProperties())
        ->toHaveCount(3);
});

test('contribution decision record pins exactly the approved current register and derives approval identity', function () {
    [
        $owner,
        $workspace,
        $revision,
    ] = c2F9PublishedRegister();

    $beforePayload = $revision->payload;

    $service = app(
        PbrV2ContributionDecisionRecordService::class
    );

    $record = $service->recordCurrent(
        $owner,
        $workspace,
        c2F9DecisionInput()
    );

    expect($record['state'])
        ->toBe('recorded')
        ->and($record['workspace_id'])
        ->toBe((int) $workspace->id)
        ->and($record['source'])
        ->toBe([
            'canonical_revision_id' =>
                (int) $revision->id,
            'domain_key' => 'contribution',
            'component_key' =>
                'accepted_contribution_register',
            'revision' => 1,
        ])
        ->and($record['payload'])
        ->toBe([
            'schema_version' =>
                'contribution-decision-record-v1',
            'owner_reference' =>
                'Contribution Owner',
            'status' => 'approved',
            'effective_date' =>
                '2026-09-09',
            'review_date' =>
                '2027-03-09',
            'decision_summary' =>
                'Accepted contribution values confirmed for operating use.',
            'evidence_references' => [
                'contribution-approval-minutes-001',
                'accepted-register-review-001',
            ],
            'approved_by' =>
                (int) $revision
                    ->published_by_user_id,
            'approved_at' =>
                $revision->published_at
                    ->utc()
                    ->toISOString(),
            'source_register_revision' => 1,
        ])
        ->and(
            $revision->fresh()->payload
        )->toBe($beforePayload)
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(1);

    c2F9ExpectValidation(
        fn () => $service->recordCurrent(
            $owner,
            $workspace,
            c2F9DecisionInput()
        ),
        'decision_record'
    );

    expect(
        WorkspaceV2OperatingRecord::query()
            ->where(
                'component_key',
                'contribution_decision_record'
            )
            ->count()
    )->toBe(1);
});

test('contribution decision record validates source fields and rejects caller approval injection', function () {
    [
        $owner,
        $workspace,
    ] = c2F9PublishedRegister();

    $service = app(
        PbrV2ContributionDecisionRecordService::class
    );

    $cases = [
        [
            [],
            'owner_reference',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                ['status' => ' ']
            ),
            'status',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                [
                    'effective_date' =>
                        '2026-02-30',
                ]
            ),
            'effective_date',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                [
                    'review_date' =>
                        '2026-09-08',
                ]
            ),
            'review_date',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                [
                    'evidence_references' =>
                        [],
                ]
            ),
            'evidence_references',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                [
                    'evidence_references' => [
                        'same-proof',
                        'same-proof',
                    ],
                ]
            ),
            'evidence_references',
        ],
        [
            array_replace(
                c2F9DecisionInput(),
                [
                    'approved_by' => 999999,
                ]
            ),
            'decision_record',
        ],
    ];

    foreach ($cases as [$input, $key]) {
        c2F9ExpectValidation(
            fn () => $service->recordCurrent(
                $owner,
                $workspace,
                $input
            ),
            $key
        );
    }

    expect(
        WorkspaceV2OperatingRecord::query()
            ->count()
    )->toBe(0);
});

test('contribution decision record requires an approved published contribution source', function () {
    $owner = c2F9Owner();
    $workspace = c2F9Workspace($owner);

    c2F9ExpectValidation(
        fn () => app(
            PbrV2ContributionDecisionRecordService::class
        )->recordCurrent(
            $owner,
            $workspace,
            c2F9DecisionInput()
        ),
        'accepted_contribution_register'
    );

    expect(
        WorkspaceV2OperatingRecord::query()
            ->count()
    )->toBe(0);
});

test('contribution execution remains manager only even for accepted read only partners', function () {
    [
        $owner,
        $workspace,
    ] = c2F9PublishedRegister();

    $partner = c2F9Partner();

    WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);

    $decision = app(
        PbrV2ContributionDecisionRecordService::class
    );

    $actions = app(
        PbrV2ContributionActionPlanService::class
    );

    expect(fn () =>
        $decision->recordCurrent(
            $partner,
            $workspace,
            c2F9DecisionInput()
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect(fn () =>
        $decision->records(
            $partner,
            $workspace
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect(fn () =>
        $actions->actions(
            $partner,
            $workspace
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect(
        WorkspaceV2OperatingRecord::query()
            ->count()
    )->toBe(0)
        ->and(
            WorkspaceV2Action::query()->count()
        )->toBe(0);
});

test('contribution action plan is created only from the recorded decision and cannot change accepted value', function () {
    [
        $owner,
        $workspace,
        $revision,
    ] = c2F9PublishedRegister();

    $read = app(
        PbrV2ContributionReadService::class
    );

    $decision = app(
        PbrV2ContributionDecisionRecordService::class
    )->recordCurrent(
        $owner,
        $workspace,
        c2F9DecisionInput()
    );

    $before = $read->current(
        $owner,
        $workspace
    );

    $beforePayload = $revision->payload;

    $service = app(
        PbrV2ContributionActionPlanService::class
    );

    $action = $service
        ->createFromDecisionRecord(
            $owner,
            $workspace,
            $decision['id'],
            [
                'title' =>
                    'Collect final contribution documentation',
                'description' =>
                    'Archive final delivery and acceptance evidence.',
                'owner_name' =>
                    'Contribution Owner',
                'priority' => 'normal',
                'due_date' =>
                    '2026-09-30',
            ]
        );

    expect($action['action_domain_key'])
        ->toBe('contribution')
        ->and($action['action_component_key'])
        ->toBe('contribution_action_plan')
        ->and($action['status'])
        ->toBe('open')
        ->and($action['producer'])
        ->toBe([
            'domain_key' => 'contribution',
            'component_key' =>
                'contribution_decision_record',
            'canonical_revision_id' => null,
            'operating_record_id' =>
                $decision['id'],
        ])
        ->and($action['sources'])
        ->toHaveCount(1)
        ->and(
            $action['sources'][0][
                'canonical_revision_id'
            ]
        )->toBe((int) $revision->id)
        ->and(
            $action['sources'][0][
                'component_key'
            ]
        )->toBe(
            'accepted_contribution_register'
        );

    $inProgress = $service->transition(
        $owner,
        $workspace,
        $action['id'],
        'in_progress'
    );

    expect($inProgress['status'])
        ->toBe('in_progress');

    $completed = $service->transition(
        $owner,
        $workspace,
        $action['id'],
        'completed'
    );

    expect($completed['status'])
        ->toBe('completed')
        ->and($completed['completed_at'])
        ->not->toBeNull();

    $after = $read->current(
        $owner,
        $workspace
    );

    expect(
        $after[
            'total_accepted_contribution_value'
        ]
    )->toBe(
        $before[
            'total_accepted_contribution_value'
        ]
    )->and(
        $after['contribution_matrix']
    )->toBe(
        $before['contribution_matrix']
    )->and(
        $revision->fresh()->payload
    )->toBe($beforePayload)
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(1);
});

test('contribution action plan rejects missing foreign and malformed decision records', function () {
    [
        $owner,
        $workspace,
    ] = c2F9PublishedRegister();

    $service = app(
        PbrV2ContributionActionPlanService::class
    );

    c2F9ExpectValidation(
        fn () => $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                999999,
                ['title' => 'Missing']
            ),
        'decision_record'
    );

    $otherWorkspace = c2F9Workspace(
        $owner,
        'Other Contribution Workspace'
    );

    $foreign = WorkspaceV2OperatingRecord::query()
        ->create([
            'workspace_id' =>
                $otherWorkspace->id,
            'domain_key' => 'contribution',
            'component_key' =>
                'contribution_decision_record',
            'recorded_by_user_id' =>
                $owner->id,
            'state' => 'recorded',
            'title' => 'Foreign Record',
            'record_date' => '2026-09-09',
            'effective_at' => now(),
            'payload' => [
                'fixture' => true,
            ],
            'recorded_at' => now(),
        ]);

    c2F9ExpectValidation(
        fn () => $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $foreign->id,
                ['title' => 'Foreign']
            ),
        'decision_record'
    );

    $malformed = WorkspaceV2OperatingRecord::query()
        ->create([
            'workspace_id' => $workspace->id,
            'domain_key' => 'contribution',
            'component_key' =>
                'contribution_decision_record',
            'recorded_by_user_id' =>
                $owner->id,
            'state' => 'recorded',
            'title' => 'Malformed Record',
            'record_date' => '2026-09-09',
            'effective_at' => now(),
            'payload' => [
                'fixture' => true,
            ],
            'recorded_at' => now(),
        ]);

    c2F9ExpectValidation(
        fn () => $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $malformed->id,
                ['title' => 'Malformed']
            ),
        'decision_record'
    );

    expect(
        WorkspaceV2Action::query()->count()
    )->toBe(0);
});

test('contribution action creation rejects a decision pinned to a superseded register', function () {
    [
        $owner,
        $workspace,
        $first,
    ] = c2F9PublishedRegister();

    $decision = app(
        PbrV2ContributionDecisionRecordService::class
    )->recordCurrent(
        $owner,
        $workspace,
        c2F9DecisionInput()
    );

    $approvalDecision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        'contribution',
        'accepted_contribution_register',
        'proposed',
        true,
        true
    );

    $second = app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        'contribution',
        'accepted_contribution_register',
        $first->payload,
        $approvalDecision,
        $owner
    );

    expect($first->fresh()->state)
        ->toBe('superseded')
        ->and($second->state)
        ->toBe('approved_current');

    c2F9ExpectValidation(
        fn () => app(
            PbrV2ContributionActionPlanService::class
        )->createFromDecisionRecord(
            $owner,
            $workspace,
            $decision['id'],
            [
                'title' =>
                    'Stale source must fail',
            ]
        ),
        'source_canonical_revision_ids'
    );

    expect(
        WorkspaceV2Action::query()->count()
    )->toBe(0);
});

test('contribution decision and action listings are read only', function () {
    [
        $owner,
        $workspace,
    ] = c2F9PublishedRegister();

    $decisionService = app(
        PbrV2ContributionDecisionRecordService::class
    );

    $record = $decisionService
        ->recordCurrent(
            $owner,
            $workspace,
            c2F9DecisionInput()
        );

    $actionService = app(
        PbrV2ContributionActionPlanService::class
    );

    $actionService->createFromDecisionRecord(
        $owner,
        $workspace,
        $record['id'],
        [
            'title' =>
                'Read-only listing fixture',
        ]
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

    $records = $decisionService->records(
        $owner,
        $workspace
    );

    $actions = $actionService->actions(
        $owner,
        $workspace
    );

    expect($writes)->toBe([])
        ->and($records)->toHaveCount(1)
        ->and($actions)->toHaveCount(1)
        ->and(
            $records[0]['source'][
                'component_key'
            ]
        )->toBe(
            'accepted_contribution_register'
        )
        ->and(
            $actions[0][
                'action_component_key'
            ]
        )->toBe(
            'contribution_action_plan'
        );
});
