<?php

use App\Models\PartnershipWorkspace;
use App\Models\PortalSavedItem;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceBusinessPlan;
use App\Models\WorkspaceBusinessPlanRevision;
use App\Models\WorkspaceKnowledgeChunk;
use App\Models\WorkspaceKnowledgeSource;
use App\Services\Portal\PartnerAiGateway;
use App\Services\Portal\WorkspaceKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['partner_portal.enabled' => true]);
    Http::preventStrayRequests();
});

function planOwner(array $attributes = []): User
{
    return User::factory()->create($attributes + [
        'role' => 'student', 'is_admin' => false, 'account_status' => 'active',
        'portal_access_expires_at' => now()->addMonth(),
    ]);
}

function planWorkspace(User $owner, string $name = 'Synthetic Lotus business'): PartnershipWorkspace
{
    return PartnershipWorkspace::create([
        'owner_user_id' => $owner->id, 'name' => $name, 'business_name' => $name, 'status' => 'active',
    ]);
}

function planValues(array $extra = []): array
{
    return $extra + [
        'version' => 0, 'business_name' => 'Synthetic Lotus Studio', 'business_stage' => 'planning',
        'value_proposition' => 'Small partnership workshops', 'customer_segments' => 'Local business owners',
        'demand_notes' => 'Synthetic interviews; demand is not established.',
        'break_even_notes' => 'Owner-entered estimate only.',
    ];
}

function planConfirm($test, User $owner, PartnershipWorkspace $workspace, array $extra = []): WorkspaceBusinessPlanRevision
{
    $version = WorkspaceBusinessPlan::where('workspace_id', $workspace->id)->value('lock_version') ?? 0;
    $test->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), planValues($extra + ['version' => $version]))->assertRedirect();
    $test->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => $version + 1, 'reviewed' => 1])->assertRedirect();

    return WorkspaceBusinessPlanRevision::whereHas('businessPlan', fn ($query) => $query->where('workspace_id', $workspace->id))->latest('revision')->firstOrFail();
}

test('business plan entry and read-only pages do not create records or call AI', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->get(route('portal.workspaces.show', $workspace))->assertOk()->assertSee('Business Plan');
    $this->get(route('portal.workspaces.business-plan.edit', $workspace))->assertOk()->assertSee($workspace->name)->assertSee('Save Draft');
    $this->get(route('portal.workspaces.business-plan.history', $workspace))->assertOk();
    $this->get(route('portal.workspaces.business-plan.review', $workspace))->assertRedirect(route('portal.workspaces.business-plan.edit', $workspace));
    expect(WorkspaceBusinessPlan::count())->toBe(0)->and(WorkspaceBusinessPlanRevision::count())->toBe(0);
    Http::assertNothingSent();
});

test('an incomplete draft is saved independently of confirmation and forged ownership fields are ignored', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $other = planWorkspace(planOwner());
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), [
        'version' => 0, 'business_name' => '  လုပ်ငန်းအကြမ်း  ', 'workspace_id' => $other->id,
        'confirmed_by_user_id' => $other->owner_user_id, 'lock_version' => 500, 'content' => ['stolen' => true],
    ])->assertRedirect();
    $plan = WorkspaceBusinessPlan::firstOrFail();
    expect($plan->workspace_id)->toBe($workspace->id)->and($plan->lock_version)->toBe(1)
        ->and($plan->draft['business_name'])->toBe('လုပ်ငန်းအကြမ်း')->and($plan->draft['value_proposition'])->toBe('')
        ->and(array_key_exists('content', $plan->draft))->toBeFalse()
        ->and(WorkspaceBusinessPlanRevision::count())->toBe(0)->and(WorkspaceKnowledgeSource::count())->toBe(0);
    $this->get(route('portal.workspaces.business-plan.review', $workspace))->assertOk()->assertSee('Confirm လုပ်ရန် ဖြည့်ဖို့ ကျန်နေပါတယ်။');
});

test('save and review includes the text just submitted and confirmation stores an immutable snapshot', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['intent' => 'review']))
        ->assertRedirect(route('portal.workspaces.business-plan.review', $workspace));
    $this->get(route('portal.workspaces.business-plan.review', $workspace))->assertOk()->assertSee('Small partnership workshops')->assertSee('name="reviewed"', false);
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 1, 'reviewed' => 1, 'content' => ['business_name' => 'Unreviewed replacement']])->assertRedirect();
    $plan = WorkspaceBusinessPlan::firstOrFail();
    $revision = WorkspaceBusinessPlanRevision::firstOrFail();
    expect($plan->draft)->toBeNull()->and($plan->lock_version)->toBe(2)->and($revision->revision)->toBe(1)
        ->and($revision->content['business_name'])->toBe('Synthetic Lotus Studio')->and($revision->confirmed_by_user_id)->toBe($owner->id)
        ->and($revision->knowledge_source_id)->toBeNull()->and(WorkspaceKnowledgeSource::count())->toBe(0);
    $this->get(route('portal.workspaces.business-plan.revision', [$workspace, $revision->id]))->assertOk()->assertSee('Confirmed')->assertSee('Version 1');
    Http::assertNothingSent();
});

test('confirmation requires review and the three displayed basic fields without losing the draft', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), ['version' => 0, 'business_name' => 'Incomplete'])->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 1])->assertSessionHasErrors('reviewed');
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 1, 'reviewed' => 1])->assertSessionHasErrors(['value_proposition', 'customer_segments']);
    expect(WorkspaceBusinessPlan::firstOrFail()->lock_version)->toBe(1)->and(WorkspaceBusinessPlanRevision::count())->toBe(0);
});

test('editing a confirmed plan preserves its earlier version and provenance', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $first = planConfirm($this, $owner, $workspace);
    $saved = $first->getAttributes();
    $this->get(route('portal.workspaces.business-plan.edit', $workspace))->assertOk()->assertSee('Small partnership workshops');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['version' => 2, 'value_proposition' => 'Updated workshop format']))->assertRedirect();
    $this->get(route('portal.workspaces.show', $workspace))->assertOk()->assertSee('Draft ရှိပါတယ်')->assertSee('Confirmed Version 1');
    expect($first->fresh()->getAttributes())->toBe($saved);
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 3, 'reviewed' => 1])->assertRedirect();
    $second = WorkspaceBusinessPlanRevision::latest('revision')->firstOrFail();
    expect($second->revision)->toBe(2)->and($second->based_on_revision)->toBe(1)
        ->and($second->content['value_proposition'])->toBe('Updated workshop format')->and($first->fresh()->getAttributes())->toBe($saved);
    $this->get(route('portal.workspaces.business-plan.history', $workspace))->assertOk()->assertSeeInOrder(['Version 2', 'Version 1']);
    $this->get(route('portal.workspaces.business-plan.revision', [$workspace, $first->id]))->assertOk()->assertSee('Small partnership workshops')->assertDontSee('Updated workshop format');
});

test('stale saves retain submitted text for recovery and never overwrite a newer draft', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), planValues())->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['business_name' => 'Unsaved text from old tab']))
        ->assertStatus(409)->assertSee('Unsaved text from old tab')->assertHeader('Cache-Control', 'no-store, private');
    expect(WorkspaceBusinessPlan::count())->toBe(1)->and(WorkspaceBusinessPlan::firstOrFail()->draft['business_name'])->toBe('Synthetic Lotus Studio');
});

test('a stale confirmation and a double submission cannot create duplicate revisions', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), planValues())->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['version' => 1, 'value_proposition' => 'New saved text']))->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 1, 'reviewed' => 1])->assertStatus(409);
    expect(WorkspaceBusinessPlanRevision::count())->toBe(0);
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 2, 'reviewed' => 1])->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 2, 'reviewed' => 1])->assertStatus(409);
    expect(WorkspaceBusinessPlanRevision::count())->toBe(1)->and(WorkspaceBusinessPlanRevision::firstOrFail()->content['value_proposition'])->toBe('New saved text');
});

test('an old confirmed revision can become a new draft without changing history', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $first = planConfirm($this, $owner, $workspace);
    $second = planConfirm($this, $owner, $workspace, ['value_proposition' => 'Second version']);
    $this->post(route('portal.workspaces.business-plan.copy', [$workspace, $first->id]), ['version' => 4])->assertRedirect();
    expect(WorkspaceBusinessPlan::firstOrFail()->draft)->toBe($first->content)->and($second->fresh()->content['value_proposition'])->toBe('Second version');
    $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 5, 'reviewed' => 1])->assertRedirect();
    $third = WorkspaceBusinessPlanRevision::latest('revision')->firstOrFail();
    expect($third->revision)->toBe(3)->and($third->based_on_revision)->toBe(1)->and($third->content)->toBe($first->content);
});

test('a stale history copy cannot replace work saved in another tab', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $revision = planConfirm($this, $owner, $workspace);
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['version' => 2, 'value_proposition' => 'Keep this draft']))->assertRedirect();
    $this->post(route('portal.workspaces.business-plan.copy', [$workspace, $revision->id]), ['version' => 2])->assertStatus(409);
    expect(WorkspaceBusinessPlan::firstOrFail()->draft['value_proposition'])->toBe('Keep this draft');
});

test('the same Owner keeps business plans isolated across Workspaces and cannot cross-link revision IDs', function (): void {
    $owner = planOwner();
    $first = planWorkspace($owner, 'Workspace A');
    $second = planWorkspace($owner, 'Workspace B');
    $revision = planConfirm($this, $owner, $first, ['business_name' => 'PRIVATE-A-731']);
    $this->get(route('portal.workspaces.business-plan.edit', $second))->assertOk()->assertDontSee('PRIVATE-A-731');
    $this->get(route('portal.workspaces.business-plan.history', $second))->assertOk()->assertDontSee('PRIVATE-A-731');
    $this->get(route('portal.workspaces.business-plan.revision', [$second, $revision->id]))->assertNotFound();
    $this->post(route('portal.workspaces.business-plan.copy', [$second, $revision->id]), ['version' => 0])->assertNotFound();
    $this->post(route('portal.workspaces.business-plan.knowledge', [$second, $revision->id]))->assertNotFound();
    expect(WorkspaceBusinessPlan::count())->toBe(1)->and(WorkspaceKnowledgeSource::count())->toBe(0);
    $otherRevision = planConfirm($this, $owner, $second, ['business_name' => 'PRIVATE-B-952']);
    expect($otherRevision->business_plan_id)->not->toBe($revision->business_plan_id);
    $this->get(route('portal.workspaces.business-plan.history', $first))->assertOk()->assertSee('PRIVATE-A-731')->assertDontSee('PRIVATE-B-952');
});

test('other Owners ordinary Admins and invitation-only Partners cannot read or modify any plan endpoint', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $revision = planConfirm($this, $owner, $workspace);
    $other = planOwner();
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'account_status' => 'active']);
    $partner = User::factory()->create(['role' => 'public', 'is_admin' => false, 'account_status' => 'active']);
    $workspace->memberships()->create(['user_id' => $partner->id, 'member_role' => 'partner', 'invitation_status' => 'accepted', 'permissions' => ['approved_workspace_read_only']]);
    foreach ([$other, $admin, $partner] as $actor) {
        $status = $actor->isStudent() ? 404 : 403;
        $this->actingAs($actor);
        foreach (['edit', 'review', 'history'] as $action) {
            $this->get(route('portal.workspaces.business-plan.'.$action, $workspace))->assertStatus($status);
        }
        $this->get(route('portal.workspaces.business-plan.revision', [$workspace, $revision->id]))->assertStatus($status);
        $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues())->assertStatus($status);
        $this->post(route('portal.workspaces.business-plan.confirm', $workspace), ['version' => 2, 'reviewed' => 1])->assertStatus($status);
        foreach (['copy', 'knowledge'] as $action) {
            $this->post(route('portal.workspaces.business-plan.'.$action, [$workspace, $revision->id]), ['version' => 2])->assertStatus($status);
        }
    }
    $this->actingAs($partner)->get(route('partner-dynamics.index'))->assertOk();
    expect(WorkspaceBusinessPlanRevision::count())->toBe(1)->and(WorkspaceBusinessPlan::firstOrFail()->lock_version)->toBe(2)->and(WorkspaceKnowledgeSource::count())->toBe(0);
    Http::assertNothingSent();
});

test('guests pending accounts and expired entitlements cannot enter business plans', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->get(route('portal.workspaces.business-plan.edit', $workspace))->assertRedirect(route('login'));
    $this->get('/portal/workspaces/0/business-plan')->assertRedirect(route('login'));
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues())->assertRedirect(route('login'));
    $owner->update(['portal_access_expires_at' => now()->subMinute()]);
    $this->actingAs($owner)->get(route('portal.workspaces.business-plan.edit', $workspace))->assertForbidden();
    $owner->update(['portal_access_expires_at' => now()->addMonth()]);
    StudentEnrollment::create(['user_id' => $owner->id, 'status' => 'active', 'started_at' => now()->subMonths(2), 'access_expires_at' => now()->subMonth()]);
    $this->actingAs($owner->fresh())->post(route('portal.workspaces.business-plan.save', $workspace), planValues())->assertForbidden();
    $pending = planOwner(['role' => 'public', 'portal_access_expires_at' => null]);
    $this->actingAs($pending)->get(route('portal.workspaces.business-plan.edit', $workspace))->assertForbidden();
    expect(WorkspaceBusinessPlan::count())->toBe(0);
});

test('inactive accounts and a disabled portal cannot enter plan endpoints', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    config(['partner_portal.enabled' => false]);
    $this->actingAs($owner)->get(route('portal.workspaces.business-plan.edit', $workspace))->assertNotFound();
    config(['partner_portal.enabled' => true]);
    $owner->update(['account_status' => 'inactive']);
    $this->actingAs($owner->fresh())->get(route('portal.workspaces.business-plan.edit', $workspace))->assertRedirect(route('login'));
    expect(WorkspaceBusinessPlan::count())->toBe(0);
});

test('plan pages have private cache headers and escape entered HTML in every presentation', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $html = '<script>private_plan()</script>';
    $revision = planConfirm($this, $owner, $workspace, ['business_name' => $html, 'value_proposition' => $html]);
    foreach ([route('portal.workspaces.business-plan.edit', $workspace), route('portal.workspaces.business-plan.history', $workspace), route('portal.workspaces.business-plan.revision', [$workspace, $revision->id])] as $url) {
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('&lt;script&gt;private_plan()&lt;/script&gt;', false)->assertDontSee($html, false);
    }
});

test('invalid drafts and version tokens fail before changing stored work', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $this->actingAs($owner)->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['business_stage' => 'invented']))->assertSessionHasErrors('business_stage');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['channels' => ['not a string']]))->assertSessionHasErrors('channels');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['business_name' => str_repeat('x', 161)]))->assertSessionHasErrors('business_name');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['demand_notes' => str_repeat('x', 3001)]))->assertSessionHasErrors('demand_notes');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), planValues(['version' => -1]))->assertSessionHasErrors('version');
    $this->post(route('portal.workspaces.business-plan.save', $workspace), ['business_name' => 'No version'])->assertSessionHasErrors('version');
    expect(WorkspaceBusinessPlan::count())->toBe(0);
});

test('AI preparation creates a reviewable private source once and does not send content to a provider', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $signature = app(WorkspaceKnowledge::class)->signature($workspace);
    $revision = planConfirm($this, $owner, $workspace);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))->assertRedirect();
    $source = WorkspaceKnowledgeSource::firstOrFail();
    expect($source->workspace_id)->toBe($workspace->id)->and($source->processing_status)->toBe('needs_review')
        ->and($source->business_status)->toBe('current')->and($source->reviewed_text)->toContain('Small partnership workshops')
        ->and($source->reviewed_text)->toContain('Version 1')->and(WorkspaceKnowledgeChunk::count())->toBe(0)
        ->and($revision->fresh()->knowledge_source_id)->toBe($source->id)
        ->and(app(WorkspaceKnowledge::class)->signature($workspace))->toBe($signature);
    $source->update(['reviewed_text' => 'Owner edited this source independently', 'processing_status' => 'ready']);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))
        ->assertRedirect(route('portal.workspaces.knowledge.show', [$workspace, $source]));
    expect(WorkspaceKnowledgeSource::count())->toBe(1)->and($source->fresh()->reviewed_text)->toBe('Owner edited this source independently')->and($source->fresh()->processing_status)->toBe('ready');
    Http::assertNothingSent();
});

test('old plan snapshots export as historical while existing Knowledge and confirmed snapshots stay preserved', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $first = planConfirm($this, $owner, $workspace);
    $second = planConfirm($this, $owner, $workspace, ['value_proposition' => 'New approach']);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $first->id]))->assertRedirect();
    $oldSource = WorkspaceKnowledgeSource::firstOrFail();
    $oldAttributes = $oldSource->getAttributes();
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $second->id]))->assertRedirect();
    expect($oldSource->business_status)->toBe('historical')->and($oldSource->fresh()->getAttributes())->toBe($oldAttributes)
        ->and(WorkspaceKnowledgeSource::latest('id')->firstOrFail()->business_status)->toBe('current')
        ->and($first->fresh()->content['value_proposition'])->toBe('Small partnership workshops');
});

test('deleting an exported Knowledge source preserves the plan and allows an explicit fresh review', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $revision = planConfirm($this, $owner, $workspace);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))->assertRedirect();
    $source = WorkspaceKnowledgeSource::firstOrFail();
    $this->delete(route('portal.workspaces.knowledge.destroy', [$workspace, $source]))->assertRedirect();
    expect($revision->fresh()->knowledge_source_id)->toBeNull()->and($revision->fresh()->content)->toBe($revision->content);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))->assertRedirect();
    expect(WorkspaceKnowledgeSource::count())->toBe(1)->and(WorkspaceKnowledgeSource::firstOrFail()->processing_status)->toBe('needs_review');
    Http::assertNothingSent();
});

test('plan evidence reaches private retrieval only after the existing Owner approval workflow', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $revision = planConfirm($this, $owner, $workspace);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))->assertRedirect();
    $source = WorkspaceKnowledgeSource::firstOrFail();
    $knowledge = app(WorkspaceKnowledge::class);
    $gateway = app(PartnerAiGateway::class);
    expect($knowledge->retrieve($workspace, 'What does this business offer?', $gateway)['sources'])->toBe([]);
    Http::assertNothingSent();
    config(['pbr_ai.base_url' => 'http://127.0.0.1:3107', 'pbr_ai.internal_secret' => str_repeat('plan-test-', 4)]);
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/health')) {
            return Http::response(['service' => 'pbr-ai-advisor', 'chatInterfaceProtocol' => 1, 'knowledgeReady' => true, 'vectors' => 657, 'privateKnowledgeProtocol' => 1]);
        }
        if (str_ends_with($request->url(), '/private/embed')) {
            return Http::response(['vectors' => array_map(fn ($text) => array_fill(0, 128, .25), $request['texts']), 'embeddingKey' => 'plan-test:128:private-v1']);
        }
        throw new RuntimeException('Unexpected synthetic provider request');
    });
    $this->put(route('portal.workspaces.knowledge.update', [$workspace, $source]), [
        'title' => $source->title, 'content' => $source->reviewed_text, 'business_status' => 'current',
        'action' => 'approve', 'reviewed' => 1, 'revision' => $source->revision,
    ])->assertRedirect(route('portal.workspaces.knowledge.index', $workspace));
    expect($source->fresh()->processing_status)->toBe('ready');
    $retrieved = $knowledge->retrieve($workspace, 'What does this business offer?', $gateway);
    expect($retrieved['sources'])->not->toBeEmpty()->and(json_encode($retrieved['excerpts']))->toContain('Small partnership workshops');
    $otherWorkspace = planWorkspace($owner, 'Separate private business');
    expect($knowledge->retrieve($otherWorkspace, 'What does this business offer?', $gateway)['sources'])->toBe([]);
});

test('business plan writes preserve existing account access Workspace fields and personal saved items', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    $saved = PortalSavedItem::create(['user_id' => $owner->id, 'kind' => 'capital', 'title' => 'Existing result', 'input' => ['monthly' => 42], 'output' => ['total' => 84]]);
    $before = [$owner->fresh()->getAttributes(), $workspace->fresh()->getAttributes(), $saved->fresh()->getAttributes()];
    $revision = planConfirm($this, $owner, $workspace, ['business_name' => 'A separate plan display name']);
    $this->post(route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]))->assertRedirect();
    expect([$owner->fresh()->getAttributes(), $workspace->fresh()->getAttributes(), $saved->fresh()->getAttributes()])->toBe($before);
    $this->get(route('portal.saved', $saved))->assertOk();
});

test('the old test-data cleanup refuses to remove private business plan records', function (): void {
    $owner = planOwner();
    $workspace = planWorkspace($owner);
    User::factory()->create(['role' => 'admin', 'is_admin' => true, 'account_status' => 'active']);
    $revision = planConfirm($this, $owner, $workspace);
    $cleanup = new \App\Services\Portal\PortalTestDataCleanup(DB::connection());
    expect(fn () => $cleanup->plan())->toThrow(RuntimeException::class, 'PRIVATE_WORKSPACE_DATA_PRESENT');
    expect($revision->fresh()->content['business_name'])->toBe('Synthetic Lotus Studio')->and($workspace->fresh())->not->toBeNull();
});
