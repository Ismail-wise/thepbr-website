<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceToolOutput;
use App\Services\PbrTools\PbrChapterStateService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chapters two to ten route canonical summaries through dedicated domain engines', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Domain Routing Business',
        'business_name' => 'Domain Routing Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $cases = [
        2 => [
            'tool' => 'future_dilution_simulator',
            'result' => [
                'new_units' => 20,
            ],
            'summary_key' => 'latest_dilution',
        ],

        3 => [
            'tool' => 'vesting_calculator',
            'result' => [
                'vested_percentage' => 40,
            ],
            'summary_key' => 'vesting',
        ],

        4 => [
            'tool' => 'salary_profit_share_planner',
            'result' => [
                'annual_salary' => 300000,
            ],
            'summary_key' => 'salary_profit_plan',
        ],

        5 => [
            'tool' => 'cashflow_dashboard',
            'result' => [
                'net_cashflow' => 15000,
            ],
            'summary_key' => 'cashflow',
        ],

        6 => [
            'tool' => 'decision_rights_matrix',
            'result' => [
                'decision_count' => 4,
            ],
            'summary_key' => 'decision_rights',
        ],

        7 => [
            'tool' => 'partner_buyout_calculator',
            'result' => [
                'buyout_value' => 500000,
            ],
            'summary_key' => 'buyout',
        ],

        8 => [
            'tool' => 'succession_planner',
            'result' => [
                'successor' => 'Partner B',
            ],
            'summary_key' => 'succession',
        ],

        9 => [
            'tool' => 'share_transfer_simulator',
            'result' => [
                'transfer_units' => 10,
            ],
            'summary_key' => 'latest_transfer_scenario',
        ],

        10 => [
            'tool' => 'dispute_log',
            'result' => [
                'issue' => 'Voting deadlock',
            ],
            'summary_key' => 'latest_dispute',
        ],
    ];

    foreach ($cases as $chapter => $case) {
        $tool = ChapterTool::query()
            ->where(
                'tool_key',
                $case['tool']
            )
            ->firstOrFail();

        expect(
            (int) $tool->chapter->chapter_number
        )->toBe($chapter);

        WorkspaceToolOutput::create([
            'workspace_id' =>
                $workspace->id,

            'chapter_tool_id' =>
                $tool->id,

            'source_tool_session_id' =>
                null,

            'revision' =>
                1,

            'status' =>
                'agreed',

            'output_data' =>
                $case['result'],

            'generated_by_user_id' =>
                $owner->id,

            'generated_at' =>
                now(),

            'agreed_at' =>
                now(),
        ]);

        $state = app(
            PbrChapterStateService::class
        )->build(
            $workspace,
            $chapter,
            'agreed'
        );

        expect(
            $state['summary'][
                $case['summary_key']
            ]
        )->toBe(
            $case['result']
        );
    }
});
