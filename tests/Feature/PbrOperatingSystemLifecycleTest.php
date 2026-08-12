<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspaceToolOutput;
use App\Services\Ai\PbrAiContextBuilder;
use App\Services\PbrTools\PbrChapterStateService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrLifecycleFixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Lifecycle Test Business',
        'business_name' => 'Lifecycle Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => $partner->email,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    return compact('owner', 'partner', 'workspace', 'tool');
}

test('owner can publish an agreed rule and operating snapshot is revisioned', function () {
    extract(pbrLifecycleFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $input = [
        'partners' => [
            ['name' => 'Owner', 'units' => 60, 'voting_units' => 60],
            ['name' => 'Partner', 'units' => 40, 'voting_units' => 40],
        ],
        'reserved_units' => 0,
    ];

    $result = $engine->calculate($tool->tool_key, $input, $workspace);
    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Base Ownership',
        $input,
        $result
    );

    $agreed = $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $session
    );

    expect($agreed->status)->toBe('agreed')
        ->and($agreed->revision)->toBe(1)
        ->and($scenarios->latestAgreedOutput($workspace, $tool)?->id)->toBe($agreed->id);

    $snapshot = WorkspaceOperatingSnapshot::query()
        ->where('workspace_id', $workspace->id)
        ->where('domain_key', 'ownership')
        ->where('status', 'agreed')
        ->latest('revision')
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot->summary['total_units'])->toBe(100.0)
        ->and($snapshot->summary['holders'])->toHaveCount(2);
});

test('accepted partner is not a workspace manager', function () {
    extract(pbrLifecycleFixture());

    $scenarios = app(ToolScenarioService::class);

    expect($scenarios->canManage($owner, $workspace))->toBeTrue()
        ->and($scenarios->canManage($partner, $workspace))->toBeFalse()
        ->and($partner->canAccessWorkspace($workspace))->toBeTrue();
});

test('working chapter state combines a new draft with unchanged agreed tools', function () {
    extract(pbrLifecycleFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $capInput = [
        'partners' => [
            ['name' => 'Owner', 'units' => 60, 'voting_units' => 60],
            ['name' => 'Partner', 'units' => 40, 'voting_units' => 40],
        ],
        'reserved_units' => 0,
    ];
    $capSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Agreed Cap Table',
        $capInput,
        $engine->calculate($tool->tool_key, $capInput, $workspace)
    );
    $scenarios->publishAgreedOutput($owner, $workspace, $tool, $capSession);

    $shareTool = ChapterTool::query()
        ->where('tool_key', 'share_value_calculator')
        ->firstOrFail();
    $shareInput = [
        'equity_value' => 2400000,
        'total_units' => 100,
        'stake_percentage' => 25,
    ];
    $shareSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $shareTool,
        'Working Share Value',
        $shareInput,
        $engine->calculate($shareTool->tool_key, $shareInput, $workspace)
    );
    $scenarios->createWorkspaceOutput(
        $owner,
        $workspace,
        $shareTool,
        $shareSession
    );

    $working = app(PbrChapterStateService::class)->build(
        $workspace,
        2,
        'draft'
    );
    $agreed = app(PbrChapterStateService::class)->build(
        $workspace,
        2,
        'agreed'
    );

    expect($working['payload']['source_status'])->toBe('working_latest_draft_or_agreed')
        ->and($working['payload']['tools'])->toHaveKeys([
            'cap_table_builder',
            'share_value_calculator',
        ])
        ->and($working['payload']['tools']['cap_table_builder']['status'])->toBe('agreed')
        ->and($working['payload']['tools']['share_value_calculator']['status'])->toBe('draft')
        ->and($agreed['payload']['tools'])->toHaveKey('cap_table_builder')
        ->and($agreed['payload']['tools'])->not->toHaveKey('share_value_calculator');
});

test('partner AI context receives agreed output but never later draft output', function () {
    extract(pbrLifecycleFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $agreedInput = [
        'partners' => [
            ['name' => 'Owner', 'units' => 60, 'voting_units' => 60],
            ['name' => 'Partner', 'units' => 40, 'voting_units' => 40],
        ],
        'reserved_units' => 0,
    ];

    $agreedResult = $engine->calculate($tool->tool_key, $agreedInput, $workspace);
    $agreedSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Agreed 60-40',
        $agreedInput,
        $agreedResult
    );
    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $agreedSession
    );

    $draftInput = [
        'partners' => [
            ['name' => 'Owner', 'units' => 10, 'voting_units' => 10],
            ['name' => 'Partner', 'units' => 90, 'voting_units' => 90],
        ],
        'reserved_units' => 0,
    ];
    $draftResult = $engine->calculate($tool->tool_key, $draftInput, $workspace);
    $draftSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Private Draft 10-90',
        $draftInput,
        $draftResult
    );
    $scenarios->createWorkspaceOutput(
        $owner,
        $workspace,
        $tool,
        $draftSession
    );

    expect(WorkspaceToolOutput::query()
        ->where('workspace_id', $workspace->id)
        ->where('chapter_tool_id', $tool->id)
        ->where('status', 'draft')
        ->exists())->toBeTrue();

    $context = app(PbrAiContextBuilder::class)->build($partner, $workspace);
    $outputs = collect($context['business_tool_outputs'] ?? []);

    expect($context['access_scope']['workspace_tool_output_scope'])->toBe('agreed_only')
        ->and($outputs)->not->toBeEmpty()
        ->and($outputs->pluck('status')->unique()->values()->all())->toBe(['agreed']);

    $capOutput = $outputs->firstWhere('tool.key', 'cap_table_builder');
    $holders = $capOutput['output']['data']['holders'] ?? [];

    expect((float) ($holders[0]['ownership_percentage'] ?? 0))->toBe(60.0);
});
