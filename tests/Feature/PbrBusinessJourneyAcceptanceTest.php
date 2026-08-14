<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\PbrBusinessOperatingService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolRuntimeContractService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function c2JourneyFixture(): array
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
        'name' => 'C2 Journey Business',
        'business_name' =>
            'C2 Journey Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

function c2ApproveTool(
    User $owner,
    PartnershipWorkspace $workspace,
    string $toolKey,
    array $input,
    string $scenarioName
): void {
    $tool = ChapterTool::query()
        ->with('chapter')
        ->where(
            'tool_key',
            $toolKey
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

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
        $scenarioName,
        $input,
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );
}

function c2AddPartner(
    PartnershipWorkspace $workspace,
    User $owner
): User {
    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' =>
            $workspace->id,
        'user_id' =>
            $partner->id,
        'member_role' =>
            'partner',
        'invitation_status' =>
            'accepted',
        'invited_email' =>
            strtolower(
                $partner->email
            ),
        'invitation_token_hash' =>
            null,
        'invited_by_user_id' =>
            $owner->id,
        'invited_at' =>
            now(),
        'accepted_at' =>
            now(),
        'permissions' =>
            null,
    ]);

    return $partner;
}

test('business journey contains ten areas and counts current rules separately from record tools', function () {
    extract(c2JourneyFixture());

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    expect(
        $state['journey']['steps']
    )->toHaveCount(10);

    $contracts = app(
        PbrToolRuntimeContractService::class
    );

    $expectedRuleCount =
        ChapterTool::query()
            ->with('chapter')
            ->where(
                'supports_existing_business',
                true
            )
            ->get()
            ->filter(
                fn (ChapterTool $tool): bool =>
                    ! $contracts
                        ->forTool($tool)
                        ['is_record']
            )
            ->count();

    expect(
        $state['journey']['metrics']
            ['total_rule_count']
    )->toBe(
        $expectedRuleCount
    );

    expect(
        $state['journey']['metrics']
            ['approved_rule_count']
    )->toBe(0);

    expect(
        $state['journey']
            ['completion_percent']
    )->toBe(0);
});

test('approved operating record never appears in the current rule register or rule coverage', function () {
    extract(c2JourneyFixture());

    c2ApproveTool(
        $owner,
        $workspace,
        'meeting_decision_log',
        [
            'decision_date' =>
                '2026-08-14',
            'decision' =>
                'Approve new supplier process',
            'owner' =>
                'Operations Partner',
            'rationale' =>
                'Partner review completed',
            'follow_up' =>
                'Implement supplier checklist',
        ],
        'Approved Supplier Decision'
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    expect(
        $state['active_rules']
            ->pluck('key')
            ->all()
    )->not->toContain(
        'meeting_decision_log'
    );

    expect(
        $state['metrics']
            ['active_rule_count']
    )->toBe(0);

    expect(
        $state['metrics']
            ['operating_record_count']
    )->toBe(1);
});

test('partially configured business area still guides owner to its next missing current rule', function () {
    extract(c2JourneyFixture());

    c2ApproveTool(
        $owner,
        $workspace,
        'cap_table_builder',
        [
            'partners' => [
                [
                    'name' =>
                        'Partner A',
                    'units' => 60,
                    'voting_units' => 40,
                ],
                [
                    'name' =>
                        'Partner B',
                    'units' => 40,
                    'voting_units' => 60,
                ],
            ],
            'reserved_units' => 0,
        ],
        'Approved Cap Table'
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    $ownership =
        $state['system_map']
            ->get('ownership');

    expect(
        $ownership['active_count']
    )->toBe(1);

    expect(
        $ownership['missing_rule_count']
    )->toBeGreaterThan(0);

    $missingModule =
        collect(
            $ownership['modules']
        )
            ->first(
                fn (array $module): bool =>
                    ! $module['is_record']
                    && empty(
                        $module[
                            'active_revision'
                        ]
                    )
            );

    expect($missingModule)
        ->not->toBeNull();

    $action =
        $state['action_items']
            ->firstWhere(
                'domain',
                'ownership'
            );

    expect($action)
        ->not->toBeNull();

    expect(
        $action['module_key']
    )->toBe(
        $missingModule['key']
    );
});

test('newer upstream approved data creates a downstream review signal without replacing the current downstream rule', function () {
    extract(c2JourneyFixture());

    try {
        Carbon::setTestNow(
            '2026-08-14 10:00:00'
        );

        c2ApproveTool(
            $owner,
            $workspace,
            'cap_table_builder',
            [
                'partners' => [
                    [
                        'name' =>
                            'Partner A',
                        'units' => 60,
                        'voting_units' => 50,
                    ],
                    [
                        'name' =>
                            'Partner B',
                        'units' => 40,
                        'voting_units' => 50,
                    ],
                ],
                'reserved_units' => 0,
            ],
            'Ownership Rule V1'
        );

        Carbon::setTestNow(
            '2026-08-14 11:00:00'
        );

        c2ApproveTool(
            $owner,
            $workspace,
            'profit_distribution_calculator',
            [
                'net_profit' =>
                    100000,
                'reserve_percentage' =>
                    20,
                'partners' => [
                    [
                        'name' =>
                            'Partner A',
                        'percentage' => 60,
                    ],
                    [
                        'name' =>
                            'Partner B',
                        'percentage' => 40,
                    ],
                ],
            ],
            'Distribution Rule V1'
        );

        Carbon::setTestNow(
            '2026-08-14 12:00:00'
        );

        c2ApproveTool(
            $owner,
            $workspace,
            'cap_table_builder',
            [
                'partners' => [
                    [
                        'name' =>
                            'Partner A',
                        'units' => 55,
                        'voting_units' => 50,
                    ],
                    [
                        'name' =>
                            'Partner B',
                        'units' => 45,
                        'voting_units' => 50,
                    ],
                ],
                'reserved_units' => 0,
            ],
            'Ownership Rule V2'
        );

        $state = app(
            PbrBusinessOperatingService::class
        )->workspaceState(
            $owner,
            $workspace
        );

        $distribution =
            $state['system_map']
                ->get('distribution');

        $module = collect(
            $distribution['modules']
        )->firstWhere(
            'key',
            'profit_distribution_calculator'
        );

        expect(
            $module[
                'active_revision'
            ]
        )->toBe(1);

        expect(
            $module[
                'dependency_review_required'
            ]
        )->toBeTrue();

        expect(
            $module[
                'stale_source_domains'
            ]
        )->toContain(
            'ownership'
        );

        $action =
            $state['action_items']
                ->firstWhere(
                    'domain',
                    'distribution'
                );

        expect($action)
            ->not->toBeNull();

        expect(
            $action['module_key']
        )->toBe(
            'profit_distribution_calculator'
        );
    } finally {
        Carbon::setTestNow();
    }
});

test('invited partner sees approved journey without student only ai or owner draft metadata', function () {
    extract(c2JourneyFixture());

    $partner = c2AddPartner(
        $workspace,
        $owner
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $input = [
        'partners' => [
            [
                'name' =>
                    'Private Partner Draft A',
                'units' => 60,
                'voting_units' => 50,
            ],
            [
                'name' =>
                    'Private Partner Draft B',
                'units' => 40,
                'voting_units' => 50,
            ],
        ],
        'reserved_units' => 0,
    ];

    $result = app(
        PbrOperatingToolEngine::class
    )->calculate(
        $tool->tool_key,
        $input,
        $workspace
    );

    app(
        ToolScenarioService::class
    )->saveDraft(
        $owner,
        $workspace,
        $tool,
        'OWNER PRIVATE C2 DRAFT',
        $input,
        $result
    );

    $response = $this
        ->actingAs($partner)
        ->get(
            route(
                'workspaces.tools.index',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-pbr-business-journey',
            false
        )
        ->assertDontSee(
            'PBR AI ကို မေးရန်'
        )
        ->assertDontSee(
            'OWNER PRIVATE C2 DRAFT'
        )
        ->assertDontSee(
            'Private Partner Draft A'
        );
});

test('owner operating system exposes the ten area journey and approved rulebook entry point', function () {
    extract(c2JourneyFixture());

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
            'data-pbr-business-journey',
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
            route(
                'workspaces.rulebook.show',
                $workspace
            ),
            false
        )
        ->assertSee(
            'Business Rulebook'
        );
});
