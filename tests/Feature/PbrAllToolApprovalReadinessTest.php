<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolApprovalReadinessService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function approvalReadinessFixture(): array
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
        'owner_user_id' => $owner->id,
        'name' => 'Approval Readiness Business',
        'business_name' =>
            'Approval Readiness Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact('owner', 'workspace');
}

function approvalStateFor(
    PartnershipWorkspace $workspace,
    ChapterTool $tool,
    array $input
): array {
    $engine = app(
        PbrOperatingToolEngine::class
    );

    $session = new ToolSession();
    $session->input_data = $input;

    $session->result_data =
        (int) $tool->chapter->chapter_number >= 2
            ? $engine->calculate(
                $tool->tool_key,
                $input,
                $workspace
            )
            : [];

    return app(
        PbrToolApprovalReadinessService::class
    )->assess(
        $workspace,
        $tool,
        $session
    );
}

test('all sixty four tools resolve through the approval readiness contract', function () {
    extract(approvalReadinessFixture());

    $tools = ChapterTool::query()
        ->with('chapter')
        ->get();

    expect($tools)->toHaveCount(64);

    $engine = app(
        PbrOperatingToolEngine::class
    );

    foreach ($tools as $tool) {
        $chapter =
            (int) $tool->chapter->chapter_number;

        $input = $chapter >= 2
            ? $engine->defaultInput(
                $tool->tool_key
            )
            : [];

        $state = approvalStateFor(
            $workspace,
            $tool,
            $input
        );

        expect($state)
            ->toHaveKeys([
                'ready',
                'errors',
                'warnings',
                'tool_key',
                'chapter',
            ]);

        expect($state['ready'])
            ->toBeBool();

        expect($state['errors'])
            ->toBeArray();

        expect($state['warnings'])
            ->toBeArray();
    }
});

test('untouched default cap table cannot become approved business ownership', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $state = approvalStateFor(
        $workspace,
        $tool,
        [
            'partners' => [],
            'reserved_units' => 0,
        ]
    );

    expect($state['ready'])
        ->toBeFalse();

    expect(
        implode(' ', $state['errors'])
    )->toContain(
        'Cap Table'
    );
});

test('valid cap table is approval ready while voting remains independent', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $state = approvalStateFor(
        $workspace,
        $tool,
        [
            'partners' => [
                [
                    'name' => 'Partner A',
                    'units' => 60,
                    'voting_units' => 40,
                ],
                [
                    'name' => 'Partner B',
                    'units' => 40,
                    'voting_units' => 60,
                ],
            ],
            'reserved_units' => 0,
        ]
    );

    expect($state['ready'])
        ->toBeTrue();
});

test('profit distribution cannot be approved unless percentages total one hundred', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'profit_distribution_calculator'
        )
        ->firstOrFail();

    $bad = approvalStateFor(
        $workspace,
        $tool,
        [
            'net_profit' => 100000,
            'reserve_percentage' => 20,
            'partners' => [
                [
                    'name' => 'A',
                    'percentage' => 40,
                ],
                [
                    'name' => 'B',
                    'percentage' => 40,
                ],
            ],
        ]
    );

    expect($bad['ready'])
        ->toBeFalse();

    $good = approvalStateFor(
        $workspace,
        $tool,
        [
            'net_profit' => 100000,
            'reserve_percentage' => 20,
            'partners' => [
                [
                    'name' => 'A',
                    'percentage' => 50,
                ],
                [
                    'name' => 'B',
                    'percentage' => 50,
                ],
            ],
        ]
    );

    expect($good['ready'])
        ->toBeTrue();
});

test('financial approval ranges cannot overlap', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'expense_approval_matrix'
        )
        ->firstOrFail();

    $state = approvalStateFor(
        $workspace,
        $tool,
        [
            'rules' => [
                [
                    'min_amount' => 0,
                    'max_amount' => 10000,
                    'approver' => 'Manager',
                    'approvals_required' => 1,
                ],
                [
                    'min_amount' => 5000,
                    'max_amount' => 50000,
                    'approver' => 'Partners',
                    'approvals_required' => 2,
                ],
            ],
        ]
    );

    expect($state['ready'])
        ->toBeFalse();

    expect(
        implode(' ', $state['errors'])
    )->toContain('overlap');
});

test('operating records require enough information to become permanent history', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'meeting_decision_log'
        )
        ->firstOrFail();

    $bad = approvalStateFor(
        $workspace,
        $tool,
        [
            'decision_date' => '',
            'decision' => '',
            'owner' => '',
            'rationale' => '',
            'follow_up' => '',
        ]
    );

    expect($bad['ready'])
        ->toBeFalse();

    $good = approvalStateFor(
        $workspace,
        $tool,
        [
            'decision_date' => '2026-08-14',
            'decision' =>
                'Open second sales channel',
            'owner' => 'Operations',
            'rationale' =>
                'Approved partner decision',
            'follow_up' =>
                'Prepare launch plan',
        ]
    );

    expect($good['ready'])
        ->toBeTrue();
});

test('share transfer blocks impossible seller buyer and unit combinations', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'share_transfer_simulator'
        )
        ->firstOrFail();

    $input = [
        'total_units' => 100,
        'seller_name' => 'Partner A',
        'seller_units' => 20,
        'buyer_name' => 'Partner A',
        'buyer_units' => 10,
        'transfer_units' => 30,
    ];

    /*
     * Calculation-time integrity is intentionally stricter than the
     * approval gate for impossible transfers. The engine must reject
     * this scenario immediately.
     */
    expect(
        fn () => app(
            PbrOperatingToolEngine::class
        )->calculate(
            $tool->tool_key,
            $input,
            $workspace
        )
    )->toThrow(
        ValidationException::class
    );

    /*
     * Test the approval-readiness layer independently as well.
     * We bypass calculation here because calculation already blocks
     * the impossible transfer before an approval state can exist.
     */
    $session = new ToolSession();
    $session->input_data = $input;
    $session->result_data = [];

    $state = app(
        PbrToolApprovalReadinessService::class
    )->assess(
        $workspace,
        $tool,
        $session
    );

    expect($state['ready'])
        ->toBeFalse();

    $errors = implode(
        ' ',
        $state['errors']
    );

    expect($errors)
        ->toContain('Seller');

    expect($errors)
        ->toContain('units');
});

test('zero readiness checklist can be approved but carries an explicit warning', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'financial_control_checklist'
        )
        ->firstOrFail();

    $input = app(
        PbrOperatingToolEngine::class
    )->defaultInput(
        $tool->tool_key
    );

    $state = approvalStateFor(
        $workspace,
        $tool,
        $input
    );

    expect($state['ready'])
        ->toBeTrue();

    expect($state['warnings'])
        ->not->toBeEmpty();
});

test('publish service refuses an unready draft instead of creating active business data', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $input = [
        'partners' => [],
        'reserved_units' => 0,
    ];

    $result = $engine->calculate(
        $tool->tool_key,
        $input,
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $draft = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Incomplete Ownership Draft',
        $input,
        $result
    );

    expect(
        fn () =>
            $scenarios->publishAgreedOutput(
                $owner,
                $workspace,
                $tool,
                $draft
            )
    )->toThrow(
        ValidationException::class
    );

    expect(
        $draft->workspaceOutputs()
            ->where('status', 'agreed')
            ->exists()
    )->toBeFalse();
});

test('operating tool shows approval blockers before activation', function () {
    extract(approvalReadinessFixture());

    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $input = [
        'partners' => [],
        'reserved_units' => 0,
    ];

    $result = $engine->calculate(
        $tool->tool_key,
        $input,
        $workspace
    );

    $draft = app(
        ToolScenarioService::class
    )->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Incomplete UI Draft',
        $input,
        $result
    );

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.operating.show',
                [
                    $workspace,
                    $tool->slug,
                    'session' => $draft->id,
                ]
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-pbr-approval-ready="0"',
            false
        )
        ->assertSee('APPROVAL BLOCKED')
        ->assertSee('Cap Table');
});
