<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capitalCommandCenterFixture(
    string $stage = 'existing'
): array {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital Command Business',
        'business_name' => 'Capital Command Business',
        'business_stage' => $stage,
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('existing business shows a six step capital operating command center', function () {
    extract(
        capitalCommandCenterFixture(
            'existing'
        )
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
        ->assertSee('CAPITAL & FUNDING', false)
        ->assertSee('Current Rule Progress')
        ->assertSee('0 / 6')
        ->assertSee(
            'data-capital-step="current_capital_position"',
            false
        )
        ->assertDontSee(
            'data-capital-step="startup_capital_planner"',
            false
        )
        ->assertSee(
            'data-capital-manager-action',
            false
        )
        ->assertSee('နောက်တစ်ဆင့် စတင်ရန်');
});

test('new business command center starts with startup capital instead of current capital position', function () {
    extract(
        capitalCommandCenterFixture(
            'new'
        )
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
            'data-capital-step="startup_capital_planner"',
            false
        )
        ->assertDontSee(
            'data-capital-step="current_capital_position"',
            false
        )
        ->assertSee('Startup Capital');
});

test('partner command center is approved rule only and never exposes private capital draft metadata', function () {
    extract(
        capitalCommandCenterFixture(
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
            'working_capital_calculator'
        )
        ->firstOrFail();

    app(ToolScenarioService::class)
        ->saveDraft(
            $owner,
            $workspace,
            $tool,
            'Private Capital Draft 999',
            [],
            [
                'working_capital_required' =>
                    999999,
            ]
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
            'data-capital-partner-view',
            false
        )
        ->assertSee('Approved Rules Only')
        ->assertDontSee(
            'data-capital-manager-action',
            false
        )
        ->assertDontSee(
            'Private Capital Draft 999'
        )
        ->assertDontSee(
            'Working Change ရှိသည်'
        );
});
