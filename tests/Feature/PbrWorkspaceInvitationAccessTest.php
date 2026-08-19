<?php

use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function invitationAccessUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ], $attributes));
}

function invitationAccessWorkspace(User $owner): PartnershipWorkspace
{
    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Invitation Access Business',
        'business_name' => 'Invitation Access Business',
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

function invitationAccessPending(
    PartnershipWorkspace $workspace,
    User $owner,
    string $email,
    mixed $expiresAt = null
): array {
    $token = Str::random(64);

    $invitation = WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => User::query()->where('email', $email)->value('id'),
        'member_role' => 'partner',
        'invitation_status' => 'pending',
        'invited_email' => strtolower($email),
        'invitation_token_hash' => WorkspaceMember::fingerprintInvitationToken($token),
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'invitation_expires_at' => $expiresAt ?? now()->addDays(7),
        'accepted_at' => null,
        'permissions' => ['approved_workspace_read_only'],
    ]);

    return [$invitation, $token];
}

function invitationAccessAccepted(
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

test('owner creates an email bound expiring invitation with honest delivery copy', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    $invitedEmail = 'future.partner@example.com';

    $response = $this->actingAs($owner)
        ->post(route('workspace-invitations.store', $workspace), [
            'email' => strtoupper($invitedEmail),
        ])
        ->assertRedirect(route('workspaces.show', $workspace))
        ->assertSessionHas('invitation_link')
        ->assertSessionHas('invitation_email', $invitedEmail);

    $invitation = WorkspaceMember::query()
        ->where('workspace_id', $workspace->id)
        ->where('invited_email', $invitedEmail)
        ->firstOrFail();

    expect($invitation->member_role)->toBe('partner')
        ->and($invitation->invitation_status)->toBe('pending')
        ->and($invitation->permissions)->toBe(['approved_workspace_read_only'])
        ->and($invitation->invitation_token_hash)->not->toBeNull()
        ->and($invitation->invitation_expires_at?->isFuture())->toBeTrue()
        ->and($invitation->invitation_expires_at?->lte(now()->addDays(8)))->toBeTrue()
        ->and(Route::has('workspace-invitations.shareable.store'))->toBeFalse();

    $invitationLink = $response->getSession()->get('invitation_link');
    expect($invitationLink)->toContain('/workspace-invitations/');

    $this->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('PBR က email အလိုအလျောက်မပို့သေးပါ')
        ->assertSee('Create Secure Link')
        ->assertDontSee('Shareable');
});

test('invitation can only be accepted by the exact invited email', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    [$invitation, $token] = invitationAccessPending(
        $workspace,
        $owner,
        'invited.partner@example.com'
    );
    $wrongAccount = invitationAccessUser([
        'email' => 'wrong.partner@example.com',
    ]);

    $this->actingAs($wrongAccount)
        ->post(route('workspace-invitations.accept', ['token' => $token]))
        ->assertSessionHasErrors('invitation');

    expect($invitation->fresh()->invitation_status)->toBe('pending')
        ->and($wrongAccount->canAccessWorkspace($workspace))->toBeFalse();
});

test('invited account accepts once as a read only partner without changing account type', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    $partner = invitationAccessUser([
        'email' => 'accepted.partner@example.com',
    ]);
    [$invitation, $token] = invitationAccessPending(
        $workspace,
        $owner,
        $partner->email
    );

    $this->actingAs($partner)
        ->post(route('workspace-invitations.accept', ['token' => $token]))
        ->assertRedirect(route('workspaces.show', $workspace));

    $invitation->refresh();
    $partner->refresh();

    expect($invitation->invitation_status)->toBe('accepted')
        ->and($invitation->member_role)->toBe('partner')
        ->and($invitation->invitation_token_hash)->toBeNull()
        ->and($invitation->permissions)->toBe(['approved_workspace_read_only'])
        ->and($partner->role)->toBe('public')
        ->and($partner->isStudent())->toBeFalse()
        ->and($partner->canAccessWorkspace($workspace))->toBeTrue();

    $this->post(route('workspace-invitations.accept', ['token' => $token]))
        ->assertNotFound();
});

test('expired invitation cannot be opened or accepted', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    $partner = invitationAccessUser([
        'email' => 'late.partner@example.com',
    ]);
    [$invitation, $token] = invitationAccessPending(
        $workspace,
        $owner,
        $partner->email,
        now()->subMinute()
    );

    $this->get(route('workspace-invitations.show', ['token' => $token]))
        ->assertStatus(410);

    $this->actingAs($partner)
        ->post(route('workspace-invitations.accept', ['token' => $token]))
        ->assertStatus(410);

    expect($invitation->fresh()->invitation_status)->toBe('pending')
        ->and($partner->canAccessWorkspace($workspace))->toBeFalse();
});

test('owner can remove an accepted partner and access ends immediately', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    $studentPartner = invitationAccessUser([
        'role' => 'student',
    ]);
    StudentEnrollment::query()->create([
        'user_id' => $studentPartner->id,
        'class_session_id' => null,
        'student_access_code_id' => null,
        'status' => 'active',
        'started_at' => now()->subDay(),
        'access_expires_at' => now()->addYear(),
    ]);
    $membership = invitationAccessAccepted($workspace, $owner, $studentPartner);

    expect($studentPartner->canAccessWorkspace($workspace))->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('workspace-members.destroy', [$workspace, $membership]))
        ->assertRedirect(route('workspaces.show', $workspace));

    $membership->refresh();
    $studentPartner->refresh();

    expect($membership->invitation_status)->toBe('removed')
        ->and($membership->permissions)->toBeNull()
        ->and($studentPartner->isStudent())->toBeTrue()
        ->and($studentPartner->canUsePbrAiAdvisor())->toBeTrue()
        ->and($studentPartner->canAccessWorkspace($workspace))->toBeFalse();

    $this->actingAs($studentPartner)
        ->get(route('workspaces.show', $workspace))
        ->assertForbidden();

    $this->get(route('student.dashboard'))
        ->assertOk();
});

test('non owner cannot revoke invitations or remove accepted partners', function () {
    $owner = invitationAccessUser([
        'role' => 'student',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = invitationAccessWorkspace($owner);
    $partner = invitationAccessUser();
    $accepted = invitationAccessAccepted($workspace, $owner, $partner);
    [$pending] = invitationAccessPending(
        $workspace,
        $owner,
        'another.partner@example.com'
    );

    $this->actingAs($partner)
        ->delete(route('workspace-invitations.revoke', [$workspace, $pending]))
        ->assertForbidden();

    $this->delete(route('workspace-members.destroy', [$workspace, $accepted]))
        ->assertForbidden();

    expect($pending->fresh()->invitation_status)->toBe('pending')
        ->and($accepted->fresh()->invitation_status)->toBe('accepted');
});
