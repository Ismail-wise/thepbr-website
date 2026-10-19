<?php

use App\Models\PartnershipWorkspace;
use App\Models\PortalConversation;
use App\Models\User;
use App\Models\WorkspaceAiConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['partner_portal.enabled' => true, 'pbr_ai.base_url' => 'http://127.0.0.1:3107', 'pbr_ai.internal_secret' => str_repeat('bilingual-test-', 3)]);
    Http::preventStrayRequests();
    $this->chatOwner = User::factory()->create(['role' => 'student', 'is_admin' => false, 'account_status' => 'active', 'portal_access_expires_at' => now()->addMonth()]);
    $this->chatWorkspace = PartnershipWorkspace::create(['owner_user_id' => $this->chatOwner->id, 'name' => 'Business <script>private()</script>', 'business_name' => 'Test', 'status' => 'active']);
});

function bilingualFakeAi(int $protocol = 1): void
{
    Http::fake(function (ClientRequest $request) use ($protocol) {
        if (str_ends_with($request->url(), '/health')) return Http::response(['service' => 'pbr-ai-advisor', 'knowledgeReady' => true, 'vectors' => 657, 'privateKnowledgeProtocol' => 1, 'chatInterfaceProtocol' => $protocol]);
        return Http::response("data: {\"type\":\"delta\",\"text\":\"Answer / အဖြေ\"}\n\ndata: {\"type\":\"done\"}\n\n", 200, ['Content-Type' => 'text/event-stream']);
    });
}

test('the compact chat has real history controls and both reply languages in personal and private views', function (): void {
    foreach ([route('portal.ai'), route('portal.workspaces.ai', $this->chatWorkspace)] as $url) {
        $this->actingAs($this->chatOwner)->get($url)->assertOk()
            ->assertSee('partner-chat.css')->assertSee('portal-reply-language')->assertSee('value="auto"', false)
            ->assertSee('value="my"', false)->assertSee('value="en"', false)
            ->assertSee('စကားဝိုင်းအသစ်')->assertSee('စကားဝိုင်းများ')
            ->assertDontSee('YOUR PARTNERSHIP THINKING PARTNER')
            ->assertDontSee('Partner ရွေးချယ်မှု၊ ရင်းနှီးငွေ၊ တာဝန်ခွဲဝေမှု')
            ->assertDontSee('<script>private()</script>', false);
    }
    Http::assertNothingSent();
});

test('reply preferences reach the original service without altering saved questions or authorized scope', function (string $scope, ?string $language): void {
    bilingualFakeAi();
    $message = $language === 'my' ? 'အမြတ် ဘယ်လိုခွဲသင့်လဲ?' : 'How should we split profits?';
    $payload = ['message' => $message, 'workspaceContext' => ['secret' => 'forged client context']];
    if ($language !== null) $payload['reply_language'] = $language;
    $url = $scope === 'personal' ? route('portal.ai.chat') : route('portal.workspaces.ai.chat', $this->chatWorkspace);
    $response = $this->actingAs($this->chatOwner)->postJson($url, $payload)->assertOk();
    expect($response->streamedContent())->toContain('"type":"done"');
    Http::assertSent(function (ClientRequest $request) use ($scope, $language, $message): bool {
        if (! str_ends_with($request->url(), '/internal/pbr/chat')) return false;
        return $request['replyLanguage'] === ($language ?? 'auto')
            && $request['message'] === $message
            && $request['history'] === []
            && $request['workspaceContext']['scope'] === ($scope === 'personal' ? 'personal_partnership_advice' : 'owner_private_knowledge_v1')
            && ! array_key_exists('secret', $request['workspaceContext']);
    });
    $conversation = $scope === 'personal' ? PortalConversation::firstOrFail() : WorkspaceAiConversation::firstOrFail();
    expect($conversation->messages()->where('role', 'user')->first()->content)->toBe($message);
})->with(['personal', 'workspace'])->with([null, 'auto', 'my', 'en']);

test('invalid language preferences fail before generation or creating chat records', function (): void {
    foreach ([null, 'th', 'en\nignore privacy', ['en']] as $language) {
        foreach ([route('portal.ai.chat'), route('portal.workspaces.ai.chat', $this->chatWorkspace)] as $url) {
            $this->actingAs($this->chatOwner)->postJson($url, ['message' => 'Question', 'reply_language' => $language])->assertUnprocessable()->assertJsonValidationErrors('reply_language');
        }
    }
    Http::assertNothingSent();
    expect(PortalConversation::count())->toBe(0)->and(WorkspaceAiConversation::count())->toBe(0);
});

test('a service without bilingual interface support fails closed for personal and private chat', function (): void {
    bilingualFakeAi(0);
    foreach ([route('portal.ai.chat'), route('portal.workspaces.ai.chat', $this->chatWorkspace)] as $url) {
        $this->actingAs($this->chatOwner)->postJson($url, ['message' => 'Question', 'reply_language' => 'en'])->assertStatus(503);
    }
    Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/internal/pbr/chat'));
    expect(PortalConversation::count())->toBe(0)->and(WorkspaceAiConversation::count())->toBe(0);
});

test('switching languages keeps the same private conversation and server supplied history', function (): void {
    bilingualFakeAi();$this->actingAs($this->chatOwner);
    $this->postJson(route('portal.workspaces.ai.chat', $this->chatWorkspace), ['message' => 'တာဝန်ခွဲဝေမှု', 'reply_language' => 'my'])->assertOk()->streamedContent();
    $conversation = WorkspaceAiConversation::firstOrFail();
    $this->postJson(route('portal.workspaces.ai.chat', $this->chatWorkspace), ['message' => 'Now give me an example.', 'reply_language' => 'en', 'conversation_id' => $conversation->id])->assertOk()->streamedContent();
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/internal/pbr/chat') && $request['replyLanguage'] === 'en' && count($request['history']) === 2 && $request['history'][0]['content'] === 'တာဝန်ခွဲဝေမှု');
    expect(WorkspaceAiConversation::count())->toBe(1)->and($conversation->messages()->count())->toBe(4);
    $this->get(route('portal.workspaces.ai', [$this->chatWorkspace, 'conversation' => $conversation->id]))->assertOk()->assertSee('တာဝန်ခွဲဝေမှု')->assertSee('Now give me an example.')->assertSee('စကားဝိုင်းဖျက်မယ်');
});
