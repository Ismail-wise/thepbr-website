<?php

use App\Http\Controllers\WorkspaceV2CapitalActionPlanController;
use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Services\PbrTools\PbrV2CapitalActionPlanService;
use App\Services\PbrTools\PbrV2CapitalDecisionRecordService;
use App\Services\PbrTools\PbrV2LifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function v2CapitalActionRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalActionRuntimePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalActionRuntimeWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' =>
            'Capital Action Runtime Workspace',
        'business_name' =>
            'Capital Action Runtime Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalActionRuntimeMembership(
    User $partner,
    PartnershipWorkspace $workspace
): WorkspaceMember {
    return WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);
}

function v2CapitalActionRuntimePayload(
    string $name = 'Runtime Action Capital Plan'
): array {
    $today = now()->toDateString();

    return [
        'schema_version' => 'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => $name,
        'scenario_type' => 'base',
        'assessment_date' => $today,
        'assumptions' => [
            'opening_date' =>
                now()->addDay()->toDateString(),
            'working_capital_months' => 3,
            'contingency_percentage' => '10.00',
            'monthly_burn' => '0.00',
        ],
        'cost_items' => [
            [
                'id' => 'action-runtime-cost-1',
                'category' => 'pre_opening',
                'description' =>
                    'Action runtime setup',
                'amount' => '1000.00',
                'basis' => 'one_time',
                'evidence_reference' =>
                    'action-runtime-quote',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'action-runtime-funding-1',
                'source_type' => 'partner_cash',
                'description' =>
                    'Action runtime funding',
                'confirmed_amount' => '1100.00',
                'confirmation_date' => $today,
                'evidence_reference' =>
                    'action-runtime-bank',
            ],
        ],
        'shortfall_response' => null,
        'decision_record' => [
            'owner_reference' =>
                'Runtime Finance Lead',
            'effective_date' => $today,
            'review_date' =>
                now()->addMonth()->toDateString(),
            'decision_summary' =>
                'Execute the recorded runtime capital decision.',
            'evidence_references' => [
                'action-runtime-board-minute',
                'action-runtime-capital-sheet',
            ],
        ],
    ];
}

function v2CapitalActionRuntimeRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    int $revision = 1,
    string $state = 'approved_current',
    string $name = 'Runtime Action Capital Plan'
): WorkspaceV2CanonicalRevision {
    return WorkspaceV2CanonicalRevision::query()
        ->create([
            'workspace_id' => $workspace->id,
            'domain_key' => 'capital',
            'component_key' =>
                'approved_capital_plan',
            'component_type' => 'plan',
            'revision' => $revision,
            'state' => $state,
            'payload' =>
                v2CapitalActionRuntimePayload(
                    $name
                ),
            'approval_decision' => [
                'decision' => 'approved',
            ],
            'published_by_user_id' =>
                $publisher->id,
            'published_at' =>
                now()->subMinute(),
            'superseded_at' =>
                $state === 'superseded'
                    ? now()
                    : null,
        ]);
}

function v2CapitalActionRuntimeRecord(
    User $owner,
    PartnershipWorkspace $workspace
): array {
    return app(
        PbrV2CapitalDecisionRecordService::class
    )->recordCurrent(
        $owner,
        $workspace
    );
}

function v2CapitalActionRuntimeStorePayload(
    int $recordId,
    string $title = 'Fund opening inventory'
): array {
    return [
        'decision_record_id' => $recordId,
        'title' => $title,
        'description' =>
            'Execute the recorded capital decision.',
        'owner_name' => 'Finance Lead',
        'priority' => 'high',
        'due_date' => '2026-09-30',
    ];
}

test(
    'capital action runtime uses dedicated routes and controller while frozen capital controller contract stays unchanged',
    function () {
        $show = Route::getRoutes()->getByName(
            'workspaces.tools.v2.capital.action-plan.show'
        );

        $store = Route::getRoutes()->getByName(
            'workspaces.tools.v2.capital.action-plan.store'
        );

        $transition = Route::getRoutes()->getByName(
            'workspaces.tools.v2.capital.action-plan.transition'
        );

        expect($show)->not->toBeNull()
            ->and($show->methods())
            ->toContain('GET');

        expect($store)->not->toBeNull()
            ->and($store->methods())
            ->toContain('POST');

        expect($transition)->not->toBeNull()
            ->and($transition->methods())
            ->toContain('PATCH');

        $controller = new ReflectionClass(
            WorkspaceV2CapitalActionPlanController::class
        );

        expect($controller->isFinal())
            ->toBeTrue();

        expect(
            array_map(
                fn ($parameter) =>
                    (string) $parameter->getType(),
                $controller
                    ->getConstructor()
                    ->getParameters()
            )
        )->toBe([
            PbrV2CapitalActionPlanService::class,
            PbrV2CapitalDecisionRecordService::class,
            PbrV2LifecycleService::class,
        ]);

        $capital = new ReflectionClass(
            WorkspaceV2CapitalController::class
        );

        expect(
            $capital
                ->getConstructor()
                ->getParameters()
        )->toHaveCount(6)
            ->and(
                $capital
                    ->getMethod('show')
                    ->getParameters()
            )
            ->toHaveCount(3);
    }
);

test(
    'manager sees record to act phase but cannot create action before a decision record exists',
    function () {
        $owner =
            v2CapitalActionRuntimeOwner();

        $workspace =
            v2CapitalActionRuntimeWorkspace(
                $owner
            );

        v2CapitalActionRuntimeRevision(
            $owner,
            $workspace
        );

        $main = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $main
            ->assertOk()
            ->assertSee('Capital Action Plan')
            ->assertSee('Record phase ကို')
            ->assertDontSee('Open Action Plan');

        $response = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.action-plan.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'ACT / CAPITAL ACTION PLAN'
            )
            ->assertSee(
                'Record Decision first'
            )
            ->assertDontSee(
                'Create Action Item'
            );

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'manager creates action from recorded decision and sees exact execution provenance',
    function () {
        $owner =
            v2CapitalActionRuntimeOwner();

        $workspace =
            v2CapitalActionRuntimeWorkspace(
                $owner
            );

        v2CapitalActionRuntimeRevision(
            $owner,
            $workspace
        );

        $record =
            v2CapitalActionRuntimeRecord(
                $owner,
                $workspace
            );

        $main = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $main
            ->assertOk()
            ->assertSeeInOrder([
                'CAPITAL DECISION RECORD',
                'Capital Action Plan',
                'VERSION HISTORY',
            ])
            ->assertSee('Open Action Plan');

        $showUrl = route(
            'workspaces.tools.v2.capital.action-plan.show',
            $workspace
        );

        $this
            ->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.action-plan.store',
                    $workspace
                ),
                v2CapitalActionRuntimeStorePayload(
                    $record['id']
                )
            )
            ->assertRedirect($showUrl)
            ->assertSessionHas('status');

        $action = WorkspaceV2Action::query()
            ->firstOrFail();

        expect($action->status)
            ->toBe('open')
            ->and(
                $action->producer_operating_record_id
            )
            ->toBe($record['id']);

        $page = $this
            ->actingAs($owner)
            ->get($showUrl);

        $page
            ->assertOk()
            ->assertSee(
                'Fund opening inventory'
            )
            ->assertSee('Finance Lead')
            ->assertSee('HIGH')
            ->assertSee('OPEN')
            ->assertSee('2026-09-30')
            ->assertSee(
                'Decision Record #'.$record['id']
            )
            ->assertSee('Revision 1')
            ->assertSee('IN PROGRESS')
            ->assertSee('BLOCKED')
            ->assertSee('COMPLETED')
            ->assertSee('SUPERSEDED')
            ->assertSee(
                'ACT Authority Boundary'
            );
    }
);

test(
    'capital action runtime transitions only through frozen lifecycle and terminal action cannot reopen',
    function () {
        $owner =
            v2CapitalActionRuntimeOwner();

        $workspace =
            v2CapitalActionRuntimeWorkspace(
                $owner
            );

        v2CapitalActionRuntimeRevision(
            $owner,
            $workspace
        );

        $record =
            v2CapitalActionRuntimeRecord(
                $owner,
                $workspace
            );

        $action = app(
            PbrV2CapitalActionPlanService::class
        )->createFromDecisionRecord(
            $owner,
            $workspace,
            $record['id'],
            [
                'title' =>
                    'Lifecycle runtime item',
                'priority' => 'normal',
            ]
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.action-plan.show',
            $workspace
        );

        $transitionUrl =
            route(
                'workspaces.tools.v2.capital.action-plan.transition',
                [
                    'workspace' => $workspace,
                    'actionId' => $action['id'],
                ]
            );

        $this
            ->actingAs($owner)
            ->patch(
                $transitionUrl,
                [
                    'to_state' =>
                        'in_progress',
                ]
            )
            ->assertRedirect($showUrl);

        $this
            ->actingAs($owner)
            ->patch(
                $transitionUrl,
                [
                    'to_state' =>
                        'completed',
                ]
            )
            ->assertRedirect($showUrl);

        $completed =
            WorkspaceV2Action::query()
                ->findOrFail($action['id']);

        expect($completed->status)
            ->toBe('completed')
            ->and($completed->completed_at)
            ->not->toBeNull();

        $this
            ->actingAs($owner)
            ->from($showUrl)
            ->patch(
                $transitionUrl,
                [
                    'to_state' =>
                        'in_progress',
                ]
            )
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors();

        expect(
            WorkspaceV2Action::query()
                ->findOrFail($action['id'])
                ->status
        )->toBe('completed');

        $this
            ->actingAs($owner)
            ->get($showUrl)
            ->assertOk()
            ->assertSee('Terminal');
    }
);

test(
    'accepted partner never receives capital action management read create or transition surface',
    function () {
        $owner =
            v2CapitalActionRuntimeOwner();

        $workspace =
            v2CapitalActionRuntimeWorkspace(
                $owner
            );

        v2CapitalActionRuntimeRevision(
            $owner,
            $workspace
        );

        $record =
            v2CapitalActionRuntimeRecord(
                $owner,
                $workspace
            );

        $action = app(
            PbrV2CapitalActionPlanService::class
        )->createFromDecisionRecord(
            $owner,
            $workspace,
            $record['id'],
            [
                'title' =>
                    'Owner only runtime action',
                'priority' => 'normal',
            ]
        );

        $partner =
            v2CapitalActionRuntimePartner();

        v2CapitalActionRuntimeMembership(
            $partner,
            $workspace
        );

        $main = $this
            ->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $main->assertOk();

        expect($main->getContent())
            ->not->toContain(
                'id="v2-capital-action-plan"'
            );

        $this
            ->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.action-plan.show',
                    $workspace
                )
            )
            ->assertForbidden();

        $before =
            WorkspaceV2Action::query()
                ->count();

        $this
            ->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.action-plan.store',
                    $workspace
                ),
                []
            )
            ->assertForbidden();

        $this
            ->actingAs($partner)
            ->patch(
                route(
                    'workspaces.tools.v2.capital.action-plan.transition',
                    [
                        'workspace' => $workspace,
                        'actionId' =>
                            $action['id'],
                    ]
                ),
                [
                    'to_state' =>
                        'completed',
                ]
            )
            ->assertForbidden();

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe($before)
            ->and(
                WorkspaceV2Action::query()
                    ->findOrFail(
                        $action['id']
                    )
                    ->status
            )
            ->toBe('open');
    }
);

test(
    'runtime creation rejects a recorded decision whose approved plan source has become stale',
    function () {
        $owner =
            v2CapitalActionRuntimeOwner();

        $workspace =
            v2CapitalActionRuntimeWorkspace(
                $owner
            );

        $revision =
            v2CapitalActionRuntimeRevision(
                $owner,
                $workspace
            );

        $record =
            v2CapitalActionRuntimeRecord(
                $owner,
                $workspace
            );

        $revision->state = 'superseded';
        $revision->superseded_at = now();
        $revision->save();

        v2CapitalActionRuntimeRevision(
            $owner,
            $workspace,
            2,
            'approved_current',
            'Replacement Runtime Capital Plan'
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.action-plan.show',
            $workspace
        );

        $this
            ->actingAs($owner)
            ->from($showUrl)
            ->post(
                route(
                    'workspaces.tools.v2.capital.action-plan.store',
                    $workspace
                ),
                v2CapitalActionRuntimeStorePayload(
                    $record['id'],
                    'Stale source action'
                )
            )
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors();

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe(0);
    }
);
