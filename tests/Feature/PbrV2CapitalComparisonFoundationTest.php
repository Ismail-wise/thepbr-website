<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalComparisonService;
use App\Services\PbrTools\PbrV2CapitalPlanReadinessService;
use App\Services\PbrTools\PbrV2CapitalReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v2CapitalCompareOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalComparePartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalCompareWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' =>
            'Capital Comparison Workspace',
        'business_name' =>
            'Capital Comparison Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalCompareMembership(
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

function v2CapitalComparePayload(
    string $scenario,
    string $name,
    string $cost = '1000.00',
    string $contingency = '10.00',
    string $funding = '1100.00',
    int $months = 3
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
                    'Capital requirement',
                'amount' => $cost,
                'basis' => 'one_time',
                'evidence_reference' =>
                    'Evidence A',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'funding-1',
                'source_type' =>
                    'partner_cash',
                'description' =>
                    'Confirmed capital',
                'confirmed_amount' =>
                    $funding,
                'confirmation_date' =>
                    $date,
                'evidence_reference' =>
                    'Evidence B',
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
                'Capital plan comparison decision.',
            'evidence_references' => [
                'Evidence A',
                'Evidence B',
            ],
        ],
    ];
}

function v2CapitalCompareDraft(
    User $owner,
    PartnershipWorkspace $workspace,
    array $payload
): WorkspaceV2CanonicalWorkArtifact {
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
    'capital comparison exposes the exact read only public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalComparisonService::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

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
                === PbrV2CapitalComparisonService::class
            ) {
                $public[] =
                    $method->getName();
            }
        }

        sort($public);

        expect($public)->toBe([
            '__construct',
            'compare',
        ]);

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull()
            ->and(
                count(
                    $constructor
                        ->getParameters()
                )
            )
            ->toBe(2);

        $types = [];

        foreach (
            $constructor->getParameters()
            as $parameter
        ) {
            $types[
                $parameter->getName()
            ] = (string) $parameter
                ->getType();
        }

        expect($types)->toBe([
            'read' =>
                PbrV2CapitalReadService::class,
            'readiness' =>
                PbrV2CapitalPlanReadinessService::class,
        ]);

        $source = file_get_contents(
            app_path(
                'Services/PbrTools/PbrV2CapitalComparisonService.php'
            )
        );

        expect($source)
            ->not->toContain('DB::')
            ->not->toContain(
                'WorkspaceV2CanonicalWorkArtifact::'
            )
            ->not->toContain(
                'WorkspaceV2CanonicalRevision::'
            )
            ->not->toContain(
                'ToolScenarioService'
            )
            ->not->toContain(
                'StartupCapitalCalculator'
            )
            ->not->toContain(
                'publishAgreedOutput'
            );
    }
);

test(
    'capital comparison finds the latest lean base and growth plans across read pagination',
    function () {
        $owner = v2CapitalCompareOwner();

        $workspace =
            v2CapitalCompareWorkspace(
                $owner
            );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'lean',
                'Lean Plan',
                '1000.00',
                '10.00',
                '1100.00',
                2
            )
        );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'base',
                'Old Base Plan',
                '1000.00',
                '10.00',
                '1100.00',
                3
            )
        );

        for ($index = 1; $index <= 25; $index++) {
            v2CapitalCompareDraft(
                $owner,
                $workspace,
                v2CapitalComparePayload(
                    'custom',
                    'Custom '.$index,
                    '500.00',
                    '0.00',
                    '500.00',
                    1
                )
            );
        }

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'base',
                'Latest Base Plan',
                '2000.00',
                '5.00',
                '2100.00',
                4
            )
        );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'growth',
                'Growth Plan',
                '3000.00',
                '20.00',
                '3600.00',
                6
            )
        );

        $result = app(
            PbrV2CapitalComparisonService::class
        )->compare(
            $owner,
            $workspace
        );

        expect(
            array_column(
                $result['plans'],
                'scenario_type'
            )
        )->toBe([
            'lean',
            'base',
            'growth',
        ]);

        expect(
            array_column(
                $result['plans'],
                'plan_name'
            )
        )->toBe([
            'Lean Plan',
            'Latest Base Plan',
            'Growth Plan',
        ]);

        expect(
            $result['missing_scenarios']
        )->toBe([]);

        expect($result['plans'][0])
            ->toMatchArray([
                'currency_code' => 'THB',
                'working_capital_months' => 2,
                'contingency_percentage' =>
                    '10.00',
                'total_required' =>
                    '1100.00',
                'funding_gap' => '0.00',
                'readiness_passed' => true,
                'readiness_error_count' => 0,
            ]);

        expect($result['plans'][1])
            ->toMatchArray([
                'working_capital_months' => 4,
                'contingency_percentage' =>
                    '5.00',
                'total_required' =>
                    '2100.00',
                'funding_gap' => '0.00',
                'readiness_passed' => true,
            ]);

        expect($result['plans'][2])
            ->toMatchArray([
                'working_capital_months' => 6,
                'contingency_percentage' =>
                    '20.00',
                'total_required' =>
                    '3600.00',
                'funding_gap' => '0.00',
                'readiness_passed' => true,
            ]);
    }
);

test(
    'capital comparison reports an incomplete candidate without inventing calculations',
    function () {
        $owner = v2CapitalCompareOwner();

        $workspace =
            v2CapitalCompareWorkspace(
                $owner
            );

        $payload =
            v2CapitalComparePayload(
                'base',
                'Temporary'
            );

        $payload['plan_name'] = '';

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            $payload
        );

        $result = app(
            PbrV2CapitalComparisonService::class
        )->compare(
            $owner,
            $workspace
        );

        expect($result['plans'])
            ->toHaveCount(1)
            ->and(
                $result['plans'][0]
                    ['scenario_type']
            )
            ->toBe('base')
            ->and(
                $result['plans'][0]
                    ['readiness_passed']
            )
            ->toBeFalse()
            ->and(
                $result['plans'][0]
                    ['readiness_error_count']
            )
            ->toBeGreaterThan(0)
            ->and(
                $result['plans'][0]
                    ['total_required']
            )
            ->toBeNull()
            ->and(
                $result['plans'][0]
                    ['funding_gap']
            )
            ->toBeNull()
            ->and(
                $result[
                    'missing_scenarios'
                ]
            )
            ->toBe([
                'lean',
                'growth',
            ]);
    }
);

test(
    'accepted partner cannot access private capital comparison candidates',
    function () {
        $owner = v2CapitalCompareOwner();

        $workspace =
            v2CapitalCompareWorkspace(
                $owner
            );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'lean',
                'PRIVATE LEAN PLAN'
            )
        );

        $partner =
            v2CapitalComparePartner();

        v2CapitalCompareMembership(
            $partner,
            $workspace
        );

        expect(
            fn () => app(
                PbrV2CapitalComparisonService::class
            )->compare(
                $partner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );
    }
);

test(
    'capital comparison performs no database writes',
    function () {
        $owner = v2CapitalCompareOwner();

        $workspace =
            v2CapitalCompareWorkspace(
                $owner
            );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'lean',
                'Lean Plan'
            )
        );

        v2CapitalCompareDraft(
            $owner,
            $workspace,
            v2CapitalComparePayload(
                'base',
                'Base Plan'
            )
        );

        $artifactCount =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->count();

        $revisionCount =
            WorkspaceV2CanonicalRevision::query()
                ->count();

        $result = app(
            PbrV2CapitalComparisonService::class
        )->compare(
            $owner,
            $workspace
        );

        expect($result['plans'])
            ->toHaveCount(2);

        expect(
            WorkspaceV2CanonicalWorkArtifact::query()
                ->count()
        )->toBe($artifactCount);

        expect(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe($revisionCount);
    }
);
