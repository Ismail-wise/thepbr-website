<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\ChapterOneCapitalService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capitalToolFlowFixture(
    string $stage = 'existing'
): array {
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
        'name' => 'Capital Tool Flow Business',
        'business_name' =>
            'Capital Tool Flow Business',
        'business_stage' => $stage,
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('shared capital tool keeps the user inside the six step operating workflow', function () {
    extract(
        capitalToolFlowFixture(
            'existing'
        )
    );

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.chapter-one.show',
                [
                    $workspace,
                    'current-capital-position',
                ]
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-capital-tool-workflow',
            false
        )
        ->assertSee('Capital Command Center')
        ->assertSee('Step 1')
        ->assertSee('of 6')
        ->assertSee(
            'data-tool-step="current_capital_position"',
            false
        )
        ->assertSee(
            'data-tool-step="working_capital_calculator"',
            false
        )
        ->assertDontSee(
            'data-tool-step="startup_capital_planner"',
            false
        )
        ->assertSee('NEXT CAPITAL STEP')
        ->assertSee('Working Capital Calculator');
});

test('startup capital keeps its rich planner while joining the same capital workflow', function () {
    extract(
        capitalToolFlowFixture(
            'new'
        )
    );

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.startup-capital.show',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-capital-tool-workflow',
            false
        )
        ->assertSee(
            'data-tool-step="startup_capital_planner"',
            false
        )
        ->assertSee('Step 1')
        ->assertSee('of 6')
        ->assertSee(
            'pbr-capital-workspace-grid',
            false
        )
        ->assertSee(
            'အသုံးများတဲ့ ကုန်ကျစရိတ်အုပ်စုကို တစ်ချက်နဲ့ထည့်ပါ'
        )
        ->assertSee('NEXT CAPITAL STEP')
        ->assertSee('Working Capital Calculator');
});

test('partner individual tool workflow exposes approved rule state but never owner working change metadata', function () {
    extract(
        capitalToolFlowFixture(
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
        'invited_email' =>
            strtolower($partner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'current_capital_position'
        )
        ->firstOrFail();

    $scenarios = app(
        ToolScenarioService::class
    );

    $capital = app(
        ChapterOneCapitalService::class
    );

    $approvedInput = [
        'resources' => [
            [
                'name' => 'Business Resources',
                'items' => [
                    [
                        'name' => 'Cash',
                        'amount' => 100000,
                    ],
                ],
            ],
        ],
        'liabilities' => [
            [
                'name' => 'Business Liabilities',
                'items' => [
                    [
                        'name' => 'Payables',
                        'amount' => 20000,
                    ],
                ],
            ],
        ],
    ];

    $approvedResult =
        $capital->currentCapitalPosition(
            $approvedInput
        );

    $approvedDraft =
        $scenarios->saveDraft(
            $owner,
            $workspace,
            $tool,
            'Approved Capital Position',
            $approvedInput,
            $approvedResult
        );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $approvedDraft
    );

    $privateInput = [
        'resources' => [
            [
                'name' => 'Business Resources',
                'items' => [
                    [
                        'name' => 'Cash',
                        'amount' => 115000,
                    ],
                ],
            ],
        ],
        'liabilities' => [
            [
                'name' => 'Business Liabilities',
                'items' => [
                    [
                        'name' => 'Payables',
                        'amount' => 20000,
                    ],
                ],
            ],
        ],
    ];

    $privateResult =
        $capital->currentCapitalPosition(
            $privateInput
        );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Owner Private Capital Review',
        $privateInput,
        $privateResult
    );

    $response = $this
        ->actingAs($partner)
        ->get(
            route(
                'workspaces.tools.chapter-one.show',
                [
                    $workspace,
                    'current-capital-position',
                ]
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-capital-tool-workflow',
            false
        )
        ->assertSee('Active Rule')
        ->assertDontSee(
            'Owner Private Capital Review'
        )
        ->assertDontSee(
            'Working Changes'
        )
        ->assertDontSee(
            'Active Rule + Working Change'
        );
});
