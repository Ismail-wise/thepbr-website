<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function premiumMyBusinessesOwner(string $name = 'Portfolio Business'): array
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

test('my businesses is a premium operating portfolio instead of a generic workspace list', function () {
    extract(premiumMyBusinessesOwner('Premium Trading Co'));

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => ['source' => 'acceptance-test'],
        'summary' => [
            'capital_required' => 120000,
            'capital_secured' => 100000,
            'funding_gap' => 20000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertSee('BUSINESS PORTFOLIO')
        ->assertSee('My Businesses')
        ->assertSee('Managed by You')
        ->assertSee('Needs Attention')
        ->assertSee('Premium Trading Co')
        ->assertSee('Funding Gap')
        ->assertSee('THB 20,000')
        ->assertSee('Open Business')
        ->assertSee('pbr-premium-workspaces.css', false)
        ->assertSee('pbr-workspaces-grid', false)
        ->assertDontSee('Business Control Center</a>', false);

    $summary = $response->viewData('portfolioSummary');
    $business = $response->viewData('businesses')->first();

    expect($summary['business_count'])->toBe(1)
        ->and($summary['owned_count'])->toBe(1)
        ->and($summary['needs_attention_count'])->toBe(1)
        ->and($business['status']['key'])->toBe('needs_action')
        ->and((float) $business['metrics']['funding_gap'])->toBe(20000.0);
});

test('my businesses uses the operating partner profile source of truth', function () {
    extract(premiumMyBusinessesOwner('Partner Profile Business'));

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'partner_key' => (string) Str::uuid(),
        'display_name' => 'Planned Partner',
        'status' => 'planned',
        'profile_data' => ['workspace_role' => 'partner'],
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertSee('Partner Profiles 2');

    $business = $response->viewData('businesses')->first();

    expect($business['metrics']['partner_count'])->toBe(2);
});

test('partner access businesses are separated from managed businesses and stay read only', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $partner = User::factory()->create([
        'role' => 'partner',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Shared Partner Business',
        'business_name' => 'Shared Partner Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
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
        'permissions' => null,
    ]);

    $response = $this
        ->actingAs($partner)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertSee('PARTNER ACCESS')
        ->assertSee('Shared Partner Business')
        ->assertSee('Partner View')
        ->assertSee('View Active Rules')
        ->assertDontSee('Setup Required')
        ->assertDontSee(route('workspaces.edit', $workspace), false);

    $summary = $response->viewData('portfolioSummary');
    $business = $response->viewData('businesses')->first();

    expect($summary['owned_count'])->toBe(0)
        ->and($summary['partner_access_count'])->toBe(1)
        ->and($business['status']['key'])->toBe('partner_access');
});
