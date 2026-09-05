<?php

use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalApprovalService;
use App\Services\PbrTools\PbrV2CapitalComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v2CapitalComparisonRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalComparisonRuntimePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalComparisonRuntimeWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' =>
            'Comparison Runtime Workspace',
        'business_name' =>
            'Comparison Runtime Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalComparisonRuntimeMembership(
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

function v2CapitalComparisonRuntimePayload(
    string $scenario,
    string $name,
    string $cost,
    string $contingency,
    string $funding,
    int $months
): array {
    $date = now()->toDateString();

    return [
        'schema_version' =>
            'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => $name,
        'scenario_type' => $scenario,
        'assessment_date' => $date,
        'assumptions' => [
            'opening_date' =>
                now()->addDay()->toDateString(),
            'working_capital_months' =>
                $months,
            'contingency_percentage' =>
                $contingency,
            'monthly_burn' => '0.00',
        ],
        'cost_items' => [
            [
                'id' => 'cost-1',
                'category' => 'pre_opening',
                'description' =>
                    'Runtime capital requirement',
                'amount' => $cost,
                'basis' => 'one_time',
                'evidence_reference' =>
                    'Runtime Evidence A',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'funding-1',
                'source_type' =>
                    'partner_cash',
                'description' =>
                    'Confirmed runtime funding',
                'confirmed_amount' =>
                    $funding,
                'confirmation_date' =>
                    $date,
                'evidence_reference' =>
                    'Runtime Evidence B',
            ],
        ],
        'shortfall_response' => null,
        'decision_record' => [
            'owner_reference' =>
                'Workspace Owner',
            'effective_date' => $date,
            'review_date' =>
                now()->addMonth()
                    ->toDateString(),
            'decision_summary' =>
                'Runtime comparison decision.',
            'evidence_references' => [
                'Runtime Evidence A',
                'Runtime Evidence B',
            ],
        ],
    ];
}

function v2CapitalComparisonRuntimeDraft(
    User $owner,
    PartnershipWorkspace $workspace,
    array $payload
) {
    return app(
        PbrV2ApplicationService::class
    )->createWorkingDraft(
        $owner,
        $workspace,
        'capital',
        'approved_capital_plan',
        'capital',
        'capital_funding_workflow',
        $payload,
        []
    );
}

test(
    'v2 capital controller consumes the frozen comparison service as an explicit dependency',
    function () {
        $reflection = new ReflectionClass(
            WorkspaceV2CapitalController::class
        );

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull();

        $dependencies = [];

        foreach (
            $constructor->getParameters()
            as $parameter
        ) {
            $dependencies[
                $parameter->getName()
            ] = (string) $parameter
                ->getType();
        }

        expect($dependencies)
            ->toHaveKey(
                'comparison',
                PbrV2CapitalComparisonService::class
            );
    }
);

test(
    'manager sees latest lean base and growth candidates in exact comparison order',
    function () {
        $owner =
            v2CapitalComparisonRuntimeOwner();

        $workspace =
            v2CapitalComparisonRuntimeWorkspace(
                $owner
            );

        v2CapitalComparisonRuntimeDraft(
            $owner,
            $workspace,
            v2CapitalComparisonRuntimePayload(
                'lean',
                'UI Lean Candidate',
                '1000.00',
                '10.00',
                '1100.00',
                2
            )
        );

        v2CapitalComparisonRuntimeDraft(
            $owner,
            $workspace,
            v2CapitalComparisonRuntimePayload(
                'base',
                'UI Base Candidate',
                '2000.00',
                '5.00',
                '2100.00',
                4
            )
        );

        v2CapitalComparisonRuntimeDraft(
            $owner,
            $workspace,
            v2CapitalComparisonRuntimePayload(
                'growth',
                'UI Growth Candidate',
                '3000.00',
                '20.00',
                '3600.00',
                6
            )
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
            ->assertSee('PLAN COMPARISON')
            ->assertSee(
                'Lean / Base / Growth နှိုင်းယှဉ်မှု'
            )
            ->assertSeeInOrder([
                'UI Lean Candidate',
                'UI Base Candidate',
                'UI Growth Candidate',
            ])
            ->assertSee('10.00%')
            ->assertSee('5.00%')
            ->assertSee('20.00%')
            ->assertSeeInOrder([
                'THB',
                '1100.00',
            ])
            ->assertSeeInOrder([
                'THB',
                '2100.00',
            ])
            ->assertSeeInOrder([
                'THB',
                '3600.00',
            ])
            ->assertSee('READY')
            ->assertSee(
                'Comparison Authority Boundary'
            );

        $html = $response->getContent();

        expect($html)
            ->toMatch('/>\s*2\s+months\s*</')
            ->toMatch('/>\s*4\s+months\s*</')
            ->toMatch('/>\s*6\s+months\s*</');
    }
);

test(
    'comparison runtime reports missing scenarios without inventing candidate rows',
    function () {
        $owner =
            v2CapitalComparisonRuntimeOwner();

        $workspace =
            v2CapitalComparisonRuntimeWorkspace(
                $owner
            );

        v2CapitalComparisonRuntimeDraft(
            $owner,
            $workspace,
            v2CapitalComparisonRuntimePayload(
                'base',
                'Only Base Candidate',
                '2000.00',
                '5.00',
                '2100.00',
                4
            )
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
            ->assertSee('Only Base Candidate')
            ->assertSee('Missing scenarios')
            ->assertSeeInOrder([
                'Missing scenarios',
                'Lean',
                'Growth',
            ])
            ->assertDontSee(
                'UI Lean Candidate'
            )
            ->assertDontSee(
                'UI Growth Candidate'
            );
    }
);

test(
    'accepted partner never receives private comparison surface or candidate names',
    function () {
        $owner =
            v2CapitalComparisonRuntimeOwner();

        $workspace =
            v2CapitalComparisonRuntimeWorkspace(
                $owner
            );

        $approvedDraft =
            v2CapitalComparisonRuntimeDraft(
                $owner,
                $workspace,
                v2CapitalComparisonRuntimePayload(
                    'base',
                    'Approved Base Capital',
                    '2000.00',
                    '5.00',
                    '2100.00',
                    4
                )
            );

        $proposal = app(
            PbrV2ApplicationService::class
        )->submitProposal(
            $owner,
            $workspace,
            $approvedDraft
        );

        app(
            PbrV2CapitalApprovalService::class
        )->approveAndPublish(
            $owner,
            $workspace,
            $proposal
        );

        v2CapitalComparisonRuntimeDraft(
            $owner,
            $workspace,
            v2CapitalComparisonRuntimePayload(
                'lean',
                'PRIVATE LEAN COMPARISON',
                '1000.00',
                '10.00',
                '1100.00',
                2
            )
        );

        $partner =
            v2CapitalComparisonRuntimePartner();

        v2CapitalComparisonRuntimeMembership(
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
                'Approved Current Plan ကိုသာ ကြည့်ရှုနိုင်ပါသည်'
            )
            ->assertSee(
                'Approved Base Capital'
            )
            ->assertDontSee(
                'PLAN COMPARISON'
            )
            ->assertDontSee(
                'Capital Plan Comparison'
            )
            ->assertDontSee(
                'PRIVATE LEAN COMPARISON'
            )
            ->assertDontSee(
                'Missing scenarios'
            );
    }
);
