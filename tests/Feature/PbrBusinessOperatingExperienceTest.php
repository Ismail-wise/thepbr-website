<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Services\Ai\PbrAiContextBuilder;
use App\Services\PbrTools\PbrBusinessOperatingService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrBusinessOsFixture(bool $withPartner = false): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Real Operating Business',
        'business_name' => 'Real Operating Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $partner = null;

    if ($withPartner) {
        $partner = User::factory()->create([
            'role' => 'public',
            'account_status' => 'active',
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
    }

    $ownershipTool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    return compact('owner', 'partner', 'workspace', 'ownershipTool');
}

function pbrBusinessOsCapInput(float $ownerUnits, float $partnerUnits): array
{
    return [
        'partners' => [
            ['name' => 'Owner', 'units' => $ownerUnits, 'voting_units' => $ownerUnits],
            ['name' => 'Partner', 'units' => $partnerUnits, 'voting_units' => $partnerUnits],
        ],
        'reserved_units' => 0,
    ];
}

test('approving a working plan closes the draft and makes it the active business rule', function () {
    extract(pbrBusinessOsFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);
    $input = pbrBusinessOsCapInput(60, 40);

    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Current Ownership 60-40',
        $input,
        $engine->calculate($ownershipTool->tool_key, $input, $workspace)
    );

    $output = $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $ownershipTool,
        $session
    );

    $session->refresh();

    expect($output->status)->toBe('agreed')
        ->and($session->status)->toBe('completed')
        ->and($session->completed_at)->not->toBeNull()
        ->and($scenarios->drafts($owner, $workspace, $ownershipTool))->toBeEmpty();

    $state = app(PbrBusinessOperatingService::class)
        ->workspaceState($owner, $workspace);
    $ownership = $state['system_map']->get('ownership');

    expect($state['metrics']['active_rule_count'])->toBe(1)
        ->and($state['metrics']['working_change_count'])->toBe(0)
        ->and($state['active_rules'])->toHaveCount(1)
        ->and($ownership['state']['key'])->toBe('active')
        ->and($ownership['working_count'])->toBe(0);
});

test('a later working change never replaces current approved data for owner or partner', function () {
    extract(pbrBusinessOsFixture(withPartner: true));

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $approvedInput = pbrBusinessOsCapInput(60, 40);
    $approvedSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Approved Ownership 60-40',
        $approvedInput,
        $engine->calculate($ownershipTool->tool_key, $approvedInput, $workspace)
    );
    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $ownershipTool,
        $approvedSession
    );

    $workingInput = pbrBusinessOsCapInput(10, 90);
    $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Private Working Change 10-90',
        $workingInput,
        $engine->calculate($ownershipTool->tool_key, $workingInput, $workspace)
    );

    $ownerState = app(PbrBusinessOperatingService::class)
        ->workspaceState($owner, $workspace);
    $partnerState = app(PbrBusinessOperatingService::class)
        ->workspaceState($partner, $workspace);

    $ownerOwnership = $ownerState['system_map']->get('ownership');
    $partnerOwnership = $partnerState['system_map']->get('ownership');
    $partnerRule = $partnerState['active_rules']->firstWhere('key', 'cap_table_builder');
    $holders = $partnerRule['active_result']['data']['holders'] ?? [];

    expect($ownerState['metrics']['working_change_count'])->toBe(1)
        ->and($ownerOwnership['state']['key'])->toBe('review')
        ->and($partnerState['metrics']['working_change_count'])->toBe(0)
        ->and($partnerOwnership['state']['key'])->toBe('active')
        ->and($partnerOwnership['working_count'])->toBe(0)
        ->and((float) ($holders[0]['ownership_percentage'] ?? 0))->toBe(60.0)
        ->and((float) ($holders[1]['ownership_percentage'] ?? 0))->toBe(40.0);
});

test('pbr ai uses current approved rules for owners and partners and excludes later working changes', function () {
    extract(pbrBusinessOsFixture(withPartner: true));

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $approvedInput = pbrBusinessOsCapInput(60, 40);
    $approvedSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Approved Ownership 60-40',
        $approvedInput,
        $engine->calculate($ownershipTool->tool_key, $approvedInput, $workspace)
    );
    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $ownershipTool,
        $approvedSession
    );

    $workingInput = pbrBusinessOsCapInput(10, 90);
    $workingSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Private Working Change 10-90',
        $workingInput,
        $engine->calculate($ownershipTool->tool_key, $workingInput, $workspace)
    );
    $scenarios->createWorkspaceOutput(
        $owner,
        $workspace,
        $ownershipTool,
        $workingSession
    );

    $builder = app(PbrAiContextBuilder::class);
    $ownerContext = $builder->build($owner, $workspace);
    $partnerContext = $builder->build($partner, $workspace);

    foreach ([$ownerContext, $partnerContext] as $context) {
        $outputs = collect($context['business_tool_outputs'] ?? []);
        $capOutput = $outputs->firstWhere('tool.key', 'cap_table_builder');
        $holders = $capOutput['output']['data']['holders'] ?? [];

        expect($context['access_scope']['workspace_tool_output_scope'])->toBe('agreed_only')
            ->and($context['access_scope']['operating_rule_scope'])->toBe('approved_current_rules_only')
            ->and($outputs->pluck('status')->unique()->values()->all())->toBe(['agreed'])
            ->and((float) ($holders[0]['ownership_percentage'] ?? 0))->toBe(60.0)
            ->and((float) ($holders[1]['ownership_percentage'] ?? 0))->toBe(40.0)
            ->and($context['operating_system']['ownership']['status'] ?? null)->toBe('agreed');
    }
});

test('business dashboard capital position keeps approved snapshot as source of truth while a draft exists', function () {
    extract(pbrBusinessOsFixture());

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => ['source_status' => 'agreed_only'],
        'summary' => [
            'capital_required' => 100000,
            'capital_secured' => 90000,
            'funding_gap' => 10000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now()->subMinute(),
        'agreed_at' => now()->subMinute(),
    ]);

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 2,
        'status' => 'draft',
        'schema_version' => 'v1',
        'payload' => ['source_status' => 'working_latest_draft_or_agreed'],
        'summary' => [
            'capital_required' => 500000,
            'capital_secured' => 1000,
            'funding_gap' => 499000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => null,
    ]);

    $state = app(PbrBusinessOperatingService::class)
        ->workspaceState($owner, $workspace);

    expect($state['capital_source'])->toBe('active')
        ->and((float) $state['metrics']['capital_required'])->toBe(100000.0)
        ->and((float) $state['metrics']['capital_secured'])->toBe(90000.0)
        ->and((float) $state['metrics']['funding_gap'])->toBe(10000.0);
});

test('approval endpoint returns to the current rule instead of reopening a completed draft', function () {
    extract(pbrBusinessOsFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);
    $input = pbrBusinessOsCapInput(55, 45);

    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $ownershipTool,
        'Ownership Policy',
        $input,
        $engine->calculate($ownershipTool->tool_key, $input, $workspace)
    );

    $response = $this
        ->actingAs($owner)
        ->post(route('workspaces.tools.scenarios.approve', [
            $workspace,
            $ownershipTool->slug,
            $session->id,
        ]));

    $response->assertRedirect(route('workspaces.tools.operating.show', [
        $workspace,
        $ownershipTool->slug,
    ]));

    $session->refresh();
    expect($session->status)->toBe('completed');

    $this
        ->actingAs($owner)
        ->get(route('workspaces.tools.operating.show', [
            $workspace,
            $ownershipTool->slug,
        ]))
        ->assertOk()
        ->assertSee('Current Active Business Rule');
});

test('logged in product is organized as ten business operating areas without course progress language', function () {
    extract(pbrBusinessOsFixture());

    $areas = config('pbr_business_operating_system.areas');

    expect($areas)->toHaveCount(10)
        ->and(collect($areas)->pluck('domain')->unique())->toHaveCount(10)
        ->and(collect($areas)->pluck('slug')->unique())->toHaveCount(10);

    $this
        ->actingAs($owner)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSee('PBR BUSINESS OPERATING SYSTEM')
        ->assertDontSee('Learning Chapters')
        ->assertDontSee('Chapter Completion')
        ->assertDontSee('သင်ခန်းစာ');

    $this
        ->actingAs($owner)
        ->get(route('workspaces.tools.index', $workspace))
        ->assertOk()
        ->assertSee('Capital &amp; Funding', false)
        ->assertSee('Ownership &amp; Equity', false)
        ->assertSee('Conflict &amp; Resolution', false)
        ->assertSee('Business Rulebook')
        ->assertDontSee('Current Business Rule Register')
        ->assertDontSee('Chapter 1')
        ->assertDontSee('Completion percentage')
        ->assertDontSee('Lesson');
});
