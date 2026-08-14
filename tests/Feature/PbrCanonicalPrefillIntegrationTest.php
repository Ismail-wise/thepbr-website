<?php

use App\Models\BusinessValuation;
use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Services\PbrTools\ChapterOneIntegrationService;
use App\Services\PbrTools\PbrToolPrefillService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrCanonicalPrefillFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Canonical Prefill Business',
        'business_name' => 'Canonical Prefill Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('general tool prefill reads agreed ownership and ignores newer draft state', function () {
    extract(pbrCanonicalPrefillFixture());

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'ownership',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => [
            'source_status' => 'agreed_only',
        ],
        'summary' => [
            'total_units' => 100,
            'holders' => [
                [
                    'holder' => 'Owner',
                    'units' => 60,
                    'ownership_percentage' => 60,
                    'voting_percentage' => 60,
                ],
                [
                    'holder' => 'Partner',
                    'units' => 40,
                    'ownership_percentage' => 40,
                    'voting_percentage' => 40,
                ],
            ],
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now()->subMinute(),
        'agreed_at' => now()->subMinute(),
    ]);

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'ownership',
        'revision' => 2,
        'status' => 'draft',
        'schema_version' => 'v1',
        'payload' => [
            'source_status' => 'working_latest_draft_or_agreed',
        ],
        'summary' => [
            'total_units' => 100,
            'holders' => [
                [
                    'holder' => 'Owner',
                    'units' => 5,
                    'ownership_percentage' => 5,
                    'voting_percentage' => 5,
                ],
                [
                    'holder' => 'Partner',
                    'units' => 95,
                    'ownership_percentage' => 95,
                    'voting_percentage' => 95,
                ],
            ],
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => null,
    ]);

    $tool = new ChapterTool();
    $tool->tool_key = 'voting_simulator';

    $input = app(
        PbrToolPrefillService::class
    )->prefill(
        $workspace,
        $tool,
        []
    );

    expect($input['votes'])
        ->toHaveCount(2)
        ->and((float) $input['votes'][0]['weight'])
        ->toBe(60.0)
        ->and((float) $input['votes'][1]['weight'])
        ->toBe(40.0);
});

test('chapter one prefill never uses a newer unapproved review output', function () {
    extract(pbrCanonicalPrefillFixture());

    app(CourseCatalogSeeder::class)->run();

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'working_capital_calculator'
        )
        ->firstOrFail();

    $scenarios = app(
        ToolScenarioService::class
    );

    $approved = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Approved Working Capital',
        [
            'monthly_operating_cost' => 50000,
        ],
        [
            'working_capital_required' => 100000,
            'monthly_operating_cost' => 50000,
        ]
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $approved
    );

    $workingChange = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Unapproved Working Capital Change',
        [
            'monthly_operating_cost' => 450000,
        ],
        [
            'working_capital_required' => 900000,
            'monthly_operating_cost' => 450000,
        ]
    );

    $scenarios->createWorkspaceOutput(
        $owner,
        $workspace,
        $tool,
        $workingChange
    );

    $input = app(
        ChapterOneIntegrationService::class
    )->prefill(
        $workspace,
        'contingency_fund_calculator',
        [
            'base_capital' => '',
            'monthly_operating_cost' => '',
        ]
    );

    expect(
        (float) $input['base_capital']
    )->toBe(100000.0);

    expect(
        (float) $input['monthly_operating_cost']
    )->toBe(50000.0);
});

test('advisory valuation can fill blanks but never overrides explicit user data', function () {
    extract(pbrCanonicalPrefillFixture());

    BusinessValuation::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'inputs' => [
            'method' => 'planning',
        ],
        'result' => [
            'base' => 1000000,
        ],
    ]);

    $tool = new ChapterTool();
    $tool->tool_key = 'share_value_calculator';

    $service = app(
        PbrToolPrefillService::class
    );

    $suggested = $service->prefill(
        $workspace,
        $tool,
        [
            'equity_value' => '',
        ]
    );

    expect(
        (float) $suggested['equity_value']
    )->toBe(1000000.0);

    $explicit = $service->prefill(
        $workspace,
        $tool,
        [
            'equity_value' => 250000,
            'total_units' => 100,
        ]
    );

    expect(
        (float) $explicit['equity_value']
    )->toBe(250000.0);
});
