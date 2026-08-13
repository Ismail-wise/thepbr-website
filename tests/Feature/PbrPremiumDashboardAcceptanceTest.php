<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function premiumDashboardFixture(string $name = 'Premium Dashboard Business'): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact('owner', 'workspace');
}

test('business control center uses dashboard url and legacy student url redirects', function () {
    extract(premiumDashboardFixture());

    expect(parse_url(route('student.dashboard'), PHP_URL_PATH))->toBe('/dashboard');

    $this
        ->actingAs($owner)
        ->get('/student')
        ->assertRedirect(route('student.dashboard'));

    $this
        ->actingAs($owner)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSee('Business Control Center')
        ->assertSee('Needs Action')
        ->assertSee('Needs Review')
        ->assertSee('Setup Required')
        ->assertSee('Business Portfolio')
        ->assertSee('Open Business OS')
        ->assertSee('pbr-premium-shell.css', false)
        ->assertDontSee('10 Learning Chapters')
        ->assertDontSee('Chapter Completion')
        ->assertDontSee('Partner Roster');
});

test('unconfigured business is setup required and not counted as urgent attention', function () {
    extract(premiumDashboardFixture('Setup Only Business'));

    $response = $this
        ->actingAs($owner)
        ->get(route('student.dashboard'))
        ->assertOk();

    $metrics = $response->viewData('portfolioMetrics');
    $business = $response->viewData('businesses')->first();

    expect($metrics['business_count'])->toBe(1)
        ->and($metrics['needs_action_count'])->toBe(0)
        ->and($metrics['needs_review_count'])->toBe(0)
        ->and($metrics['setup_required_count'])->toBe(1)
        ->and($metrics['businesses_needing_attention'])->toBe(0)
        ->and($business['status']['key'])->toBe('setup_required');
});

test('working change is review required instead of generic setup attention', function () {
    extract(premiumDashboardFixture('Review Business'));

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    $input = [
        'partners' => [
            ['name' => 'Owner', 'units' => 60, 'voting_units' => 60],
            ['name' => 'Partner', 'units' => 40, 'voting_units' => 40],
        ],
        'reserved_units' => 0,
    ];

    app(ToolScenarioService::class)->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Ownership Change',
        $input,
        app(PbrOperatingToolEngine::class)->calculate($tool->tool_key, $input, $workspace)
    );

    $response = $this
        ->actingAs($owner)
        ->get(route('student.dashboard'))
        ->assertOk();

    $metrics = $response->viewData('portfolioMetrics');
    $business = $response->viewData('businesses')->first();

    expect($metrics['needs_action_count'])->toBe(0)
        ->and($metrics['needs_review_count'])->toBe(1)
        ->and($metrics['setup_required_count'])->toBe(0)
        ->and($metrics['businesses_needing_attention'])->toBe(1)
        ->and($business['status']['key'])->toBe('needs_review');
});

test('funding action outranks review while both portfolio signals remain visible', function () {
    extract(premiumDashboardFixture('Funding Review Business'));

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    $input = [
        'partners' => [
            ['name' => 'Owner', 'units' => 70, 'voting_units' => 70],
            ['name' => 'Partner', 'units' => 30, 'voting_units' => 30],
        ],
        'reserved_units' => 0,
    ];

    app(ToolScenarioService::class)->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Ownership Review',
        $input,
        app(PbrOperatingToolEngine::class)->calculate($tool->tool_key, $input, $workspace)
    );

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => ['source' => 'acceptance-test'],
        'summary' => [
            'capital_required' => 100000,
            'capital_secured' => 80000,
            'funding_gap' => 20000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('student.dashboard'))
        ->assertOk();

    $metrics = $response->viewData('portfolioMetrics');
    $business = $response->viewData('businesses')->first();

    expect($metrics['needs_action_count'])->toBe(1)
        ->and($metrics['needs_review_count'])->toBe(1)
        ->and($metrics['setup_required_count'])->toBe(0)
        ->and($business['status']['key'])->toBe('needs_action')
        ->and((float) $business['metrics']['funding_gap'])->toBe(20000.0);
});
