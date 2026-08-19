<?php

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Resources\UserAccess\UserAccessResource;
use App\Filament\Resources\WorkspaceMembers\WorkspaceMemberResource;
use App\Models\PartnerDynamicsAssessment;
use App\Models\PartnerDynamicsReport;
use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\AccessAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function administrationTestUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ], $attributes));
}

function administrationTestWorkspace(User $owner): PartnershipWorkspace
{
    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Administration Test Business',
        'business_name' => 'Administration Test Business',
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

function administrationTestPartner(
    PartnershipWorkspace $workspace,
    User $owner,
    User $partner
): WorkspaceMember {
    return WorkspaceMember::query()->create([
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
}

function administrationTestAssessment(User $user, float $score): PartnerDynamicsAssessment
{
    $dimensions = [
        'vision' => $score,
        'execution' => $score,
        'people' => $score,
        'analysis' => $score,
        'structure' => $score,
        'risk' => $score,
        'decision' => $score,
        'adaptability' => $score,
    ];

    return PartnerDynamicsAssessment::query()->create([
        'user_id' => $user->id,
        'assessment_version' => 'v1',
        'status' => 'completed',
        'answers' => [],
        'dimension_scores' => $dimensions,
        'profile_scores' => ['operator' => $score],
        'primary_profile' => 'operator',
        'primary_score' => $score,
        'secondary_profile' => 'guardian',
        'secondary_score' => $score - 5,
        'is_blended' => false,
        'result_confidence' => 'strong',
        'consistency_data' => [],
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);
}

test('admin access control resources are available only through the admin panel', function () {
    $admin = administrationTestUser([
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(UserAccessResource::getUrl('index'))
        ->assertOk();

    $this->get(StudentEnrollmentResource::getUrl('index'))
        ->assertOk();

    $this->get(WorkspaceMemberResource::getUrl('index'))
        ->assertOk();
});

test('admin service can deactivate accounts renew student access and remove partner access', function () {
    $admin = administrationTestUser([
        'role' => 'admin',
        'is_admin' => true,
    ]);
    $student = administrationTestUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $enrollment = StudentEnrollment::query()->create([
        'user_id' => $student->id,
        'class_session_id' => null,
        'student_access_code_id' => null,
        'status' => 'active',
        'started_at' => now()->subMonth(),
        'access_expires_at' => now()->addMonth(),
    ]);
    $owner = administrationTestUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = administrationTestWorkspace($owner);
    $membership = administrationTestPartner($workspace, $owner, $student);
    $access = app(AccessAdministrationService::class);

    $access->deactivateAccount($admin, $student);
    expect($student->fresh()->hasActiveAccount())->toBeFalse()
        ->and($student->fresh()->canAccessWorkspace($workspace))->toBeFalse();

    $access->activateAccount($admin, $student);
    $access->revokeStudentEntitlement($admin, $enrollment);
    expect($student->fresh()->hasActiveAccount())->toBeTrue()
        ->and($student->fresh()->isStudent())->toBeFalse();

    $renewed = $access->renewStudentEntitlement($admin, $enrollment);
    expect($renewed->isActive())->toBeTrue()
        ->and($renewed->access_expires_at?->isFuture())->toBeTrue()
        ->and($student->fresh()->isStudent())->toBeTrue();

    $lifetime = $access->grantLifetimeStudentEntitlement($admin, $enrollment);
    expect($lifetime->isActive())->toBeTrue()
        ->and($lifetime->access_expires_at)->toBeNull();

    $removed = $access->removePartnerAccess($admin, $membership);
    expect($removed->invitation_status)->toBe('removed')
        ->and($student->fresh()->canAccessWorkspace($workspace))->toBeFalse()
        ->and($student->fresh()->isStudent())->toBeTrue();
});

test('partner dynamics page is read only until owner explicitly saves the report', function () {
    $owner = administrationTestUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = administrationTestWorkspace($owner);
    $partner = administrationTestUser();
    administrationTestPartner($workspace, $owner, $partner);
    administrationTestAssessment($owner, 80);
    administrationTestAssessment($partner, 70);

    expect(PartnerDynamicsReport::query()->count())->toBe(0);

    $this->actingAs($owner)
        ->get(route('workspaces.partner-dynamics.show', $workspace))
        ->assertOk()
        ->assertSee('Unsaved Preview')
        ->assertSee('Generate & Save Report');

    expect(PartnerDynamicsReport::query()->count())->toBe(0);

    $this->actingAs($partner)
        ->get(route('workspaces.partner-dynamics.show', $workspace))
        ->assertOk()
        ->assertSee('Saved Report Pending');

    $this->post(route('workspaces.partner-dynamics.report.generate', $workspace))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('workspaces.partner-dynamics.report.generate', $workspace))
        ->assertRedirect(route('workspaces.partner-dynamics.show', $workspace));

    $report = PartnerDynamicsReport::query()->firstOrFail();
    $savedAt = $report->updated_at->copy();

    $this->travel(5)->minutes();

    $this->get(route('workspaces.partner-dynamics.show', $workspace))
        ->assertOk()
        ->assertSee('Saved Alignment Report');

    expect(PartnerDynamicsReport::query()->count())->toBe(1)
        ->and($report->fresh()->updated_at->equalTo($savedAt))->toBeTrue();
});
