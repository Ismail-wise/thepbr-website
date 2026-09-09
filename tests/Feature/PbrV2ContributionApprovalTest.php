<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2ContributionApprovalService;
use App\Services\PbrTools\PbrV2ContributionReadinessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function c2PublicationOwner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ], $attributes));
}

function c2PublicationWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Contribution publication workspace',
        'business_name' => 'Contribution publication business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function c2PublicationSetup(array $approverIds): array
{
    return [
        'valuation_date' => '2026-09-09',
        'currency_code' => 'THB',
        'contribution_period' => [
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ],
        'valuation_owner_reference' => 'Contribution Owner',
        'approver_user_ids' => $approverIds,
    ];
}

function c2PublicationItem(
    int $number,
    string $status = 'accepted',
    string $value = '100.00'
): array {
    $partnerKey = 'partner-'.$number;

    return [
        'id' => 'contribution-'.$number,
        'partner_key' => $partnerKey,
        'partner_snapshot' => [
            'profile_id' => $number,
            'partner_key' => $partnerKey,
            'user_id' => null,
            'display_name' => 'Partner '.$number,
            'status' => 'planned',
        ],
        'contribution_type' => 'cash',
        'description' => 'Cash contribution '.$number,
        'owner_reference' => 'Partner '.$number,
        'proposed_value' => $value,
        'valuation_method' => 'Cash receipt',
        'evidence_references' => [
            'receipt-'.$number,
        ],
        'conditions' => '',
        'notes' => '',
        'amount_committed' => $value,
        'amount_received' => $value,
        'payment_date' => '2026-09-09',
        'reviewed_value' => $value,
        'agreed_value' => $value,
        'accepted_value' => $value,
        'committed_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'delivered_date' => '2026-09-20',
        'delivery_extent' => 'full',
        'status' => $status,
        'approval_records' => [],
        'event_history' => [],
    ];
}

function c2PublicationDraft(
    User $owner,
    PartnershipWorkspace $workspace,
    array $itemSpecifications = [
        ['accepted', '100.00'],
    ],
    ?array $approverIds = null
): WorkspaceV2CanonicalWorkArtifact {
    $approverIds ??= [(int) $owner->id];

    $setup = c2PublicationSetup($approverIds);

    $items = [];
    $partners = [];

    foreach ($itemSpecifications as $index => $specification) {
        [$status, $value] = $specification;
        $number = $index + 1;

        $item = c2PublicationItem(
            $number,
            $status,
            $value
        );

        $items[] = $item;
        $partners[] = $item['partner_snapshot'];
    }

    $payload = [
        'schema_version' => 'contribution-register-v1',
        'edit_version' => 1,
        'setup' => $setup,
        'partners' => $partners,
        'items' => $items,
        'event_history' => [[
            'action' => 'publication_fixture_created',
            'actor_user_id' => (int) $owner->id,
            'occurred_at' => '2026-09-09T06:00:00Z',
        ]],
    ];

    $work = app(PbrV2CanonicalWorkService::class);

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

    $payload = $draft->payload;
    $readiness = app(
        PbrV2ContributionReadinessService::class
    );

    foreach ($payload['items'] as $index => $item) {
        if (($item['status'] ?? null) !== 'accepted') {
            continue;
        }

        $fingerprint = $readiness
            ->acceptanceFingerprint(
                (int) $workspace->id,
                (int) $draft->id,
                $setup,
                $item
            );

        $payload['items'][$index]['approval_records'] =
            array_map(
                static fn (int $id): array => [
                    'user_id' => $id,
                    'decision' => 'approved',
                    'approved_at' =>
                        '2026-09-09T06:10:00Z',
                    'evidence_reference' =>
                        'acceptance-'.$id,
                    'notes' => '',
                    'fingerprint' => $fingerprint,
                ],
                $approverIds
            );
    }

    return $work->updateWorkingDraftPayload(
        $workspace,
        $draft,
        $payload
    );
}

function c2PublicationExpectValidation(
    callable $operation,
    string $key
): void {
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    throw new RuntimeException(
        'Expected ValidationException for '.$key
    );
}

test('contribution publication submission uses persisted readiness and accepted only totals', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $draft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '100.00'],
            ['cancelled', '900.00'],
        ]
    );

    $payload = $draft->payload;

    $result = app(
        PbrV2ContributionApprovalService::class
    )->submitForPublication(
        $owner,
        $workspace,
        $draft,
        1
    );

    $proposal = $result['work_artifact'];

    expect(array_keys($result))->toBe([
        'readiness',
        'work_artifact',
    ])
        ->and($result['readiness']['passed'])
        ->toBeTrue()
        ->and(
            $result['readiness']['calculations']
                ['accepted_item_count']
        )->toBe(1)
        ->and(
            $result['readiness']['calculations']
                ['excluded_item_count']
        )->toBe(1)
        ->and(
            $result['readiness']['calculations']
                ['total_accepted_contribution_value']
        )->toBe('100.00')
        ->and($proposal->state)->toBe('proposed')
        ->and($proposal->payload)->toBe($payload)
        ->and($proposal->submitted_by_user_id)
        ->toBe($owner->id)
        ->and($proposal->published_canonical_revision_id)
        ->toBeNull()
        ->and(
            DB::table(
                'workspace_v2_canonical_work_sources'
            )->count()
        )->toBe(0)
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0);
});

test('contribution publication creates approved current revision from locked persisted proposal', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $draft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '125.00'],
            ['defaulted', '500.00'],
        ]
    );

    $submission = app(
        PbrV2ContributionApprovalService::class
    )->submitForPublication(
        $owner,
        $workspace,
        $draft,
        1
    );

    $proposal = $submission['work_artifact'];
    $persistedPayload = $proposal->payload;

    // Dirty caller attributes must not replace locked database evidence.
    $proposal->payload = ['forged' => true];
    $proposal->target_component_key = 'capital_call_rule';
    $workspace->currency_code = 'USD';

    $result = app(
        PbrV2ContributionApprovalService::class
    )->approveAndPublish(
        $owner,
        $workspace,
        $proposal
    );

    $revision =
        $result['workflow']['canonical_revision'];

    expect(array_keys($result))->toBe([
        'readiness',
        'workflow',
    ])
        ->and($result['readiness']['passed'])
        ->toBeTrue()
        ->and(
            $result['readiness']['calculations']
                ['total_accepted_contribution_value']
        )->toBe('125.00')
        ->and($revision->domain_key)
        ->toBe('contribution')
        ->and($revision->component_key)
        ->toBe('accepted_contribution_register')
        ->and($revision->state)
        ->toBe('approved_current')
        ->and($revision->payload)
        ->toBe($persistedPayload)
        ->and($revision->payload['items'])
        ->toHaveCount(2)
        ->and($revision->payload['items'][1]['status'])
        ->toBe('defaulted')
        ->and((int) $revision->published_by_user_id)
        ->toBe((int) $owner->id)
        ->and($revision->published_at)
        ->not->toBeNull()
        ->and($proposal->fresh()->consumed_at)
        ->not->toBeNull()
        ->and(
            (int) $proposal->fresh()
                ->published_canonical_revision_id
        )->toBe((int) $revision->id)
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->where('state', 'approved_current')
                ->count()
        )->toBe(1);
});

test('contribution publication rejects stale browser submission and unresolved rows', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $draft = c2PublicationDraft(
        $owner,
        $workspace
    );

    c2PublicationExpectValidation(
        fn () => app(
            PbrV2ContributionApprovalService::class
        )->submitForPublication(
            $owner,
            $workspace,
            $draft,
            2
        ),
        'edit_version'
    );

    $unresolved = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['delivered', '100.00'],
        ]
    );

    c2PublicationExpectValidation(
        fn () => app(
            PbrV2ContributionApprovalService::class
        )->submitForPublication(
            $owner,
            $workspace,
            $unresolved,
            1
        ),
        'payload.items.0.status'
    );

    expect($draft->fresh()->state)
        ->toBe('working_draft')
        ->and($unresolved->fresh()->state)
        ->toBe('working_draft')
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0);
});

test('contribution publication denies non managers before proposal mutation', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);
    $draft = c2PublicationDraft(
        $owner,
        $workspace
    );

    $other = c2PublicationOwner();

    expect(fn () => app(
        PbrV2ContributionApprovalService::class
    )->submitForPublication(
        $other,
        $workspace,
        $draft,
        1
    ))->toThrow(
        AuthorizationException::class
    );

    expect($draft->fresh()->state)
        ->toBe('working_draft')
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0);
});

test('contribution publication rechecks every designated approver current access', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $partner = c2PublicationOwner([
        'role' => 'partner',
    ]);

    DB::table('workspace_members')->insert([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
    ]);

    $draft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '100.00'],
        ],
        [
            (int) $owner->id,
            (int) $partner->id,
        ]
    );

    DB::table('users')
        ->where('id', $partner->id)
        ->update([
            'account_status' => 'inactive',
        ]);

    c2PublicationExpectValidation(
        fn () => app(
            PbrV2ContributionApprovalService::class
        )->submitForPublication(
            $owner,
            $workspace,
            $draft,
            1
        ),
        'payload.setup.approver_user_ids'
    );

    expect($draft->fresh()->state)
        ->toBe('working_draft');
});

test('contribution publication revalidates tampered persisted acceptance before canonical publish', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $draft = c2PublicationDraft(
        $owner,
        $workspace
    );

    $proposal = app(
        PbrV2ContributionApprovalService::class
    )->submitForPublication(
        $owner,
        $workspace,
        $draft,
        1
    )['work_artifact'];

    $payload = $proposal->payload;
    $payload['items'][0]['accepted_value'] =
        '90.00';

    DB::table(
        'workspace_v2_canonical_work_artifacts'
    )
        ->where('id', $proposal->id)
        ->update([
            'payload' => json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            ),
        ]);

    c2PublicationExpectValidation(
        fn () => app(
            PbrV2ContributionApprovalService::class
        )->approveAndPublish(
            $owner,
            $workspace,
            $proposal
        ),
        'payload.items.0.approval_records.0.fingerprint'
    );

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->count()
    )->toBe(0)
        ->and($proposal->fresh()->consumed_at)
        ->toBeNull()
        ->and(
            $proposal->fresh()
                ->published_canonical_revision_id
        )->toBeNull();
});

test('contribution publication cannot bypass proposed state', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $draft = c2PublicationDraft(
        $owner,
        $workspace
    );

    c2PublicationExpectValidation(
        fn () => app(
            PbrV2ContributionApprovalService::class
        )->approveAndPublish(
            $owner,
            $workspace,
            $draft
        ),
        'state'
    );

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->count()
    )->toBe(0);
});

test('new root contribution draft cannot silently supersede an approved register', function () {
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $service = app(
        PbrV2ContributionApprovalService::class
    );

    $firstDraft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '100.00'],
        ]
    );

    $firstProposal = $service
        ->submitForPublication(
            $owner,
            $workspace,
            $firstDraft,
            1
        )['work_artifact'];

    $firstRevision = $service
        ->approveAndPublish(
            $owner,
            $workspace,
            $firstProposal
        )['workflow']['canonical_revision'];

    $secondDraft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '200.00'],
        ]
    );

    c2PublicationExpectValidation(
        fn () => $service->submitForPublication(
            $owner,
            $workspace,
            $secondDraft,
            1
        ),
        'amendment'
    );

    expect($firstRevision->fresh()->state)
        ->toBe('approved_current')
        ->and($secondDraft->fresh()->state)
        ->toBe('working_draft')
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(1);
});

function c2PublishedRegisterForAmendment(): array
{
    $owner = c2PublicationOwner();
    $workspace = c2PublicationWorkspace($owner);

    $approval = app(
        PbrV2ContributionApprovalService::class
    );

    $draft = c2PublicationDraft(
        $owner,
        $workspace,
        [
            ['accepted', '100.00'],
            ['cancelled', '25.00'],
        ]
    );

    $proposal = $approval->submitForPublication(
        $owner,
        $workspace,
        $draft,
        1
    )['work_artifact'];

    $revision = $approval->approveAndPublish(
        $owner,
        $workspace,
        $proposal
    )['workflow']['canonical_revision'];

    return [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ];
}

test('contribution review may resolve with no change without creating amendment work', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ] = c2PublishedRegisterForAmendment();

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $beforeArtifacts =
        WorkspaceV2CanonicalWorkArtifact::query()
            ->count();

    $case = $flow->openReview(
        $owner,
        $workspace,
        'manual_review',
        ['reason' => 'Periodic contribution review']
    );

    expect($case->status)->toBe('open')
        ->and($case->target_canonical_revision_id)
        ->toBe($revision->id)
        ->and($revision->fresh()->state)
        ->toBe('approved_current');

    $resolved = $flow->resolveReview(
        $owner,
        $workspace,
        $case,
        'no_change',
        'Accepted contribution register remains suitable.'
    );

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution)
        ->toBe('no_change')
        ->and($revision->fresh()->state)
        ->toBe('approved_current')
        ->and(
            WorkspaceV2CanonicalWorkArtifact::query()
                ->count()
        )->toBe($beforeArtifacts);
});

test('contribution amendment preserves prior canonical acceptance as history and requires fresh acceptance', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ] = c2PublishedRegisterForAmendment();

    $canonicalBefore = $revision->payload;
    $oldApproval =
        $canonicalBefore['items'][0]
            ['approval_records'][0];

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace,
        'manual_review',
        ['reason' => 'Accepted contribution amendment']
    );

    $result =
        $flow->requestAmendmentAndCreateDraft(
            $owner,
            $workspace,
            $case,
            'Contribution register requires amendment.',
            ['requested_focus' => 'accepted value review']
        );

    $draft = $result['work_artifact'];
    $item = $draft->payload['items'][0];
    $history =
        $item['event_history'][
            array_key_last($item['event_history'])
        ];

    expect(array_keys($result))->toBe([
        'review_handoff',
        'work_artifact',
    ])
        ->and(
            $result['review_handoff']['resolution']
        )->toBe('amendment_required')
        ->and($case->fresh()->status)
        ->toBe('resolved')
        ->and($case->fresh()->resolution)
        ->toBe('amendment_required')
        ->and($draft->state)
        ->toBe('working_draft')
        ->and($draft->payload['edit_version'])
        ->toBe(1)
        ->and($draft->amendment_review_case_id)
        ->toBe($case->id)
        ->and(
            $draft
                ->amendment_target_canonical_revision_id
        )->toBe($revision->id)
        ->and($item['status'])
        ->toBe('delivered')
        ->and($item['approval_records'])
        ->toBe([])
        ->and($item['accepted_value'])
        ->toBe('100.00')
        ->and($item['delivered_date'])
        ->toBe('2026-09-20')
        ->and($history['action'])
        ->toBe(
            'amendment_reopened_for_acceptance'
        )
        ->and(
            $history['previous_approval_records'][0]
        )->toBe($oldApproval)
        ->and(
            $draft->payload['items'][1]['status']
        )->toBe('cancelled')
        ->and(
            DB::table(
                'workspace_v2_canonical_work_sources'
            )
                ->where(
                    'work_artifact_id',
                    $draft->id
                )
                ->count()
        )->toBe(0)
        ->and($revision->fresh()->state)
        ->toBe('approved_current')
        ->and($revision->fresh()->payload)
        ->toBe($canonicalBefore);

    $freshFingerprint = app(
        PbrV2ContributionReadinessService::class
    )->acceptanceFingerprint(
        (int) $workspace->id,
        (int) $draft->id,
        $draft->payload['setup'],
        $item
    );

    expect($freshFingerprint)
        ->not->toBe($oldApproval['fingerprint']);
});

test('inherited accepted contribution requires fresh amendment acceptance before revision two publication', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $first,
    ] = c2PublishedRegisterForAmendment();

    $firstPayload = $first->payload;

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace
    );

    $amendment =
        $flow->requestAmendmentAndCreateDraft(
            $owner,
            $workspace,
            $case,
            'Reconfirm accepted contribution.'
        )['work_artifact'];

    c2PublicationExpectValidation(
        fn () => $approval->submitForPublication(
            $owner,
            $workspace,
            $amendment,
            1
        ),
        'payload.items.0.status'
    );

    $itemId = $amendment->payload['items'][0]['id'];

    $accepted = $approval->acceptItem(
        $owner,
        $workspace,
        $amendment,
        1,
        $itemId,
        'amendment-acceptance-proof',
        'Reconfirmed under amendment revision.'
    );

    expect($accepted->payload['items'][0]['status'])
        ->toBe('accepted')
        ->and($accepted->payload['edit_version'])
        ->toBe(2)
        ->and(
            $accepted->payload['items'][0]
                ['approval_records']
        )->toHaveCount(1);

    $secondProposal =
        $approval->submitForPublication(
            $owner,
            $workspace,
            $accepted,
            2
        )['work_artifact'];

    $second = $approval->approveAndPublish(
        $owner,
        $workspace,
        $secondProposal
    )['workflow']['canonical_revision'];

    expect($first->fresh()->state)
        ->toBe('superseded')
        ->and($first->fresh()->superseded_at)
        ->not->toBeNull()
        ->and($first->fresh()->payload)
        ->toBe($firstPayload)
        ->and($second->revision)
        ->toBe(2)
        ->and($second->state)
        ->toBe('approved_current')
        ->and(
            $second->payload['items'][0]['status']
        )->toBe('accepted')
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->where(
                    'domain_key',
                    'contribution'
                )
                ->where(
                    'component_key',
                    'accepted_contribution_register'
                )
                ->where(
                    'state',
                    'approved_current'
                )
                ->count()
        )->toBe(1);
});

test('amendment setup change escalates inherited delivered row back to proposed', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ] = c2PublishedRegisterForAmendment();

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace
    );

    $draft =
        $flow->requestAmendmentAndCreateDraft(
            $owner,
            $workspace,
            $case,
            'Change valuation date.'
        )['work_artifact'];

    expect($draft->payload['items'][0]['status'])
        ->toBe('delivered');

    $setup = $draft->payload['setup'];
    $setup['valuation_date'] = '2026-09-10';

    $saved = $flow->saveSetup(
        $owner,
        $workspace,
        $draft,
        1,
        $setup
    );

    expect($saved->payload['items'][0]['status'])
        ->toBe('proposed')
        ->and(
            $saved->payload['items'][0]
                ['approval_records']
        )->toBe([])
        ->and($saved->payload['edit_version'])
        ->toBe(2)
        ->and($revision->fresh()->state)
        ->toBe('approved_current');
});

test('contribution amendment request rejects review after its target is superseded', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $first,
    ] = c2PublishedRegisterForAmendment();

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace
    );

    $decision = app(
        \App\Services\PbrTools\PbrV2ApprovalEngineService::class
    )->approve(
        'contribution',
        'accepted_contribution_register',
        'proposed',
        true,
        true
    );

    $replacement = app(
        \App\Services\PbrTools\PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        'contribution',
        'accepted_contribution_register',
        $first->payload,
        $decision,
        $owner
    );

    expect($first->fresh()->state)
        ->toBe('superseded')
        ->and($replacement->state)
        ->toBe('approved_current');

    c2PublicationExpectValidation(
        fn () =>
            $flow->requestAmendmentAndCreateDraft(
                $owner,
                $workspace,
                $case,
                'Stale review must not amend.'
            ),
        'review_case'
    );

    expect($case->fresh()->status)
        ->toBe('open');
});

test('contribution review and amendment require Chapter 2 management authority', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ] = c2PublishedRegisterForAmendment();

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $outsider = c2PublicationOwner();

    expect(fn () => $flow->openReview(
        $outsider,
        $workspace
    ))->toThrow(
        AuthorizationException::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace
    );

    expect(fn () =>
        $flow->requestAmendmentAndCreateDraft(
            $outsider,
            $workspace,
            $case,
            'Unauthorized amendment attempt.'
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect($case->fresh()->status)
        ->toBe('open')
        ->and($revision->fresh()->state)
        ->toBe('approved_current');
});

test('resolved amendment review cannot create a second contribution amendment draft', function () {
    [
        $owner,
        $workspace,
        $approval,
        $proposal,
        $revision,
    ] = c2PublishedRegisterForAmendment();

    $flow = app(
        \App\Services\PbrTools\PbrV2ContributionWorkflowService::class
    );

    $case = $flow->openReview(
        $owner,
        $workspace
    );

    $first =
        $flow->requestAmendmentAndCreateDraft(
            $owner,
            $workspace,
            $case,
            'One amendment only.'
        );

    $before =
        WorkspaceV2CanonicalWorkArtifact::query()
            ->count();

    expect(fn () =>
        $flow->requestAmendmentAndCreateDraft(
            $owner,
            $workspace,
            $case,
            'Duplicate attempt.'
        )
    )->toThrow(
        \DomainException::class
    );

    expect(
        WorkspaceV2CanonicalWorkArtifact::query()
            ->count()
    )->toBe($before)
        ->and(
            $first['work_artifact']->fresh()->state
        )->toBe('working_draft')
        ->and($revision->fresh()->state)
        ->toBe('approved_current');
});

