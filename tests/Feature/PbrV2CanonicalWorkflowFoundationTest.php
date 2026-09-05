<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2CanonicalWorkSource;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkflowService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function f11Workspace(
    string $name = 'F11 Canonical Workflow'
): array {
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return [$owner, $workspace];
}

function f11Publish(
    PartnershipWorkspace $workspace,
    User $actor,
    string $domainKey,
    string $componentKey,
    array $payload = ['fixture' => true]
): WorkspaceV2CanonicalRevision {
    $decision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        $domainKey,
        $componentKey,
        'proposed',
        true,
        true
    );

    return app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        $domainKey,
        $componentKey,
        $payload,
        $decision,
        $actor
    );
}

function f11RootDraft(
    PartnershipWorkspace $workspace,
    ?User $actor = null,
    array $payload = ['rule' => 'draft']
): WorkspaceV2CanonicalWorkArtifact {
    return app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'capital',
        'capital_call_rule',
        'capital',
        'startup_capital_planner',
        $payload,
        [],
        $actor
    );
}

function f11RootProposal(
    PartnershipWorkspace $workspace,
    ?User $actor = null,
    array $payload = ['rule' => 'proposal']
): WorkspaceV2CanonicalWorkArtifact {
    $draft = f11RootDraft(
        $workspace,
        $actor,
        $payload
    );

    return app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $draft,
        $actor
    );
}

function f11SourceDraft(
    PartnershipWorkspace $workspace,
    User $actor,
    array $payload = ['shares' => ['partner' => 100]]
): array {
    $source = f11Publish(
        $workspace,
        $actor,
        'contribution',
        'accepted_contribution_register',
        ['register' => 'accepted']
    );

    $draft = app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'ownership',
        'final_share_register',
        'ownership',
        'share_allocation_analysis',
        $payload,
        [$source->id],
        $actor
    );

    return [$draft, $source];
}

test('f11 has the exact stateless two file architecture boundary', function () {
    expect(
        file_exists(
            app_path(
                'Services/PbrTools/PbrV2CanonicalWorkflowService.php'
            )
        )
    )->toBeTrue()
        ->and(
            file_exists(
                base_path(
                    'tests/Feature/PbrV2CanonicalWorkflowFoundationTest.php'
                )
            )
        )->toBeTrue()
        ->and(
            file_exists(
                config_path(
                    'pbr_v2_canonical_workflow.php'
                )
            )
        )->toBeFalse()
        ->and(
            file_exists(
                app_path(
                    'Models/WorkspaceV2CanonicalWorkflow.php'
                )
            )
        )->toBeFalse()
        ->and(
            glob(
                database_path(
                    'migrations/*canonical_workflow*'
                )
            )
        )->toBe([]);

    $source = file_get_contents(
        app_path(
            'Services/PbrTools/PbrV2CanonicalWorkflowService.php'
        )
    );

    expect($source)
        ->not->toContain('Schema::create')
        ->not->toContain('DB::table(')
        ->not->toContain('::create(')
        ->not->toContain('PbrV2ReviewAuditService')
        ->not->toContain('PbrV2ExecutionService')
        ->not->toContain('ToolSession')
        ->not->toContain('WorkspaceToolOutput');
});

test('f11 exposes the exact frozen public surface and constructor dependencies', function () {
    $class = new \ReflectionClass(
        PbrV2CanonicalWorkflowService::class
    );

    $constructor = $class->getConstructor();

    $constructorParameters = array_map(
        static fn (\ReflectionParameter $parameter): array => [
            'name' => $parameter->getName(),
            'type' => (string) $parameter->getType(),
        ],
        $constructor?->getParameters() ?? []
    );

    expect($constructorParameters)->toBe([
        [
            'name' => 'work',
            'type' => PbrV2CanonicalWorkService::class,
        ],
        [
            'name' => 'approval',
            'type' => PbrV2ApprovalEngineService::class,
        ],
        [
            'name' => 'canonical',
            'type' => PbrV2CanonicalOutputService::class,
        ],
    ]);

    $public = [];

    foreach (
        $class->getMethods(
            \ReflectionMethod::IS_PUBLIC
        ) as $method
    ) {
        if (
            $method->getDeclaringClass()->getName()
            !== $class->getName()
            || $method->getName() === '__construct'
        ) {
            continue;
        }

        $public[$method->getName()] = [
            'parameters' => array_map(
                static fn (\ReflectionParameter $parameter): string =>
                    $parameter->getName(),
                $method->getParameters()
            ),
            'return' => (string) $method->getReturnType(),
        ];
    }

    expect($public)->toBe([
        'approveAndPublish' => [
            'parameters' => [
                'workspace',
                'artifact',
                'readinessPassed',
                'actor',
            ],
            'return' => 'array',
        ],
        'rejectAndReturnToWorkingDraft' => [
            'parameters' => [
                'workspace',
                'artifact',
                'reason',
            ],
            'return' => 'array',
        ],
    ]);

    $private = collect(
        $class->getMethods(
            \ReflectionMethod::IS_PRIVATE
        )
    )
        ->filter(
            fn (\ReflectionMethod $method): bool =>
                $method->getDeclaringClass()->getName()
                === $class->getName()
        )
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    $expectedPrivate = [
        'assertApprovedDecision',
        'assertConsumedArtifact',
        'assertPublishedRevision',
        'assertRejectedDecision',
        'assertReturnedWorkingDraft',
        'currentSourcesFresh',
        'lockedProposal',
        'validationError',
    ];

    sort($expectedPrivate);

    expect($private)->toBe($expectedPrivate);
});

test('f11 approval reloads the locked database proposal and publishes persisted payload', function () {
    [$owner, $workspace] = f11Workspace();

    $staleCaller = f11RootDraft(
        $workspace,
        $owner,
        ['rule' => 'caller-old']
    );

    $updated = app(
        PbrV2CanonicalWorkService::class
    )->updateWorkingDraftPayload(
        $workspace,
        $staleCaller,
        ['rule' => 'persisted-new']
    );

    app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $updated,
        $owner
    );

    $result = app(
        PbrV2CanonicalWorkflowService::class
    )->approveAndPublish(
        $workspace,
        $staleCaller,
        true,
        $owner
    );

    expect(array_keys($result))->toBe([
        'decision',
        'canonical_revision',
        'work_artifact',
    ]);

    expect($result['decision']['decision'])
        ->toBe('approved')
        ->and($result['decision']['sources_fresh'])
        ->toBeTrue()
        ->and($result['canonical_revision']->payload)
        ->toBe(['rule' => 'persisted-new'])
        ->and(
            $result['canonical_revision']
                ->approval_decision
        )
        ->toBe($result['decision'])
        ->and(
            $result['canonical_revision']
                ->published_by_user_id
        )
        ->toBe($owner->id)
        ->and($result['work_artifact']->id)
        ->toBe($staleCaller->id)
        ->and(
            $result['work_artifact']
                ->published_canonical_revision_id
        )
        ->toBe($result['canonical_revision']->id)
        ->and($result['work_artifact']->consumed_at)
        ->not->toBeNull();

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', 'capital')
            ->where('component_key', 'capital_call_rule')
            ->where('state', 'approved_current')
            ->count()
    )->toBe(1);
});

test('f11 rejects a proposal supplied with the wrong workspace', function () {
    [$owner, $workspace] = f11Workspace(
        'F11 Workspace A'
    );

    [, $otherWorkspace] = f11Workspace(
        'F11 Workspace B'
    );

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $otherWorkspace,
            $proposal,
            true,
            $owner
        )
    )->toThrow(
        ValidationException::class
    );
});

test('f11 rejects working draft as approval input', function () {
    [$owner, $workspace] = f11Workspace();

    $draft = f11RootDraft(
        $workspace,
        $owner
    );

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $draft,
            true,
            $owner
        )
    )->toThrow(
        ValidationException::class
    );
});

test('f11 rejects already consumed proposal', function () {
    [$owner, $workspace] = f11Workspace();

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    app(
        PbrV2CanonicalWorkflowService::class
    )->approveAndPublish(
        $workspace,
        $proposal,
        true,
        $owner
    );

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            true,
            $owner
        )
    )->toThrow(
        ValidationException::class
    );
});

test('f11 treats empty current source generation as fresh', function () {
    [$owner, $workspace] = f11Workspace();

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    $result = app(
        PbrV2CanonicalWorkflowService::class
    )->approveAndPublish(
        $workspace,
        $proposal,
        true,
        $owner
    );

    expect($result['decision']['sources_fresh'])
        ->toBeTrue()
        ->and($result['decision']['readiness_passed'])
        ->toBeTrue();
});

test('f11 evaluates only the exact current source generation', function () {
    [$owner, $workspace] = f11Workspace();

    [$draft, $oldSource] = f11SourceDraft(
        $workspace,
        $owner
    );

    $newSource = f11Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register',
        ['register' => 'new-current']
    );

    expect($oldSource->fresh()->state)
        ->toBe('superseded')
        ->and($newSource->state)
        ->toBe('approved_current');

    $refreshed = app(
        PbrV2CanonicalWorkService::class
    )->refreshWorkingDraftSources(
        $workspace,
        $draft,
        [$newSource->id]
    );

    expect($refreshed->source_generation)
        ->toBe(2);

    $proposal = app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $refreshed,
        $owner
    );

    $result = app(
        PbrV2CanonicalWorkflowService::class
    )->approveAndPublish(
        $workspace,
        $proposal,
        true,
        $owner
    );

    expect($result['decision']['sources_fresh'])
        ->toBeTrue();
});

test('f11 derives stale when current generation source becomes superseded', function () {
    [$owner, $workspace] = f11Workspace();

    [$draft, $source] = f11SourceDraft(
        $workspace,
        $owner
    );

    $proposal = app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $draft,
        $owner
    );

    f11Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register',
        ['register' => 'superseding']
    );

    expect($source->fresh()->state)
        ->toBe('superseded');

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            true,
            $owner
        )
    )->toThrow(
        \DomainException::class,
        'Proposal sources are stale.'
    );

    expect($proposal->fresh()->state)
        ->toBe('proposed')
        ->and($proposal->fresh()->consumed_at)
        ->toBeNull();
});

test('f11 derives stale when current source metadata mismatches revision', function () {
    [$owner, $workspace] = f11Workspace();

    [$draft] = f11SourceDraft(
        $workspace,
        $owner
    );

    $proposal = app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $draft,
        $owner
    );

    $sourceRow = WorkspaceV2CanonicalWorkSource::query()
        ->where(
            'work_artifact_id',
            $proposal->id
        )
        ->where(
            'source_generation',
            $proposal->source_generation
        )
        ->firstOrFail();

    DB::table(
        'workspace_v2_canonical_work_sources'
    )
        ->where('id', $sourceRow->id)
        ->update([
            'source_revision' =>
                ((int) $sourceRow->source_revision) + 100,
        ]);

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            true,
            $owner
        )
    )->toThrow(
        \DomainException::class,
        'Proposal sources are stale.'
    );
});

test('f11 loads wrong workspace source row and classifies it stale', function () {
    [$owner, $workspace] = f11Workspace(
        'F11 Source Workspace'
    );

    [, $otherWorkspace] = f11Workspace(
        'F11 Foreign Source Workspace'
    );

    [$draft] = f11SourceDraft(
        $workspace,
        $owner
    );

    $proposal = app(
        PbrV2CanonicalWorkService::class
    )->submitProposal(
        $workspace,
        $draft,
        $owner
    );

    $sourceRow = WorkspaceV2CanonicalWorkSource::query()
        ->where(
            'work_artifact_id',
            $proposal->id
        )
        ->where(
            'source_generation',
            $proposal->source_generation
        )
        ->firstOrFail();

    DB::table(
        'workspace_v2_canonical_work_sources'
    )
        ->where('id', $sourceRow->id)
        ->update([
            'workspace_id' => $otherWorkspace->id,
        ]);

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            true,
            $owner
        )
    )->toThrow(
        \DomainException::class,
        'Proposal sources are stale.'
    );

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', 'ownership')
            ->where('component_key', 'final_share_register')
            ->count()
    )->toBe(0);
});

test('f11 delegates readiness false to f6 without publishing or consuming', function () {
    [$owner, $workspace] = f11Workspace();

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            false,
            $owner
        )
    )->toThrow(
        \DomainException::class,
        'Proposal is not approval ready.'
    );

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', 'capital')
            ->where('component_key', 'capital_call_rule')
            ->count()
    )->toBe(0);

    $fresh = $proposal->fresh();

    expect($fresh->state)
        ->toBe('proposed')
        ->and($fresh->published_canonical_revision_id)
        ->toBeNull()
        ->and($fresh->consumed_at)
        ->toBeNull();
});

test('f11 rejection returns the same proposal row to working draft without canonical publication', function () {
    [$owner, $workspace] = f11Workspace();

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    expect($proposal->submitted_by_user_id)
        ->toBe($owner->id)
        ->and($proposal->submitted_at)
        ->not->toBeNull();

    $result = app(
        PbrV2CanonicalWorkflowService::class
    )->rejectAndReturnToWorkingDraft(
        $workspace,
        $proposal,
        '  Needs revision  '
    );

    expect(array_keys($result))->toBe([
        'decision',
        'work_artifact',
    ]);

    expect($result['decision']['decision'])
        ->toBe('rejected')
        ->and($result['decision']['reason'])
        ->toBe('Needs revision')
        ->and($result['decision']['revision_path'])
        ->toBe('working_draft')
        ->and($result['work_artifact']->id)
        ->toBe($proposal->id)
        ->and($result['work_artifact']->state)
        ->toBe('working_draft')
        ->and(
            $result['work_artifact']
                ->submitted_by_user_id
        )
        ->toBeNull()
        ->and($result['work_artifact']->submitted_at)
        ->toBeNull()
        ->and(
            $result['work_artifact']
                ->published_canonical_revision_id
        )
        ->toBeNull()
        ->and($result['work_artifact']->consumed_at)
        ->toBeNull();

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', 'capital')
            ->where('component_key', 'capital_call_rule')
            ->count()
    )->toBe(0);
});

test('f11 preserves f6 blank rejection reason exception behavior', function () {
    [$owner, $workspace] = f11Workspace();

    $proposal = f11RootProposal(
        $workspace,
        $owner
    );

    expect(
        fn () => app(
            PbrV2CanonicalWorkflowService::class
        )->rejectAndReturnToWorkingDraft(
            $workspace,
            $proposal,
            '   '
        )
    )->toThrow(
        \InvalidArgumentException::class,
        'Rejection reason is required.'
    );

    expect($proposal->fresh()->state)
        ->toBe('proposed')
        ->and($proposal->fresh()->submitted_at)
        ->not->toBeNull();
});

test('f11 outer transaction rolls back f7 publication when f10 consumption fails', function () {
    [$owner, $workspace] = f11Workspace();

    $existing = f11Publish(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule',
        ['rule' => 'existing-current']
    );

    $proposal = f11RootProposal(
        $workspace,
        $owner,
        ['rule' => 'attempted-new']
    );

    DB::unprepared(
        <<<'SQL'
CREATE TRIGGER f11_force_consumption_failure
BEFORE UPDATE OF published_canonical_revision_id, consumed_at
ON workspace_v2_canonical_work_artifacts
WHEN NEW.published_canonical_revision_id IS NOT NULL
     OR NEW.consumed_at IS NOT NULL
BEGIN
    SELECT RAISE(ABORT, 'forced consumption failure');
END;
SQL
    );

    $thrown = null;

    try {
        app(
            PbrV2CanonicalWorkflowService::class
        )->approveAndPublish(
            $workspace,
            $proposal,
            true,
            $owner
        );
    } catch (\Throwable $exception) {
        $thrown = $exception;
    } finally {
        DB::unprepared(
            'DROP TRIGGER IF EXISTS '
            .'f11_force_consumption_failure'
        );
    }

    expect($thrown)->not->toBeNull();

    $history = WorkspaceV2CanonicalRevision::query()
        ->where('workspace_id', $workspace->id)
        ->where('domain_key', 'capital')
        ->where('component_key', 'capital_call_rule')
        ->orderBy('revision')
        ->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->id)
        ->toBe($existing->id)
        ->and($history->first()->state)
        ->toBe('approved_current')
        ->and($history->first()->payload)
        ->toBe(['rule' => 'existing-current']);

    $freshProposal = $proposal->fresh();

    expect($freshProposal->state)
        ->toBe('proposed')
        ->and(
            $freshProposal
                ->published_canonical_revision_id
        )
        ->toBeNull()
        ->and($freshProposal->consumed_at)
        ->toBeNull();
});

test('f11 preserves frozen f6 f7 and f10 public signatures', function () {
    $checks = [
        [
            PbrV2ApprovalEngineService::class,
            'approve',
            [
                'domainKey',
                'componentKey',
                'proposalState',
                'readinessPassed',
                'sourcesFresh',
            ],
        ],
        [
            PbrV2ApprovalEngineService::class,
            'reject',
            [
                'domainKey',
                'componentKey',
                'proposalState',
                'reason',
            ],
        ],
        [
            PbrV2CanonicalOutputService::class,
            'publish',
            [
                'workspace',
                'domainKey',
                'componentKey',
                'payload',
                'approvalDecision',
                'actor',
            ],
        ],
        [
            PbrV2CanonicalWorkService::class,
            'recordPublicationConsumption',
            [
                'workspace',
                'artifact',
                'revision',
            ],
        ],
        [
            PbrV2CanonicalWorkService::class,
            'returnProposalToWorkingDraft',
            [
                'workspace',
                'artifact',
            ],
        ],
    ];

    foreach ($checks as [$class, $method, $expected]) {
        $reflection = new \ReflectionMethod(
            $class,
            $method
        );

        $actual = array_map(
            static fn (\ReflectionParameter $parameter): string =>
                $parameter->getName(),
            $reflection->getParameters()
        );

        expect($actual)->toBe($expected);
    }
});
