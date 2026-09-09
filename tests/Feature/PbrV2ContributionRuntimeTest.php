<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspacePartnerProfile;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrOperatingSystemService;
use App\Services\PbrTools\PbrV2ContributionApprovalService;
use App\Services\PbrTools\PbrV2ContributionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait C2RuntimeIsolatedDatabase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || ! empty(config('database.connections.sqlite.url'))) {
            throw new RuntimeException('STOP — contribution runtime tests require isolated SQLite :memory:.');
        }
    }
}

uses(C2RuntimeIsolatedDatabase::class);

function c2RuntimeFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student', 'is_admin' => false, 'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);
    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id, 'name' => 'Contribution Runtime Workspace',
        'business_name' => 'Contribution Runtime Business', 'business_stage' => 'new',
        'currency_code' => 'THB', 'status' => 'active',
    ]);
    app(PbrOperatingSystemService::class)->syncWorkspacePartners($workspace);
    $profile = WorkspacePartnerProfile::query()
        ->where('workspace_id', $workspace->id)->where('user_id', $owner->id)->firstOrFail();
    return [$owner, $workspace, $profile];
}

function c2RuntimeSetup(User $owner): array
{
    return [
        'valuation_date' => '2026-09-09', 'currency_code' => 'THB',
        'contribution_period' => ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'],
        'valuation_owner_reference' => 'Runtime valuation owner',
        'approver_user_ids' => [(int) $owner->id],
    ];
}

function c2RuntimeCash(string $partnerKey): array
{
    return [
        'partner_key' => $partnerKey, 'contribution_type' => 'cash',
        'description' => 'Runtime accepted cash', 'owner_reference' => 'Runtime owner',
        'proposed_value' => '100.00', 'valuation_method' => 'Bank evidence',
        'evidence_references' => ['runtime-bank'], 'conditions' => '',
        'amount_committed' => '100.00', 'amount_received' => '0.00',
    ];
}

function c2RuntimePartner(PartnershipWorkspace $workspace): User
{
    $partner = User::factory()->create([
        'role' => 'public', 'is_admin' => false, 'account_status' => 'active',
    ]);
    WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id, 'user_id' => $partner->id,
        'member_role' => 'partner', 'invitation_status' => 'accepted',
        'permissions' => ['approved_workspace_read_only'],
    ]);
    return $partner;
}

test('contribution browser entry requires authentication and denies unrelated users', function () {
    [$owner, $workspace] = c2RuntimeFixture();
    $url = route('workspaces.tools.v2.contribution.show', $workspace);
    $this->get($url)->assertRedirect(route('login'));
    $outsider = User::factory()->create([
        'role' => 'public', 'is_admin' => false, 'account_status' => 'active',
    ]);
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $this->actingAs($owner)->get($url)->assertOk()->assertSee('Create Contribution Draft');
});

test('manager edits contribution draft through HTTP with server identities and stale version protection', function () {
    [$owner, $workspace, $profile] = c2RuntimeFixture();
    $this->actingAs($owner)->post(
        route('workspaces.tools.v2.contribution.store', $workspace)
    )->assertRedirect();
    $draft = WorkspaceV2CanonicalWorkArtifact::query()
        ->where('workspace_id', $workspace->id)->where('target_domain_key', 'contribution')
        ->firstOrFail();
    $params = ['workspace' => $workspace, 'artifactId' => $draft->id];
    $this->put(route('workspaces.tools.v2.contribution.setup', $params), [
        'edit_version' => '1', 'setup' => c2RuntimeSetup($owner),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->put(route('workspaces.tools.v2.contribution.partners', $params), [
        'edit_version' => '2', 'profile_ids' => [(string) $profile->id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $item = c2RuntimeCash($profile->partner_key);
    $item['evidence_references'] = "runtime-bank\nruntime-second-proof";
    $this->post(route('workspaces.tools.v2.contribution.items.store', $params), [
        'edit_version' => '3', 'item' => $item,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $saved = $draft->fresh()->payload;
    expect($saved['edit_version'])->toBe(4)
        ->and($saved['items'][0]['status'])->toBe('proposed')
        ->and($saved['items'][0]['proposed_value'])->toBe('100.00')
        ->and($saved['items'][0]['conditions'])->toBe('')
        ->and($saved['items'][0]['evidence_references'])->toBe(['runtime-bank', 'runtime-second-proof'])
        ->and($saved['items'][0]['approval_records'])->toBe([]);
    $this->put(route('workspaces.tools.v2.contribution.setup', $params), [
        'edit_version' => '1', 'setup' => c2RuntimeSetup($owner),
    ])->assertSessionHasErrors();
    expect($draft->fresh()->payload)->toBe($saved);
    $this->get(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace, 'artifact_id' => $draft->id,
    ]))->assertOk()->assertSee('Runtime accepted cash')->assertSee('Edit version 4');
});

test('contribution browser rejects forged item authority without altering the draft', function () {
    [$owner, $workspace, $profile] = c2RuntimeFixture();
    $flow = app(PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($owner, $workspace);
    $draft = $flow->savePartners($owner, $workspace, $draft, 1, [(int) $profile->id]);
    $before = $draft->payload;
    $item = c2RuntimeCash($profile->partner_key);
    $item['evidence_references'] = 'runtime-bank';
    $item['status'] = 'accepted';
    $this->actingAs($owner)->post(route('workspaces.tools.v2.contribution.items.store', [
        'workspace' => $workspace, 'artifactId' => $draft->id,
    ]), ['edit_version' => '2', 'item' => $item])->assertSessionHasErrors('item');
    expect($draft->fresh()->payload)->toBe($before);
});

test('accepted partner gets no draft selectors or management mutation authority even with malformed input', function () {
    [$owner, $workspace] = c2RuntimeFixture();
    $draft = app(PbrV2ContributionWorkflowService::class)->createDraft($owner, $workspace);
    $partner = c2RuntimePartner($workspace);
    $url = route('workspaces.tools.v2.contribution.show', $workspace);
    $this->actingAs($partner)->get($url)->assertOk()
        ->assertDontSee('Create Contribution Draft')->assertDontSee('c2-management');
    $this->get($url.'?artifact_id=invalid')->assertForbidden();
    $this->post(route('workspaces.tools.v2.contribution.store', $workspace), [])
        ->assertForbidden();
    foreach (['setup', 'partners'] as $operation) {
        $this->put(route('workspaces.tools.v2.contribution.'.$operation, [
            'workspace' => $workspace, 'artifactId' => $draft->id,
        ]), [])->assertForbidden();
    }
    $this->post(route('workspaces.tools.v2.contribution.items.store', [
        'workspace' => $workspace, 'artifactId' => $draft->id,
    ]), [])->assertForbidden();
    expect($draft->fresh()->payload['edit_version'])->toBe(1);
});

test('published contribution browser preserves closed rows and uses accepted only server matrix for partners', function () {
    [$owner, $workspace, $profile] = c2RuntimeFixture();
    $flow = app(PbrV2ContributionWorkflowService::class);
    $approval = app(PbrV2ContributionApprovalService::class);
    $draft = $flow->createDraft($owner, $workspace);
    $draft = $flow->saveSetup($owner, $workspace, $draft, 1, c2RuntimeSetup($owner));
    $draft = $flow->savePartners($owner, $workspace, $draft, 2, [(int) $profile->id]);
    $draft = $flow->saveItem($owner, $workspace, $draft, 3, c2RuntimeCash($profile->partner_key));
    $id = $draft->payload['items'][0]['id'];
    $draft = $flow->reviewItem($owner, $workspace, $draft, 4, $id, '100.00');
    $draft = $approval->approveItem($owner, $workspace, $draft, 5, $id, '100.00', 'runtime-agreement');
    $draft = $flow->recordDelivery($owner, $workspace, $draft, 6, $id, [
        'accepted_value' => '100.00', 'committed_date' => '2026-09-01',
        'due_date' => '2026-09-10', 'delivered_date' => '2026-09-09',
        'delivery_extent' => 'full', 'amount_received' => '100.00',
        'payment_date' => '2026-09-09',
    ], 'runtime-delivery');
    $draft = $approval->acceptItem($owner, $workspace, $draft, 7, $id, 'runtime-acceptance');
    $closed = c2RuntimeCash($profile->partner_key);
    $closed['description'] = 'Runtime cancelled row';
    $closed['proposed_value'] = '900.00';
    $draft = $flow->saveItem($owner, $workspace, $draft, 8, $closed);
    $closedId = $draft->payload['items'][1]['id'];
    $draft = $flow->closeItem(
        $owner, $workspace, $draft, 9, $closedId, 'cancelled', 'Withdrawn', 'runtime-closure'
    );
    $proposal = $approval->submitForPublication($owner, $workspace, $draft, 10)['work_artifact'];
    $approval->approveAndPublish($owner, $workspace, $proposal);
    $partner = c2RuntimePartner($workspace);
    $response = $this->actingAs($partner)->get(
        route('workspaces.tools.v2.contribution.show', $workspace)
    );
    $response->assertOk()->assertSee('Runtime accepted cash')
        ->assertSee('Runtime cancelled row')->assertSee('cancelled')
        ->assertSee('Contribution Matrix')->assertDontSee('c2-management');
    $projection = $response->viewData('current');
    expect($projection['total_accepted_contribution_value'])->toBe('100.00')
        ->and($projection['accepted_item_count'])->toBe(1)
        ->and($projection['excluded_item_count'])->toBe(1)
        ->and($projection['contribution_register'])->toHaveCount(2)
        ->and($projection['contribution_matrix']['totals']['accepted_total'])->toBe('100.00');
    $this->actingAs($owner)->get(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace, 'artifact_id' => $draft->id,
    ]))->assertSessionHasErrors('work_artifact');
});


function c2RuntimeLifecyclePost(
    $test, User $actor, PartnershipWorkspace $workspace,
    WorkspaceV2CanonicalWorkArtifact $draft, string $operation,
    array $data = [], ?string $itemId = null
) {
    $params = ['workspace' => $workspace, 'artifactId' => $draft->id];
    if ($itemId !== null) {
        $params['itemId'] = $itemId;
    }
    if ($operation !== 'publish' && ! array_key_exists('edit_version', $data)) {
        $data['edit_version'] = $draft->fresh()->payload['edit_version'];
    }
    $showUrl = route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace,
        'artifact_id' => $draft->id,
    ]);

    return $test->actingAs($actor)->from($showUrl)->post(
        route('workspaces.tools.v2.contribution.'.$operation, $params), $data
    );
}

test('browser lifecycle requires all agreement and fresh acceptance approvals and publishes resolved rows', function () {
    [$owner, $workspace, $profile] = c2RuntimeFixture();
    $partner = c2RuntimePartner($workspace);
    $flow = app(PbrV2ContributionWorkflowService::class);
    $setup = c2RuntimeSetup($owner);
    $setup['approver_user_ids'][] = (int) $partner->id;
    $draft = $flow->createDraft($owner, $workspace);
    $draft = $flow->saveSetup($owner, $workspace, $draft, 1, $setup);
    $draft = $flow->savePartners($owner, $workspace, $draft, 2, [(int) $profile->id]);
    $draft = $flow->saveItem($owner, $workspace, $draft, 3, c2RuntimeCash($profile->partner_key));
    $itemId = $draft->payload['items'][0]['id'];
    $other = c2RuntimeCash($profile->partner_key);
    $other['description'] = 'Private unrelated draft item';
    $draft = $flow->saveItem($owner, $workspace, $draft, 4, $other);
    $otherId = $draft->payload['items'][1]['id'];

    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'close', [
        'target_status' => 'cancelled', 'reason' => 'Withdrawn', 'evidence_reference' => 'closure-proof',
    ], $otherId)->assertRedirect();
    c2RuntimeLifecyclePost(
        $this, $owner, $workspace, $draft, 'submit'
    )->assertRedirect(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace,
        'artifact_id' => $draft->id,
    ]))->assertSessionHasErrors();
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'review', [
        'reviewed_value' => '100.00',
    ], $itemId)->assertRedirect();

    $params = ['workspace'=>$workspace, 'artifactId'=>$draft->id, 'itemId'=>$itemId];
    $this->actingAs($partner)->get(
        route('workspaces.tools.v2.contribution.approval.show', $params)
    )->assertOk()->assertSee('Runtime accepted cash')
        ->assertDontSee('Private unrelated draft item')->assertSee('Approve Agreement');

    c2RuntimeLifecyclePost($this, $partner, $workspace, $draft, 'review', [], $itemId)
        ->assertForbidden();
    c2RuntimeLifecyclePost($this, $partner, $workspace, $draft, 'submit')->assertForbidden();
    c2RuntimeLifecyclePost($this, $partner, $workspace, $draft, 'publish')->assertForbidden();

    $before = $draft->fresh()->payload;
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'agree', [
        'edit_version' => 1, 'agreed_value'=>'100.00', 'evidence_reference'=>'stale-proof',
    ], $itemId)->assertRedirect(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace,
        'artifact_id' => $draft->id,
    ]))->assertSessionHasErrors('edit_version');
    expect($draft->fresh()->payload)->toBe($before);

    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'agree', [
        'agreed_value'=>'100.00', 'evidence_reference'=>'owner-agreement',
    ], $itemId)->assertRedirect();
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('reviewed');
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'agree', [
        'agreed_value'=>'100.00', 'evidence_reference'=>'duplicate-agreement',
    ], $itemId)->assertRedirect(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace,
        'artifact_id' => $draft->id,
    ]))->assertSessionHasErrors('approval_records');
    c2RuntimeLifecyclePost($this, $partner, $workspace, $draft, 'agree', [
        'agreed_value'=>'100.00', 'evidence_reference'=>'partner-agreement',
    ], $itemId)->assertRedirect(route('workspaces.tools.v2.contribution.show', $workspace));
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('approved');
    $agreementFingerprint = $draft->fresh()->payload['items'][0]['approval_records'][0]['fingerprint'];

    $this->actingAs($owner)->get(route('workspaces.tools.v2.contribution.show', [
        'workspace'=>$workspace, 'artifact_id'=>$draft->id,
    ]))->assertOk()->assertSee('Record Delivery');

    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'deliver', [
        'delivery'=>[
            'accepted_value'=>'100.00', 'committed_date'=>'2026-09-01',
            'due_date'=>'2026-09-10', 'delivered_date'=>'2026-09-09',
            'delivery_extent'=>'full', 'amount_received'=>'100.00', 'payment_date'=>'2026-09-09',
        ],
        'evidence_reference'=>'delivery-proof',
    ], $itemId)->assertRedirect();
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('delivered')
        ->and($draft->fresh()->payload['items'][0]['approval_records'])->toBe([]);

    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'accept', [
        'evidence_reference'=>'owner-acceptance',
    ], $itemId)->assertRedirect();
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('delivered');
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'submit')
        ->assertRedirect(route('workspaces.tools.v2.contribution.show', [
            'workspace' => $workspace,
            'artifact_id' => $draft->id,
        ]))
        ->assertSessionHasErrors();

    $this->actingAs($partner)->get(
        route('workspaces.tools.v2.contribution.approval.show', $params)
    )->assertOk()->assertSee('Approve Acceptance')->assertDontSee('Private unrelated draft item');
    c2RuntimeLifecyclePost($this, $partner, $workspace, $draft, 'accept', [
        'evidence_reference'=>'partner-acceptance',
    ], $itemId)->assertRedirect();
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('accepted')
        ->and($draft->fresh()->payload['items'][0]['approval_records'])->toHaveCount(2)
        ->and($draft->fresh()->payload['items'][0]['approval_records'][0]['fingerprint'])
        ->not->toBe($agreementFingerprint);

    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'submit', [
        'total_accepted_contribution_value'=>'999999.00',
    ])->assertRedirect(route('workspaces.tools.v2.contribution.show', [
        'workspace' => $workspace,
        'artifact_id' => $draft->id,
    ]))->assertSessionHasErrors('request');
    expect($draft->fresh()->state)->toBe('working_draft');
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'submit')->assertRedirect();
    $this->actingAs($owner)->get(route('workspaces.tools.v2.contribution.show', [
        'workspace'=>$workspace, 'artifact_id'=>$draft->id,
    ]))->assertOk()->assertSee('Approve and Publish Register');
    c2RuntimeLifecyclePost($this, $owner, $workspace, $draft, 'publish')->assertRedirect(
        route('workspaces.tools.v2.contribution.show', $workspace)
    );
    $page = $this->actingAs($partner)->get(route('workspaces.tools.v2.contribution.show', $workspace));
    $page->assertOk()->assertDontSee('c2-lifecycle');
    expect($page->viewData('current')['total_accepted_contribution_value'])->toBe('100.00')
        ->and($page->viewData('current')['contribution_register'])->toHaveCount(2);
});

test('approval routes reject nondesignated and revoked partners before input validation', function () {
    [$owner, $workspace, $profile] = c2RuntimeFixture();
    $partner = c2RuntimePartner($workspace);
    $flow = app(PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($owner, $workspace);
    $draft = $flow->saveSetup($owner, $workspace, $draft, 1, c2RuntimeSetup($owner));
    $draft = $flow->savePartners($owner, $workspace, $draft, 2, [(int) $profile->id]);
    $draft = $flow->saveItem($owner, $workspace, $draft, 3, c2RuntimeCash($profile->partner_key));
    $id = $draft->payload['items'][0]['id'];
    $params = ['workspace'=>$workspace, 'artifactId'=>$draft->id, 'itemId'=>$id];
    $this->actingAs($partner)->get(
        route('workspaces.tools.v2.contribution.approval.show', $params)
    )->assertForbidden();
    $this->postJson(route('workspaces.tools.v2.contribution.agree', $params), [])
        ->assertForbidden();
    $setup = c2RuntimeSetup($owner);
    $setup['approver_user_ids'][] = (int) $partner->id;
    $draft = $flow->saveSetup($owner, $workspace, $draft, 4, $setup);
    WorkspaceMember::query()->where('workspace_id', $workspace->id)
        ->where('user_id', $partner->id)->update(['invitation_status'=>'pending']);
    $before = $draft->fresh()->payload;
    $this->get(route('workspaces.tools.v2.contribution.approval.show', $params))
        ->assertForbidden();
    $this->postJson(route('workspaces.tools.v2.contribution.accept', $params), [])
        ->assertForbidden();
    expect($draft->fresh()->payload)->toBe($before);
});
