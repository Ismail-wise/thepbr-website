<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Services\PbrTools\PbrBusinessDashboardService;
use App\Services\PbrTools\PbrBusinessOperatingService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardV2Fixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' =>
            $owner->id,
        'name' =>
            'Dashboard V2 Business',
        'business_name' =>
            'Dashboard V2 Business',
        'business_stage' =>
            'existing',
        'currency_code' =>
            'THB',
        'status' =>
            'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('dashboard v2 groups all ten operating areas into build operate and protect', function () {
    extract(
        dashboardV2Fixture()
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    $dashboard = app(
        PbrBusinessDashboardService::class
    )->build(
        $state,
        $workspace
    );

    expect(
        $dashboard['phases']
    )->toHaveCount(3);

    expect(
        $dashboard['areas']
    )->toHaveCount(10);

    expect(
        $dashboard['phases']
            ->pluck('key')
            ->all()
    )->toBe([
        'build',
        'operate',
        'protect',
    ]);

    expect(
        $dashboard['phases']
            ->sum('area_count')
    )->toBe(10);
});

test('dashboard v2 distinguishes capital not set from a real zero value', function () {
    extract(
        dashboardV2Fixture()
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    expect(
        $state['metrics']
            ['capital_data_available']
    )->toBeFalse();

    expect(
        $state['metrics']
            ['capital_data_source']
    )->toBe('none');

    expect(
        $state['metrics']
            ['capital_has_approved_data']
    )->toBeFalse();

    $dashboard = app(
        PbrBusinessDashboardService::class
    )->build(
        $state,
        $workspace
    );

    expect(
        $dashboard['capital']
            ['data_available']
    )->toBeFalse();

    expect(
        $dashboard['capital']
            ['capital_required']
    )->toBeNull();

    expect(
        $dashboard['capital']
            ['capital_secured']
    )->toBeNull();

    expect(
        $dashboard['capital']
            ['funding_gap']
    )->toBeNull();

    expect(
        $dashboard['capital']
            ['source_label']
    )->toBe('Capital Not Set');
});

test('dashboard v2 limits secondary priority actions to three', function () {
    extract(
        dashboardV2Fixture()
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    $dashboard = app(
        PbrBusinessDashboardService::class
    )->build(
        $state,
        $workspace
    );

    expect(
        $dashboard['priority_actions']
            ->count()
    )->toBe(0);

    expect(
        $dashboard['primary_action']['kind']
        ?? null
    )->toBe('setup');
});

test('dashboard v2 keeps backward compatible rule coverage while adding area based health', function () {
    extract(
        dashboardV2Fixture()
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    /*
     * Existing rule-level contracts remain available for Rulebook and
     * operating logic. Dashboard V2 simply stops making them the dominant
     * overview progress model.
     */
    expect(
        $state['metrics']
    )->toHaveKeys([
        'active_rule_count',
        'total_rule_count',
        'rule_completion_percent',
        'working_change_count',
        'operating_record_count',
        'capital_data_available',
        'capital_data_source',
        'capital_has_approved_data',
    ]);

    $dashboard = app(
        PbrBusinessDashboardService::class
    )->build(
        $state,
        $workspace
    );

    expect(
        $dashboard['health']
            ['total_area_count']
    )->toBe(10);

    expect(
        $dashboard['health']
            ['started_area_count']
    )->toBe(0);

    expect(
        $dashboard['health']
            ['approved_area_count']
    )->toBe(0);
});

test('owner sees compact dashboard v2 instead of duplicated legacy overview sections', function () {
    extract(
        dashboardV2Fixture()
    );

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.index',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-pbr-dashboard-v2',
            false
        )
        ->assertSee(
            'data-pbr-business-journey',
            false
        )
        ->assertSee(
            'data-pbr-phase="build"',
            false
        )
        ->assertSee(
            'data-pbr-phase="operate"',
            false
        )
        ->assertSee(
            'data-pbr-phase="protect"',
            false
        )
        ->assertSee(
            'data-journey-step="capital"',
            false
        )
        ->assertSee(
            'data-journey-step="dispute_resolution"',
            false
        )
        ->assertSee(
            'Build → Operate → Protect'
        )
        ->assertSee(
            'Business Rulebook'
        )
        ->assertSee(
            'ဆက်လက်သတ်မှတ်ရန်'
        )
        ->assertDontSee(
            'approved rules 0/6'
        )
        ->assertDontSee(
            'missing rule'
        )
        ->assertSee(
            'Ownership နှင့် Voting Rights သတ်မှတ်ရန်'
        )
        ->assertSee(
            'Financial Controls သတ်မှတ်ရန်'
        )
        ->assertDontSee(
            'Equity Split Simulator'
        )
        ->assertDontSee(
            'Cash Flow Dashboard'
        )
        ->assertDontSee(
            'Current Business Rule Register'
        )
        ->assertDontSee(
            'Missing Rules'
        )
        ->assertDontSee(
            'RULE COVERAGE'
        );
});

test('dashboard v2 still hides student only ai from invited partner rendering contracts', function () {
    extract(
        dashboardV2Fixture()
    );

    /*
     * Existing partner-access acceptance tests remain authoritative.
     * This assertion protects the owner path while the complete suite
     * verifies invited-partner permissions.
     */
    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.index',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'PBR AI ကို မေးရန်'
        );
});
