<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Models\WorkspaceV2CanonicalWorkSource;
use App\Models\WorkspaceV2ReviewCase;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2ComponentRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function f10Workspace(
    string $name = 'F10 Canonical Work'
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

function f10Publish(
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

function f10RootDraft(
    PartnershipWorkspace $workspace,
    ?User $actor = null
): WorkspaceV2CanonicalWorkArtifact {
    return app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'capital',
        'capital_call_rule',
        'capital',
        'startup_capital_planner',
        ['rule' => 'draft'],
        [],
        $actor
    );
}

test('f10 exposes the exact two table schema', function () {
    expect(
        Schema::hasTable(
            'workspace_v2_canonical_work_artifacts'
        )
    )->toBeTrue()
        ->and(
            Schema::hasTable(
                'workspace_v2_canonical_work_sources'
            )
        )->toBeTrue();

    $artifactColumns = Schema::getColumnListing(
        'workspace_v2_canonical_work_artifacts'
    );

    $sourceColumns = Schema::getColumnListing(
        'workspace_v2_canonical_work_sources'
    );

    sort($artifactColumns);
    sort($sourceColumns);

    $expectedArtifact = [
        'id',
        'workspace_id',
        'target_domain_key',
        'target_component_key',
        'producer_domain_key',
        'producer_component_key',
        'state',
        'payload',
        'source_generation',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'amendment_review_case_id',
        'amendment_target_canonical_revision_id',
        'published_canonical_revision_id',
        'consumed_at',
        'created_at',
        'updated_at',
    ];

    $expectedSource = [
        'id',
        'workspace_id',
        'work_artifact_id',
        'source_generation',
        'canonical_revision_id',
        'source_domain_key',
        'source_component_key',
        'source_revision',
        'pinned_at',
    ];

    sort($expectedArtifact);
    sort($expectedSource);

    expect($artifactColumns)
        ->toBe($expectedArtifact)
        ->and($sourceColumns)
        ->toBe($expectedSource);
});

test('f10 exposes only working draft and proposed states', function () {
    expect(
        app(PbrV2CanonicalWorkService::class)
            ->states()
    )->toBe([
        'working_draft',
        'proposed',
    ]);
});

test('ordinary root working draft preserves separate producer target and artifact identities', function () {
    [$owner, $workspace] = f10Workspace();

    $artifact = f10RootDraft(
        $workspace,
        $owner
    );

    expect($artifact->state)
        ->toBe('working_draft')
        ->and($artifact->source_generation)
        ->toBe(1)
        ->and($artifact->target_domain_key)
        ->toBe('capital')
        ->and($artifact->target_component_key)
        ->toBe('capital_call_rule')
        ->and($artifact->producer_domain_key)
        ->toBe('capital')
        ->and($artifact->producer_component_key)
        ->toBe('startup_capital_planner')
        ->and($artifact->created_by_user_id)
        ->toBe($owner->id)
        ->and($artifact->published_canonical_revision_id)
        ->toBeNull()
        ->and($artifact->consumed_at)
        ->toBeNull();

    expect(
        app(PbrV2CanonicalWorkService::class)
            ->sources($workspace, $artifact)
    )->toHaveCount(0);
});

test('multiple independent work artifacts may target the same canonical identity', function () {
    [, $workspace] = f10Workspace();

    $one = f10RootDraft($workspace);
    $two = f10RootDraft($workspace);

    expect($one->id)
        ->not->toBe($two->id)
        ->and($one->target_component_key)
        ->toBe($two->target_component_key);
});

test('producer and target contracts fail closed', function () {
    [, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    expect(fn () => $service->createWorkingDraft(
        $workspace,
        'financial_controls',
        'payment_approval_rule',
        'financial_controls',
        'cashflow_dashboard',
        []
    ))->toThrow(ValidationException::class);

    expect(fn () => $service->createWorkingDraft(
        $workspace,
        'capital',
        'startup_capital_planner',
        'capital',
        'startup_capital_planner',
        []
    ))->toThrow(ValidationException::class);

    expect(fn () => $service->createWorkingDraft(
        $workspace,
        'ownership',
        'final_share_register',
        'capital',
        'startup_capital_planner',
        []
    ))->toThrow(ValidationException::class);

    expect(fn () => $service->createWorkingDraft(
        $workspace,
        'capital',
        'missing_component',
        'capital',
        'startup_capital_planner',
        []
    ))->toThrow(ValidationException::class);
});

test('root canonical targets accept empty evidence and reject supplied upstream evidence', function () {
    [$owner, $workspace] = f10Workspace();

    $source = f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register'
    );

    expect(
        f10RootDraft($workspace)
    )->toBeInstanceOf(
        WorkspaceV2CanonicalWorkArtifact::class
    );

    expect(fn () => app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'capital',
        'capital_call_rule',
        'capital',
        'startup_capital_planner',
        [],
        [$source->id]
    ))->toThrow(ValidationException::class);
});

test('canonical source validation rejects unauthorized cross workspace stale and duplicate evidence', function () {
    [$owner, $workspace] = f10Workspace();
    [$otherOwner, $otherWorkspace] =
        f10Workspace('F10 Other');

    $allowed = f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register'
    );

    $outside = f10Publish(
        $workspace,
        $owner,
        'capital',
        'approved_capital_plan'
    );

    $foreign = f10Publish(
        $otherWorkspace,
        $otherOwner,
        'contribution',
        'accepted_contribution_register'
    );

    $stale = f10Publish(
        $workspace,
        $owner,
        'governance',
        'decision_rights_register'
    );

    f10Publish(
        $workspace,
        $owner,
        'governance',
        'decision_rights_register',
        ['fixture' => 'new-current']
    );

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $create = fn (array $sources) =>
        $service->createWorkingDraft(
            $workspace,
            'ownership',
            'final_share_register',
            'ownership',
            'share_allocation_analysis',
            [],
            $sources
        );

    expect(fn () => $create([
        $outside->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $create([
        $foreign->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $service->createWorkingDraft(
        $workspace,
        'financial_controls',
        'payment_approval_rule',
        'financial_controls',
        'financial_position_analysis',
        [],
        [$stale->id]
    ))->toThrow(ValidationException::class);

    expect(fn () => $create([
        $allowed->id,
        $allowed->id,
    ]))->toThrow(ValidationException::class);
});

test('working draft source refresh appends immutable generations without deleting history', function () {
    [$owner, $workspace] = f10Workspace();

    $source = f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register'
    );

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = $service->createWorkingDraft(
        $workspace,
        'ownership',
        'final_share_register',
        'ownership',
        'share_allocation_analysis',
        [],
        [$source->id]
    );

    expect($artifact->source_generation)
        ->toBe(1)
        ->and(
            $service->sources(
                $workspace,
                $artifact,
                1
            )
        )->toHaveCount(1);

    $artifact =
        $service->refreshWorkingDraftSources(
            $workspace,
            $artifact,
            [$source->id]
        );

    expect($artifact->source_generation)
        ->toBe(2)
        ->and(
            $service->sources(
                $workspace,
                $artifact,
                1
            )
        )->toHaveCount(1)
        ->and(
            $service->sources(
                $workspace,
                $artifact,
                2
            )
        )->toHaveCount(1);

    expect(
        WorkspaceV2CanonicalWorkSource::query()
            ->where(
                'work_artifact_id',
                $artifact->id
            )
            ->count()
    )->toBe(2);
});

test('working draft payload is service mutable while direct model mutation is forbidden', function () {
    [, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = f10RootDraft($workspace);

    $artifact =
        $service->updateWorkingDraftPayload(
            $workspace,
            $artifact,
            ['rule' => 'updated']
        );

    expect($artifact->payload)
        ->toBe(['rule' => 'updated']);

    $artifact->payload = ['rule' => 'direct'];

    expect(fn () => $artifact->save())
        ->toThrow(LogicException::class);
});

test('proposal submission uses f5 freezes payload and source refresh and records submission evidence', function () {
    [$owner, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = f10RootDraft($workspace);

    $artifact = $service->submitProposal(
        $workspace,
        $artifact,
        $owner
    );

    expect($artifact->state)
        ->toBe('proposed')
        ->and($artifact->submitted_by_user_id)
        ->toBe($owner->id)
        ->and($artifact->submitted_at)
        ->not->toBeNull();

    expect(fn () =>
        $service->updateWorkingDraftPayload(
            $workspace,
            $artifact,
            ['rule' => 'illegal']
        )
    )->toThrow(ValidationException::class);

    expect(fn () =>
        $service->refreshWorkingDraftSources(
            $workspace,
            $artifact,
            []
        )
    )->toThrow(ValidationException::class);
});

test('proposal submission rechecks current generation freshness', function () {
    [$owner, $workspace] = f10Workspace();

    $source = f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register',
        ['version' => 1]
    );

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = $service->createWorkingDraft(
        $workspace,
        'ownership',
        'final_share_register',
        'ownership',
        'share_allocation_analysis',
        [],
        [$source->id]
    );

    f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register',
        ['version' => 2]
    );

    expect(fn () =>
        $service->submitProposal(
            $workspace,
            $artifact,
            $owner
        )
    )->toThrow(ValidationException::class);

    expect(
        $artifact->fresh()->state
    )->toBe('working_draft');
});

test('proposed artifact returns to the same working draft row and clears submission metadata', function () {
    [$owner, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = f10RootDraft(
        $workspace,
        $owner
    );

    $id = $artifact->id;
    $payload = $artifact->payload;
    $generation = $artifact->source_generation;

    $artifact = $service->submitProposal(
        $workspace,
        $artifact,
        $owner
    );

    $artifact =
        $service->returnProposalToWorkingDraft(
            $workspace,
            $artifact
        );

    expect($artifact->id)
        ->toBe($id)
        ->and($artifact->state)
        ->toBe('working_draft')
        ->and($artifact->payload)
        ->toBe($payload)
        ->and($artifact->source_generation)
        ->toBe($generation)
        ->and($artifact->submitted_by_user_id)
        ->toBeNull()
        ->and($artifact->submitted_at)
        ->toBeNull();
});

test('amendment creates a separate working artifact with exact f8 and f7 lineage', function () {
    [$owner, $workspace] = f10Workspace();

    $target = f10Publish(
        $workspace,
        $owner,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'current']
    );

    $case = WorkspaceV2ReviewCase::create([
        'workspace_id' =>
            $workspace->id,
        'target_canonical_revision_id' =>
            $target->id,
        'target_domain_key' =>
            'share_transfer',
        'target_component_key' =>
            'share_transfer_rule',
        'target_revision' =>
            $target->revision,
        'producer_domain_key' =>
            'implementation_review',
        'producer_component_key' =>
            'amendment_control_workflow',
        'trigger_type' =>
            'manual_review',
        'trigger_context' =>
            [],
        'status' =>
            'resolved',
        'resolution' =>
            'amendment_required',
        'resolution_note' =>
            'Amendment required.',
        'opened_by_user_id' =>
            $owner->id,
        'resolved_by_user_id' =>
            $owner->id,
        'opened_at' =>
            now(),
        'resolved_at' =>
            now(),
    ]);

    $artifact = app(
        PbrV2CanonicalWorkService::class
    )->createAmendmentWorkingDraft(
        $workspace,
        $case,
        'share_transfer',
        'share_transfer_workflow',
        ['rule' => 'amended'],
        [],
        $owner
    );

    expect($artifact->state)
        ->toBe('working_draft')
        ->and($artifact->amendment_review_case_id)
        ->toBe($case->id)
        ->and(
            $artifact
                ->amendment_target_canonical_revision_id
        )
        ->toBe($target->id)
        ->and($artifact->target_domain_key)
        ->toBe('share_transfer')
        ->and($artifact->target_component_key)
        ->toBe('share_transfer_rule');

    expect($target->fresh()->state)
        ->toBe('approved_current');
});

test('amendment creation rejects review cases without amendment required resolution', function () {
    [$owner, $workspace] = f10Workspace();

    $target = f10Publish(
        $workspace,
        $owner,
        'share_transfer',
        'share_transfer_rule'
    );

    $case = WorkspaceV2ReviewCase::create([
        'workspace_id' => $workspace->id,
        'target_canonical_revision_id' => $target->id,
        'target_domain_key' => 'share_transfer',
        'target_component_key' => 'share_transfer_rule',
        'target_revision' => $target->revision,
        'producer_domain_key' => 'implementation_review',
        'producer_component_key' => 'amendment_control_workflow',
        'trigger_type' => 'manual_review',
        'trigger_context' => [],
        'status' => 'resolved',
        'resolution' => 'no_change',
        'resolution_note' => 'No change.',
        'opened_by_user_id' => $owner->id,
        'resolved_by_user_id' => $owner->id,
        'opened_at' => now(),
        'resolved_at' => now(),
    ]);

    expect(fn () => app(
        PbrV2CanonicalWorkService::class
    )->createAmendmentWorkingDraft(
        $workspace,
        $case,
        'share_transfer',
        'share_transfer_workflow',
        []
    ))->toThrow(ValidationException::class);
});

test('publication consumption links exact f7 revision without changing f10 state vocabulary', function () {
    [$owner, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = f10RootDraft(
        $workspace,
        $owner
    );

    $artifact = $service->submitProposal(
        $workspace,
        $artifact,
        $owner
    );

    $revision = f10Publish(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule',
        $artifact->payload
    );

    $artifact =
        $service->recordPublicationConsumption(
            $workspace,
            $artifact,
            $revision
        );

    expect($artifact->state)
        ->toBe('proposed')
        ->and(
            $artifact
                ->published_canonical_revision_id
        )
        ->toBe($revision->id)
        ->and($artifact->consumed_at)
        ->not->toBeNull()
        ->and($artifact->isConsumed())
        ->toBeTrue();
});

test('publication consumption rejects mismatched f7 revision identity', function () {
    [$owner, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = $service->submitProposal(
        $workspace,
        f10RootDraft($workspace),
        $owner
    );

    $wrong = f10Publish(
        $workspace,
        $owner,
        'capital',
        'approved_capital_plan'
    );

    expect(fn () =>
        $service->recordPublicationConsumption(
            $workspace,
            $artifact,
            $wrong
        )
    )->toThrow(ValidationException::class);
});

test('consumed proposal is terminal inside f10', function () {
    [$owner, $workspace] = f10Workspace();

    $service = app(
        PbrV2CanonicalWorkService::class
    );

    $artifact = $service->submitProposal(
        $workspace,
        f10RootDraft($workspace),
        $owner
    );

    $revision = f10Publish(
        $workspace,
        $owner,
        'capital',
        'capital_call_rule',
        $artifact->payload
    );

    $artifact =
        $service->recordPublicationConsumption(
            $workspace,
            $artifact,
            $revision
        );

    expect(fn () =>
        $service->returnProposalToWorkingDraft(
            $workspace,
            $artifact
        )
    )->toThrow(ValidationException::class);

    expect(fn () =>
        $service->updateWorkingDraftPayload(
            $workspace,
            $artifact,
            []
        )
    )->toThrow(ValidationException::class);

    expect(fn () =>
        $service->refreshWorkingDraftSources(
            $workspace,
            $artifact,
            []
        )
    )->toThrow(ValidationException::class);

    expect(fn () =>
        $service->submitProposal(
            $workspace,
            $artifact,
            $owner
        )
    )->toThrow(ValidationException::class);

    expect(fn () => $artifact->delete())
        ->toThrow(LogicException::class);
});

test('canonical work source rows are append only immutable evidence', function () {
    [$owner, $workspace] = f10Workspace();

    $source = f10Publish(
        $workspace,
        $owner,
        'contribution',
        'accepted_contribution_register'
    );

    $artifact = app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'ownership',
        'final_share_register',
        'ownership',
        'share_allocation_analysis',
        [],
        [$source->id]
    );

    $row = WorkspaceV2CanonicalWorkSource::query()
        ->where('work_artifact_id', $artifact->id)
        ->firstOrFail();

    $row->source_domain_key = 'capital';

    expect(fn () => $row->save())
        ->toThrow(LogicException::class);

    $row->refresh();

    expect(fn () => $row->delete())
        ->toThrow(LogicException::class);
});

test('canonical work artifacts cannot be deleted', function () {
    [, $workspace] = f10Workspace();

    $artifact = f10RootDraft($workspace);

    expect(fn () => $artifact->delete())
        ->toThrow(LogicException::class);
});

test('f10 service exposes only the frozen public surface', function () {
    $reflection = new ReflectionClass(
        PbrV2CanonicalWorkService::class
    );

    $methods = collect(
        $reflection->getMethods(
            ReflectionMethod::IS_PUBLIC
        )
    )
        ->filter(
            fn (ReflectionMethod $method): bool =>
                $method->getDeclaringClass()->getName()
                === PbrV2CanonicalWorkService::class
                && $method->getName() !== '__construct'
        )
        ->map(
            fn (ReflectionMethod $method): string =>
                $method->getName()
        )
        ->sort()
        ->values()
        ->all();

    $expected = [
        'createAmendmentWorkingDraft',
        'createWorkingDraft',
        'recordPublicationConsumption',
        'refreshWorkingDraftSources',
        'returnProposalToWorkingDraft',
        'sources',
        'states',
        'submitProposal',
        'updateWorkingDraftPayload',
    ];

    sort($expected);

    expect($methods)->toBe($expected);
});

test('f10 contains none of the reserved f6 f7 f8 or f9 authority methods', function () {
    $reflection = new ReflectionClass(
        PbrV2CanonicalWorkService::class
    );

    foreach ([
        'approve',
        'reject',
        'publish',
        'supersede',
        'resolveReview',
        'requestAmendment',
        'createAction',
        'appendRecord',
        'delete',
    ] as $method) {
        expect(
            $reflection->hasMethod($method)
        )->toBeFalse(
            'Reserved authority leaked into F10: '.$method
        );
    }
});

test('f10 implementation has no v1 or chapter identity leakage', function () {
    $files = [
        app_path(
            'Models/WorkspaceV2CanonicalWorkArtifact.php'
        ),
        app_path(
            'Models/WorkspaceV2CanonicalWorkSource.php'
        ),
        app_path(
            'Services/PbrTools/PbrV2CanonicalWorkService.php'
        ),
        config_path(
            'pbr_v2_canonical_work.php'
        ),
        database_path(
            'migrations/2026_09_05_030000_create_workspace_v2_canonical_work_tables.php'
        ),
    ];

    $content = implode(
        "\n",
        array_map(
            static fn (string $file): string =>
                file_get_contents($file),
            $files
        )
    );

    foreach ([
        'chapter_number',
        'chapter_tool_id',
        'source_tool_session_id',
        'workspace_tool_output_id',
        'ToolSession',
        'WorkspaceToolOutput',
    ] as $forbidden) {
        expect(
            str_contains(
                $content,
                $forbidden
            )
        )->toBeFalse(
            'Legacy identity leaked into F10: '.$forbidden
        );
    }
});

test('frozen f6 and f7 public signatures remain unchanged', function () {
    $f6 = new ReflectionClass(
        PbrV2ApprovalEngineService::class
    );

    $f7 = new ReflectionClass(
        PbrV2CanonicalOutputService::class
    );

    expect(
        collect(
            $f6->getMethod('approve')
                ->getParameters()
        )->pluck('name')->all()
    )->toBe([
        'domainKey',
        'componentKey',
        'proposalState',
        'readinessPassed',
        'sourcesFresh',
    ])->and(
        collect(
            $f6->getMethod('reject')
                ->getParameters()
        )->pluck('name')->all()
    )->toBe([
        'domainKey',
        'componentKey',
        'proposalState',
        'reason',
    ])->and(
        collect(
            $f7->getMethod('publish')
                ->getParameters()
        )->pluck('name')->all()
    )->toBe([
        'workspace',
        'domainKey',
        'componentKey',
        'payload',
        'approvalDecision',
        'actor',
    ]);
});

test('f10 preserves exact producer and canonical target inventory boundary', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $all = collect($registry->all());

    expect(
        $all->filter(
            fn (array $component): bool =>
                ($component['can_create_proposal'] ?? false)
                === true
        )
    )->toHaveCount(73)
        ->and(
            $all->filter(
                fn (array $component): bool =>
                    ($component['canonical_capability'] ?? 'none')
                    !== 'none'
            )
        )->toHaveCount(32);
});
