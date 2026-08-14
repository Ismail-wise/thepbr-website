<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\CapitalWorkflowService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capitalWorkflowFixture(
    string $stage = 'new'
): array {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital Workflow Business',
        'business_name' =>
            'Capital Workflow Business',
        'business_stage' => $stage,
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('new business capital workflow starts with startup capital and excludes current capital position', function () {
    extract(
        capitalWorkflowFixture('new')
    );

    $state = app(
        CapitalWorkflowService::class
    )->build(
        $owner,
        $workspace
    );

    expect(
        collect($state['steps'])
            ->pluck('tool_key')
            ->all()
    )->toBe([
        'startup_capital_planner',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ]);

    expect(
        $state['next_step']['tool_key']
    )->toBe(
        'startup_capital_planner'
    );

    expect(
        $state['approved_count']
    )->toBe(0);

    expect(
        $state['is_complete']
    )->toBeFalse();
});

test('existing business capital workflow starts with current capital position and excludes startup capital', function () {
    extract(
        capitalWorkflowFixture(
            'existing'
        )
    );

    $state = app(
        CapitalWorkflowService::class
    )->build(
        $owner,
        $workspace
    );

    expect(
        collect($state['steps'])
            ->pluck('tool_key')
            ->all()
    )->toBe([
        'current_capital_position',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ]);

    expect(
        $state['next_step']['tool_key']
    )->toBe(
        'current_capital_position'
    );
});

test('approved step advances workflow to the next incomplete capital tool', function () {
    extract(
        capitalWorkflowFixture(
            'existing'
        )
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'current_capital_position'
        )
        ->firstOrFail();

    $scenarios = app(
        ToolScenarioService::class
    );

    $draft = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Current Position',
        [
            'resources' => [],
            'liabilities' => [],
        ],
        [
            'total_resources' => 100000,
            'total_liabilities' => 20000,
            'net_capital_position' => 80000,
            'position_status' => 'positive',
            'resources' => [],
            'liabilities' => [],
        ]
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    $state = app(
        CapitalWorkflowService::class
    )->build(
        $owner,
        $workspace
    );

    expect(
        $state['steps'][0]['state']
    )->toBe('approved');

    expect(
        $state['approved_count']
    )->toBe(1);

    expect(
        $state['next_step']['tool_key']
    )->toBe(
        'working_capital_calculator'
    );

    expect(
        (float) $state['current_rule']
            ['summary']
            ['current_net_capital_position']
    )->toBe(80000.0);
});

test('working draft is visible to manager but never treated as approved current rule', function () {
    extract(
        capitalWorkflowFixture(
            'existing'
        )
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'working_capital_calculator'
        )
        ->firstOrFail();

    $scenarios = app(
        ToolScenarioService::class
    );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Working Capital Draft',
        [],
        [
            'working_capital_required' =>
                500000,
            'monthly_operating_cost' =>
                100000,
        ]
    );

    $state = app(
        CapitalWorkflowService::class
    )->build(
        $owner,
        $workspace
    );

    $working = collect(
        $state['steps']
    )->firstWhere(
        'tool_key',
        'working_capital_calculator'
    );

    expect(
        $working['state']
    )->toBe('working');

    expect(
        $working['is_approved']
    )->toBeFalse();

    expect(
        $state['working_count']
    )->toBe(1);

    expect(
        $state['current_rule']['summary']
    )->toBe([]);
});

test('partner workflow view never exposes manager draft metadata', function () {
    extract(
        capitalWorkflowFixture(
            'existing'
        )
    );

    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => strtolower($partner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'working_capital_calculator'
        )
        ->firstOrFail();

    app(ToolScenarioService::class)
        ->saveDraft(
            $owner,
            $workspace,
            $tool,
            'Private Draft',
            [],
            [
                'working_capital_required' =>
                    999999,
            ]
        );

    $state = app(
        CapitalWorkflowService::class
    )->build(
        $partner,
        $workspace
    );

    expect(
        $state['can_manage']
    )->toBeFalse();

    expect(
        $state['working_count']
    )->toBe(0);

    foreach ($state['steps'] as $step) {
        expect(
            $step['draft_id']
        )->toBeNull();

        expect(
            $step['draft_name']
        )->toBeNull();

        expect(
            $step['draft_updated_at']
        )->toBeNull();
    }
});
