<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function premiumBusinessOverviewFixture(string $name = 'Overview Trading Co'): array
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

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'member_role' => 'owner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($owner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    return compact('owner', 'workspace');
}

test('business overview is a premium operational control center with consistent live metrics', function () {
    extract(premiumBusinessOverviewFixture('Premium Overview Co'));

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'partner_key' => (string) Str::uuid(),
        'display_name' => 'Planned Partner',
        'status' => 'planned',
        'profile_data' => ['workspace_role' => 'partner'],
    ]);

    $acceptedPartner = User::factory()->create([
        'role' => 'partner',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $acceptedPartner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($acceptedPartner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => ['documents'],
    ]);

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => ['source' => 'premium-overview-test'],
        'summary' => [
            'capital_required' => 100000,
            'capital_secured' => 80000,
            'funding_gap' => 20000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);

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
        'Ownership Review',
        $input,
        app(PbrOperatingToolEngine::class)->calculate($tool->tool_key, $input, $workspace)
    );

    $response = $this
        ->actingAs($owner)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('BUSINESS CONTROL CENTER')
        ->assertSee('CURRENT BUSINESS POSITION', false)
        ->assertSee('Pending Review')
        ->assertSee('Partner Profiles')
        ->assertSee('Accepted Partner Accounts — 1')
        ->assertSee('THB 20,000')
        ->assertSee('Needs Action')
        ->assertSee('OPERATING AREAS')
        ->assertSee('Business Operating Areas 10')
        ->assertSee('pbr-premium-business-overview.css', false)
        ->assertSee('pbr-overview-v2', false)
        ->assertDontSee('<section class="pbr-business-page', false);

    $state = $response->viewData('businessState');

    expect((float) $state['metrics']['funding_gap'])->toBe(20000.0)
        ->and((int) $state['metrics']['working_change_count'])->toBe(1)
        ->and((int) $state['metrics']['partner_count'])->toBe(2);
});

test('priority actions consolidate duplicate operating areas instead of flooding the overview', function () {
    extract(premiumBusinessOverviewFixture('Priority Business'));

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => ['source' => 'premium-overview-test'],
        'summary' => [
            'capital_required' => 150000,
            'capital_secured' => 100000,
            'funding_gap' => 50000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('PRIORITY ACTIONS')
        ->assertSee('အခု ဘာလုပ်ရမလဲ')
        ->assertSee('FUNDING GAP');

    $actions = $response->viewData('businessState')['action_items'];
    $capitalActionCount = $actions->where('domain', 'capital')->count();

    expect($capitalActionCount)->toBeGreaterThanOrEqual(1);
});

test('partner overview is permission safe and never exposes owner access management', function () {
    extract(premiumBusinessOverviewFixture('Partner Read Only Co'));

    $partner = User::factory()->create([
        'role' => 'partner',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($partner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => ['documents'],
    ]);

    $this
        ->actingAs($partner)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('Partner Read-Only View')
        ->assertSee('Permission Safe')
        ->assertDontSee('Invite Partner')
        ->assertDontSee('Create Shareable Link')
        ->assertDontSee('Business Settings')
        ->assertDontSee('Accepted Partner Accounts —');
});
