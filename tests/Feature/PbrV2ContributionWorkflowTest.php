<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Services\PbrTools\PbrV2ContributionAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Access integration only. No RefreshDatabase or application migrations.
 * The small schema is deliberately limited to queries made by access checks.
 * It does not verify the production migration schema.
 */
beforeEach(function () {
    if (! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.driver') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || ! empty(config('database.connections.sqlite.url'))) {
        throw new RuntimeException('STOP — isolated SQLite configuration required.');
    }

    $connection = DB::connection('sqlite');
    if ($connection->getDriverName() !== 'sqlite'
        || $connection->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('STOP — SQLite memory connection required.');
    }

    foreach ($connection->select('PRAGMA database_list') as $database) {
        if ($database->file !== '') {
            throw new RuntimeException('STOP — file-backed SQLite detected.');
        }
    }

    Schema::connection('sqlite')->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role');
        $table->boolean('is_admin')->default(false);
        $table->string('account_status')->default('active');
        $table->dateTime('portal_access_expires_at')->nullable();
    });
    Schema::connection('sqlite')->create('partnership_workspaces', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('owner_user_id');
    });
    Schema::connection('sqlite')->create('workspace_members', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->unsignedBigInteger('user_id');
        $table->string('member_role');
        $table->string('invitation_status');
    });
    Schema::connection('sqlite')->create('student_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('status');
        $table->dateTime('access_expires_at')->nullable();
    });

    $this->access = new PbrV2ContributionAccessService;
    $this->owner = contributionAccessUser();
    $id = DB::table('partnership_workspaces')->insertGetId([
        'owner_user_id' => $this->owner->id,
    ]);
    $this->workspace = PartnershipWorkspace::query()->findOrFail($id);
});

function contributionAccessUser(array $attributes = []): User
{
    $id = DB::table('users')->insertGetId(array_merge([
        'name' => 'Contribution access user',
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' => '2099-01-01 00:00:00',
    ], $attributes));

    return User::query()->findOrFail($id);
}

function contributionAccessMembership(
    User $user,
    PartnershipWorkspace $workspace,
    string $status = 'accepted',
    string $role = 'partner',
): void {
    DB::table('workspace_members')->insert([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'member_role' => $role,
        'invitation_status' => $status,
    ]);
}

test('contribution access permits owner and active administrator management', function () {
    $admin = contributionAccessUser(['role' => 'admin', 'is_admin' => true]);

    foreach ([$this->owner, $admin] as $actor) {
        $context = $this->access->managementContext($actor, $this->workspace);
        expect($context['actor']->id)->toBe($actor->id)
            ->and($context['workspace']->id)->toBe($this->workspace->id);
    }
});

test('contribution partner current access does not grant management', function () {
    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);

    expect($this->access->currentContext($partner, $this->workspace)['can_manage'])
        ->toBeFalse();

    expect(fn () => $this->access->managementContext($partner, $this->workspace))
        ->toThrow(AuthorizationException::class);
});

test('contribution access denies pending and non partner memberships', function ($status, $role) {
    $user = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($user, $this->workspace, $status, $role);

    expect(fn () => $this->access->currentContext($user, $this->workspace))
        ->toThrow(AuthorizationException::class);
})->with([
    'pending partner' => ['pending', 'partner'],
    'accepted observer' => ['accepted', 'observer'],
]);

test('contribution access never borrows membership from another workspace', function () {
    $partner = contributionAccessUser(['role' => 'partner']);
    $otherId = DB::table('partnership_workspaces')->insertGetId([
        'owner_user_id' => $this->owner->id,
    ]);
    contributionAccessMembership(
        $partner,
        PartnershipWorkspace::query()->findOrFail($otherId),
    );

    expect(fn () => $this->access->currentContext($partner, $this->workspace))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->access->approvalContext($partner, $this->workspace, [$partner->id]))
        ->toThrow(AuthorizationException::class);
});

test('contribution access reloads account status and workspace ownership', function () {
    $admin = contributionAccessUser(['role' => 'admin', 'is_admin' => true]);
    DB::table('users')->where('id', $admin->id)->update(['account_status' => 'suspended']);

    expect(fn () => $this->access->managementContext($admin, $this->workspace))
        ->toThrow(AuthorizationException::class);

    $replacement = contributionAccessUser();
    DB::table('partnership_workspaces')->where('id', $this->workspace->id)
        ->update(['owner_user_id' => $replacement->id]);

    expect(fn () => $this->access->managementContext($this->owner, $this->workspace))
        ->toThrow(AuthorizationException::class);
});

test('contribution access respects authoritative enrollment and expired legacy access', function () {
    DB::table('student_enrollments')->insert([
        'user_id' => $this->owner->id,
        'status' => 'inactive',
        'access_expires_at' => null,
    ]);

    expect(fn () => $this->access->managementContext($this->owner, $this->workspace))
        ->toThrow(AuthorizationException::class);

    DB::table('student_enrollments')->where('user_id', $this->owner->id)->delete();
    DB::table('users')->where('id', $this->owner->id)
        ->update(['portal_access_expires_at' => '2000-01-01 00:00:00']);

    expect(fn () => $this->access->managementContext($this->owner, $this->workspace))
        ->toThrow(AuthorizationException::class);
});

test('contribution approval requires assignment even for administrators', function () {
    $admin = contributionAccessUser(['role' => 'admin', 'is_admin' => true]);
    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);

    expect(fn () => $this->access->approvalContext($admin, $this->workspace, [$partner->id]))
        ->toThrow(AuthorizationException::class);

    expect($this->access->approvalContext($partner, $this->workspace, [$partner->id])['actor']->id)
        ->toBe($partner->id);

    DB::table('workspace_members')->where('user_id', $partner->id)
        ->update(['invitation_status' => 'pending']);

    expect(fn () => $this->access->approvalContext($partner, $this->workspace, [$partner->id]))
        ->toThrow(AuthorizationException::class);
});

test('contribution approval rejects malformed approver assignments', function () {
    $id = (int) $this->owner->id;
    foreach ([[], [(string) $id], [$id, $id], ['owner' => $id], [0, $id]] as $ids) {
        expect(fn () => $this->access->approvalContext($this->owner, $this->workspace, $ids))
            ->toThrow(AuthorizationException::class);
    }
});

test('contribution access rejects unsaved and deleted actors', function () {
    expect(fn () => $this->access->currentContext(new User, $this->workspace))
        ->toThrow(ModelNotFoundException::class);

    DB::table('users')->where('id', $this->owner->id)->delete();
    expect(fn () => $this->access->currentContext($this->owner, $this->workspace))
        ->toThrow(ModelNotFoundException::class);
});

test('contribution access checks execute reads only', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $this->access->managementContext($this->owner, $this->workspace);
        $this->access->currentContext($this->owner, $this->workspace);
        $this->access->approvalContext($this->owner, $this->workspace, [$this->owner->id]);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($queries)->not->toBeEmpty();
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});

function contributionDraftStorage(): void
{
    if (! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('STOP — draft storage requires SQLite memory.');
    }
    foreach (DB::select('PRAGMA database_list') as $database) {
        if ($database->file !== '') {
            throw new RuntimeException('STOP — file-backed database detected.');
        }
    }

    Schema::table('partnership_workspaces', function (Blueprint $table) {
        $table->string('currency_code')->default('THB');
    });
    // FK target stubs only; publication and review behavior are not tested here.
    foreach (['workspace_v2_canonical_revisions', 'workspace_v2_review_cases'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
        });
    }
    $migration = require base_path(
        'database/migrations/2026_09_05_030000_create_workspace_v2_canonical_work_tables.php'
    );
    $migration->up();
}

function contributionDraftSetup(int $approverId): array
{
    return [
        'valuation_date' => '2026-09-09',
        'currency_code' => 'THB',
        'contribution_period' => ['start_date' => '2026-09-01', 'end_date' => '2027-08-31'],
        'valuation_owner_reference' => 'Workspace owner',
        'approver_user_ids' => [$approverId],
    ];
}

test('contribution draft persists through canonical work without publication or upstream pins', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $stored = $draft->fresh();

    expect($stored->state)->toBe('working_draft')
        ->and($stored->target_component_key)->toBe('accepted_contribution_register')
        ->and($stored->payload['edit_version'])->toBe(1)
        ->and($stored->payload['items'])->toBe([])
        ->and($stored->payload['event_history'][0]['actor_user_id'])->toBe($this->owner->id)
        ->and($stored->published_canonical_revision_id)->toBeNull()
        ->and(DB::table('workspace_v2_canonical_work_sources')->count())->toBe(0)
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

test('contribution setup save rejects stale browser state without losing the winning update', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $setup = contributionDraftSetup($this->owner->id);
    $saved = $flow->saveSetup($this->owner, $this->workspace, $draft, 1, $setup);
    $before = $saved->payload;
    $setup['valuation_owner_reference'] = 'Stale browser';

    expect(fn () => $flow->saveSetup($this->owner, $this->workspace, $draft, 1, $setup))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($draft->fresh()->payload)->toBe($before)
        ->and($before['edit_version'])->toBe(2)
        ->and($before['event_history'])->toHaveCount(2);
});

test('contribution draft denies partner writes and cross workspace artifacts', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);
    expect(fn () => $flow->createDraft($partner, $this->workspace))
        ->toThrow(AuthorizationException::class);

    $draft = $flow->createDraft($this->owner, $this->workspace);
    $otherId = DB::table('partnership_workspaces')->insertGetId([
        'owner_user_id' => $this->owner->id, 'currency_code' => 'THB',
    ]);
    $other = PartnershipWorkspace::query()->findOrFail($otherId);
    expect(fn () => $flow->saveSetup(
        $this->owner, $other, $draft, 1, contributionDraftSetup($this->owner->id),
    ))->toThrow(ModelNotFoundException::class);
    expect($draft->fresh()->payload['edit_version'])->toBe(1);
});

test('contribution setup rejects injected fields currency mismatch and unrelated approvers', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $setup = contributionDraftSetup($this->owner->id);
    foreach ([
        array_merge($setup, ['approval_records' => []]),
        array_merge($setup, ['currency_code' => 'USD']),
        [],
    ] as $bad) {
        expect(fn () => $flow->saveSetup($this->owner, $this->workspace, $draft, 1, $bad))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    }
    $outsider = contributionAccessUser(['role' => 'partner']);
    $setup['approver_user_ids'] = [$outsider->id];
    expect(fn () => $flow->saveSetup($this->owner, $this->workspace, $draft, 1, $setup))
        ->toThrow(AuthorizationException::class);
    expect($draft->fresh()->payload['edit_version'])->toBe(1);
});

test('contribution setup changes invalidate acceptance and retain prior approval history', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $payload = $draft->payload;
    $payload['items'] = [[
        'id' => 'historical-item',
        'status' => 'accepted',
        'accepted_value' => '100.00',
        'approval_records' => [['user_id' => $this->owner->id]],
        'event_history' => [],
    ]];
    app(\App\Services\PbrTools\PbrV2CanonicalWorkService::class)
        ->updateWorkingDraftPayload($this->workspace, $draft, $payload);

    $saved = $flow->saveSetup(
        $this->owner, $this->workspace, $draft, 1, contributionDraftSetup($this->owner->id),
    );
    $item = $saved->payload['items'][0];
    expect($item['status'])->toBe('proposed')
        ->and($item['approval_records'])->toBe([])
        ->and($item['event_history'][0]['previous_status'])->toBe('accepted')
        ->and($item['event_history'][0]['previous_approval_records'])
        ->toBe([['user_id' => $this->owner->id]]);
});

test('contribution setup cannot edit proposed consumed or wrong target artifacts', function () {
    contributionDraftStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    foreach ([
        ['state' => 'proposed'],
        ['consumed_at' => '2026-09-09 00:00:00'],
        ['target_component_key' => 'other_register'],
    ] as $change) {
        $draft = $flow->createDraft($this->owner, $this->workspace);
        // Persist an adversarial fixture; application code must use the work service.
        DB::table('workspace_v2_canonical_work_artifacts')->where('id', $draft->id)->update($change);
        expect(fn () => $flow->saveSetup(
            $this->owner, $this->workspace, $draft, 1, contributionDraftSetup($this->owner->id),
        ))->toThrow(\Illuminate\Validation\ValidationException::class);
        expect($draft->fresh()->payload['edit_version'])->toBe(1);
    }
});

function contributionPartnerStorage(): void
{
    contributionDraftStorage();
    Schema::create('workspace_partner_profiles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('partner_key');
        $table->string('display_name');
        $table->string('status');
    });
}

function contributionProfile(PartnershipWorkspace $workspace, string $key, ?User $user = null): int
{
    return DB::table('workspace_partner_profiles')->insertGetId([
        'workspace_id' => $workspace->id,
        'user_id' => $user?->id,
        'partner_key' => $key,
        'display_name' => 'Roster '.$key,
        'status' => $user === null ? 'planned' : 'active',
    ]);
}

test('contribution partner snapshots use scoped roster and preserve saved identity', function () {
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $profile = contributionProfile($this->workspace, 'owner-key', $this->owner);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $saved = $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$profile]);
    expect($saved->payload['partners'][0]['display_name'])->toBe($this->owner->name);

    DB::table('users')->where('id', $this->owner->id)->update(['name' => 'Renamed account']);
    $again = $flow->savePartners($this->owner, $this->workspace, $saved, 2, [$profile]);
    expect($again->payload['partners'])->toBe($saved->payload['partners'])
        ->and($again->payload['edit_version'])->toBe(2);
});

test('contribution partner selection rejects foreign profiles and duplicate IDs', function () {
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $profile = contributionProfile($this->workspace, 'local');
    expect(fn () => $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$profile, $profile]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $otherId = DB::table('partnership_workspaces')->insertGetId(['owner_user_id' => $this->owner->id]);
    $other = PartnershipWorkspace::query()->findOrFail($otherId);
    $foreign = contributionProfile($other, 'foreign');
    expect(fn () => $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$foreign]))
        ->toThrow(ModelNotFoundException::class);
    expect($draft->fresh()->payload['partners'])->toBe([]);
});

test('all contribution types save as proposed with server identity', function ($type) {
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $profile = contributionProfile($this->workspace, 'planned-key');
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $draft = $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$profile]);
    $draft = $flow->saveItem($this->owner, $this->workspace, $draft, 2, [
        'partner_key' => 'planned-key',
        'contribution_type' => $type,
        'proposed_value' => '100.00',
    ]);
    $item = $draft->payload['items'][0];
    expect(\Illuminate\Support\Str::isUuid($item['id']))->toBeTrue()
        ->and($item['status'])->toBe('proposed')
        ->and($item['approval_records'])->toBe([])
        ->and($item['partner_snapshot']['partner_key'])->toBe('planned-key')
        ->and($draft->payload['edit_version'])->toBe(3)
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
})->with(['cash', 'time_skill', 'property_asset', 'ip_intangible']);

test('contribution item rejects forged authority and unselected partners', function () {
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $profile = contributionProfile($this->workspace, 'partner-key');
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $draft = $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$profile]);
    $input = ['partner_key' => 'partner-key', 'contribution_type' => 'cash'];
    foreach ([
        ['status' => 'accepted'],
        ['approval_records' => []],
        ['partner_snapshot' => []],
        ['event_history' => []],
        ['id' => 'forged'],
        ['shares' => '10'],
        ['partner_key' => 'unselected'],
        ['proposed_value' => 1.25],
    ] as $change) {
        expect(fn () => $flow->saveItem($this->owner, $this->workspace, $draft, 2, array_merge($input, $change)))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    }
    expect($draft->fresh()->payload['items'])->toBe([]);
});

test('contribution item revision retains history and stale writes cannot overwrite it', function () {
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $profile = contributionProfile($this->workspace, 'partner-key');
    $draft = $flow->createDraft($this->owner, $this->workspace);
    $draft = $flow->savePartners($this->owner, $this->workspace, $draft, 1, [$profile]);
    $input = ['partner_key' => 'partner-key', 'contribution_type' => 'cash', 'proposed_value' => '100.00'];
    $draft = $flow->saveItem($this->owner, $this->workspace, $draft, 2, $input);
    $id = $draft->payload['items'][0]['id'];

    $payload = $draft->payload;
    $payload['items'][0]['status'] = 'accepted';
    $payload['items'][0]['approval_records'] = [['user_id' => $this->owner->id]];
    app(\App\Services\PbrTools\PbrV2CanonicalWorkService::class)
        ->updateWorkingDraftPayload($this->workspace, $draft, $payload);

    $input['proposed_value'] = '200.00';
    $saved = $flow->saveItem($this->owner, $this->workspace, $draft, 3, $input, $id);
    $item = $saved->payload['items'][0];
    expect($saved->payload['items'])->toHaveCount(1)
        ->and($item['status'])->toBe('proposed')
        ->and($item['approval_records'])->toBe([])
        ->and($item['event_history'][1]['previous_item']['status'])->toBe('accepted')
        ->and($item['event_history'][1]['previous_item']['proposed_value'])->toBe('100.00');

    expect(fn () => $flow->saveItem($this->owner, $this->workspace, $draft, 3, $input, $id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($draft->fresh()->payload)->toBe($saved->payload);

    $another = contributionProfile($this->workspace, 'another-partner');
    expect(fn () => $flow->savePartners($this->owner, $this->workspace, $saved, 4, [$another]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

function contributionReviewDraft(User $owner, PartnershipWorkspace $workspace, array $changes = []): array
{
    contributionPartnerStorage();
    $flow = app(\App\Services\PbrTools\PbrV2ContributionWorkflowService::class);
    $profile = contributionProfile($workspace, 'review-partner');
    $draft = $flow->createDraft($owner, $workspace);
    $draft = $flow->saveSetup($owner, $workspace, $draft, 1, contributionDraftSetup($owner->id));
    $draft = $flow->savePartners($owner, $workspace, $draft, 2, [$profile]);
    $input = array_merge([
        'partner_key' => 'review-partner',
        'contribution_type' => 'cash',
        'description' => 'Cash contribution',
        'owner_reference' => 'Review partner',
        'proposed_value' => '100.00',
        'valuation_method' => 'Cash receipt',
        'evidence_references' => ['receipt-001'],
        'conditions' => '',
        'notes' => '',
        'amount_committed' => '100.00',
        'amount_received' => '100.00',
        'payment_date' => '2026-09-09',
    ], $changes);
    $draft = $flow->saveItem($owner, $workspace, $draft, 3, $input);

    return [$flow, $draft, $draft->payload['items'][0]['id']];
}

test('contribution review validates stored evidence and records reviewer without acceptance', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    $saved = $flow->reviewItem($this->owner, $this->workspace, $draft, 4, $id, '95.00');
    $item = $saved->payload['items'][0];

    expect($item['status'])->toBe('reviewed')
        ->and($item['reviewed_value'])->toBe('95.00')
        ->and($item['approval_records'])->toBe([])
        ->and($item['event_history'][1]['actor_user_id'])->toBe($this->owner->id)
        ->and($saved->payload['edit_version'])->toBe(5)
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

test('contribution review blocks missing evidence without altering stored draft', function () {
    [$flow, $draft, $id] = contributionReviewDraft(
        $this->owner, $this->workspace, ['evidence_references' => []],
    );
    $before = $draft->payload;
    expect(fn () => $flow->reviewItem($this->owner, $this->workspace, $draft, 4, $id, '100.00'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($draft->fresh()->payload)->toBe($before);
});

test('contribution review rejects invalid value stale version and partner authority', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    expect(fn () => $flow->reviewItem($this->owner, $this->workspace, $draft, 4, $id, '-1'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $flow->reviewItem($this->owner, $this->workspace, $draft, 3, $id, '100'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);
    expect(fn () => $flow->reviewItem($partner, $this->workspace, $draft, 4, $id, '100'))
        ->toThrow(AuthorizationException::class);
    expect($draft->fresh()->payload['items'][0]['status'])->toBe('proposed');
});

test('contribution closure records reasons and excludes acceptance', function ($target) {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    $saved = $flow->closeItem(
        $this->owner, $this->workspace, $draft, 4, $id,
        $target, 'Partner withdrew the offer', 'decision-note-001',
    );
    $item = $saved->payload['items'][0];

    expect($item['status'])->toBe($target)
        ->and($item['approval_records'])->toBe([])
        ->and($item['event_history'][1]['reason'])->toBe('Partner withdrew the offer')
        ->and($item['event_history'][1]['evidence_reference'])->toBe('decision-note-001');
    expect(fn () => $flow->reviewItem($this->owner, $this->workspace, $saved, 5, $id, '100'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
})->with(['rejected', 'cancelled']);

test('contribution closure prevents state bypass and requires evidence', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    foreach ([
        ['accepted', 'reason', 'ref'],
        ['defaulted', 'reason', 'ref'],
        ['cancelled', '', 'ref'],
        ['cancelled', 'reason', ''],
    ] as [$status, $reason, $evidence]) {
        expect(fn () => $flow->closeItem(
            $this->owner, $this->workspace, $draft, 4, $id, $status, $reason, $evidence,
        ))->toThrow(\Illuminate\Validation\ValidationException::class);
    }
    expect($draft->fresh()->payload['edit_version'])->toBe(4);
});

test('contribution default records prior approval without retaining active approval', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    // Isolate the default transition; the approval workflow is tested separately.
    $payload = $draft->payload;
    $payload['items'][0]['status'] = 'approved';
    $payload['items'][0]['approval_records'] = [['user_id' => $this->owner->id]];
    app(\App\Services\PbrTools\PbrV2CanonicalWorkService::class)
        ->updateWorkingDraftPayload($this->workspace, $draft, $payload);
    $saved = $flow->closeItem(
        $this->owner, $this->workspace, $draft, 4, $id,
        'defaulted', 'Commitment was not fulfilled', 'default-review-001',
    );
    $item = $saved->payload['items'][0];
    expect($item['status'])->toBe('defaulted')
        ->and($item['approval_records'])->toBe([])
        ->and($item['event_history'][1]['previous_approval_records'])
        ->toBe([['user_id' => $this->owner->id]]);
});

test('contribution agreement approval records real actor without accepting or publishing', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    $draft = $flow->reviewItem($this->owner, $this->workspace, $draft, 4, $id, '100.00');
    $approval = app(\App\Services\PbrTools\PbrV2ContributionApprovalService::class);
    $saved = $approval->approveItem(
        $this->owner, $this->workspace, $draft, 5, $id, '95.00', 'agreement-001',
    );
    $item = $saved->payload['items'][0];
    expect($item['status'])->toBe('approved')
        ->and($item['agreed_value'])->toBe('95.00')
        ->and($item['approval_records'][0]['user_id'])->toBe($this->owner->id)
        ->and($item['approval_records'][0]['evidence_reference'])->toBe('agreement-001')
        ->and($saved->state)->toBe('working_draft')
        ->and($saved->published_canonical_revision_id)->toBeNull()
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

test('contribution agreement needs every designated approver on the same subject', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);
    $setup = contributionDraftSetup($this->owner->id);
    $setup['approver_user_ids'][] = $partner->id;
    $draft = $flow->saveSetup($this->owner, $this->workspace, $draft, 4, $setup);
    $draft = $flow->reviewItem($this->owner, $this->workspace, $draft, 5, $id, '100.00');
    $approval = app(\App\Services\PbrTools\PbrV2ContributionApprovalService::class);
    $first = $approval->approveItem(
        $this->owner, $this->workspace, $draft, 6, $id, '95.00', 'owner-agreement',
    );
    expect($first->payload['items'][0]['status'])->toBe('reviewed');

    expect(fn () => $approval->approveItem(
        $this->owner, $this->workspace, $first, 7, $id, '95.00', 'duplicate',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $approval->approveItem(
        $partner, $this->workspace, $first, 7, $id, '90.00', 'different-value',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $saved = $approval->approveItem(
        $partner, $this->workspace, $first, 7, $id, '95.00', 'partner-agreement',
    );
    expect($saved->payload['items'][0]['status'])->toBe('approved')
        ->and($saved->payload['items'][0]['approval_records'])->toHaveCount(2)
        ->and($saved->payload['edit_version'])->toBe(8);
});

test('contribution agreement denies unassigned admin and stale or premature approval', function () {
    [$flow, $draft, $id] = contributionReviewDraft($this->owner, $this->workspace);
    $approval = app(\App\Services\PbrTools\PbrV2ContributionApprovalService::class);
    expect(fn () => $approval->approveItem(
        $this->owner, $this->workspace, $draft, 4, $id, '100', 'premature',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $draft = $flow->reviewItem($this->owner, $this->workspace, $draft, 4, $id, '100.00');
    $admin = contributionAccessUser(['role' => 'admin', 'is_admin' => true]);
    expect(fn () => $approval->approveItem(
        $admin, $this->workspace, $draft, 5, $id, '100', 'unassigned',
    ))->toThrow(AuthorizationException::class);
    expect(fn () => $approval->approveItem(
        $this->owner, $this->workspace, $draft, 4, $id, '100', 'stale',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($draft->fresh()->payload['items'][0]['approval_records'])->toBe([]);
});

function contributionApprovedDraft(
    User $owner,
    PartnershipWorkspace $workspace,
    array $changes = [],
): array {
    [$flow, $draft, $id] = contributionReviewDraft($owner, $workspace, $changes);

    $draft = $flow->reviewItem(
        $owner,
        $workspace,
        $draft,
        4,
        $id,
        '100.00',
    );

    $approval = app(\App\Services\PbrTools\PbrV2ContributionApprovalService::class);

    $draft = $approval->approveItem(
        $owner,
        $workspace,
        $draft,
        5,
        $id,
        '95.00',
        'agreement-approved-001',
    );

    return [$flow, $approval, $draft, $id];
}

function contributionDeliveryInput(array $changes = []): array
{
    return array_merge([
        'accepted_value' => '95.00',
        'committed_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'delivered_date' => '2026-09-20',
        'delivery_extent' => 'full',
        'amount_received' => '100.00',
        'payment_date' => '2026-09-09',
    ], $changes);
}

test('contribution delivery preserves agreement history and starts a fresh acceptance stage', function () {
    [$flow, $approval, $draft, $id] = contributionApprovedDraft(
        $this->owner,
        $this->workspace,
    );

    $agreementRecords = $draft->payload['items'][0]['approval_records'];

    $delivery = contributionDeliveryInput([
        'accepted_value' => '90.00',
        'delivery_extent' => 'partial',
        'accepted_scope' => 'Documented portion accepted at THB 90.00',
        'adjustment_basis' => 'Remaining THB 5.00 is not accepted in this delivery.',
    ]);

    $saved = $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        6,
        $id,
        $delivery,
        'delivery-proof-001',
    );

    $item = $saved->payload['items'][0];
    $event = $item['event_history'][array_key_last($item['event_history'])];

    expect($item['status'])->toBe('delivered')
        ->and($item['accepted_value'])->toBe('90.00')
        ->and($item['delivery_extent'])->toBe('partial')
        ->and($item['accepted_scope'])->toBe('Documented portion accepted at THB 90.00')
        ->and($item['approval_records'])->toBe([])
        ->and($item['evidence_references'])->toContain('receipt-001', 'delivery-proof-001')
        ->and($event['action'])->toBe('item_delivered')
        ->and($event['previous_approval_records'])->toBe($agreementRecords)
        ->and($saved->payload['edit_version'])->toBe(7)
        ->and($saved->state)->toBe('working_draft')
        ->and($saved->published_canonical_revision_id)->toBeNull()
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

test('contribution acceptance uses fresh delivery-bound approval without publishing', function () {
    [$flow, $approval, $draft, $id] = contributionApprovedDraft(
        $this->owner,
        $this->workspace,
    );

    $agreementFingerprint = $draft->payload['items'][0]['approval_records'][0]['fingerprint'];

    $delivered = $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        6,
        $id,
        contributionDeliveryInput(),
        'delivery-proof-002',
    );

    expect($delivered->payload['items'][0]['status'])->toBe('delivered')
        ->and($delivered->payload['items'][0]['approval_records'])->toBe([]);

    $accepted = $approval->acceptItem(
        $this->owner,
        $this->workspace,
        $delivered,
        7,
        $id,
        'acceptance-proof-001',
        'Delivered value verified.',
    );

    $item = $accepted->payload['items'][0];
    $acceptanceFingerprint = $item['approval_records'][0]['fingerprint'];

    $assessment = app(\App\Services\PbrTools\PbrV2ContributionReadinessService::class)
        ->assessAcceptedItem(
            (int) $this->workspace->id,
            (int) $accepted->id,
            $accepted->payload['setup'],
            $item,
        );

    expect($item['status'])->toBe('accepted')
        ->and($item['approval_records'])->toHaveCount(1)
        ->and($item['approval_records'][0]['evidence_reference'])->toBe('acceptance-proof-001')
        ->and($acceptanceFingerprint)->not->toBe($agreementFingerprint)
        ->and($assessment['passed'])->toBeTrue()
        ->and($assessment['accepted_value'])->toBe('95.00')
        ->and($accepted->payload['edit_version'])->toBe(8)
        ->and($accepted->state)->toBe('working_draft')
        ->and($accepted->published_canonical_revision_id)->toBeNull()
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

test('contribution delivery cannot bypass agreement approval', function () {
    [$flow, $draft, $id] = contributionReviewDraft(
        $this->owner,
        $this->workspace,
    );

    $reviewed = $flow->reviewItem(
        $this->owner,
        $this->workspace,
        $draft,
        4,
        $id,
        '100.00',
    );

    $before = $reviewed->payload;

    expect(fn () => $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $reviewed,
        5,
        $id,
        contributionDeliveryInput(),
        'premature-delivery',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($reviewed->fresh()->payload)->toBe($before);
});

test('contribution delivery rejects stale non manager and incomplete partial writes', function () {
    [$flow, $approval, $draft, $id] = contributionApprovedDraft(
        $this->owner,
        $this->workspace,
    );

    $before = $draft->payload;

    expect(fn () => $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        5,
        $id,
        contributionDeliveryInput(),
        'stale-delivery',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);

    expect(fn () => $flow->recordDelivery(
        $partner,
        $this->workspace,
        $draft,
        6,
        $id,
        contributionDeliveryInput(),
        'partner-delivery',
    ))->toThrow(AuthorizationException::class);

    expect(fn () => $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        6,
        $id,
        contributionDeliveryInput([
            'accepted_value' => '90.00',
            'delivery_extent' => 'partial',
        ]),
        'incomplete-partial',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($draft->fresh()->payload)->toBe($before);
});

test('agreement approval cannot be reused as acceptance proof even when material subject is unchanged', function () {
    $prefilled = contributionDeliveryInput();

    [$flow, $approval, $draft, $id] = contributionApprovedDraft(
        $this->owner,
        $this->workspace,
        $prefilled,
    );

    $agreementRecords = $draft->payload['items'][0]['approval_records'];
    $agreementFingerprint = $agreementRecords[0]['fingerprint'];

    $delivered = $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        6,
        $id,
        $prefilled,
        'receipt-001',
    );

    $item = $delivered->payload['items'][0];

    $acceptanceFingerprint = app(\App\Services\PbrTools\PbrV2ContributionReadinessService::class)
        ->acceptanceFingerprint(
            (int) $this->workspace->id,
            (int) $delivered->id,
            $delivered->payload['setup'],
            $item,
        );

    expect($acceptanceFingerprint)->not->toBe($agreementFingerprint);

    $payload = $delivered->payload;
    $payload['items'][0]['approval_records'] = $agreementRecords;

    app(\App\Services\PbrTools\PbrV2CanonicalWorkService::class)
        ->updateWorkingDraftPayload($this->workspace, $delivered, $payload);

    $tampered = $delivered->fresh();
    $before = $tampered->payload;

    expect(fn () => $approval->acceptItem(
        $this->owner,
        $this->workspace,
        $tampered,
        7,
        $id,
        'reuse-attempt',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($tampered->fresh()->payload)->toBe($before);
});

test('contribution acceptance requires every designated approver on the delivered subject', function () {
    [$flow, $draft, $id] = contributionReviewDraft(
        $this->owner,
        $this->workspace,
    );

    $partner = contributionAccessUser(['role' => 'partner']);
    contributionAccessMembership($partner, $this->workspace);

    $setup = contributionDraftSetup($this->owner->id);
    $setup['approver_user_ids'][] = $partner->id;

    $draft = $flow->saveSetup(
        $this->owner,
        $this->workspace,
        $draft,
        4,
        $setup,
    );

    $draft = $flow->reviewItem(
        $this->owner,
        $this->workspace,
        $draft,
        5,
        $id,
        '100.00',
    );

    $approval = app(\App\Services\PbrTools\PbrV2ContributionApprovalService::class);

    $draft = $approval->approveItem(
        $this->owner,
        $this->workspace,
        $draft,
        6,
        $id,
        '95.00',
        'owner-agreement-f5',
    );

    $draft = $approval->approveItem(
        $partner,
        $this->workspace,
        $draft,
        7,
        $id,
        '95.00',
        'partner-agreement-f5',
    );

    expect($draft->payload['items'][0]['status'])->toBe('approved');

    $delivered = $flow->recordDelivery(
        $this->owner,
        $this->workspace,
        $draft,
        8,
        $id,
        contributionDeliveryInput(),
        'delivery-proof-multi',
    );

    $first = $approval->acceptItem(
        $this->owner,
        $this->workspace,
        $delivered,
        9,
        $id,
        'owner-acceptance',
    );

    expect($first->payload['items'][0]['status'])->toBe('delivered')
        ->and($first->payload['items'][0]['approval_records'])->toHaveCount(1)
        ->and($first->payload['edit_version'])->toBe(10);

    expect(fn () => $approval->acceptItem(
        $this->owner,
        $this->workspace,
        $first,
        10,
        $id,
        'duplicate-owner-acceptance',
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $accepted = $approval->acceptItem(
        $partner,
        $this->workspace,
        $first,
        10,
        $id,
        'partner-acceptance',
    );

    expect($accepted->payload['items'][0]['status'])->toBe('accepted')
        ->and($accepted->payload['items'][0]['approval_records'])->toHaveCount(2)
        ->and($accepted->payload['edit_version'])->toBe(11)
        ->and($accepted->published_canonical_revision_id)->toBeNull()
        ->and(DB::table('workspace_v2_canonical_revisions')->count())->toBe(0);
});

