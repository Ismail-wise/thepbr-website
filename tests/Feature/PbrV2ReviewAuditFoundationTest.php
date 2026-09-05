<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2ReviewAuditEvent;
use App\Models\WorkspaceV2ReviewCase;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2ComponentRegistryService;
use App\Services\PbrTools\PbrV2ReviewAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function f8ReviewWorkspace(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'F8 Review Workspace',
        'business_name' => 'F8 Review Workspace',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return [$owner, $workspace];
}

function f8PublishCurrent(
    PartnershipWorkspace $workspace,
    User $actor,
    string $domainKey = 'share_transfer',
    string $componentKey = 'share_transfer_rule',
    array $payload = ['rule' => 'current']
): WorkspaceV2CanonicalRevision {
    $decision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        $domainKey,
        $componentKey,
        'proposed',
        true,
        true
    );

    return app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        $domainKey,
        $componentKey,
        $payload,
        $decision,
        $actor
    );
}

test('f8 review and audit tables expose the exact required columns', function () {
    expect(
        Schema::hasTable(
            'workspace_v2_review_cases'
        )
    )->toBeTrue()
        ->and(
            Schema::hasTable(
                'workspace_v2_review_audit_events'
            )
        )->toBeTrue();

    foreach ([
        'workspace_id',
        'target_canonical_revision_id',
        'target_domain_key',
        'target_component_key',
        'target_revision',
        'producer_domain_key',
        'producer_component_key',
        'trigger_type',
        'trigger_context',
        'status',
        'resolution',
        'resolution_note',
        'opened_by_user_id',
        'resolved_by_user_id',
        'opened_at',
        'resolved_at',
    ] as $column) {
        expect(
            Schema::hasColumn(
                'workspace_v2_review_cases',
                $column
            )
        )->toBeTrue();
    }

    foreach ([
        'workspace_id',
        'review_case_id',
        'event_type',
        'actor_user_id',
        'metadata',
        'occurred_at',
    ] as $column) {
        expect(
            Schema::hasColumn(
                'workspace_v2_review_audit_events',
                $column
            )
        )->toBeTrue();
    }
});

test('f8 exposes the exact frozen review vocabulary', function () {
    $service = app(
        PbrV2ReviewAuditService::class
    );

    expect($service->statuses())->toBe([
        'open',
        'resolved',
    ])->and($service->resolutions())->toBe([
        'no_change',
        'amendment_required',
        'dismissed',
    ])->and($service->triggerTypes())->toBe([
        'dependency_change',
        'scheduled_review',
        'manual_review',
        'producer_signal',
    ])->and($service->auditEventTypes())->toBe([
        'review_opened',
        'review_resolved',
        'amendment_requested',
    ]);
});

test('f8 opens review only against exact approved current canonical revision', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $case = app(
        PbrV2ReviewAuditService::class
    )->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review',
        ['reason' => 'Periodic check'],
        $owner
    );

    expect($case->status)->toBe('open')
        ->and($case->resolution)->toBeNull()
        ->and($case->target_canonical_revision_id)
        ->toBe($current->id)
        ->and($case->target_revision)
        ->toBe(1)
        ->and($case->isOpen())
        ->toBeTrue()
        ->and($case->opened_by_user_id)
        ->toBe($owner->id);

    $events = app(
        PbrV2ReviewAuditService::class
    )->events($workspace, $case);

    expect($events)->toHaveCount(1)
        ->and($events[0]->event_type)
        ->toBe('review_opened');
});

test('opening review never changes canonical current state', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    app(PbrV2ReviewAuditService::class)
        ->openReview(
            $workspace,
            'share_transfer',
            'share_transfer_rule',
            'share_transfer',
            'share_transfer_rule',
            $current->revision,
            'manual_review',
            [],
            $owner
        );

    expect($current->fresh()->state)
        ->toBe('approved_current')
        ->and($current->fresh()->superseded_at)
        ->toBeNull();
});

test('superseded canonical revision cannot become a new review target', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $first = f8PublishCurrent(
        $workspace,
        $owner,
        'share_transfer',
        'share_transfer_rule',
        ['version' => 1]
    );

    f8PublishCurrent(
        $workspace,
        $owner,
        'share_transfer',
        'share_transfer_rule',
        ['version' => 2]
    );

    expect(
        fn () =>
            app(PbrV2ReviewAuditService::class)
                ->openReview(
                    $workspace,
                    'share_transfer',
                    'share_transfer_rule',
                    'share_transfer',
                    'share_transfer_rule',
                    $first->revision,
                    'manual_review',
                    [],
                    $owner
                )
    )->toThrow(DomainException::class);
});

test('producer identity and review capability are enforced', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    expect(
        fn () =>
            app(PbrV2ReviewAuditService::class)
                ->openReview(
                    $workspace,
                    'ownership',
                    'share_transfer_rule',
                    'share_transfer',
                    'share_transfer_rule',
                    $current->revision,
                    'manual_review'
                )
    )->toThrow(DomainException::class);

    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $blockedKey = null;
    $blocked = null;

    foreach ($registry->all() as $key => $component) {
        if (
            ($component['can_trigger_review'] ?? true)
            === false
        ) {
            $blockedKey = $key;
            $blocked = $component;
            break;
        }
    }

    expect($blockedKey)->not->toBeNull();

    expect(
        fn () =>
            app(PbrV2ReviewAuditService::class)
                ->openReview(
                    $workspace,
                    $blocked['domain_key'],
                    $blockedKey,
                    'share_transfer',
                    'share_transfer_rule',
                    $current->revision,
                    'manual_review'
                )
    )->toThrow(DomainException::class);
});

test('review producer cannot gain arbitrary cross domain authority', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    expect(
        fn () =>
            app(PbrV2ReviewAuditService::class)
                ->openReview(
                    $workspace,
                    'capital',
                    'capital_call_rule',
                    'share_transfer',
                    'share_transfer_rule',
                    $current->revision,
                    'producer_signal'
                )
    )->toThrow(DomainException::class);
});

test('implementation review workflow may review its declared upstream domains', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $case = app(
        PbrV2ReviewAuditService::class
    )->openReview(
        $workspace,
        'implementation_review',
        'implementation_review_workflow',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'scheduled_review',
        [],
        $owner
    );

    expect($case->producer_component_key)
        ->toBe('implementation_review_workflow')
        ->and($case->target_component_key)
        ->toBe('share_transfer_rule')
        ->and($case->status)
        ->toBe('open');
});

test('review may resolve with no change while current remains authoritative', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review',
        [],
        $owner
    );

    $resolved = $service->resolveReview(
        $workspace,
        $case,
        'no_change',
        'Current rule remains suitable.',
        $owner
    );

    expect($resolved->status)
        ->toBe('resolved')
        ->and($resolved->resolution)
        ->toBe('no_change')
        ->and($resolved->isResolved())
        ->toBeTrue()
        ->and($current->fresh()->state)
        ->toBe('approved_current');

    expect(
        $service->events(
            $workspace,
            $resolved
        )->pluck('event_type')->all()
    )->toBe([
        'review_opened',
        'review_resolved',
    ]);
});

test('review may be dismissed without changing canonical state', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'producer_signal',
        [],
        $owner
    );

    $resolved = $service->resolveReview(
        $workspace,
        $case,
        'dismissed',
        null,
        $owner
    );

    expect($resolved->resolution)
        ->toBe('dismissed')
        ->and($current->fresh()->state)
        ->toBe('approved_current');
});

test('amendment request resolves review and returns orchestration handoff only', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'dependency_change',
        [
            'changed_source' => 'ownership',
        ],
        $owner
    );

    $handoff = $service->requestAmendment(
        $workspace,
        $case,
        'Ownership structure changed.',
        [
            'requested_focus' =>
                'transfer eligibility',
        ],
        $owner
    );

    $case->refresh();

    expect($case->status)
        ->toBe('resolved')
        ->and($case->resolution)
        ->toBe('amendment_required')
        ->and($handoff['working_draft_required'])
        ->toBeTrue()
        ->and($handoff['working_draft_created'])
        ->toBeFalse()
        ->and($handoff['proposal_created'])
        ->toBeFalse()
        ->and($handoff['approval_decision_created'])
        ->toBeFalse()
        ->and($handoff['canonical_revision_created'])
        ->toBeFalse()
        ->and($handoff['publication_performed'])
        ->toBeFalse()
        ->and($handoff['current_state_changed'])
        ->toBeFalse()
        ->and($handoff['required_next_artifact_state'])
        ->toBe('working_draft')
        ->and($current->fresh()->state)
        ->toBe('approved_current');

    expect(
        $service->events(
            $workspace,
            $case
        )->pluck('event_type')->all()
    )->toBe([
        'review_opened',
        'review_resolved',
        'amendment_requested',
    ]);

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->count()
    )->toBe(1);
});

test('amendment required cannot bypass the dedicated amendment handoff', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review'
    );

    expect(
        fn () =>
            $service->resolveReview(
                $workspace,
                $case,
                'amendment_required'
            )
    )->toThrow(InvalidArgumentException::class);
});

test('resolved review cannot be resolved or amended twice', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review'
    );

    $service->resolveReview(
        $workspace,
        $case,
        'no_change'
    );

    expect(
        fn () =>
            $service->resolveReview(
                $workspace,
                $case,
                'dismissed'
            )
    )->toThrow(DomainException::class);

    expect(
        fn () =>
            $service->requestAmendment(
                $workspace,
                $case
            )
    )->toThrow(DomainException::class);
});

test('review audit events are immutable append only evidence', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review'
    );

    $event = $service
        ->events($workspace, $case)
        ->first();

    expect(
        fn () =>
            $event->forceFill([
                'event_type' => 'review_resolved',
            ])->save()
    )->toThrow(LogicException::class);

    $event->refresh();

    expect(
        fn () => $event->delete()
    )->toThrow(LogicException::class);

    expect(
        WorkspaceV2ReviewAuditEvent::query()
            ->count()
    )->toBe(1)
        ->and(
            WorkspaceV2ReviewAuditEvent::query()
                ->first()
                ->event_type
        )->toBe('review_opened');
});

test('review cases cannot be deleted while audit evidence must remain attached', function () {
    [$owner, $workspace] = f8ReviewWorkspace();

    $current = f8PublishCurrent(
        $workspace,
        $owner
    );

    $service = app(
        PbrV2ReviewAuditService::class
    );

    $case = $service->openReview(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        'share_transfer',
        'share_transfer_rule',
        $current->revision,
        'manual_review',
        [],
        $owner
    );

    expect(
        fn () => $case->delete()
    )->toThrow(LogicException::class);

    expect(
        WorkspaceV2ReviewCase::query()->count()
    )->toBe(1)
        ->and(
            WorkspaceV2ReviewAuditEvent::query()
                ->count()
        )->toBe(1);

    $migration = file_get_contents(
        base_path(
            'database/migrations/'
            .'2026_09_05_010000_'
            .'create_workspace_v2_review_audit_tables.php'
        )
    );

    expect($migration)
        ->toContain(
            "'review_case_id'"
        )
        ->toContain(
            '->restrictOnDelete();'
        );
});

test('f8 preserves the exact review producer and canonical target counts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $all = collect($registry->all());

    expect(
        $all->filter(
            fn (array $component): bool =>
                ($component['can_trigger_review'] ?? false)
                === true
        )
    )->toHaveCount(103)
        ->and(
            $all->filter(
                fn (array $component): bool =>
                    ($component[
                        'canonical_capability'
                    ] ?? 'none') !== 'none'
            )
        )->toHaveCount(32);
});

test('f8 service exposes none of the reserved f6 f7 or execution authority methods', function () {
    $reflection = new ReflectionClass(
        PbrV2ReviewAuditService::class
    );

    foreach (
        config(
            'pbr_v2_review_audit.'
            .'forbidden_service_methods',
            []
        )
        as $method
    ) {
        expect(
            $reflection->hasMethod($method)
        )->toBeFalse();
    }
});

test('f8 service contains no canonical publication draft store or legacy execution side effects', function () {
    $source = file_get_contents(
        base_path(
            'app/Services/PbrTools/'
            .'PbrV2ReviewAuditService.php'
        )
    );

    expect($source)
        ->not->toContain('publishAgreedOutput')
        ->not->toContain('WorkspaceToolAction')
        ->not->toContain('WorkspaceOperatingRecord')
        ->not->toContain('ToolSession')
        ->not->toContain('workspace_v2_working')
        ->not->toContain('workspace_v2_proposal')
        ->not->toContain('chapter_number')
        ->not->toContain('chapterNumber');
});

test('review case and audit evidence remain separate storage responsibilities', function () {
    expect(
        WorkspaceV2ReviewCase::query()->count()
    )->toBe(0)
        ->and(
            WorkspaceV2ReviewAuditEvent::query()
                ->count()
        )->toBe(0);

    $migration = file_get_contents(
        base_path(
            'database/migrations/'
            .'2026_09_05_010000_'
            .'create_workspace_v2_review_audit_tables.php'
        )
    );

    expect($migration)
        ->toContain('workspace_v2_review_cases')
        ->toContain(
            'workspace_v2_review_audit_events'
        );
});
