<?php

use App\Http\Controllers\WorkspaceStartupCapitalController;
use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function v2CapitalRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalRuntimePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalRuntimeWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'V2 Capital Runtime Workspace',
        'business_name' =>
            'V2 Capital Runtime Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalRuntimeMembership(
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

function v2CapitalRuntimeForm(
    array $overrides = []
): array {
    $date = now()->toDateString();

    $base = [
        'plan_name' => 'Base Capital Plan',
        'scenario_type' => 'base',
        'assessment_date' => $date,
        'opening_date' =>
            now()->addDay()->toDateString(),
        'working_capital_months' => 3,
        'contingency_percentage' => '10.00',
        'monthly_burn' => '0.00',
        'cost_items' => [
            [
                'id' => 'cost-1',
                'category' => 'pre_opening',
                'description' =>
                    'Registration and setup',
                'amount' => '1000.00',
                'basis' => 'one_time',
                'evidence_reference' =>
                    'Setup quotation A',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'funding-1',
                'source_type' =>
                    'partner_cash',
                'description' =>
                    'Partner confirmed cash',
                'confirmed_amount' =>
                    '1100.00',
                'confirmation_date' => $date,
                'evidence_reference' =>
                    'Bank confirmation A',
            ],
        ],
        'decision_owner_reference' =>
            'Workspace Owner',
        'decision_effective_date' => $date,
        'decision_review_date' =>
            now()->addMonth()->toDateString(),
        'decision_summary' =>
            'Approve the base capital plan for launch.',
        'decision_evidence_references' =>
            "Setup quotation A\nBank confirmation A",
    ];

    return array_replace_recursive(
        $base,
        $overrides
    );
}

function v2CapitalRuntimeCreateDraft(
    $test,
    User $owner,
    PartnershipWorkspace $workspace,
    array $overrides = []
): WorkspaceV2CanonicalWorkArtifact {
    $response = $test
        ->actingAs($owner)
        ->post(
            route(
                'workspaces.tools.v2.capital.store',
                $workspace
            ),
            v2CapitalRuntimeForm($overrides)
        );

    $artifact = WorkspaceV2CanonicalWorkArtifact::query()
        ->where('workspace_id', $workspace->id)
        ->where(
            'target_domain_key',
            'capital'
        )
        ->where(
            'target_component_key',
            'approved_capital_plan'
        )
        ->latest('id')
        ->firstOrFail();

    $response->assertRedirect(
        route(
            'workspaces.tools.v2.capital.show',
            [
                'workspace' => $workspace,
                'artifact' => $artifact->id,
            ]
        )
    );

    return $artifact;
}

test(
    'v2 capital runtime exposes isolated authenticated routes without replacing legacy startup capital',
    function () {
        $show = Route::getRoutes()->getByName(
            'workspaces.tools.v2.capital.show'
        );

        expect($show)->not->toBeNull()
            ->and($show->uri())
            ->toBe(
                'workspaces/{workspace}/v2/capital'
            )
            ->and($show->getActionName())
            ->toContain(
                WorkspaceV2CapitalController::class
            );

        foreach ([
            'workspaces.tools.v2.capital.store',
            'workspaces.tools.v2.capital.update',
            'workspaces.tools.v2.capital.submit',
            'workspaces.tools.v2.capital.approve',
            'workspaces.tools.v2.capital.reject',
        ] as $name) {
            expect(
                Route::getRoutes()->getByName($name)
            )->not->toBeNull();
        }

        $legacy = Route::getRoutes()->getByName(
            'workspaces.tools.startup-capital.show'
        );

        expect($legacy)->not->toBeNull()
            ->and($legacy->getActionName())
            ->toContain(
                WorkspaceStartupCapitalController::class
            );

        $source = file_get_contents(
            app_path(
                'Http/Controllers/WorkspaceV2CapitalController.php'
            )
        );

        expect($source)
            ->not->toContain('ToolScenarioService')
            ->not->toContain(
                'StartupCapitalCalculator'
            )
            ->not->toContain(
                'publishAgreedOutput'
            );
    }
);

test(
    'v2 capital runtime requires authentication',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $this->get(
            route(
                'workspaces.tools.v2.capital.show',
                $workspace
            )
        )->assertRedirect(
            route('login')
        );
    }
);

test(
    'owner can open the separate v2 capital authority page',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee('Capital Plan Authority')
            ->assertSee('V2 CANONICAL PLAN')
            ->assertSee('Create V2 Draft')
            ->assertSee(
                'capital / approved_capital_plan'
            );
    }
);

test(
    'owner creates an explicit capital plan v1 working draft through v2 runtime',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $artifact = v2CapitalRuntimeCreateDraft(
            $this,
            $owner,
            $workspace
        );

        expect($artifact->state)
            ->toBe('working_draft')
            ->and(
                $artifact->target_domain_key
            )
            ->toBe('capital')
            ->and(
                $artifact->target_component_key
            )
            ->toBe('approved_capital_plan')
            ->and(
                $artifact->producer_domain_key
            )
            ->toBe('capital')
            ->and(
                $artifact->producer_component_key
            )
            ->toBe('capital_funding_workflow')
            ->and(
                $artifact->payload[
                    'schema_version'
                ]
            )
            ->toBe('capital-plan-v1')
            ->and(
                $artifact->payload[
                    'business_stage'
                ]
            )
            ->toBe('new')
            ->and(
                $artifact->payload[
                    'currency_code'
                ]
            )
            ->toBe('THB')
            ->and(
                $artifact->payload[
                    'cost_items'
                ][0]['amount']
            )
            ->toBe('1000.00');

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    [
                        'workspace' =>
                            $workspace,
                        'artifact' =>
                            $artifact->id,
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Capital Plan Ready')
            ->assertSee('Submit Proposal');
    }
);

test(
    'owner updates submits and approves the exact persisted v2 capital proposal',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $artifact = v2CapitalRuntimeCreateDraft(
            $this,
            $owner,
            $workspace
        );

        $updatedForm = v2CapitalRuntimeForm([
            'plan_name' =>
                'Growth-ready Base Plan',
        ]);

        $this->actingAs($owner)
            ->put(
                route(
                    'workspaces.tools.v2.capital.update',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                ),
                $updatedForm
            )
            ->assertRedirect();

        $artifact = $artifact->fresh();

        expect(
            $artifact->payload[
                'plan_name'
            ]
        )->toBe('Growth-ready Base Plan');

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.submit',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            )
            ->assertRedirect();

        $artifact = $artifact->fresh();

        expect($artifact->state)
            ->toBe('proposed')
            ->and($artifact->submitted_at)
            ->not->toBeNull();

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.approve',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            )
            ->assertRedirect(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $artifact = $artifact->fresh();

        $revision =
            WorkspaceV2CanonicalRevision::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'domain_key',
                    'capital'
                )
                ->where(
                    'component_key',
                    'approved_capital_plan'
                )
                ->where(
                    'state',
                    'approved_current'
                )
                ->firstOrFail();

        expect($artifact->consumed_at)
            ->not->toBeNull()
            ->and(
                (int) $artifact
                    ->published_canonical_revision_id
            )
            ->toBe((int) $revision->id)
            ->and(
                $revision->payload[
                    'plan_name'
                ]
            )
            ->toBe('Growth-ready Base Plan');

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee('APPROVED CURRENT')
            ->assertSee(
                'Growth-ready Base Plan'
            )
            ->assertSeeInOrder(['THB', '1100.00']);
    }
);

test(
    'runtime blocks proposal submission when persisted draft readiness fails',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $artifact = v2CapitalRuntimeCreateDraft(
            $this,
            $owner,
            $workspace,
            [
                'plan_name' => '',
            ]
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.show',
            [
                'workspace' => $workspace,
                'artifact' => $artifact->id,
            ]
        );

        $this->actingAs($owner)
            ->from($showUrl)
            ->post(
                route(
                    'workspaces.tools.v2.capital.submit',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            )
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(
                'payload.plan_name'
            );

        expect($artifact->fresh()->state)
            ->toBe('working_draft');
    }
);

test(
    'accepted partner sees approved current only and cannot inspect private v2 drafts',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $artifact = v2CapitalRuntimeCreateDraft(
            $this,
            $owner,
            $workspace
        );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.submit',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.approve',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            );

        $approvedPayload =
            WorkspaceV2CanonicalRevision::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'domain_key',
                    'capital'
                )
                ->where(
                    'component_key',
                    'approved_capital_plan'
                )
                ->where(
                    'state',
                    'approved_current'
                )
                ->firstOrFail()
                ->payload;

        $privatePayload = $approvedPayload;
        $privatePayload['plan_name'] =
            'PRIVATE NEXT CAPITAL PLAN';

        app(PbrV2ApplicationService::class)
            ->createWorkingDraft(
                $owner,
                $workspace,
                'capital',
                'approved_capital_plan',
                'capital',
                'capital_funding_workflow',
                $privatePayload,
                []
            );

        $partner =
            v2CapitalRuntimePartner();

        v2CapitalRuntimeMembership(
            $partner,
            $workspace
        );

        $this->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee(
                'Approved Current Plan ကိုသာ ကြည့်ရှုနိုင်ပါသည်'
            )
            ->assertSee('Base Capital Plan')
            ->assertDontSee(
                'PRIVATE NEXT CAPITAL PLAN'
            )
            ->assertDontSee('Working Plans')
            ->assertDontSee('Create V2 Draft');
    }
);

test(
    'accepted partner is denied v2 capital draft mutation before payload validation',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $partner =
            v2CapitalRuntimePartner();

        v2CapitalRuntimeMembership(
            $partner,
            $workspace
        );

        $this->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.store',
                    $workspace
                ),
                []
            )
            ->assertForbidden();

        expect(
            WorkspaceV2CanonicalWorkArtifact::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->count()
        )->toBe(0);
    }
);

test(
    'v2 current display preserves published payload currency instead of relabeling from changed workspace',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $artifact = v2CapitalRuntimeCreateDraft(
            $this,
            $owner,
            $workspace
        );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.submit',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.approve',
                    [
                        $workspace,
                        $artifact->id,
                    ]
                )
            );

        PartnershipWorkspace::query()
            ->whereKey($workspace->id)
            ->update([
                'currency_code' => 'USD',
            ]);

        $partner =
            v2CapitalRuntimePartner();

        v2CapitalRuntimeMembership(
            $partner,
            $workspace
        );

        $this->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSeeInOrder(['THB', '1100.00']);
    }
);

test(
    'unrelated active user cannot read another workspace v2 capital authority',
    function () {
        $owner = v2CapitalRuntimeOwner();
        $workspace =
            v2CapitalRuntimeWorkspace($owner);

        $outsider =
            v2CapitalRuntimePartner();

        $this->actingAs($outsider)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertForbidden();
    }
);
