<?php

use App\Models\BusinessFeasibilityAssessment;
use App\Models\BusinessValuation;
use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\Ai\PbrAiContextBuilder;
use App\Services\StudentAccessUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function hardeningUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ], $attributes));
}

function hardeningEnrollment(
    User $user,
    string $status = 'active',
    mixed $expiresAt = null
): StudentEnrollment {
    return StudentEnrollment::query()->create([
        'user_id' => $user->id,
        'class_session_id' => null,
        'student_access_code_id' => null,
        'status' => $status,
        'started_at' => now()->subDay(),
        'access_expires_at' => $expiresAt,
    ]);
}

function hardeningWorkspace(
    User $owner,
    string $name = 'Access Hardening Business'
): PartnershipWorkspace {
    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
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
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    return $workspace;
}

function hardeningAcceptPartner(
    PartnershipWorkspace $workspace,
    User $partner,
    User $owner
): WorkspaceMember {
    return WorkspaceMember::query()->create([
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

test('an active enrollment grants student access regardless of the legacy role', function () {
    $student = hardeningUser([
        'role' => 'public',
    ]);

    hardeningEnrollment($student, 'active', now()->addDay());

    expect($student->fresh()->isStudent())->toBeTrue()
        ->and($student->fresh()->load('studentEnrollments')->isStudent())->toBeTrue();
});

test('a legacy student without an enrollment keeps access during entitlement migration', function () {
    $legacyStudent = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => null,
    ]);

    expect($legacyStudent->isStudent())->toBeTrue()
        ->and($legacyStudent->canUsePbrAiAdvisor())->toBeTrue();
});

test('an existing expired enrollment overrides legacy student fields consistently', function () {
    $formerStudent = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);

    hardeningEnrollment($formerStudent, 'active', now()->subMinute());

    expect($formerStudent->fresh()->isStudent())->toBeFalse()
        ->and($formerStudent->fresh()->load('studentEnrollments')->isStudent())->toBeFalse()
        ->and($formerStudent->fresh()->canUsePbrAiAdvisor())->toBeFalse();
});

test('inactive accounts cannot keep admin student or partner access', function () {
    $inactiveAdmin = hardeningUser([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => 'inactive',
    ]);

    $inactiveStudent = hardeningUser([
        'role' => 'student',
        'account_status' => 'inactive',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    hardeningEnrollment($inactiveStudent, 'active', now()->addYear());

    $owner = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = hardeningWorkspace($owner);

    $inactivePartner = hardeningUser([
        'account_status' => 'inactive',
    ]);
    hardeningAcceptPartner($workspace, $inactivePartner, $owner);

    expect($inactiveAdmin->isAdmin())->toBeFalse()
        ->and($inactiveAdmin->canAccessWorkspace($workspace))->toBeFalse()
        ->and($inactiveStudent->isStudent())->toBeFalse()
        ->and($inactiveStudent->canUsePbrAiAdvisor())->toBeFalse()
        ->and($inactivePartner->isPartner())->toBeFalse()
        ->and($inactivePartner->canAccessWorkspace($workspace))->toBeFalse();

    $this->actingAs($inactiveAdmin)
        ->get(route('workspaces.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->actingAs($inactivePartner)
        ->get(route('workspaces.show', $workspace))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('inactive accounts are rejected during login and remain logged out', function () {
    $inactive = hardeningUser([
        'account_status' => 'inactive',
    ]);

    $this->post(route('login.store'), [
        'email' => $inactive->email,
        'password' => 'password',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive accounts cannot redeem a student access code', function () {
    $inactive = hardeningUser([
        'account_status' => 'inactive',
    ]);

    expect(fn () => app(StudentAccessUpgradeService::class)
        ->upgrade($inactive, 'PBR-ANY-CODE'))
        ->toThrow(ValidationException::class);
});

test('admin access alone does not grant pbr ai but an active student enrollment does', function () {
    $owner = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = hardeningWorkspace($owner, 'Admin AI Boundary Business');

    $admin = hardeningUser([
        'role' => 'admin',
        'is_admin' => true,
    ]);

    expect($admin->canUsePbrAiAdvisor())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertForbidden();

    hardeningEnrollment($admin, 'active', now()->addYear());

    expect($admin->fresh()->canUsePbrAiAdvisor())->toBeTrue();

    $this->actingAs($admin->fresh())
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertOk();
});

test('student partner keeps ai access but receives no owner private decision data', function () {
    $owner = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = hardeningWorkspace($owner, 'Private AI Context Business');

    $studentPartner = hardeningUser([
        'role' => 'student',
        'portal_access_expires_at' => null,
    ]);
    hardeningEnrollment($studentPartner, 'active', now()->addYear());
    hardeningAcceptPartner($workspace, $studentPartner, $owner);

    BusinessFeasibilityAssessment::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'project_name' => 'Confidential Expansion',
        'inputs' => ['owner_private_budget' => 750000],
        'result' => ['recommendation' => 'owner only'],
    ]);

    BusinessValuation::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'inputs' => ['owner_private_revenue' => 1200000],
        'result' => ['estimated_value' => 3600000],
    ]);

    $this->actingAs($studentPartner)
        ->get(route('workspaces.ai-advisor.index', $workspace))
        ->assertOk();

    $this->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('Owner-private Feasibility');

    $context = app(PbrAiContextBuilder::class)->build(
        $studentPartner->fresh(),
        $workspace->fresh()
    );

    expect($context['access_scope']['actor_type'])->toBe('accepted_partner')
        ->and($context['access_scope']['manager_sensitive_context_included'])->toBeFalse()
        ->and($context['feasibility'])->toBeNull()
        ->and($context['valuation'])->toBeNull();
});
