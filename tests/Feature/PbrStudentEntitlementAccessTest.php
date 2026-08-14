<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entitlementUser(string $type): User
{
    return match ($type) {
        'student' => User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'portal_access_expires_at' => now()->addDay(),
            'is_admin' => false,
        ]),

        'admin' => User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'is_admin' => true,
        ]),

        default => User::factory()->create([
            'role' => 'public',
            'account_status' => 'active',
            'portal_access_expires_at' => null,
            'is_admin' => false,
        ]),
    };
}

function entitlementWorkspace(User $owner, string $name = 'Entitlement Test Business'): PartnershipWorkspace
{
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

    return $workspace;
}

function entitlementAcceptPartner(
    PartnershipWorkspace $workspace,
    User $partner,
    User $owner
): void {
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
}

test('public account cannot enter my businesses without student entitlement or accepted invitation', function () {
    $public = entitlementUser('public');

    $this
        ->actingAs($public)
        ->get(route('workspaces.index'))
        ->assertForbidden();
});

test('active student can enter business os and create a business', function () {
    $student = entitlementUser('student');

    $this
        ->actingAs($student)
        ->get(route('workspaces.index'))
        ->assertOk();

    $this
        ->actingAs($student)
        ->get(route('workspaces.create'))
        ->assertOk();
});

test('accepted invited partner can access only the invited business and cannot create businesses', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = entitlementUser('student');
    $otherOwner = entitlementUser('student');
    $partner = entitlementUser('public');

    $invitedWorkspace = entitlementWorkspace($owner, 'Invited Business');
    $otherWorkspace = entitlementWorkspace($otherOwner, 'Private Business');

    entitlementAcceptPartner($invitedWorkspace, $partner, $owner);

    $this
        ->actingAs($partner)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertSee('Invited Business')
        ->assertDontSee('Private Business');

    $this
        ->actingAs($partner)
        ->get(route('workspaces.show', $invitedWorkspace))
        ->assertOk();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.show', $otherWorkspace))
        ->assertForbidden();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.create'))
        ->assertForbidden();
});

test('pbr ai advisor is available to students but forbidden to non student invited partners', function () {
    $owner = entitlementUser('student');
    $partner = entitlementUser('public');

    $workspace = entitlementWorkspace($owner, 'AI Entitlement Business');
    entitlementAcceptPartner($workspace, $partner, $owner);

    $this
        ->actingAs($owner)
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertOk();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertForbidden();
});

test('non student invited partner cannot call ai chat endpoint directly', function () {
    $owner = entitlementUser('student');
    $partner = entitlementUser('public');

    $workspace = entitlementWorkspace($owner, 'AI Direct Access Business');
    entitlementAcceptPartner($workspace, $partner, $owner);

    $this
        ->actingAs($partner)
        ->post(route('workspaces.ai-advisor.chat', $workspace), [
            'message' => 'Can I use PBR AI?',
        ])
        ->assertForbidden();
});

test('general partner dynamics belongs to student entitlement not invited partner access', function () {
    $owner = entitlementUser('student');
    $partner = entitlementUser('public');

    $workspace = entitlementWorkspace($owner, 'Partner Dynamics Business');
    entitlementAcceptPartner($workspace, $partner, $owner);

    $this
        ->actingAs($owner)
        ->get(route('partner-dynamics.index'))
        ->assertOk();

    $this
        ->actingAs($partner)
        ->get(route('partner-dynamics.index'))
        ->assertForbidden();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.partner-dynamics.show', $workspace))
        ->assertOk();
});

test('partner business overview does not advertise student only ai advisor', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = entitlementUser('student');
    $partner = entitlementUser('public');

    $workspace = entitlementWorkspace($owner, 'Partner Safe Business');
    entitlementAcceptPartner($workspace, $partner, $owner);

    $this
        ->actingAs($partner)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertDontSee('Ask PBR AI')
        ->assertDontSee('PBR AI ADVISOR')
        ->assertDontSee('Partner Roster & Roles')
        ->assertDontSee('Business Feasibility')
        ->assertDontSee('Business Valuation');
});

test('expired former student owner cannot keep business os access through owner membership', function () {
    $expiredOwner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->subMinute(),
        'is_admin' => false,
    ]);

    $workspace = entitlementWorkspace(
        $expiredOwner,
        'Expired Student Business'
    );

    $this
        ->actingAs($expiredOwner)
        ->get(route('workspaces.index'))
        ->assertForbidden();

    $this
        ->actingAs($expiredOwner)
        ->get(route('workspaces.show', $workspace))
        ->assertForbidden();

    $this
        ->actingAs($expiredOwner)
        ->get(route('workspaces.edit', $workspace))
        ->assertForbidden();

    $this
        ->actingAs($expiredOwner)
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertForbidden();
});

test('invited partner cannot open owner private feasibility valuation or partner roster', function () {
    $owner = entitlementUser('student');
    $partner = entitlementUser('public');

    $workspace = entitlementWorkspace(
        $owner,
        'Private Owner Services Business'
    );

    entitlementAcceptPartner(
        $workspace,
        $partner,
        $owner
    );

    $this
        ->actingAs($partner)
        ->get(route('workspaces.feasibility.show', $workspace))
        ->assertForbidden();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.valuation.show', $workspace))
        ->assertForbidden();

    $this
        ->actingAs($partner)
        ->get(route('workspaces.partner-roster.index', $workspace))
        ->assertForbidden();
});
