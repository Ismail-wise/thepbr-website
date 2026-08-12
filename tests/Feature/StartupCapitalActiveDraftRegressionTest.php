<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('startup capital renders saved drafts without losing active session scope', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Startup Draft Regression Business',
        'business_name' => 'Startup Draft Regression Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'startup_capital_planner')
        ->firstOrFail();

    $input = [
        'categories' => [[
            'name' => 'Premises',
            'items' => [[
                'name' => 'Shop Deposit',
                'amount' => 30000,
                'priority' => 'essential',
                'frequency' => 'one_time',
                'funded_amount' => 10000,
                'funding_source' => 'Owner',
            ]],
        ]],
    ];

    $result = app(StartupCapitalCalculator::class)->calculate($input);
    $session = app(ToolScenarioService::class)->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Launch Draft',
        $input,
        $result
    );

    $this
        ->actingAs($owner)
        ->get(route('workspaces.tools.startup-capital.show', $workspace))
        ->assertOk()
        ->assertSee('Launch Draft');

    $this
        ->actingAs($owner)
        ->get(route('workspaces.tools.startup-capital.show', [
            'workspace' => $workspace,
            'session' => $session->id,
        ]))
        ->assertOk()
        ->assertSee('Launch Draft');
});
