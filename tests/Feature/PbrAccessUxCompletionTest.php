<?php

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Resources\UserAccess\UserAccessResource;
use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspacePartnerProfile;
use App\Services\AccessAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function accessUxUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ], $attributes));
}

function accessUxWorkspace(User $owner): PartnershipWorkspace
{
    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Access UX Business',
        'business_name' => 'Access UX Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'member_role' => 'owner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($owner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'invitation_expires_at' => null,
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    return $workspace;
}

test('account dashboard explicitly explains account type and every effective access layer', function () {
    $public = accessUxUser();

    $this->actingAs($public)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Account Type')
        ->assertSee('Public Account')
        ->assertSee('Student Access')
        ->assertSee('Not active')
        ->assertSee('PBR AI')
        ->assertSee('Not available · Students only')
        ->assertSee('0 workspaces');

    $owner = accessUxUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = accessUxWorkspace($owner);
    $partner = accessUxUser();

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($partner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now()->subDay(),
        'invitation_expires_at' => now()->addDays(6),
        'accepted_at' => now(),
        'permissions' => ['approved_workspace_read_only'],
    ]);

    $this->actingAs($partner)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Public Account + Invited Partner')
        ->assertSee('1 workspace')
        ->assertSee('Not available · Students only');

    $this->actingAs($owner)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Active Student')
        ->assertSee('Active · Legacy compatibility')
        ->assertSee('Available');
});

test('partner access is directly linked and invitation expiry is shown exactly', function () {
    $this->travelTo(Carbon::parse('2026-08-18 09:00:00', 'UTC'));

    $owner = accessUxUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = accessUxWorkspace($owner);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'member_role' => 'partner',
        'invitation_status' => 'pending',
        'invited_email' => 'pending@example.com',
        'invitation_token_hash' => hash('sha256', 'pending-token'),
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'invitation_expires_at' => now()->addDays(7),
        'accepted_at' => null,
        'permissions' => ['approved_workspace_read_only'],
    ]);

    $partnerAccessUrl = route('workspaces.show', $workspace).'#partner-access';

    $this->actingAs($owner)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('expires Aug 25, 2026 9:00 AM UTC');

    $this->get(route('workspaces.edit', $workspace))
        ->assertOk()
        ->assertSee('Manage Partner Access')
        ->assertSee($partnerAccessUrl, false);

    $this->get(route('workspaces.partner-roster.index', $workspace))
        ->assertOk()
        ->assertSee('Manage Login Access & Invitations', false)
        ->assertSee($partnerAccessUrl, false);
});

test('admin can replace legacy fallback with an authoritative entitlement', function () {
    $admin = accessUxUser([
        'role' => 'admin',
        'is_admin' => true,
    ]);
    $legacyStudent = accessUxUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $public = accessUxUser();
    $access = app(AccessAdministrationService::class);

    expect($legacyStudent->isStudent())->toBeTrue()
        ->and($legacyStudent->studentEnrollments()->count())->toBe(0);

    $oneYear = $access->grantOneYearStudentAccess($admin, $legacyStudent);
    $lifetime = $access->grantLifetimeStudentAccess($admin, $public);

    expect($oneYear)->toBeInstanceOf(StudentEnrollment::class)
        ->and($oneYear->isActive())->toBeTrue()
        ->and($oneYear->access_expires_at?->isFuture())->toBeTrue()
        ->and($legacyStudent->fresh()->studentEnrollments()->count())->toBe(1)
        ->and($legacyStudent->fresh()->isStudent())->toBeTrue()
        ->and($lifetime->isActive())->toBeTrue()
        ->and($lifetime->access_expires_at)->toBeNull()
        ->and($public->fresh()->isStudent())->toBeTrue();

    expect(fn () => $access->grantLifetimeStudentAccess($admin, $legacyStudent))
        ->toThrow(ValidationException::class);

    $this->actingAs($admin)
        ->get(UserAccessResource::getUrl('index'))
        ->assertOk();

    $this->get(StudentEnrollmentResource::getUrl('index'))
        ->assertOk()
        ->assertSee($legacyStudent->email)
        ->assertSee($public->email);
});

test('partner roster separates business profile status from login access', function () {
    $owner = accessUxUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = accessUxWorkspace($owner);
    $formerPartner = accessUxUser();

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $formerPartner->id,
        'member_role' => 'partner',
        'invitation_status' => 'removed',
        'invited_email' => strtolower($formerPartner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now()->subWeek(),
        'invitation_expires_at' => now()->subDay(),
        'accepted_at' => now()->subDays(6),
        'permissions' => null,
    ]);

    WorkspacePartnerProfile::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $formerPartner->id,
        'partner_key' => (string) \Illuminate\Support\Str::uuid(),
        'display_name' => $formerPartner->name,
        'status' => 'active',
        'profile_data' => ['workspace_role' => 'partner'],
    ]);

    $this->actingAs($owner)
        ->get(route('workspaces.partner-roster.index', $workspace))
        ->assertOk()
        ->assertSee('2 Business Profiles')
        ->assertSee('Active Business Profile')
        ->assertSee('Owner Login Access')
        ->assertSee('No Login Access');
});
