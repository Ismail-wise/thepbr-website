<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2ActionCanonicalSource;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Models\WorkspaceV2OperatingRecordCanonicalSource;
use App\Services\PbrTools\PbrV2ComponentRegistryService;
use App\Services\PbrTools\PbrV2ExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function pbrV2ExecutionFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'V2 Execution Foundation',
        'business_name' => 'V2 Execution Foundation',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact('owner', 'workspace');
}

function pbrV2ExecutionRevision(
    PartnershipWorkspace $workspace,
    User $actor,
    string $domainKey,
    string $componentKey,
    string $state = 'approved_current'
): WorkspaceV2CanonicalRevision {
    $definition = app(
        PbrV2ComponentRegistryService::class
    )->find($componentKey);

    if (! is_array($definition)) {
        throw new RuntimeException(
            'Missing V2 component fixture: '.$componentKey
        );
    }

    $revision = (
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', $domainKey)
            ->where('component_key', $componentKey)
            ->max('revision')
        ?? 0
    ) + 1;

    return WorkspaceV2CanonicalRevision::create([
        'workspace_id' => $workspace->id,
        'domain_key' => $domainKey,
        'component_key' => $componentKey,
        'component_type' =>
            $definition['component_type'],
        'revision' => $revision,
        'state' => $state,
        'payload' => [
            'fixture' => $componentKey,
        ],
        'approval_decision' => [
            'decision' => 'approved',
            'publication_authorized' => true,
        ],
        'published_by_user_id' => $actor->id,
        'published_at' => now(),
        'superseded_at' =>
            $state === 'superseded'
                ? now()
                : null,
    ]);
}

test('f9 execution tables expose the frozen four table shape', function () {
    expect(
        Schema::hasColumns(
            'workspace_v2_actions',
            [
                'id',
                'workspace_id',
                'action_domain_key',
                'action_component_key',
                'producer_domain_key',
                'producer_component_key',
                'producer_canonical_revision_id',
                'producer_operating_record_id',
                'created_by_user_id',
                'title',
                'description',
                'owner_name',
                'priority',
                'status',
                'due_date',
                'completed_at',
                'metadata',
                'created_at',
                'updated_at',
            ]
        )
    )->toBeTrue();

    expect(
        Schema::hasColumns(
            'workspace_v2_operating_records',
            [
                'id',
                'workspace_id',
                'domain_key',
                'component_key',
                'recorded_by_user_id',
                'state',
                'title',
                'record_date',
                'effective_at',
                'payload',
                'metadata',
                'recorded_at',
                'created_at',
                'updated_at',
            ]
        )
    )->toBeTrue();

    expect(
        Schema::hasColumns(
            'workspace_v2_action_canonical_sources',
            [
                'id',
                'workspace_id',
                'action_id',
                'canonical_revision_id',
                'source_domain_key',
                'source_component_key',
                'source_revision',
                'pinned_at',
            ]
        )
    )->toBeTrue();

    expect(
        Schema::hasColumns(
            'workspace_v2_operating_record_canonical_sources',
            [
                'id',
                'workspace_id',
                'operating_record_id',
                'canonical_revision_id',
                'source_domain_key',
                'source_component_key',
                'source_revision',
                'pinned_at',
            ]
        )
    )->toBeTrue();
});

test('f9 exposes the exact frozen execution vocabulary', function () {
    $service = app(PbrV2ExecutionService::class);

    expect($service->actionStates())->toBe([
        'open',
        'in_progress',
        'blocked',
        'completed',
        'superseded',
    ])
        ->and($service->recordState())
        ->toBe('recorded')
        ->and($service->priorities())
        ->toBe([
            'low',
            'normal',
            'high',
            'critical',
        ]);
});

test('canonical producer creates same domain action with exact source pin', function () {
    extract(pbrV2ExecutionFixture());

    $revision = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $action = app(PbrV2ExecutionService::class)
        ->createAction(
            $owner,
            $workspace,
            'capital',
            'capital_call_rule',
            [$revision->id],
            [
                'title' => 'Issue approved capital call',
                'priority' => 'high',
                'due_date' => '2026-09-30',
                'metadata' => ['source' => 'foundation'],
            ],
            $revision->id
        );

    expect($action->action_domain_key)
        ->toBe('capital')
        ->and($action->action_component_key)
        ->toBe('capital_action_plan')
        ->and($action->producer_domain_key)
        ->toBe('capital')
        ->and($action->producer_component_key)
        ->toBe('capital_call_rule')
        ->and($action->producer_canonical_revision_id)
        ->toBe($revision->id)
        ->and($action->producer_operating_record_id)
        ->toBeNull()
        ->and($action->status)
        ->toBe('open')
        ->and($action->canonicalSources)
        ->toHaveCount(1)
        ->and(
            $action->canonicalSources->first()
                ->canonical_revision_id
        )
        ->toBe($revision->id);
});

test('canonical producer revision must be a member of source evidence', function () {
    extract(pbrV2ExecutionFixture());

    $producerRevision = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $otherRevision = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'approved_capital_plan'
    );

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->createAction(
                $owner,
                $workspace,
                'capital',
                'capital_call_rule',
                [$otherRevision->id],
                ['title' => 'Invalid provenance'],
                $producerRevision->id
            )
    )->toThrow(ValidationException::class);

    expect(WorkspaceV2Action::query()->count())
        ->toBe(0);
});

test('multi source evidence is relational and preserved independently', function () {
    extract(pbrV2ExecutionFixture());

    $implementation = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'implementation_review',
        'implementation_plan'
    );

    $capital = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $action = app(PbrV2ExecutionService::class)
        ->createAction(
            $owner,
            $workspace,
            'implementation_review',
            'implementation_plan',
            [
                $implementation->id,
                $capital->id,
            ],
            [
                'title' => 'Coordinate implementation',
            ],
            $implementation->id
        );

    expect($action->canonicalSources)
        ->toHaveCount(2)
        ->and(
            $action->canonicalSources
                ->pluck('canonical_revision_id')
                ->sort()
                ->values()
                ->all()
        )
        ->toBe(
            collect([
                $implementation->id,
                $capital->id,
            ])->sort()->values()->all()
        );
});

test('source evidence rejects stale cross workspace and unauthorized sources', function () {
    extract(pbrV2ExecutionFixture());

    $producerRevision = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $ownership = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'ownership',
        'new_share_issuance_rule'
    );

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->createAction(
                $owner,
                $workspace,
                'capital',
                'capital_call_rule',
                [
                    $producerRevision->id,
                    $ownership->id,
                ],
                ['title' => 'Cross domain source'],
                $producerRevision->id
            )
    )->toThrow(ValidationException::class);

    $stale = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'approved_capital_plan',
        'superseded'
    );

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->createAction(
                $owner,
                $workspace,
                'capital',
                'approved_capital_plan',
                [$stale->id],
                ['title' => 'Stale source'],
                $stale->id
            )
    )->toThrow(ValidationException::class);

    $otherOwner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $otherWorkspace = PartnershipWorkspace::create([
        'owner_user_id' => $otherOwner->id,
        'name' => 'Other Workspace',
        'business_name' => 'Other Workspace',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $foreign = pbrV2ExecutionRevision(
        $otherWorkspace,
        $otherOwner,
        'capital',
        'capital_call_rule'
    );

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->createAction(
                $owner,
                $workspace,
                'capital',
                'capital_call_rule',
                [$foreign->id],
                ['title' => 'Foreign source'],
                $foreign->id
            )
    )->toThrow(ValidationException::class);
});

test('record producer uses exact immutable operating record instance', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    $record = $service->appendRecord(
        $owner,
        $workspace,
        'capital',
        'capital_decision_record',
        [$source->id],
        [
            'decision' => 'Proceed with capital call',
        ],
        [
            'title' => 'Capital decision',
            'record_date' => '2026-09-05',
        ]
    );

    $action = $service->createAction(
        $owner,
        $workspace,
        'capital',
        'capital_decision_record',
        [$source->id],
        [
            'title' => 'Implement recorded capital decision',
        ],
        null,
        $record->id
    );

    expect($record->state)
        ->toBe('recorded')
        ->and($record->canonicalSources)
        ->toHaveCount(1)
        ->and($action->producer_canonical_revision_id)
        ->toBeNull()
        ->and($action->producer_operating_record_id)
        ->toBe($record->id)
        ->and($action->action_component_key)
        ->toBe('capital_action_plan');
});

test('monitor producer requires source evidence without synthetic instance', function () {
    extract(pbrV2ExecutionFixture());

    $capital = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $action = app(PbrV2ExecutionService::class)
        ->createAction(
            $owner,
            $workspace,
            'financial_controls',
            'cashflow_dashboard',
            [$capital->id],
            [
                'title' => 'Investigate cashflow warning',
            ]
        );

    expect($action->action_domain_key)
        ->toBe('financial_controls')
        ->and($action->action_component_key)
        ->toBe('financial_control_action_plan')
        ->and($action->producer_canonical_revision_id)
        ->toBeNull()
        ->and($action->producer_operating_record_id)
        ->toBeNull();
});

test('action transitions enforce the exact frozen f5 matrix', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    $action = $service->createAction(
        $owner,
        $workspace,
        'capital',
        'capital_call_rule',
        [$source->id],
        ['title' => 'Lifecycle action'],
        $source->id
    );

    $action = $service->transitionAction(
        $owner,
        $workspace,
        $action,
        'in_progress'
    );

    expect($action->status)->toBe('in_progress');

    $action = $service->transitionAction(
        $owner,
        $workspace,
        $action,
        'blocked'
    );

    expect($action->status)->toBe('blocked');

    $action = $service->transitionAction(
        $owner,
        $workspace,
        $action,
        'completed'
    );

    expect($action->status)
        ->toBe('completed')
        ->and($action->completed_at)
        ->not->toBeNull();

    expect(
        fn () => $service->transitionAction(
            $owner,
            $workspace,
            $action,
            'in_progress'
        )
    )->toThrow(ValidationException::class);
});

test('superseded action is terminal', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    $action = $service->createAction(
        $owner,
        $workspace,
        'capital',
        'capital_call_rule',
        [$source->id],
        ['title' => 'Supersede this action'],
        $source->id
    );

    $action = $service->transitionAction(
        $owner,
        $workspace,
        $action,
        'superseded'
    );

    expect($action->status)
        ->toBe('superseded')
        ->and($action->completed_at)
        ->not->toBeNull();

    expect(
        fn () => $service->transitionAction(
            $owner,
            $workspace,
            $action,
            'completed'
        )
    )->toThrow(ValidationException::class);
});

test('action identity and provenance are immutable and deletion is forbidden', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $action = app(PbrV2ExecutionService::class)
        ->createAction(
            $owner,
            $workspace,
            'capital',
            'capital_call_rule',
            [$source->id],
            ['title' => 'Immutable action'],
            $source->id
        );

    $action->title = 'Changed';

    expect(
        fn () => $action->save()
    )->toThrow(LogicException::class);

    $action->refresh();

    expect(
        fn () => $action->delete()
    )->toThrow(LogicException::class);
});

test('operating records and source rows are append only immutable evidence', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    $record = $service->appendRecord(
        $owner,
        $workspace,
        'capital',
        'capital_decision_record',
        [$source->id],
        ['decision' => 'Recorded'],
        ['title' => 'Immutable record']
    );

    $recordSource = $record->canonicalSources->first();

    $record->title = 'Changed';

    expect(
        fn () => $record->save()
    )->toThrow(LogicException::class);

    $record->refresh();

    expect(
        fn () => $record->delete()
    )->toThrow(LogicException::class);

    $recordSource->source_revision = 999;

    expect(
        fn () => $recordSource->save()
    )->toThrow(LogicException::class);

    $recordSource->refresh();

    expect(
        fn () => $recordSource->delete()
    )->toThrow(LogicException::class);
});

test('action source rows are immutable and cannot be deleted', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $action = app(PbrV2ExecutionService::class)
        ->createAction(
            $owner,
            $workspace,
            'capital',
            'capital_call_rule',
            [$source->id],
            ['title' => 'Pinned source action'],
            $source->id
        );

    $pin = $action->canonicalSources->first();

    $pin->source_revision = 999;

    expect(
        fn () => $pin->save()
    )->toThrow(LogicException::class);

    $pin->refresh();

    expect(
        fn () => $pin->delete()
    )->toThrow(LogicException::class);
});

test('pinned source evidence survives later canonical supersession', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    $action = $service->createAction(
        $owner,
        $workspace,
        'capital',
        'capital_call_rule',
        [$source->id],
        ['title' => 'Historical pin'],
        $source->id
    );

    $source->state = 'superseded';
    $source->superseded_at = now();
    $source->save();

    $pins = $service->actionSources(
        $workspace,
        $action
    );

    expect($pins)
        ->toHaveCount(1)
        ->and($pins->first()->canonical_revision_id)
        ->toBe($source->id)
        ->and($pins->first()->source_revision)
        ->toBe($source->revision);
});

test('non action producer and non record component fail closed', function () {
    extract(pbrV2ExecutionFixture());

    $source = pbrV2ExecutionRevision(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule'
    );

    $service = app(PbrV2ExecutionService::class);

    expect(
        fn () => $service->createAction(
            $owner,
            $workspace,
            'capital',
            'capital_action_plan',
            [$source->id],
            ['title' => 'Recursive action']
        )
    )->toThrow(ValidationException::class);

    expect(
        fn () => $service->appendRecord(
            $owner,
            $workspace,
            'capital',
            'capital_call_rule',
            [$source->id],
            ['invalid' => true]
        )
    )->toThrow(ValidationException::class);
});

test('empty source sets fail atomically', function () {
    extract(pbrV2ExecutionFixture());

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->createAction(
                $owner,
                $workspace,
                'financial_controls',
                'cashflow_dashboard',
                [],
                ['title' => 'No sources']
            )
    )->toThrow(ValidationException::class);

    expect(WorkspaceV2Action::query()->count())
        ->toBe(0);

    expect(
        fn () => app(PbrV2ExecutionService::class)
            ->appendRecord(
                $owner,
                $workspace,
                'capital',
                'capital_decision_record',
                [],
                ['invalid' => true]
            )
    )->toThrow(ValidationException::class);

    expect(
        WorkspaceV2OperatingRecord::query()->count()
    )->toBe(0);
});

test('f9 service exposes only the frozen public execution surface', function () {
    $reflection = new ReflectionClass(
        PbrV2ExecutionService::class
    );

    $actual = collect(
        $reflection->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
    )
        ->filter(
            fn (ReflectionMethod $method): bool =>
                $method->getDeclaringClass()->getName()
                    === $reflection->getName()
                && $method->getName() !== '__construct'
        )
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $expected = [
        'actionSources',
        'actionStates',
        'appendRecord',
        'createAction',
        'priorities',
        'recordSources',
        'recordState',
        'transitionAction',
    ];

    sort($expected);

    expect($actual)->toBe($expected);

    foreach (
        config(
            'pbr_v2_execution.forbidden_service_methods',
            []
        )
        as $method
    ) {
        expect($reflection->hasMethod($method))
            ->toBeFalse();
    }
});

test('f9 service contains no legacy publication or execution identity leakage', function () {
    $source = file_get_contents(
        app_path(
            'Services/PbrTools/PbrV2ExecutionService.php'
        )
    );

    foreach ([
        'ChapterTool',
        'ToolSession',
        'WorkspaceToolOutput',
        'WorkspaceToolAction',
        'WorkspaceOperatingRecord',
        'publishAgreedOutput',
        'activateFromApprovedSession',
        'chapter_number',
        'chapter_tool_id',
        'source_tool_session_id',
        'workspace_tool_output_id',
    ] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }
});

test('f9 preserves exact producer action and record counts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $all = collect($registry->all());

    expect(
        $all->filter(
            fn (array $component): bool =>
                ($component['can_create_actions'] ?? false)
                === true
        )->count()
    )->toBe(53)
        ->and(
            $all->filter(
                fn (array $component): bool =>
                    ($component['component_type'] ?? null)
                    === 'action'
            )->count()
        )
        ->toBe(13)
        ->and(
            $all->filter(
                fn (array $component): bool =>
                    ($component['component_type'] ?? null)
                    === 'record'
            )->count()
        )
        ->toBe(13);
});
