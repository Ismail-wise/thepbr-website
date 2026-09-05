<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2CapitalApprovalService;
use App\Services\PbrTools\PbrV2CapitalPlanReadinessService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2CanonicalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function capitalApprovalOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);
}

function capitalApprovalWorkspace(User $owner): PartnershipWorkspace
{
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital approval workspace',
        'business_name' => 'Capital approval business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function capitalApprovalPayload(): array
{
    return [
        'schema_version' => 'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => 'Capital approval fixture',
        'scenario_type' => 'base',
        'assessment_date' => '2026-09-05',
        'assumptions' => [
            'opening_date' => '2026-10-01',
            'working_capital_months' => 3,
            'contingency_percentage' => '0.00',
            'monthly_burn' => '0.00',
        ],
        'cost_items' => [[
            'id' => 'asset-1',
            'category' => 'initial_assets_inventory',
            'description' => 'Equipment',
            'amount' => '100.00',
            'basis' => 'one_time',
            'evidence_reference' => 'quote-1',
        ]],
        'funding_sources' => [[
            'id' => 'cash-1',
            'source_type' => 'partner_cash',
            'description' => 'Confirmed cash',
            'confirmed_amount' => '100.00',
            'confirmation_date' => '2026-09-01',
            'evidence_reference' => 'receipt-1',
        ]],
        'shortfall_response' => null,
        'decision_record' => [
            'owner_reference' => 'capital-owner',
            'effective_date' => '2026-09-05',
            'review_date' => '2026-12-05',
            'decision_summary' => 'Approve equipment funding plan.',
            'evidence_references' => ['review-1'],
        ],
    ];
}

function capitalApprovalProposal(
    User $owner,
    PartnershipWorkspace $workspace,
    ?array $payload = null,
    string $component = 'approved_capital_plan'
): WorkspaceV2CanonicalWorkArtifact {
    $work = app(PbrV2CanonicalWorkService::class);
    $draft = $work->createWorkingDraft(
        $workspace,
        'capital',
        $component,
        'capital',
        'startup_capital_planner',
        $payload ?? capitalApprovalPayload(),
        [],
        $owner
    );

    return $work->submitProposal($workspace, $draft, $owner);
}

function capitalApprovalSnapshot(): array
{
    return [
        WorkspaceV2CanonicalWorkArtifact::query()->orderBy('id')->get()->toArray(),
        WorkspaceV2CanonicalRevision::query()->orderBy('id')->get()->toArray(),
    ];
}

function capitalApprovalExpectValidation(callable $operation, string $key): void
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    throw new RuntimeException('Expected ValidationException for '.$key);
}

test('capital approval has exactly the agreed public surface', function () {
    $class = new ReflectionClass(PbrV2CapitalApprovalService::class);
    expect($class->isFinal())->toBeTrue();

    $methods = array_map(
        fn ($method) => $method->getName(),
        $class->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    sort($methods);
    expect($methods)->toBe(['__construct', 'approveAndPublish']);

    $dependencies = [
        'readiness' => PbrV2CapitalPlanReadinessService::class,
        'workflow' => PbrV2CanonicalWorkflowService::class,
    ];
    expect($class->getProperties())->toHaveCount(2)
        ->and(array_map(
            fn ($parameter) => $parameter->getName(),
            $class->getConstructor()->getParameters()
        ))->toBe(array_keys($dependencies));

    foreach ($dependencies as $name => $type) {
        $property = $class->getProperty($name);
        expect($property->isPrivate())->toBeTrue()
            ->and($property->isReadOnly())->toBeTrue()
            ->and((string) $property->getType())->toBe($type);
    }

    $method = $class->getMethod('approveAndPublish');
    expect((string) $method->getReturnType())->toBe('array')
        ->and(array_map(
            fn ($parameter) => [$parameter->getName(), (string) $parameter->getType(), $parameter->isOptional()],
            $method->getParameters()
        ))->toBe([
            ['actor', User::class, false],
            ['workspace', PartnershipWorkspace::class, false],
            ['artifact', WorkspaceV2CanonicalWorkArtifact::class, false],
        ]);
});

test('capital approval publishes assessed persisted payload and consumes exactly once', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = capitalApprovalProposal($owner, $workspace);
    $payload = $proposal->payload;
    $expected = app(PbrV2CapitalPlanReadinessService::class)->assess(
        'capital', 'approved_capital_plan', $payload
    );

    // Dirty caller attributes must not replace database evidence.
    $proposal->payload = ['forged' => true];
    $proposal->target_component_key = 'capital_call_rule';
    $workspace->currency_code = 'USD';

    $service = app(PbrV2CapitalApprovalService::class);
    $result = $service->approveAndPublish($owner, $workspace, $proposal);
    $revision = $result['workflow']['canonical_revision'];

    expect(array_keys($result))->toBe(['readiness', 'workflow'])
        ->and($result['readiness'])->toBe($expected)
        ->and(array_keys($result['workflow']))->toBe([
            'decision', 'canonical_revision', 'work_artifact',
        ])
        ->and($revision->payload)->toBe($payload)
        ->and($revision->approval_decision)->toBe($result['workflow']['decision'])
        ->and((int) $revision->published_by_user_id)->toBe((int) $owner->id)
        ->and($revision->published_at)->not->toBeNull()
        ->and($revision->state)->toBe('approved_current')
        ->and($proposal->fresh()->consumed_at)->not->toBeNull()
        ->and((int) $proposal->fresh()->published_canonical_revision_id)->toBe((int) $revision->id)
        ->and($proposal->fresh()->payload)->toBe($payload)
        ->and(WorkspaceV2CanonicalRevision::query()->count())->toBe(1);

    $before = capitalApprovalSnapshot();
    capitalApprovalExpectValidation(
        fn () => $service->approveAndPublish($owner, $workspace, $proposal),
        'work_artifact'
    );
    expect(capitalApprovalSnapshot())->toBe($before);
});

test('capital approval permits active admin for another owners workspace', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = capitalApprovalProposal($owner, $workspace);
    $admin = User::factory()->create([
        'role' => 'admin', 'is_admin' => true, 'account_status' => 'active',
    ]);
    $result = app(PbrV2CapitalApprovalService::class)->approveAndPublish($admin, $workspace, $proposal);
    expect((int) $result['workflow']['canonical_revision']->published_by_user_id)
        ->toBe((int) $admin->id);
});

test('capital approval denies unauthorized actors before inspecting proposal', function (string $case) {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $actor = $owner;

    if ($case === 'non owner') {
        $actor = capitalApprovalOwner();
    } elseif ($case === 'expired owner') {
        $actor->update(['portal_access_expires_at' => now()->subDay()]);
    } elseif ($case === 'inactive admin') {
        $actor->update(['role' => 'admin', 'is_admin' => true, 'account_status' => 'inactive']);
    } else {
        User::query()->whereKey($actor->id)->update(['account_status' => 'inactive']);
        $actor->account_status = 'active';
        $actor->is_admin = true;
    }

    $before = capitalApprovalSnapshot();
    expect(fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish(
        $actor, $workspace, new WorkspaceV2CanonicalWorkArtifact()
    ))->toThrow(AuthorizationException::class);
    expect(capitalApprovalSnapshot())->toBe($before);
})->with(['non owner', 'expired owner', 'inactive admin', 'forged stale actor']);

test('capital approval rejects unpersisted context', function (string $kind) {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $before = capitalApprovalSnapshot();

    expect(fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish(
        $kind === 'actor' ? new User() : $owner,
        $kind === 'workspace' ? new PartnershipWorkspace() : $workspace,
        new WorkspaceV2CanonicalWorkArtifact()
    ))->toThrow(ModelNotFoundException::class);
    expect(capitalApprovalSnapshot())->toBe($before);
})->with(['actor', 'workspace']);

test('capital approval rejects foreign or missing proposal', function (string $kind) {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = $kind === 'foreign'
        ? capitalApprovalProposal($owner, capitalApprovalWorkspace($owner))
        : new WorkspaceV2CanonicalWorkArtifact();

    $before = capitalApprovalSnapshot();
    capitalApprovalExpectValidation(
        fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish($owner, $workspace, $proposal),
        'work_artifact'
    );
    expect(capitalApprovalSnapshot())->toBe($before);
})->with(['foreign', 'missing']);

test('capital approval restricts target and proposed state', function (string $kind) {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = capitalApprovalProposal(
        $owner, $workspace, null,
        $kind === 'target' ? 'capital_call_rule' : 'approved_capital_plan'
    );

    if ($kind === 'state') {
        $proposal = app(PbrV2CanonicalWorkService::class)->returnProposalToWorkingDraft($workspace, $proposal);
    }

    $before = capitalApprovalSnapshot();
    capitalApprovalExpectValidation(
        fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish($owner, $workspace, $proposal),
        $kind
    );
    expect(capitalApprovalSnapshot())->toBe($before);
})->with(['target', 'state']);

test('capital approval rejects invalid persisted readiness despite a repaired caller payload', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $payload = capitalApprovalPayload();
    $payload['cost_items'][0]['amount'] = '-1.00';
    $proposal = capitalApprovalProposal($owner, $workspace, $payload);
    $proposal->payload = capitalApprovalPayload();
    $before = capitalApprovalSnapshot();

    try {
        app(PbrV2CapitalApprovalService::class)->approveAndPublish($owner, $workspace, $proposal);
        throw new RuntimeException('Expected readiness rejection.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payload.cost_items.0.amount')
            ->and($exception->errors()['payload.cost_items.0.amount'][0])->toStartWith('INVALID_MONEY:');
    }

    expect(capitalApprovalSnapshot())->toBe($before);
});

test('capital approval checks current persisted workspace context', function (string $field, string $value) {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = capitalApprovalProposal($owner, $workspace);
    PartnershipWorkspace::query()->whereKey($workspace->id)->update([$field => $value]);
    $before = capitalApprovalSnapshot();

    capitalApprovalExpectValidation(
        fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish($owner, $workspace, $proposal),
        'payload.'.$field
    );
    expect(capitalApprovalSnapshot())->toBe($before);
})->with([
    ['business_stage', 'existing'],
    ['currency_code', 'USD'],
]);

test('capital approval reloads workspace ownership before authorization', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $proposal = capitalApprovalProposal($owner, $workspace);
    $replacement = capitalApprovalOwner();
    PartnershipWorkspace::query()->whereKey($workspace->id)->update(['owner_user_id' => $replacement->id]);
    $before = capitalApprovalSnapshot();

    expect(fn () => app(PbrV2CapitalApprovalService::class)->approveAndPublish(
        $owner, $workspace, $proposal
    ))->toThrow(AuthorizationException::class);
    expect(capitalApprovalSnapshot())->toBe($before);
});

test('capital approval returns warnings without inventing confirmed funding', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $payload = capitalApprovalPayload();
    $payload['funding_sources'] = [];
    $payload['shortfall_response'] = [
        'strategy' => 'delay',
        'owner_reference' => 'capital-owner',
        'target_date' => '2026-10-01',
        'summary' => 'Delay purchase until funding is confirmed.',
    ];
    $proposal = capitalApprovalProposal($owner, $workspace, $payload);
    $result = app(PbrV2CapitalApprovalService::class)->approveAndPublish($owner, $workspace, $proposal);

    expect($result['readiness']['passed'])->toBeTrue()
        ->and(array_column($result['readiness']['warnings'], 'code'))->toBe(['FUNDING_GAP'])
        ->and($result['readiness']['calculations']['confirmed_funding'])->toBe('0.00')
        ->and($result['readiness']['calculations']['funding_gap'])->toBe('100.00')
        ->and($result['workflow']['canonical_revision']->payload)->toBe($payload);
});

test('capital approval supersedes current revision through the frozen workflow', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $service = app(PbrV2CapitalApprovalService::class);
    $first = $service->approveAndPublish(
        $owner, $workspace, capitalApprovalProposal($owner, $workspace)
    )['workflow']['canonical_revision'];
    $second = $service->approveAndPublish(
        $owner, $workspace, capitalApprovalProposal($owner, $workspace)
    )['workflow']['canonical_revision'];

    expect($first->fresh()->state)->toBe('superseded')
        ->and($first->fresh()->superseded_at)->not->toBeNull()
        ->and($second->revision)->toBe(2)
        ->and(WorkspaceV2CanonicalRevision::query()->where('state', 'approved_current')->count())->toBe(1);
});

test('capital approval rolls back revision insertion and supersession on publication failure', function () {
    $owner = capitalApprovalOwner();
    $workspace = capitalApprovalWorkspace($owner);
    $service = app(PbrV2CapitalApprovalService::class);
    $service->approveAndPublish($owner, $workspace, capitalApprovalProposal($owner, $workspace));
    $proposal = capitalApprovalProposal($owner, $workspace);
    $before = capitalApprovalSnapshot();

    // Isolate the injected failure so existing model listeners survive this test.
    $dispatcher = WorkspaceV2CanonicalRevision::getEventDispatcher();
    WorkspaceV2CanonicalRevision::setEventDispatcher(clone $dispatcher);

    try {
        WorkspaceV2CanonicalRevision::created(function (): void {
            throw new RuntimeException('capital approval injected publication failure');
        });

        expect(fn () => $service->approveAndPublish($owner, $workspace, $proposal))
            ->toThrow(RuntimeException::class, 'capital approval injected publication failure');
    } finally {
        WorkspaceV2CanonicalRevision::setEventDispatcher($dispatcher);
    }

    expect(capitalApprovalSnapshot())->toBe($before)
        ->and($proposal->fresh()->consumed_at)->toBeNull()
        ->and($proposal->fresh()->published_canonical_revision_id)->toBeNull();
});
