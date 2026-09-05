<?php

use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalApprovalService;
use App\Services\PbrTools\PbrV2CapitalComparisonService;
use App\Services\PbrTools\PbrV2CapitalHistoryService;
use App\Services\PbrTools\PbrV2CapitalPlanReadinessService;
use App\Services\PbrTools\PbrV2CapitalReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v2CapitalHistoryRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalHistoryRuntimePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalHistoryRuntimeWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' =>
            'Capital History Runtime Workspace',
        'business_name' =>
            'Capital History Runtime Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalHistoryRuntimeMembership(
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

function v2CapitalHistoryRuntimePayload(
    string $name,
    string $scenario = 'base',
    string $currency = 'THB'
): array {
    return [
        'schema_version' => 'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => $currency,
        'scenario_type' => $scenario,
        'plan_name' => $name,
        'assessment_date' => '2026-09-05',
        'cost_items' => [],
        'funding_sources' => [],
    ];
}

function v2CapitalHistoryRuntimeRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    int $revision,
    string $state,
    string $name,
    string $scenario = 'base',
    string $currency = 'THB'
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
                v2CapitalHistoryRuntimePayload(
                    $name,
                    $scenario,
                    $currency
                ),
            'approval_decision' => [
                'decision' => 'approved',
                'private_marker' =>
                    'not-for-history-ui',
            ],
            'published_by_user_id' =>
                $publisher->id,
            'published_at' =>
                now()->subDays(
                    max(0, 3 - $revision)
                ),
            'superseded_at' =>
                $state === 'superseded'
                    ? now()->subDay()
                    : null,
        ]);
}

test(
    'v2 capital controller consumes frozen history service without changing mutation surface',
    function () {
        $reflection = new ReflectionClass(
            WorkspaceV2CapitalController::class
        );

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull();

        $actual = [];

        foreach (
            $constructor->getParameters()
            as $parameter
        ) {
            $actual[
                $parameter->getName()
            ] = (string) $parameter->getType();
        }

        expect($actual)->toBe([
            'read' =>
                PbrV2CapitalReadService::class,
            'application' =>
                PbrV2ApplicationService::class,
            'approval' =>
                PbrV2CapitalApprovalService::class,
            'readiness' =>
                PbrV2CapitalPlanReadinessService::class,
            'comparison' =>
                PbrV2CapitalComparisonService::class,
            'history' =>
                PbrV2CapitalHistoryService::class,
        ]);

        $public = [];

        foreach (
            $reflection->getMethods(
                ReflectionMethod::IS_PUBLIC
            ) as $method
        ) {
            if (
                $method
                    ->getDeclaringClass()
                    ->getName()
                === WorkspaceV2CapitalController::class
            ) {
                $public[] = $method->getName();
            }
        }

        sort($public);

        expect($public)->toBe([
            '__construct',
            'approve',
            'reject',
            'show',
            'store',
            'submit',
            'update',
        ]);
    }
);

test(
    'manager sees newest first approved capital version history with current and superseded states',
    function () {
        $owner =
            v2CapitalHistoryRuntimeOwner();

        $workspace =
            v2CapitalHistoryRuntimeWorkspace(
                $owner
            );

        v2CapitalHistoryRuntimeRevision(
            $owner,
            $workspace,
            1,
            'superseded',
            'History Old Plan',
            'lean',
            'THB'
        );

        v2CapitalHistoryRuntimeRevision(
            $owner,
            $workspace,
            2,
            'approved_current',
            'History Current Plan',
            'growth',
            'USD'
        );

        $response = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee('VERSION HISTORY')
            ->assertSee(
                'Approved Capital Plan Versions'
            )
            ->assertSee(
                'Canonical Revision History'
            )
            ->assertSeeInOrder([
                'VERSION HISTORY',
                'History Current Plan',
                'History Old Plan',
            ])
            ->assertSee('Revision 2')
            ->assertSee('Revision 1')
            ->assertSee('CURRENT')
            ->assertSee('SUPERSEDED')
            ->assertSee('USD')
            ->assertSee('THB')
            ->assertSee(
                'Canonical Version History Boundary'
            )
            ->assertSee(
                'Capital Decision Record မဟုတ်ပါ'
            )
            ->assertDontSee(
                'not-for-history-ui'
            );

        expect($response->getContent())
            ->toMatch('/>\s*2\s+Revisions\s*</');
    }
);

test(
    'manager sees explicit empty version history without invented revisions',
    function () {
        $owner =
            v2CapitalHistoryRuntimeOwner();

        $workspace =
            v2CapitalHistoryRuntimeWorkspace(
                $owner
            );

        $response = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee('VERSION HISTORY')
            ->assertSee(
                'Approved version history မရှိသေးပါ'
            )
            ->assertDontSee('Revision 1')
            ->assertDontSee('SUPERSEDED');

        expect($response->getContent())
            ->toMatch('/>\s*0\s+Revisions\s*</');
    }
);

test(
    'accepted partner remains approved current only and never receives version history surface',
    function () {
        $owner =
            v2CapitalHistoryRuntimeOwner();

        $workspace =
            v2CapitalHistoryRuntimeWorkspace(
                $owner
            );

        v2CapitalHistoryRuntimeRevision(
            $owner,
            $workspace,
            1,
            'superseded',
            'PRIVATE SUPERSEDED PLAN',
            'lean'
        );

        v2CapitalHistoryRuntimeRevision(
            $owner,
            $workspace,
            2,
            'approved_current',
            'Partner Current Plan',
            'base'
        );

        $partner =
            v2CapitalHistoryRuntimePartner();

        v2CapitalHistoryRuntimeMembership(
            $partner,
            $workspace
        );

        $response = $this
            ->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'Partner Current Plan'
            )
            ->assertSee(
                'Approved Current Plan ကိုသာ ကြည့်ရှုနိုင်ပါသည်'
            )
            ->assertDontSee('VERSION HISTORY')
            ->assertDontSee(
                'Approved Capital Plan Versions'
            )
            ->assertDontSee(
                'Canonical Version History Boundary'
            )
            ->assertDontSee(
                'PRIVATE SUPERSEDED PLAN'
            )
            ->assertDontSee(
                'not-for-history-ui'
            );
    }
);
