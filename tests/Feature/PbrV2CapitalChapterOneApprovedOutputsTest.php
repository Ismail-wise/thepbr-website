<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function c2cApprovedOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2cApprovedWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'C2C Approved Output Workspace',
        'business_name' =>
            'C2C Approved Output Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function c2cApprovedForm(
    array $overrides = []
): array {
    $today = now()->toDateString();

    $base = [
        'plan_name' =>
            'C2C Approved Output Plan',
        'scenario_type' => 'base',
        'assessment_date' => $today,
        'opening_date' =>
            now()->addDay()->toDateString(),
        'working_capital_months' => 3,
        'contingency_percentage' => '10.00',
        'monthly_burn' => '900.00',
        'cost_items' => [
            [
                'id' => 'c2c-startup',
                'category' => 'pre_opening',
                'description' =>
                    'Legal setup C2C',
                'amount' => '1000.00',
                'basis' => 'one_time',
                'evidence_reference' =>
                    'Legal quote C2C',
            ],
            [
                'id' => 'c2c-assets',
                'category' =>
                    'initial_assets_inventory',
                'description' =>
                    'Equipment C2C',
                'amount' => '2000.00',
                'basis' => 'one_time',
                'evidence_reference' =>
                    'Equipment quote C2C',
            ],
            [
                'id' => 'c2c-working',
                'category' =>
                    'working_capital',
                'description' =>
                    'Payroll reserve C2C',
                'amount' => '300.00',
                'basis' => 'monthly',
                'evidence_reference' =>
                    'Payroll plan C2C',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'c2c-funding',
                'source_type' =>
                    'partner_cash',
                'description' =>
                    'Confirmed partner cash C2C',
                'confirmed_amount' =>
                    '5000.00',
                'confirmation_date' => $today,
                'evidence_reference' =>
                    'Bank letter C2C',
            ],
        ],
        'decision_owner_reference' =>
            'C2C Workspace Owner',
        'decision_effective_date' =>
            $today,
        'decision_review_date' =>
            now()->addMonth()->toDateString(),
        'decision_summary' =>
            'Approve the C2C capital output plan.',
        'decision_evidence_references' =>
            "Legal quote C2C\nBank letter C2C",
    ];

    return array_replace_recursive(
        $base,
        $overrides
    );
}

function c2cCreateDraft(
    $test,
    User $owner,
    PartnershipWorkspace $workspace,
    array $overrides = []
): WorkspaceV2CanonicalWorkArtifact {
    $test
        ->actingAs($owner)
        ->post(
            route(
                'workspaces.tools.v2.capital.store',
                $workspace
            ),
            c2cApprovedForm($overrides)
        )
        ->assertRedirect();

    return WorkspaceV2CanonicalWorkArtifact::query()
        ->where(
            'workspace_id',
            $workspace->id
        )
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
}

function c2cApproveCurrent(
    $test,
    User $owner,
    PartnershipWorkspace $workspace
): WorkspaceV2CanonicalWorkArtifact {
    $artifact = c2cCreateDraft(
        $test,
        $owner,
        $workspace
    );

    $test
        ->actingAs($owner)
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
        ->toBe('proposed');

    $test
        ->actingAs($owner)
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

    return $artifact->fresh();
}

function c2cApprovedPartner(
    PartnershipWorkspace $workspace
): User {
    $partner = User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);

    WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);

    return $partner;
}

test(
    'manager sees the exact chapter one approved output package from approved current evidence',
    function () {
        $owner = c2cApprovedOwner();

        $workspace =
            c2cApprovedWorkspace($owner);

        c2cApproveCurrent(
            $this,
            $owner,
            $workspace
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
            ->assertSeeInOrder([
                'Startup Cost Schedule',
                'Working Capital Forecast',
                'Funding Gap Analysis',
                'Capital Allocation Summary',
                'Approved Capital Decision',
            ])
            ->assertSee('Legal setup C2C')
            ->assertSee('Legal quote C2C')
            ->assertSee('Equipment C2C')
            ->assertSee('Equipment quote C2C')
            ->assertSee('Payroll reserve C2C')
            ->assertSee('Payroll plan C2C')
            ->assertSee(
                'Confirmed partner cash C2C'
            )
            ->assertSee('Bank letter C2C')
            ->assertSee('1000.00')
            ->assertSee('2000.00')
            ->assertSee('900.00')
            ->assertSee('4290.00')
            ->assertSee('5000.00')
            ->assertSee('710.00')
            ->assertSee('116.55%')
            ->assertSee(
                'C2C Approved Output Plan'
            )
            ->assertSee(
                'Approved Capital Requirement Composition'
            );
    }
);

test(
    'capital allocation summary is only the approved requirement composition and working capital forecast adds no new authority',
    function () {
        $source = file_get_contents(
            resource_path(
                'views/workspaces/v2/capital.blade.php'
            )
        );

        expect($source)
            ->toContain(
                '<h2>Capital Allocation Summary</h2>'
            )
            ->toContain(
                'Approved Capital Requirement Composition'
            )
            ->toContain(
                'ownership/share allocation'
            )
            ->toContain(
                'funding-source allocation'
            )
            ->toContain(
                'spending authority'
            )
            ->toContain(
                '$currentCalc[\'pre_opening_total\']'
            )
            ->toContain(
                '$currentCalc[\'initial_assets_inventory_total\']'
            )
            ->toContain(
                '$currentCalc[\'working_capital_total\']'
            )
            ->toContain(
                '$currentCalc[\'contingency_reserve\']'
            )
            ->toContain(
                '$currentCalc[\'total_required\']'
            )
            ->toContain(
                '$currentCalc[\'funding_surplus\']'
            )
            ->not->toContain(
                'runway_months'
            )
            ->not->toContain(
                "\$currentPayload['assumptions']['monthly_burn'] *"
            )
            ->not->toContain(
                "\$currentPayload['assumptions']['working_capital_months'] *"
            )
            ->not->toContain(
                'Approved Capital-Call Rule'
            )
            ->not->toContain(
                'Capital-Call Rule Approved'
            );
    }
);

test(
    'accepted partner sees approved output package but never receives private capital management surfaces',
    function () {
        $owner = c2cApprovedOwner();

        $workspace =
            c2cApprovedWorkspace($owner);

        c2cApproveCurrent(
            $this,
            $owner,
            $workspace
        );

        c2cCreateDraft(
            $this,
            $owner,
            $workspace,
            [
                'plan_name' =>
                    'PRIVATE C2C NEXT PLAN',
            ]
        );

        $partner =
            c2cApprovedPartner($workspace);

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
                'C2C Approved Output Plan'
            )
            ->assertSee('Startup Cost Schedule')
            ->assertSee(
                'Working Capital Forecast'
            )
            ->assertSee(
                'Funding Gap Analysis'
            )
            ->assertSee(
                'Capital Allocation Summary'
            )
            ->assertSee(
                'Approved Capital Decision'
            )
            ->assertSee('Legal quote C2C')
            ->assertSee('Bank letter C2C')
            ->assertDontSee(
                'PRIVATE C2C NEXT PLAN'
            )
            ->assertDontSee('Your Plans')
            ->assertDontSee('Create V2 Draft')
            ->assertDontSee(
                'CAPITAL DECISION RECORD'
            )
            ->assertDontSee(
                'VERSION HISTORY'
            );
    }
);

test(
    'chapter one approved output package does not render before an approved current exists',
    function () {
        $owner = c2cApprovedOwner();

        $workspace =
            c2cApprovedWorkspace($owner);

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
            ->assertDontSee(
                'Startup Cost Schedule'
            )
            ->assertDontSee(
                'Funding Gap Analysis'
            )
            ->assertDontSee(
                'Capital Allocation Summary'
            )
            ->assertDontSee(
                'Approved Capital Decision'
            );
    }
);
