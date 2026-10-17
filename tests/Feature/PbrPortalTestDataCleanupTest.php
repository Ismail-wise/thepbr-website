<?php

use App\Filament\Resources\StudentAccessCodes\StudentAccessCodeResource;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Resources\WorkspaceMembers\WorkspaceMemberResource;
use App\Models\User;
use App\Services\Portal\PortalTestDataCleanup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function portalResetFixture(): array
{
    config(['partner_portal.enabled' => true]);
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'account_status' => 'active']);
    $student = User::factory()->create(['role' => 'student', 'is_admin' => false, 'account_status' => 'active']);
    $db = DB::connection();
    foreach ([$admin, $student] as $owner) {
        $workspace = $db->table('partnership_workspaces')->insertGetId(['owner_user_id' => $owner->id, 'name' => 'Test workspace', 'status' => 'active']);
        $db->table('workspace_members')->insert(['workspace_id' => $workspace, 'user_id' => $owner->id, 'member_role' => 'owner', 'invitation_status' => 'accepted']);
        $conversation = $db->table('ai_conversations')->insertGetId(['workspace_id' => $workspace, 'user_id' => $owner->id, 'title' => 'Workspace test chat']);
        $db->table('ai_messages')->insert(['conversation_id' => $conversation, 'role' => 'assistant', 'content' => 'Workspace test history']);
        $chat = $db->table('portal_conversations')->insertGetId(['user_id' => $owner->id, 'title' => 'Personal portal chat']);
        $db->table('portal_messages')->insert(['conversation_id' => $chat, 'role' => 'user', 'content' => 'Personal history']);
        $db->table('portal_saved_items')->insert(['user_id' => $owner->id, 'kind' => 'capital', 'title' => 'Saved scenario', 'input' => '{}', 'output' => '{}']);
        $db->table('partner_dynamics_assessments')->insert(['user_id' => $owner->id]);
        $db->table('sessions')->insert(['id' => 'session-'.$owner->id, 'user_id' => $owner->id, 'payload' => 'fixture', 'last_activity' => time()]);
        $db->table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'synthetic-reset-token']);
    }
    $db->table('articles')->insert(['title' => 'Keep public article', 'slug' => 'keep-public-article', 'category' => 'Agreement', 'excerpt' => 'Keep', 'body' => 'Keep body']);
    $db->table('videos')->insert(['title' => 'Keep public video', 'slug' => 'keep-public-video', 'youtube_id' => 'dQw4w9WgXcQ']);
    $db->table('class_sessions')->insert(['title' => 'Keep public class', 'starts_on' => '2026-11-01', 'location' => 'Online', 'enrolled' => 12]);
    $chapter = $db->table('course_chapters')->insertGetId(['chapter_number' => 1, 'slug' => 'keep-chapter', 'phase' => 'foundation', 'title_en' => 'Keep chapter', 'title_mm' => 'မူလသင်ခန်းစာ']);
    $tool = $db->table('chapter_tools')->insertGetId(['course_chapter_id' => $chapter, 'tool_key' => 'keep-tool', 'slug' => 'keep-tool', 'title_en' => 'Keep tool definition', 'tool_type' => 'calculator']);
    $db->table('tool_sessions')->insert(['user_id' => $admin->id, 'chapter_tool_id' => $tool, 'workspace_id' => $db->table('partnership_workspaces')->value('id'), 'input_data' => '{}']);

    return [$admin, $student, new PortalTestDataCleanup($db)];
}

test('authorized reset removes all test workspaces and non admins while preserving admin credentials content and personal history', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    $credentials = (array) DB::table('users')->where('id', $admin->id)->first();
    $plan = $cleanup->plan();
    $result = $cleanup->apply($plan);
    expect($result['workspaces_removed'])->toBe(2)->and($result['accounts_removed'])->toBe(1)
        ->and(User::count())->toBe(1)->and((array) DB::table('users')->where('id', $admin->id)->first())->toBe($credentials);
    foreach (['partnership_workspaces', 'workspace_members', 'tool_sessions', 'ai_conversations', 'ai_messages'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    foreach (['portal_conversations', 'portal_messages', 'portal_saved_items', 'partner_dynamics_assessments', 'articles', 'videos', 'class_sessions', 'course_chapters', 'chapter_tools', 'sessions', 'password_reset_tokens'] as $table) {
        expect(DB::table($table)->count())->toBe(1);
    }
    $this->actingAs($admin->fresh())->get('/admin')->assertOk()
        ->assertSee('Articles')->assertSee('Videos')->assertDontSee('Activation funnel')->assertDontSee('Partner Memberships');
    \Livewire\Livewire::test(\App\Filament\Widgets\PortalAccessWidget::class)->assertSee('Total Portal Users');
    $this->post(route('logout'));
    $this->post(route('register.store'), ['name' => 'Real new member', 'email' => 'fresh-member@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertRedirect(route('portal.home'));
    $new = User::where('email', 'fresh-member@example.com')->firstOrFail();
    expect($new->id)->toBeGreaterThan($student->id)->and($new->isAdmin())->toBeFalse()
        ->and(DB::table('partnership_workspaces')->count())->toBe(0);
});

test('all administrator identities are retained including inactive administrators', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    $other = User::factory()->create(['is_admin' => true, 'role' => 'public', 'account_status' => 'inactive']);
    $cleanup->apply($cleanup->plan());
    expect(User::orderBy('id')->pluck('id')->all())->toBe([$admin->id, $other->id]);
});

test('reset clears non admin audit references and legacy access codes without disabling foreign keys', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    DB::table('portal_access_events')->insert(['user_id' => $admin->id, 'actor_id' => $student->id, 'action' => 'synthetic_old_event', 'before_state' => '{}', 'after_state' => '{}', 'created_at' => now()]);
    $code = DB::table('student_access_codes')->insertGetId(['code_hash' => str_repeat('a', 64), 'code_encrypted' => 'fixture', 'code_last4' => 'TEST', 'created_by_user_id' => $admin->id, 'used_by_user_id' => $student->id]);
    DB::table('student_enrollments')->insert(['user_id' => $student->id, 'student_access_code_id' => $code, 'status' => 'active']);
    $cleanup->apply($cleanup->plan());
    expect(DB::table('portal_access_events')->count())->toBe(0)->and(DB::table('student_access_codes')->count())->toBe(0)
        ->and(DB::selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1);
});

test('cleanup refuses to remove data if no active Filament administrator will remain', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    $admin->update(['account_status' => 'inactive']);
    $before = $cleanup->fingerprint();
    expect(fn () => $cleanup->plan())->toThrow(RuntimeException::class, 'ACTIVE_FILAMENT_ADMIN_REQUIRED');
    expect($cleanup->fingerprint())->toBe($before);
});

test('changed plans and failed postconditions leave every original row intact', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    $stale = $cleanup->plan();
    User::factory()->create(['role' => 'public', 'is_admin' => false]);
    $before = $cleanup->fingerprint();
    expect(fn () => $cleanup->apply($stale))->toThrow(RuntimeException::class, 'RESET_PLAN_CHANGED_NO_DATA_REMOVED');
    expect($cleanup->fingerprint())->toBe($before);
    $plan = $cleanup->plan();
    $plan['after_fingerprint'] = str_repeat('0', 64);
    expect(fn () => $cleanup->apply($plan))->toThrow(RuntimeException::class, 'RESET_DIFFERS_FROM_MYSQL_REHEARSAL');
    expect($cleanup->fingerprint())->toBe($before);
});

test('unreviewed foreign key dependents block the reset before deleting anything', function (): void {
    [$admin, $student, $cleanup] = portalResetFixture();
    Schema::create('unknown_account_data', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    });
    $before = $cleanup->fingerprint();
    expect(fn () => $cleanup->plan())->toThrow(RuntimeException::class, 'UNREVIEWED_DATA_DEPENDENCY');
    expect($cleanup->fingerprint())->toBe($before);
    Schema::drop('unknown_account_data');
});

test('new admin navigation keeps public content and moves legacy entitlements under accounts', function (): void {
    [$admin] = portalResetFixture();
    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Accounts &amp; Access', false)->assertSee('Articles')->assertSee('Videos')->assertSee('Class Sessions')
        ->assertDontSee('Partner Memberships')->assertDontSee('Access Codes')->assertDontSee('Student Entitlements')
        ->assertDontSee('Activation funnel')->assertDontSee('Progress by business area');
    config(['partner_portal.enabled' => false]);
    expect(StudentEnrollmentResource::shouldRegisterNavigation())->toBeTrue()
        ->and(StudentAccessCodeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(WorkspaceMemberResource::shouldRegisterNavigation())->toBeTrue();
});
