<?php

use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['partner_portal.enabled' => true]);
    Http::preventStrayRequests();
});

function privateWorkspaceStudent(array $values = []): User
{
    return User::factory()->create($values + [
        'role' => 'student', 'is_admin' => false, 'account_status' => 'active',
        'portal_access_expires_at' => now()->addMonth(),
    ]);
}

function privateWorkspacePublic(array $values = []): User
{
    return User::factory()->create($values + [
        'role' => 'public', 'is_admin' => false, 'account_status' => 'active',
        'portal_access_expires_at' => null,
    ]);
}

function privateWorkspace(User $owner, string $name = 'Private business'): PartnershipWorkspace
{
    return PartnershipWorkspace::create([
        'owner_user_id' => $owner->id, 'name' => $name, 'business_name' => $name,
        'description' => 'Owner description', 'status' => 'active',
    ]);
}

function privateWorkspaceInvitation(PartnershipWorkspace $workspace, User $partner, string $status = 'pending'): array
{
    $token = Str::random(64);
    $member = $workspace->memberships()->create([
        'user_id' => $partner->id, 'member_role' => 'partner',
        'invitation_status' => $status, 'invited_email' => strtolower($partner->email),
        'invited_by_user_id' => $workspace->owner_user_id,
        'invitation_token_hash' => $status === 'pending' ? WorkspaceMember::fingerprintInvitationToken($token) : null,
        'invited_at' => now(), 'invitation_expires_at' => now()->addDays(7),
        'accepted_at' => $status === 'accepted' ? now() : null,
        // Old, broad permissions must never bypass the new boundary either.
        'permissions' => ['approved_workspace_read_only'],
    ]);

    return [$member, $token];
}

test('active students create multiple owned workspaces with a name and optional description only', function (): void {
    $owner = privateWorkspaceStudent();
    $other = privateWorkspaceStudent();
    $this->actingAs($owner)->post(route('portal.workspaces.store'), [
        'name' => '  မြန်မာ Business One  ', 'description' => '  Owner notes  ',
        'owner_user_id' => $other->id, 'status' => 'shared', 'business_stage' => 'existing', 'currency_code' => 'USD',
    ])->assertRedirect();
    $first = PartnershipWorkspace::latest('id')->firstOrFail();
    expect($first->owner_user_id)->toBe($owner->id)
        ->and($first->name)->toBe('မြန်မာ Business One')->and($first->description)->toBe('Owner notes')
        ->and($first->status)->toBe('active')->and($first->business_stage)->toBeNull()->and($first->currency_code)->toBeNull()
        ->and($first->memberships()->where('user_id', $owner->id)->where('member_role', 'owner')->where('invitation_status', 'accepted')->exists())->toBeTrue();
    $this->post(route('portal.workspaces.store'), ['name' => 'Second business'])->assertRedirect();
    expect($owner->ownedWorkspaces()->count())->toBe(2)->and($other->ownedWorkspaces()->count())->toBe(0);
    $this->get(route('portal.workspaces.index'))->assertOk()->assertSee('Second business')->assertSee('မြန်မာ Business One');
    $this->post(route('portal.workspaces.store'), ['name' => '  '])->assertSessionHasErrors('name');
    $this->post(route('portal.workspaces.store'), ['name' => 'X', 'description' => str_repeat('x', 2001)])->assertSessionHasErrors('description');
});

test('workspace lists edits and membership controls stay owner only including against application admins', function (): void {
    $owner = privateWorkspaceStudent();
    $other = privateWorkspaceStudent();
    $admin = privateWorkspacePublic(['role' => 'admin', 'is_admin' => true]);
    $workspace = privateWorkspace($owner, 'Owner confidential name');
    privateWorkspace($other, 'Other confidential name');
    $this->actingAs($owner)->get(route('portal.workspaces.index'))->assertOk()
        ->assertSee('Owner confidential name')->assertDontSee('Other confidential name');
    $this->put(route('portal.workspaces.update', $workspace), ['name' => 'Updated business', 'description' => '<script>private()</script>', 'owner_user_id' => $other->id])->assertRedirect();
    $this->get(route('portal.workspaces.show', $workspace))->assertOk()->assertSee('&lt;script&gt;private()&lt;/script&gt;', false)
        ->assertDontSee('<script>private()</script>', false)->assertHeader('Cache-Control', 'no-store, private');
    expect($workspace->fresh()->owner_user_id)->toBe($owner->id);
    foreach ([$other, $admin] as $outsider) {
        $this->actingAs($outsider)->get(route('portal.workspaces.show', $workspace))->assertStatus($outsider->isStudent() ? 404 : 403);
        $this->get(route('portal.workspaces.partners', $workspace))->assertStatus($outsider->isStudent() ? 404 : 403);
        $this->put(route('portal.workspaces.update', $workspace), ['name' => 'Stolen'])->assertStatus($outsider->isStudent() ? 404 : 403);
        $this->post(route('workspace-invitations.store', $workspace), ['email' => 'intruder@example.invalid'])->assertForbidden();
    }
    expect($workspace->fresh()->name)->toBe('Updated business');
});

test('guest pending expired and inactive accounts cannot create owner workspaces', function (): void {
    $this->get(route('portal.workspaces.create'))->assertRedirect(route('login'));
    foreach ([privateWorkspacePublic(), privateWorkspaceStudent(['portal_access_expires_at' => now()->subSecond()])] as $user) {
        $this->actingAs($user)->get(route('portal.workspaces.index'))->assertForbidden();
        $this->post(route('portal.workspaces.store'), ['name' => 'Denied'])->assertForbidden();
    }
    $this->actingAs(privateWorkspaceStudent(['account_status' => 'inactive']))
        ->post(route('portal.workspaces.store'), ['name' => 'Denied'])->assertRedirect(route('login'));
    expect(PartnershipWorkspace::count())->toBe(0);
});

test('owner invitation issues an email bound link and partner accepts without student approval', function (): void {
    $owner = privateWorkspaceStudent();
    $workspace = privateWorkspace($owner);
    $partner = privateWorkspacePublic();
    $response = $this->actingAs($owner)->post(route('workspace-invitations.store', $workspace), ['email' => strtoupper($partner->email)])
        ->assertRedirect(route('portal.workspaces.partners', $workspace))->assertSessionHas('invitation_link');
    $url = $response->getSession()->get('invitation_link');
    $member = $workspace->memberships()->where('member_role', 'partner')->firstOrFail();
    expect($member->permissions)->toBe(['partner_dynamics_only']);
    $this->get(route('portal.workspaces.partners', $workspace))->assertOk()->assertSee($url, false)->assertSee('Email အလိုအလျောက် မပို့ထားပါ');
    $this->actingAs($partner)->get($url)->assertOk()->assertSee('Accept · Partner Dynamics')->assertDontSee('Approved Current Rules')
        ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
    $this->post(route('workspace-invitations.accept', ['token' => basename($url)]))->assertRedirect(route('portal.home'));
    expect($partner->fresh()->role)->toBe('public')->and($partner->fresh()->is_admin)->toBeFalse()
        ->and($partner->studentEnrollments()->count())->toBe(0)->and($partner->fresh()->isStudent())->toBeFalse()
        ->and($member->fresh()->permissions)->toBe(['partner_dynamics_only']);
    $this->get(route('portal.home'))->assertOk()->assertSee('PartnerDynamics')->assertSee('Partner Dynamics-only Access')
        ->assertDontSee('PBR AI')->assertDontSee('Capital Calculator')->assertDontSee('My Workspaces')->assertDontSee('My Library')->assertDontSee('Admin Portal');
    $this->get(route('portal.access'))->assertRedirect(route('portal.home'));
    $this->post(route('workspace-invitations.accept', ['token' => basename($url)]))->assertNotFound();
    $this->get('/admin')->assertForbidden();
});

test('invitation survives signup roundtrip without silently activating student access', function (): void {
    $owner = privateWorkspaceStudent();
    $workspace = privateWorkspace($owner);
    $placeholder = privateWorkspacePublic(['email' => 'invitee@example.invalid']);
    [$member, $token] = privateWorkspaceInvitation($workspace, $placeholder);
    $member->update(['user_id' => null]);
    $placeholder->delete();
    $url = route('workspace-invitations.show', $token);
    $this->get($url)->assertOk()->assertSessionHas('url.intended', $url);
    $this->post(route('register.store'), ['name' => 'Real invitee', 'email' => 'invitee@example.invalid', 'password' => 'test-password', 'password_confirmation' => 'test-password'])->assertRedirect($url);
    $this->post(route('workspace-invitations.accept', $token))->assertRedirect(route('portal.home'));
    $invitee = User::where('email', 'invitee@example.invalid')->firstOrFail();
    expect($invitee->isStudent())->toBeFalse()->and($invitee->isPartner())->toBeTrue()->and($invitee->studentEnrollments()->count())->toBe(0);
});

test('wrong email expired links and revoked invitations never grant access', function (): void {
    $owner = privateWorkspaceStudent();$workspace = privateWorkspace($owner);
    $partner = privateWorkspacePublic();$wrong = privateWorkspacePublic();
    [$member, $token] = privateWorkspaceInvitation($workspace, $partner);
    $this->actingAs($wrong)->post(route('workspace-invitations.accept', $token))->assertSessionHasErrors('invitation');
    expect($member->fresh()->invitation_status)->toBe('pending');
    $member->update(['invitation_expires_at' => now()->subMinute()]);
    $this->actingAs($partner)->get(route('workspace-invitations.show', $token))->assertStatus(410);
    $this->post(route('workspace-invitations.accept', $token))->assertStatus(410);
    $this->actingAs($owner)->delete(route('workspace-invitations.revoke', [$workspace, $member]))->assertRedirect(route('portal.workspaces.partners', $workspace));
    $this->actingAs($partner)->get(route('workspace-invitations.show', $token))->assertNotFound();
    $this->post(route('workspace-invitations.accept', $token))->assertNotFound();
    expect($partner->hasAcceptedPartnerWorkspaceMembership())->toBeFalse();
});

test('an invitation only partner completes original dynamics but cannot use any student tool or private workspace', function (): void {
    $owner = privateWorkspaceStudent();$workspace = privateWorkspace($owner);
    $partner = privateWorkspacePublic();
    [$member] = privateWorkspaceInvitation($workspace, $partner, 'accepted');
    $this->actingAs($partner)->get(route('partner-dynamics.index'))->assertOk();
    $response = $this->post(route('partner-dynamics.start'))->assertRedirect();
    $assessment = \App\Models\PartnerDynamicsAssessment::where('user_id', $partner->id)->firstOrFail();
    for ($step = 1; $step <= 5; $step++) {
        $this->get(route('partner-dynamics.assessment.step', [$assessment, $step]))->assertOk();
        $keys = $step === 5 ? array_keys(config('partner_dynamics.scenario_questions')) : range((($step - 1) * 8) + 1, $step * 8);
        $this->put(route('partner-dynamics.assessment.save', [$assessment, $step]), ['answers' => array_fill_keys($keys, $step === 5 ? 'A' : 3)])->assertRedirect();
    }
    expect($assessment->fresh()->status)->toBe('completed');
    $this->get(route('partner-dynamics.result', $assessment))->assertOk();
    $this->post(route('partner-dynamics.retake'))->assertRedirect();
    $this->getJson(route('portal.ai'))->assertForbidden();
    $this->postJson(route('portal.ai.chat'), ['message' => 'forbidden'])->assertForbidden();
    foreach (['capital', 'shares', 'checklist'] as $tool) {
        $this->getJson(route('portal.tool', $tool))->assertForbidden();
        $this->postJson(route('portal.tool.save', $tool), [])->assertForbidden();
    }
    $this->getJson(route('portal.library'))->assertForbidden();
    $this->get(route('portal.workspaces.index'))->assertForbidden();
    $this->post(route('portal.workspaces.store'), ['name' => 'Not a student'])->assertForbidden();
    $this->get(route('workspaces.show', $workspace))->assertForbidden();
    $this->get(route('workspaces.partner-dynamics.show', $workspace))->assertForbidden();
    $this->get(route('portal.workspaces.show', $workspace))->assertForbidden();
    Http::assertNothingSent();
    $this->actingAs($owner)->get(route('partner-dynamics.result', $assessment))->assertForbidden();
});

test('all legacy workspace routes deny partners and unrelated admins even with old broad permissions', function (): void {
    $owner = privateWorkspaceStudent();$workspace = privateWorkspace($owner);
    $partner = privateWorkspacePublic();privateWorkspaceInvitation($workspace, $partner, 'accepted');
    $admin = privateWorkspacePublic(['is_admin' => true, 'role' => 'admin']);
    foreach ([$partner, $admin] as $actor) {
        $this->actingAs($actor);
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'workspaces.') || ! in_array('workspace', $route->parameterNames(), true)) continue;
            $parameters = array_fill_keys($route->parameterNames(), 999999);
            $parameters['workspace'] = $workspace->id;
            $response = $this->call($route->methods()[0], route($route->getName(), $parameters));
            expect(in_array($response->status(), [403,404], true))->toBeTrue($route->getName().' returned '.$response->status());
        }
    }
    expect($workspace->fresh()->name)->toBe('Private business');
    Http::assertNothingSent();
});

test('removal closes invitation only access immediately while preserving assessments and other student entitlements', function (): void {
    $owner = privateWorkspaceStudent();$workspace = privateWorkspace($owner);
    $partner = privateWorkspacePublic();[$membership] = privateWorkspaceInvitation($workspace, $partner, 'accepted');
    $this->actingAs($partner)->post(route('partner-dynamics.start'));
    $this->actingAs($owner)->delete(route('workspace-members.destroy', [$workspace, $membership]))->assertRedirect();
    $this->actingAs($partner)->get(route('partner-dynamics.index'))->assertForbidden();
    $this->get(route('portal.home'))->assertRedirect(route('portal.access'));
    expect(\App\Models\PartnerDynamicsAssessment::where('user_id', $partner->id)->count())->toBe(1);
    $student = privateWorkspaceStudent();
    $enrollment = StudentEnrollment::create(['user_id' => $student->id, 'status' => 'active', 'started_at' => now(), 'access_expires_at' => now()->addDays(24)]);
    $before = $enrollment->getAttributes();
    [$membership, $token] = privateWorkspaceInvitation($workspace, $student);
    $this->actingAs($student)->post(route('workspace-invitations.accept', $token))->assertRedirect(route('portal.home'));
    $this->actingAs($owner)->delete(route('workspace-members.destroy', [$workspace, $membership]))->assertRedirect();
    expect($enrollment->fresh()->getAttributes())->toBe($before)->and($student->fresh()->isStudent())->toBeTrue();
    $this->actingAs($student)->get(route('portal.home'))->assertOk()->assertSee('PBR AI')->assertSee('My Workspaces');
});

test('student expiry falls back to dynamics only when another accepted invitation exists', function (): void {
    $owner = privateWorkspaceStudent();$workspace = privateWorkspace($owner);
    $student = privateWorkspaceStudent();$own = privateWorkspace($student, 'Expired owner business');
    privateWorkspaceInvitation($workspace, $student, 'accepted');
    $student->update(['portal_access_expires_at' => now()->subMinute()]);
    $this->actingAs($student)->get(route('portal.home'))->assertOk()->assertSee('Partner Dynamics-only Access')->assertDontSee('My Workspaces');
    $this->get(route('portal.workspaces.show', $own))->assertForbidden();
    $this->getJson(route('portal.ai'))->assertForbidden();
    $this->get(route('partner-dynamics.index'))->assertOk();
});
