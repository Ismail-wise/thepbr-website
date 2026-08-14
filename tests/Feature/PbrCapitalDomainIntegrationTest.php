<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceToolOutput;
use App\Services\PbrTools\ChapterOneIntegrationService;
use App\Services\PbrTools\PbrChapterStateService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chapter one integration and approved snapshot state use identical capital domain summary', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital Domain Business',
        'business_name' => 'Capital Domain Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $results = [
        'startup_capital_planner' => [
            'total_startup_capital' => 300000,
            'funded_total' => 120000,
        ],

        'working_capital_calculator' => [
            'working_capital_required' => 180000,
            'monthly_operating_cost' => 45000,
        ],

        'contingency_fund_calculator' => [
            'contingency_fund' => 45000,
        ],

        'partner_contribution_matrix' => [
            'total_contribution' => 200000,
            'partners' => [
                [
                    'name' => 'Owner',
                    'total' => 120000,
                ],
                [
                    'name' => 'Partner',
                    'total' => 80000,
                ],
            ],
        ],

        'funding_gap_calculator' => [
            'other_funding' => 50000,
        ],
    ];

    foreach ($results as $toolKey => $result) {
        $tool = ChapterTool::query()
            ->where('tool_key', $toolKey)
            ->firstOrFail();

        WorkspaceToolOutput::create([
            'workspace_id' => $workspace->id,
            'chapter_tool_id' => $tool->id,
            'source_tool_session_id' => null,
            'revision' => 1,
            'status' => 'agreed',
            'output_data' => $result,
            'generated_by_user_id' => $owner->id,
            'generated_at' => now(),
            'agreed_at' => now(),
        ]);
    }

    $integration = app(
        ChapterOneIntegrationService::class
    )->summary(
        $workspace
    );

    $chapterState = app(
        PbrChapterStateService::class
    )->build(
        $workspace,
        1,
        'agreed'
    );

    unset($integration['outputs']);

    expect(
        $integration
    )->toBe(
        $chapterState['summary']
    );

    expect(
        $chapterState['summary']['capital_required']
    )->toBe(525000.0);

    expect(
        $chapterState['summary']['capital_secured']
    )->toBe(250000.0);

    expect(
        $chapterState['summary']['funding_gap']
    )->toBe(275000.0);
});
