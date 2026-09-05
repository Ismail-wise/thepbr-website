<?php

use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2OperatingRecord;
use App\Models\WorkspaceV2ReviewAuditEvent;
use App\Models\WorkspaceV2ReviewCase;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2CanonicalWorkflowService;
use App\Services\PbrTools\PbrV2ExecutionService;
use App\Services\PbrTools\PbrV2ReviewAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function f12Owner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);
}

function f12Workspace(User $owner): PartnershipWorkspace
{
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'F12 Application Workspace',
        'business_name' => 'F12 Application Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function f12Draft(User $owner, PartnershipWorkspace $workspace): WorkspaceV2CanonicalWorkArtifact
{
    return app(PbrV2CanonicalWorkService::class)->createWorkingDraft(
        $workspace,
        'capital',
        'capital_call_rule',
        'capital',
        'startup_capital_planner',
        ['rule' => 'foundation fixture'],
        [],
        $owner
    );
}

function f12Revision(
    User $owner,
    PartnershipWorkspace $workspace
): WorkspaceV2CanonicalRevision {
    $decision = app(PbrV2ApprovalEngineService::class)->approve(
        'capital', 'capital_call_rule', 'proposed', true, true
    );

    return app(PbrV2CanonicalOutputService::class)->publish(
        $workspace,
        'capital',
        'capital_call_rule',
        ['rule' => 'existing approved fixture'],
        $decision,
        $owner
    );
}

function f12Review(User $owner, PartnershipWorkspace $workspace): WorkspaceV2ReviewCase
{
    $revision = f12Revision($owner, $workspace);

    return app(PbrV2ApplicationService::class)->openReview(
        $owner,
        $workspace,
        'capital',
        'capital_funding_workflow',
        'capital',
        'capital_call_rule',
        $revision->revision,
        'manual_review',
        ['fixture' => 'f12']
    );
}

function f12OperationArguments(string $method): array
{
    return match ($method) {
        'createWorkingDraft' => [
            'capital', 'capital_call_rule', 'capital', 'startup_capital_planner', [],
        ],
        'updateWorkingDraftPayload' => [new WorkspaceV2CanonicalWorkArtifact(), []],
        'refreshWorkingDraftSources' => [new WorkspaceV2CanonicalWorkArtifact(), []],
        'submitProposal' => [new WorkspaceV2CanonicalWorkArtifact()],
        'rejectAndReturnToWorkingDraft' => [new WorkspaceV2CanonicalWorkArtifact(), 'Revise'],
        'openReview' => [
            'capital', 'capital_funding_workflow', 'capital', 'capital_call_rule',
            1, 'manual_review',
        ],
        'resolveReview' => [new WorkspaceV2ReviewCase(), 'no_change'],
        'requestAmendmentAndCreateWorkingDraft' => [
            new WorkspaceV2ReviewCase(), 'capital', 'startup_capital_planner', [],
        ],
        'createAction' => ['capital', 'capital_call_rule', []],
        'transitionAction' => [new WorkspaceV2Action(), 'in_progress'],
        'appendRecord' => ['capital', 'capital_decision_record', [], []],
    };
}

function f12Counts(): array
{
    return [
        WorkspaceV2CanonicalWorkArtifact::query()->count(),
        WorkspaceV2CanonicalRevision::query()->count(),
        WorkspaceV2ReviewCase::query()->count(),
        WorkspaceV2ReviewAuditEvent::query()->count(),
        WorkspaceV2Action::query()->count(),
        WorkspaceV2OperatingRecord::query()->count(),
    ];
}

dataset('f12 operations', [
    'createWorkingDraft',
    'updateWorkingDraftPayload',
    'refreshWorkingDraftSources',
    'submitProposal',
    'rejectAndReturnToWorkingDraft',
    'openReview',
    'resolveReview',
    'requestAmendmentAndCreateWorkingDraft',
    'createAction',
    'transitionAction',
    'appendRecord',
]);

dataset('f12 denied actors', [
    'inactive admin',
    'expired owner',
    'non owner student',
    'accepted partner',
]);

test('f12 exposes the exact constructor and eleven method signatures', function () {
    $reflection = new ReflectionClass(PbrV2ApplicationService::class);
    expect($reflection->isFinal())->toBeTrue();

    $dependencies = [
        'work' => PbrV2CanonicalWorkService::class,
        'workflow' => PbrV2CanonicalWorkflowService::class,
        'review' => PbrV2ReviewAuditService::class,
        'execution' => PbrV2ExecutionService::class,
    ];

    expect(array_map(
        fn ($p) => $p->getName(),
        $reflection->getConstructor()->getParameters()
    ))->toBe(array_keys($dependencies));

    expect($reflection->getProperties())->toHaveCount(4);

    foreach ($dependencies as $name => $type) {
        $property = $reflection->getProperty($name);
        expect($property->isPrivate())->toBeTrue()
            ->and($property->isReadOnly())->toBeTrue()
            ->and((string) $property->getType())->toBe($type);
    }

    $artifact = WorkspaceV2CanonicalWorkArtifact::class;
    $case = WorkspaceV2ReviewCase::class;
    $action = WorkspaceV2Action::class;

    $expected = [
        'createWorkingDraft' => [
            ['targetDomainKey', 'string'], ['targetComponentKey', 'string'],
            ['producerDomainKey', 'string'], ['producerComponentKey', 'string'],
            ['payload', 'array'], ['sourceCanonicalRevisionIds', 'array', []],
        ],
        'updateWorkingDraftPayload' => [['artifact', $artifact], ['payload', 'array']],
        'refreshWorkingDraftSources' => [
            ['artifact', $artifact], ['sourceCanonicalRevisionIds', 'array'],
        ],
        'submitProposal' => [['artifact', $artifact]],
        'rejectAndReturnToWorkingDraft' => [['artifact', $artifact], ['reason', 'string']],
        'openReview' => [
            ['producerDomainKey', 'string'], ['producerComponentKey', 'string'],
            ['targetDomainKey', 'string'], ['targetComponentKey', 'string'],
            ['targetRevision', 'int'], ['triggerType', 'string'],
            ['triggerContext', 'array', []],
        ],
        'resolveReview' => [
            ['case', $case], ['resolution', 'string'], ['resolutionNote', '?string', null],
        ],
        'requestAmendmentAndCreateWorkingDraft' => [
            ['case', $case], ['producerDomainKey', 'string'],
            ['producerComponentKey', 'string'], ['payload', 'array'],
            ['sourceCanonicalRevisionIds', 'array', []],
            ['reason', '?string', null], ['amendmentContext', 'array', []],
        ],
        'createAction' => [
            ['producerDomainKey', 'string'], ['producerComponentKey', 'string'],
            ['sourceCanonicalRevisionIds', 'array'], ['attributes', 'array', []],
            ['producerCanonicalRevisionId', '?int', null],
            ['producerOperatingRecordId', '?int', null],
        ],
        'transitionAction' => [['action', $action], ['toState', 'string']],
        'appendRecord' => [
            ['domainKey', 'string'], ['componentKey', 'string'],
            ['sourceCanonicalRevisionIds', 'array'], ['payload', 'array'],
            ['attributes', 'array', []],
        ],
    ];

    $returns = [
        'createWorkingDraft' => $artifact,
        'updateWorkingDraftPayload' => $artifact,
        'refreshWorkingDraftSources' => $artifact,
        'submitProposal' => $artifact,
        'rejectAndReturnToWorkingDraft' => 'array',
        'openReview' => $case,
        'resolveReview' => $case,
        'requestAmendmentAndCreateWorkingDraft' => 'array',
        'createAction' => $action,
        'transitionAction' => $action,
        'appendRecord' => WorkspaceV2OperatingRecord::class,
    ];

    $public = array_map(
        fn ($method) => $method->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    sort($public);
    $names = array_merge(['__construct'], array_keys($expected));
    sort($names);
    expect($public)->toBe($names);

    foreach ($expected as $name => $parameters) {
        $method = $reflection->getMethod($name);
        $contract = array_merge([
            ['actor', User::class],
            ['workspace', PartnershipWorkspace::class],
        ], $parameters);

        expect((string) $method->getReturnType())->toBe($returns[$name])
            ->and($method->getParameters())->toHaveCount(count($contract));

        foreach ($method->getParameters() as $index => $parameter) {
            $row = $contract[$index];
            expect($parameter->getName())->toBe($row[0])
                ->and((string) $parameter->getType())->toBe($row[1])
                ->and($parameter->isDefaultValueAvailable())->toBe(count($row) === 3);
            if (count($row) === 3) {
                expect($parameter->getDefaultValue())->toBe($row[2]);
            }
        }
    }
});

test('f12 denies every mutation before creating domain rows', function (
    string $method,
    string $kind
) {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);

    $actor = match ($kind) {
        'expired owner' => $owner,
        default => f12Owner(),
    };

    if ($kind === 'inactive admin') {
        $actor->forceFill([
            'role' => 'admin', 'is_admin' => true, 'account_status' => 'inactive',
        ])->save();
    } elseif ($kind === 'expired owner') {
        $actor->forceFill(['portal_access_expires_at' => now()->subDay()])->save();
    } elseif ($kind === 'accepted partner') {
        WorkspaceMember::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $actor->id,
            'member_role' => 'partner',
            'invitation_status' => 'accepted',
            'permissions' => ['approved_workspace_read_only'],
        ]);
    }

    $before = f12Counts();

    expect(fn () => app(PbrV2ApplicationService::class)->{$method}(
        $actor, $workspace, ...f12OperationArguments($method)
    ))->toThrow(AuthorizationException::class);

    expect(f12Counts())->toBe($before);
})->with('f12 operations')->with('f12 denied actors');

test('f12 reloads actor workspace and enrollment evidence', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $intruder = f12Owner();

    $intruder->is_admin = true;
    $workspace->owner_user_id = $intruder->id;

    expect(fn () => app(PbrV2ApplicationService::class)->createWorkingDraft(
        $intruder, $workspace, 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', []
    ))->toThrow(AuthorizationException::class);

    $owner->forceFill(['portal_access_expires_at' => now()->subDay()])->save();
    $owner->setRelation('studentEnrollments', collect([
        new StudentEnrollment([
            'status' => 'active',
            'access_expires_at' => now()->addYear(),
        ]),
    ]));

    expect(fn () => app(PbrV2ApplicationService::class)->createWorkingDraft(
        $owner, $workspace->fresh(), 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', []
    ))->toThrow(AuthorizationException::class);

    expect(WorkspaceV2CanonicalWorkArtifact::query()->count())->toBe(0);
});

test('f12 requires persisted actor and workspace', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $app = app(PbrV2ApplicationService::class);

    expect(fn () => $app->createWorkingDraft(
        new User(), $workspace, 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', []
    ))->toThrow(ModelNotFoundException::class);

    expect(fn () => $app->createWorkingDraft(
        $owner, new PartnershipWorkspace(), 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', []
    ))->toThrow(ModelNotFoundException::class);
});

test('f12 owner delegates draft update source refresh submission and rejection', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $app = app(PbrV2ApplicationService::class);

    $draft = $app->createWorkingDraft(
        $owner, $workspace, 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', ['rule' => 'initial']
    );
    expect($draft->created_by_user_id)->toBe($owner->id);

    $draft = $app->updateWorkingDraftPayload(
        $owner, $workspace, $draft, ['rule' => 'revised']
    );
    expect($draft->payload)->toBe(['rule' => 'revised']);

    $generation = $draft->source_generation;
    $draft = $app->refreshWorkingDraftSources($owner, $workspace, $draft, []);
    expect($draft->source_generation)->toBe($generation + 1);

    $proposal = $app->submitProposal($owner, $workspace, $draft);
    expect($proposal->state)->toBe('proposed')
        ->and($proposal->submitted_by_user_id)->toBe($owner->id);

    $proposal->workspace_id = -1;
    $proposal->state = 'working_draft';
    $proposal->payload = ['rule' => 'caller tampering'];

    $result = $app->rejectAndReturnToWorkingDraft(
        $owner, $workspace, $proposal, 'Needs revision'
    );

    expect(array_keys($result))->toBe(['decision', 'work_artifact'])
        ->and($result['work_artifact']->id)->toBe($draft->id)
        ->and($result['work_artifact']->state)->toBe('working_draft')
        ->and($result['work_artifact']->payload)->toBe(['rule' => 'revised'])
        ->and(WorkspaceV2CanonicalRevision::query()->count())->toBe(0)
        ->and(WorkspaceV2Action::query()->count())->toBe(0)
        ->and(WorkspaceV2OperatingRecord::query()->count())->toBe(0);
});

test('f12 active admin may manage another owners workspace', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $admin = f12Owner();
    $admin->forceFill(['role' => 'admin', 'is_admin' => true])->save();

    $draft = app(PbrV2ApplicationService::class)->createWorkingDraft(
        $admin, $workspace, 'capital', 'capital_call_rule',
        'capital', 'startup_capital_planner', []
    );

    expect($draft->workspace_id)->toBe($workspace->id)
        ->and($draft->created_by_user_id)->toBe($admin->id);
});

test('f12 rejects foreign and missing artifact identities', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $otherWorkspace = f12Workspace($owner);
    $foreign = f12Draft($owner, $otherWorkspace);
    $foreign->workspace_id = $workspace->id;
    $app = app(PbrV2ApplicationService::class);

    foreach ([
        'updateWorkingDraftPayload' => [$foreign, []],
        'refreshWorkingDraftSources' => [$foreign, []],
        'submitProposal' => [$foreign],
        'rejectAndReturnToWorkingDraft' => [$foreign, 'Revise'],
    ] as $method => $arguments) {
        expect(fn () => $app->{$method}($owner, $workspace, ...$arguments))
            ->toThrow(ValidationException::class);
    }

    expect(fn () => $app->submitProposal(
        $owner, $workspace, new WorkspaceV2CanonicalWorkArtifact()
    ))->toThrow(ValidationException::class);

    expect($foreign->fresh()->state)->toBe('working_draft');
});

test('f12 preserves the frozen rejection validation exception', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $app = app(PbrV2ApplicationService::class);
    $proposal = $app->submitProposal($owner, $workspace, f12Draft($owner, $workspace));

    expect(fn () => $app->rejectAndReturnToWorkingDraft(
        $owner, $workspace, $proposal, ' '
    ))->toThrow(\InvalidArgumentException::class);

    expect($proposal->fresh()->state)->toBe('proposed');
});

test('f12 delegates review opening and direct resolution', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $case = f12Review($owner, $workspace);

    $resolved = app(PbrV2ApplicationService::class)->resolveReview(
        $owner, $workspace, $case, 'no_change', 'Reviewed'
    );

    expect($resolved->resolution)->toBe('no_change')
        ->and($resolved->resolved_by_user_id)->toBe($owner->id)
        ->and(WorkspaceV2CanonicalRevision::query()->count())->toBe(1)
        ->and(WorkspaceV2CanonicalWorkArtifact::query()->count())->toBe(0);
});

test('f12 amendment creates one draft and preserves the f8 handoff', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $case = f12Review($owner, $workspace);
    $app = app(PbrV2ApplicationService::class);

    $result = $app->requestAmendmentAndCreateWorkingDraft(
        $owner, $workspace, $case, 'capital', 'startup_capital_planner',
        ['rule' => 'amendment'], [], 'Needs amendment', ['fixture' => true]
    );

    expect(array_keys($result))->toBe(['review_handoff', 'work_artifact'])
        ->and($result['review_handoff']['working_draft_created'])->toBeFalse()
        ->and($result['review_handoff']['review_case_id'])->toBe($case->id)
        ->and($result['work_artifact']->state)->toBe('working_draft')
        ->and($result['work_artifact']->amendment_review_case_id)->toBe($case->id)
        ->and($case->fresh()->resolution)->toBe('amendment_required')
        ->and(WorkspaceV2CanonicalRevision::query()->count())->toBe(1);

    $before = f12Counts();

    expect(fn () => $app->requestAmendmentAndCreateWorkingDraft(
        $owner, $workspace, $case, 'capital', 'startup_capital_planner', []
    ))->toThrow(\DomainException::class);

    expect(f12Counts())->toBe($before);
});

test('f12 amendment rolls back f8 resolution and audit when f10 rejects', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $case = f12Review($owner, $workspace);
    $before = f12Counts();

    expect(fn () => app(PbrV2ApplicationService::class)
        ->requestAmendmentAndCreateWorkingDraft(
            $owner, $workspace, $case, 'capital', 'missing_producer',
            ['rule' => 'must rollback']
        ))->toThrow(ValidationException::class);

    expect($case->fresh()->status)->toBe('open')
        ->and($case->fresh()->resolution)->toBeNull()
        ->and(f12Counts())->toBe($before);
});

test('f12 rejects foreign and missing review targets', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $otherWorkspace = f12Workspace($owner);
    $case = f12Review($owner, $otherWorkspace);
    $case->workspace_id = $workspace->id;
    $app = app(PbrV2ApplicationService::class);
    $before = f12Counts();

    foreach ([$case, new WorkspaceV2ReviewCase()] as $target) {
        expect(fn () => $app->resolveReview(
            $owner, $workspace, $target, 'no_change'
        ))->toThrow(ValidationException::class);

        expect(fn () => $app->requestAmendmentAndCreateWorkingDraft(
            $owner, $workspace, $target, 'capital', 'startup_capital_planner', []
        ))->toThrow(ValidationException::class);
    }

    expect(f12Counts())->toBe($before);
});

test('f12 delegates action and record creation without publishing', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $revision = f12Revision($owner, $workspace);
    $app = app(PbrV2ApplicationService::class);

    $action = $app->createAction(
        $owner, $workspace, 'capital', 'capital_call_rule', [$revision->id],
        ['title' => 'Follow approved capital decision'], $revision->id
    );

    $record = $app->appendRecord(
        $owner, $workspace, 'capital', 'capital_decision_record',
        [$revision->id], ['decision' => 'Execution fixture'],
        ['title' => 'Capital execution record']
    );

    expect($action->exists)->toBeTrue()
        ->and($action->workspace_id)->toBe($workspace->id)
        ->and($record->exists)->toBeTrue()
        ->and($record->recorded_by_user_id)->toBe($owner->id)
        ->and(WorkspaceV2CanonicalRevision::query()->count())->toBe(1);

    $foreignWorkspace = f12Workspace($owner);
    $action->workspace_id = $foreignWorkspace->id;

    expect(fn () => $app->transitionAction(
        $owner, $foreignWorkspace, $action, 'in_progress'
    ))->toThrow(ValidationException::class);

    expect(fn () => $app->transitionAction(
        $owner, $workspace, new WorkspaceV2Action(), 'in_progress'
    ))->toThrow(ValidationException::class);
});

test('f12 forwards fresh action identity and transition arguments to f9', function () {
    $owner = f12Owner();
    $workspace = f12Workspace($owner);
    $revision = f12Revision($owner, $workspace);

    $action = app(PbrV2ExecutionService::class)->createAction(
        $owner, $workspace, 'capital', 'capital_call_rule', [$revision->id],
        ['title' => 'Transition fixture'], $revision->id
    );
    $persistedState = $action->state;
    $expected = $action->fresh();

    $execution = Mockery::mock(PbrV2ExecutionService::class);
    $execution->shouldReceive('transitionAction')
        ->once()
        ->withArgs(fn ($actualActor, $actualWorkspace, $actualAction, $toState) =>
            $actualActor->id === $owner->id
            && $actualWorkspace->id === $workspace->id
            && $actualAction->id === $action->id
            && $actualAction->state === $persistedState
            && $toState === 'application-forwarding-fixture'
        )
        ->andReturn($expected);

    $application = new PbrV2ApplicationService(
        app(PbrV2CanonicalWorkService::class),
        app(PbrV2CanonicalWorkflowService::class),
        app(PbrV2ReviewAuditService::class),
        $execution
    );

    $action->state = 'caller-tampered';
    $action->workspace_id = -1;

    expect($application->transitionAction(
        $owner, $workspace, $action, 'application-forwarding-fixture'
    ))->toBe($expected);
});
