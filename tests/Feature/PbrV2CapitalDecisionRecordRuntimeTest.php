<?php

use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Http\Controllers\WorkspaceV2CapitalDecisionRecordController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Services\PbrTools\PbrV2CapitalDecisionRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function v2CapitalDecisionRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalDecisionRuntimePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalDecisionRuntimeWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' =>
            'Capital Decision Runtime Workspace',
        'business_name' =>
            'Capital Decision Runtime Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalDecisionRuntimeMembership(
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

function v2CapitalDecisionRuntimePayload(
    string $planName = 'Runtime Base Plan',
    string $summary = 'Approve runtime base capital plan.'
): array {
    $today = now()->toDateString();

    return [
        'schema_version' => 'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => $planName,
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
                'id' => 'runtime-cost-1',
                'category' => 'pre_opening',
                'description' => 'Runtime setup',
                'amount' => '1000.00',
                'basis' => 'one_time',
                'evidence_reference' =>
                    'runtime-setup-evidence',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'runtime-funding-1',
                'source_type' => 'partner_cash',
                'description' =>
                    'Runtime confirmed funding',
                'confirmed_amount' => '1100.00',
                'confirmation_date' => $today,
                'evidence_reference' =>
                    'runtime-bank-evidence',
            ],
        ],
        'shortfall_response' => null,
        'decision_record' => [
            'owner_reference' =>
                'Runtime Finance Lead',
            'effective_date' => $today,
            'review_date' =>
                now()->addMonth()->toDateString(),
            'decision_summary' => $summary,
            'evidence_references' => [
                'runtime-board-minute',
                'runtime-capital-sheet',
            ],
        ],
    ];
}

function v2CapitalDecisionRuntimeRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    int $revision = 1,
    string $planName = 'Runtime Base Plan',
    string $summary = 'Approve runtime base capital plan.'
): WorkspaceV2CanonicalRevision {
    return WorkspaceV2CanonicalRevision::query()
        ->create([
            'workspace_id' => $workspace->id,
            'domain_key' => 'capital',
            'component_key' =>
                'approved_capital_plan',
            'component_type' => 'plan',
            'revision' => $revision,
            'state' => 'approved_current',
            'payload' =>
                v2CapitalDecisionRuntimePayload(
                    $planName,
                    $summary
                ),
            'approval_decision' => [
                'decision' => 'approved',
            ],
            'published_by_user_id' =>
                $publisher->id,
            'published_at' =>
                now()->subMinute(),
            'superseded_at' => null,
        ]);
}

test(
    'decision record runtime uses a narrow post controller while frozen capital controller public surface stays unchanged',
    function () {
        $route = Route::getRoutes()->getByName(
            'workspaces.tools.v2.capital.decision-record.store'
        );

        expect($route)
            ->not->toBeNull()
            ->and($route->uri())
            ->toBe(
                'workspaces/{workspace}/v2/capital/decision-record'
            )
            ->and($route->methods())
            ->toContain('POST')
            ->and($route->getActionName())
            ->toContain(
                WorkspaceV2CapitalDecisionRecordController::class
            );

        $recordController =
            new ReflectionClass(
                WorkspaceV2CapitalDecisionRecordController::class
            );

        expect($recordController->isFinal())
            ->toBeTrue();

        $constructor =
            $recordController->getConstructor();

        expect($constructor)
            ->not->toBeNull()
            ->and(
                (string) $constructor
                    ->getParameters()[0]
                    ->getType()
            )
            ->toBe(
                PbrV2CapitalDecisionRecordService::class
            );

        $recordPublic = [];

        foreach (
            $recordController->getMethods(
                ReflectionMethod::IS_PUBLIC
            ) as $method
        ) {
            if (
                $method
                    ->getDeclaringClass()
                    ->getName()
                === WorkspaceV2CapitalDecisionRecordController::class
            ) {
                $recordPublic[] =
                    $method->getName();
            }
        }

        sort($recordPublic);

        expect($recordPublic)->toBe([
            '__construct',
            'store',
        ]);

        $capital =
            new ReflectionClass(
                WorkspaceV2CapitalController::class
            );

        $capitalPublic = [];

        foreach (
            $capital->getMethods(
                ReflectionMethod::IS_PUBLIC
            ) as $method
        ) {
            if (
                $method
                    ->getDeclaringClass()
                    ->getName()
                === WorkspaceV2CapitalController::class
            ) {
                $capitalPublic[] =
                    $method->getName();
            }
        }

        sort($capitalPublic);

        expect($capitalPublic)->toBe([
            '__construct',
            'approve',
            'reject',
            'show',
            'store',
            'submit',
            'update',
        ]);

        $constructorParameters =
            $capital
                ->getConstructor()
                ->getParameters();

        expect($constructorParameters)
            ->toHaveCount(6);

        $show = $capital->getMethod('show');

        $showParameters =
            $show->getParameters();

        expect($showParameters)
            ->toHaveCount(3)
            ->and($showParameters[2]->getName())
            ->toBe('decisionRecord')
            ->and(
                (string) $showParameters[2]
                    ->getType()
            )
            ->toBe(
                PbrV2CapitalDecisionRecordService::class
            );
    }
);

test(
    'manager records approved current decision and sees complete immutable decision record fields',
    function () {
        $owner =
            v2CapitalDecisionRuntimeOwner();

        $workspace =
            v2CapitalDecisionRuntimeWorkspace(
                $owner
            );

        v2CapitalDecisionRuntimeRevision(
            $owner,
            $workspace
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.show',
            $workspace
        );

        $before = $this
            ->actingAs($owner)
            ->get($showUrl);

        $before
            ->assertOk()
            ->assertSee(
                'CAPITAL DECISION RECORD'
            )
            ->assertSee(
                'Approved Decision Record'
            )
            ->assertSee('Record Decision');

        expect($before->getContent())
            ->toMatch(
                '/Decision Record history\s*မရှိသေးပါ/u'
            );

        $before->assertDontSee(
            'နောက် phase မှာချိတ်ပါမယ်'
        );

        $this
            ->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.decision-record.store',
                    $workspace
                )
            )
            ->assertRedirect($showUrl)
            ->assertSessionHas(
                'status'
            );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(1);

        $after = $this
            ->actingAs($owner)
            ->get($showUrl);

        $after
            ->assertOk()
            ->assertSee(
                'Immutable Decision Records'
            )
            ->assertSee(
                'Capital Decision Record - Runtime Base Plan'
            )
            ->assertSee(
                'Runtime Finance Lead'
            )
            ->assertSee('RECORDED')
            ->assertSee(
                'Approve runtime base capital plan.'
            )
            ->assertSee(
                'runtime-board-minute'
            )
            ->assertSee(
                'runtime-capital-sheet'
            )
            ->assertSee(
                'Current approved revision'
            )
            ->assertSee(
                'Decision Record Boundary'
            )
            ->assertSee(
                'Revision 1'
            );

        expect($after->getContent())
            ->not->toMatch(
                '/>\s*Record Decision\s*<\/button>/'
            );
    }
);

test(
    'duplicate post is rejected and does not append a second record',
    function () {
        $owner =
            v2CapitalDecisionRuntimeOwner();

        $workspace =
            v2CapitalDecisionRuntimeWorkspace(
                $owner
            );

        v2CapitalDecisionRuntimeRevision(
            $owner,
            $workspace
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.show',
            $workspace
        );

        $recordUrl = route(
            'workspaces.tools.v2.capital.decision-record.store',
            $workspace
        );

        $this
            ->actingAs($owner)
            ->post($recordUrl)
            ->assertRedirect($showUrl);

        $this
            ->actingAs($owner)
            ->from($showUrl)
            ->post($recordUrl)
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(
                'decision_record'
            );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(1);
    }
);

test(
    'new approved revision exposes record action again and history stays newest first',
    function () {
        $owner =
            v2CapitalDecisionRuntimeOwner();

        $workspace =
            v2CapitalDecisionRuntimeWorkspace(
                $owner
            );

        $first =
            v2CapitalDecisionRuntimeRevision(
                $owner,
                $workspace,
                1,
                'First Runtime Plan',
                'First runtime decision.'
            );

        $recordUrl = route(
            'workspaces.tools.v2.capital.decision-record.store',
            $workspace
        );

        $this
            ->actingAs($owner)
            ->post($recordUrl);

        $first->state = 'superseded';
        $first->superseded_at = now();
        $first->save();

        v2CapitalDecisionRuntimeRevision(
            $owner,
            $workspace,
            2,
            'Second Runtime Plan',
            'Second runtime decision.'
        );

        $showUrl = route(
            'workspaces.tools.v2.capital.show',
            $workspace
        );

        $pending = $this
            ->actingAs($owner)
            ->get($showUrl);

        $pending
            ->assertOk()
            ->assertSee('Record Decision')
            ->assertSee(
                'Capital Decision Record - First Runtime Plan'
            );

        $this
            ->actingAs($owner)
            ->post($recordUrl)
            ->assertRedirect($showUrl);

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(2);

        $history = $this
            ->actingAs($owner)
            ->get($showUrl);

        $history
            ->assertOk()
            ->assertSeeInOrder([
                'Capital Decision Record - Second Runtime Plan',
                'Capital Decision Record - First Runtime Plan',
            ])
            ->assertSee(
                'Second runtime decision.'
            )
            ->assertSee(
                'First runtime decision.'
            );

        expect($history->getContent())
            ->not->toMatch(
                '/>\s*Record Decision\s*<\/button>/'
            );
    }
);

test(
    'accepted partner receives no decision record management or history surface and post is forbidden',
    function () {
        $owner =
            v2CapitalDecisionRuntimeOwner();

        $workspace =
            v2CapitalDecisionRuntimeWorkspace(
                $owner
            );

        v2CapitalDecisionRuntimeRevision(
            $owner,
            $workspace,
            1,
            'Partner Visible Current Plan',
            'PRIVATE MANAGEMENT DECISION'
        );

        $this
            ->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.decision-record.store',
                    $workspace
                )
            );

        $partner =
            v2CapitalDecisionRuntimePartner();

        v2CapitalDecisionRuntimeMembership(
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
                'Partner Visible Current Plan'
            )
            ->assertSee(
                'Approved Current Plan ကိုသာ ကြည့်ရှုနိုင်ပါသည်'
            )
            ->assertDontSee(
                'CAPITAL DECISION RECORD'
            )
            ->assertDontSee(
                'Approved Decision Record'
            )
            ->assertDontSee(
                'Immutable Decision Records'
            )
            ->assertDontSee(
                'PRIVATE MANAGEMENT DECISION'
            );

        $this
            ->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.decision-record.store',
                    $workspace
                )
            )
            ->assertForbidden();

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(1);
    }
);

test(
    'manager without approved current sees record phase but cannot create an invented decision record',
    function () {
        $owner =
            v2CapitalDecisionRuntimeOwner();

        $workspace =
            v2CapitalDecisionRuntimeWorkspace(
                $owner
            );

        $showUrl = route(
            'workspaces.tools.v2.capital.show',
            $workspace
        );

        $response = $this
            ->actingAs($owner)
            ->get($showUrl);

        $response
            ->assertOk()
            ->assertSee(
                'CAPITAL DECISION RECORD'
            )
            ->assertSee(
                'Approved decision မရှိသေးပါ'
            );

        expect($response->getContent())
            ->not->toMatch(
                '/>\s*Record Decision\s*<\/button>/'
            );

        $this
            ->actingAs($owner)
            ->from($showUrl)
            ->post(
                route(
                    'workspaces.tools.v2.capital.decision-record.store',
                    $workspace
                )
            )
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(
                'approved_capital_plan'
            );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(0);
    }
);
