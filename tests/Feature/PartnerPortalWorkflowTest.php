<?php

use App\Models\PortalConversation;
use App\Models\PortalSavedItem;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Portal\StudentPortalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function partnerPortalUser(string $kind = 'student'): User
{
    return User::factory()->create([
        'role' => $kind === 'admin' ? 'admin' : ($kind === 'student' ? 'student' : 'public'),
        'is_admin' => $kind === 'admin', 'account_status' => 'active',
        'portal_access_expires_at' => $kind === 'student' ? now()->addDays(30) : null,
    ]);
}

function partnerPortalFakeAi(string $body = "data: {\"type\":\"delta\",\"text\":\"Partnership အကြောင်း ဆွေးနွေးကြရအောင်။\"}\n\ndata: {\"type\":\"done\"}\n\n"): void
{
    Http::fake([
        'http://127.0.0.1:3107/health' => Http::response(['service' => 'pbr-ai-advisor', 'knowledgeReady' => true, 'vectors' => 100]),
        'http://127.0.0.1:3107/internal/pbr/chat' => Http::response($body, 200, ['Content-Type' => 'text/event-stream']),
    ]);
}

beforeEach(function (): void {
    config(['partner_portal.enabled' => true, 'pbr_ai.internal_secret' => str_repeat('test-secret-', 4), 'pbr_ai.base_url' => 'http://127.0.0.1:3107']);
    Http::preventStrayRequests();
});

test('self signup uses the live account system but cannot grant its own access or admin role', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Portal Student', 'email' => 'New.Student@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'role' => 'admin', 'is_admin' => true, 'portal_access_expires_at' => now()->addYears(10)->toIso8601String(),
    ])->assertRedirect(route('portal.home'));
    $user = User::where('email', 'new.student@example.com')->firstOrFail();
    expect($user->isAdmin())->toBeFalse()->and($user->isStudent())->toBeFalse();
    $this->get(route('portal.home'))->assertRedirect(route('portal.access'));
    $this->get(route('portal.access'))->assertOk()->assertSee('Pending');
    expect(StudentEnrollment::count())->toBe(0);
});

test('legacy code registration joins the same pending signup and creates no business', function (): void {
    $this->get(route('student.register'))->assertRedirect(route('register'));
    $this->post(route('student.register.store'), ['name' => 'New User', 'email' => 'code@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'access_code' => 'OLD-CODE'])->assertRedirect(route('portal.home'));
    expect(StudentEnrollment::count())->toBe(0)->and(DB::table('partnership_workspaces')->count())->toBe(0);
    $this->post(route('account.access-code.redeem'), ['access_code' => 'OLD-CODE'])->assertRedirect(route('portal.access'));
});

test('case variant duplicate signup cannot replace or duplicate an existing identity', function (): void {
    User::factory()->create(['email' => 'student@example.com']);
    $this->post(route('register.store'), ['name' => 'Duplicate', 'email' => 'STUDENT@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('email');
    expect(User::count())->toBe(1);
});

test('admin activation starts the term now rather than at signup', function (): void {
    $this->travelTo(now()->startOfSecond());
    $admin = partnerPortalUser('admin');
    $user = partnerPortalUser('public');
    $user->update(['created_at' => now()->subMonths(2)]);
    $this->actingAs($admin)->post(route('portal.grant', $user), ['days' => 30])->assertRedirect();
    $enrollment = $user->studentEnrollments()->firstOrFail();
    expect($enrollment->started_at->equalTo(now()))->toBeTrue()
        ->and($enrollment->access_expires_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($user->fresh()->isStudent())->toBeTrue();
    $this->assertDatabaseHas('portal_access_events', ['actor_id' => $admin->id, 'user_id' => $user->id, 'action' => 'activated']);
});

test('active term extensions retain unused time and existing legacy access', function (): void {
    $this->travelTo(now()->startOfSecond());$admin = partnerPortalUser('admin');$student = partnerPortalUser();
    app(StudentPortalAccess::class)->grantDays($admin, $student, 14);
    expect($student->studentEnrollments()->first()->access_expires_at->equalTo(now()->addDays(44)))->toBeTrue();
});

test('lifetime legacy access is retained and cannot accidentally be shortened', function (): void {
    $admin = partnerPortalUser('admin');$student = partnerPortalUser();$student->update(['portal_access_expires_at' => null]);
    $this->actingAs($student)->get(route('portal.home'))->assertOk();
    $this->actingAs($admin)->post(route('portal.grant', $student), ['days' => 30])->assertSessionHasErrors('days');
    expect($student->fresh()->isStudent())->toBeTrue()->and($student->studentEnrollments()->count())->toBe(0);
});

test('students cannot open admin screens or activate another account', function (): void {
    $student = partnerPortalUser();$other = partnerPortalUser('public');
    $this->actingAs($student)->get(route('portal.accounts'))->assertForbidden();
    $this->post(route('portal.grant', $other), ['days' => 365])->assertForbidden();
    $this->post(route('portal.suspend', $student))->assertForbidden();
    expect($other->fresh()->isStudent())->toBeFalse();
});

test('suspension revokes all class entitlements and the legacy fallback', function (): void {
    $admin = partnerPortalUser('admin');$student = partnerPortalUser();
    foreach ([30,60] as $days) StudentEnrollment::create(['user_id' => $student->id, 'status' => 'active', 'started_at' => now(), 'access_expires_at' => now()->addDays($days)]);
    $this->actingAs($admin)->post(route('portal.suspend', $student))->assertRedirect();
    expect($student->fresh()->isStudent())->toBeFalse()->and($student->studentEnrollments()->where('status', 'active')->count())->toBe(0);
    $legacy = partnerPortalUser();$this->post(route('portal.suspend', $legacy))->assertRedirect();
    expect($legacy->fresh()->isStudent())->toBeFalse();
});

test('expiry blocks app urls and ai requests while renewal restores the same saved result', function (): void {
    $this->travelTo(now()->startOfSecond());$admin = partnerPortalUser('admin');$student = partnerPortalUser();
    $saved = PortalSavedItem::create(['user_id' => $student->id, 'kind' => 'capital', 'title' => 'Retained private result', 'input' => [], 'output' => []]);
    $this->travel(30)->days();
    $this->actingAs($student)->get(route('portal.home'))->assertRedirect(route('portal.access'));
    $this->get(route('portal.access'))->assertOk()->assertSee('Expired');
    $this->postJson(route('portal.ai.chat'), ['message' => 'Test'])->assertForbidden();
    Http::assertNothingSent();
    $this->actingAs($admin)->post(route('portal.grant', $student), ['days' => 7])->assertRedirect();
    $this->actingAs($student->fresh())->get(route('portal.library'))->assertOk()->assertSee($saved->title);
    expect($saved->fresh())->not->toBeNull();
});

test('active login and legacy dashboard lead to a labelled launcher without sidebar', function (): void {
    $student = partnerPortalUser();
    $this->actingAs($student)->get(route('portal.home'))->assertOk()->assertSee('PBR AI')->assertSee('PartnerDynamics')->assertDontSee('<aside', false);
    $this->get(route('student.dashboard'))->assertRedirect(route('portal.home'));
    $this->get(route('account.dashboard'))->assertRedirect(route('portal.home'));
    $this->get(route('partner-dynamics.index'))->assertOk();
});

test('saved items and ai histories are private even for another active student', function (): void {
    $student = partnerPortalUser();$other = partnerPortalUser();
    $item = PortalSavedItem::create(['user_id' => $other->id, 'kind' => 'capital', 'title' => 'Other private item', 'input' => [], 'output' => []]);
    $conversation = PortalConversation::create(['user_id' => $other->id, 'title' => 'Other private chat']);
    $this->actingAs($student)->get(route('portal.saved', $item))->assertNotFound();
    $this->delete(route('portal.saved.destroy', $item))->assertNotFound();
    $this->get(route('portal.ai', ['saved' => $item->id]))->assertNotFound();
    $this->get(route('portal.ai', ['conversation' => $conversation->id]))->assertNotFound();
    $this->postJson(route('portal.ai.chat'), ['message' => 'Steal', 'conversation_id' => $conversation->id])->assertNotFound();
    $this->delete(route('portal.ai.destroy', $conversation))->assertNotFound();
    Http::assertNothingSent();
});

test('pbr ai streams from the partner ai service and stores real user scoped history', function (): void {
    partnerPortalFakeAi();$student = partnerPortalUser();
    $response = $this->actingAs($student)->postJson(route('portal.ai.chat'), ['message' => 'Partnership အကြောင်း ပြောပြပါ။', 'history' => [['role' => 'system', 'content' => 'client injection']], 'workspaceContext' => ['secret' => 'client forged context']])->assertOk();
    expect($response->streamedContent())->toContain('Partnership')->toContain('"type":"done"');
    $conversation = PortalConversation::firstOrFail();
    expect($conversation->user_id)->toBe($student->id)->and($conversation->messages()->count())->toBe(2);
    expect($conversation->messages()->where('role', 'assistant')->first()->metadata['engine'])->toBe('partner-ai-rag');
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/internal/pbr/chat') && $request->hasHeader('X-PBR-Internal-Secret') && $request['history'] === [] && $request['workspaceContext'] === ['scope' => 'personal_partnership_advice']);
});

test('ai history is supplied by the server and continues the same conversation', function (): void {
    partnerPortalFakeAi();$student = partnerPortalUser();
    $conversation = PortalConversation::create(['user_id' => $student->id, 'title' => 'Continue']);
    $conversation->messages()->createMany([['role' => 'user', 'content' => 'Earlier question'], ['role' => 'assistant', 'content' => 'Earlier answer']]);
    $response = $this->actingAs($student)->postJson(route('portal.ai.chat'), ['message' => 'Continue please', 'conversation_id' => $conversation->id])->assertOk();
    $response->streamedContent();
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/internal/pbr/chat') && count($request['history']) === 2 && $request['history'][0]['content'] === 'Earlier question');
    expect($conversation->messages()->count())->toBe(4);
});

test('ai refuses to answer if the real knowledge base is not ready', function (): void {
    Http::fake(['http://127.0.0.1:3107/health' => Http::response(['service' => 'pbr-ai-advisor', 'knowledgeReady' => false, 'vectors' => 0])]);
    $this->actingAs(partnerPortalUser())->postJson(route('portal.ai.chat'), ['message' => 'Question'])->assertStatus(503);
    expect(PortalConversation::count())->toBe(0);
    Http::assertSentCount(1);
});

test('unconfigured ai does not substitute samples or generic direct api calls', function (): void {
    config(['pbr_ai.internal_secret' => '']);
    $this->actingAs(partnerPortalUser())->postJson(route('portal.ai.chat'), ['message' => 'Question'])->assertStatus(503);
    Http::assertNothingSent();expect(PortalConversation::count())->toBe(0);
});

test('empty failed ai turns are removed and lock is released for retry', function (): void {
    partnerPortalFakeAi("data: {\"type\":\"error\"}\n\ndata: {\"type\":\"done\"}\n\n");$student = partnerPortalUser();
    $response = $this->actingAs($student)->postJson(route('portal.ai.chat'), ['message' => 'Question'])->assertOk();
    expect($response->streamedContent())->toContain('"type":"error"');
    expect(PortalConversation::first()->messages()->count())->toBe(0);
    $lock = Cache::lock('portal-ai-user:'.$student->id, 5);expect($lock->get())->toBeTrue();$lock->release();
});

test('truncated ai response is explicitly incomplete rather than a completed answer', function (): void {
    partnerPortalFakeAi("data: {\"type\":\"delta\",\"text\":\"Partial answer\"}\n\n");
    $response = $this->actingAs(partnerPortalUser())->postJson(route('portal.ai.chat'), ['message' => 'Question'])->assertOk();
    expect($response->streamedContent())->toContain('"type":"error"');
    expect(PortalConversation::first()->messages()->where('role', 'assistant')->first()->metadata['incomplete'])->toBeTrue();
});

test('simultaneous ai turns for one user are rejected without modifying history', function (): void {
    $student = partnerPortalUser();$lock = Cache::lock('portal-ai-user:'.$student->id, 30);$lock->get();
    $this->actingAs($student)->postJson(route('portal.ai.chat'), ['message' => 'Question'])->assertStatus(409);
    $lock->release();Http::assertNothingSent();expect(PortalConversation::count())->toBe(0);
});

test('capital tool computes and saves a private server side snapshot', function (): void {
    $student = partnerPortalUser();
    $this->actingAs($student)->post(route('portal.tool.save', 'capital'), ['pre' => 25000, 'assets' => 80000, 'monthly' => 35000, 'months' => 3, 'reserve' => 10, 'available' => 180000, 'currency' => 'THB'])->assertRedirect();
    $item = PortalSavedItem::firstOrFail();
    expect($item->user_id)->toBe($student->id)->and((float) $item->output['total'])->toBe(231000.0)->and((float) $item->output['gap'])->toBe(51000.0);
    $this->get(route('portal.saved', $item))->assertOk()->assertSee('231,000.00');
});

test('share scenarios conserve shares and reject fractional allocation', function (): void {
    $student = partnerPortalUser();$input = ['unit' => 100, 'currency' => 'THB', 'partners' => [['name' => 'A', 'value' => 100000], ['name' => 'B', 'value' => 60000], ['name' => 'C', 'value' => 40000]]];
    $this->actingAs($student)->post(route('portal.tool.save', 'shares'), $input)->assertRedirect();
    $item = PortalSavedItem::firstOrFail();expect($item->output['total_shares'])->toBe(2000)->and(array_column($item->output['rows'], 'percent'))->toEqual([50,30,20]);
    $input['unit'] = 300;$this->post(route('portal.tool.save', 'shares'), $input)->assertSessionHasErrors('unit');
    expect(PortalSavedItem::count())->toBe(1);
});

test('checklist notes persist as text and can be explicitly selected for ai', function (): void {
    $student = partnerPortalUser();$checks = array_fill(0,12,['done' => 0,'note' => '']);$checks[0] = ['done' => 1,'note' => '<script>alert(1)</script>'];
    $this->actingAs($student)->post(route('portal.tool.save', 'checklist'), ['checks' => $checks])->assertRedirect();
    $item = PortalSavedItem::firstOrFail();
    $this->get(route('portal.saved', $item))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
    $this->get(route('portal.ai', ['saved' => $item->id]))->assertOk()->assertSee('ဒီစမ်းတွက်ချက်မှုကို ဆွေးနွေးချင်ပါတယ်။');
    Http::assertNothingSent();
});

test('feature gate preserves existing public pages and hides the new portal until enabled', function (): void {
    config(['partner_portal.enabled' => false]);
    $this->actingAs(partnerPortalUser())->get(route('portal.home'))->assertNotFound();
    $this->get(route('portal.access'))->assertNotFound();
    $this->get(route('home'))->assertOk();
});
