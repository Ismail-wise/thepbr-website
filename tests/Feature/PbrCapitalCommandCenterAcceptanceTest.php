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
        'portal_access_expires_at' =>
            now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital Command Business',
        'business_name' =>
            'Capital Command Business',
        'business_stage' => $stage,
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('existing business dashboard sends capital work into current capital position', function () {
    extract(
        capitalCommandCenterFixture(
            'existing'
        )
    );

    $currentCapitalUrl = route(
        'workspaces.tools.chapter-one.show',
        [
            $workspace,
            'current-capital-position',
        ]
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
            'data-journey-step="capital"',
            false
        )
        ->assertSee(
            'Capital &amp; Funding',
            false
        )
        ->assertSee(
            'လက်ရှိ Capital Position သတ်မှတ်ရန်'
        )
        ->assertSee(
            $currentCapitalUrl,
            false
        )
        ->assertDontSee(
            'data-capital-step=',
            false
        )
        ->assertDontSee(
            'Current Rule Progress'
        );
});

test('new business dashboard sends capital work into startup capital plan', function () {
    extract(
        capitalCommandCenterFixture(
            'new'
        )
    );

    $startupUrl = route(
        'workspaces.tools.startup-capital.show',
        $workspace
    );

    $currentCapitalUrl = route(
        'workspaces.tools.chapter-one.show',
        [
            $workspace,
            'current-capital-position',
        ]
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
            'data-journey-step="capital"',
            false
        )
        ->assertSee(
            'Startup Capital ကို စီစဉ်ရန်'
        )
        ->assertSee(
            $startupUrl,
            false
        )
        ->assertDontSee(
            $currentCapitalUrl,
            false
        )
        ->assertDontSee(
            'data-capital-step=',
            false
        );
});

test('partner dashboard remains approved state only and never exposes private capital draft metadata', function () {
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
            'data-pbr-dashboard-v2',
            false
        )
        ->assertSee(
            'Partner Read-Only View'
        )
        ->assertSee(
            'Approved state ကြည့်ရန်'
        )
        ->assertDontSee(
            'NEXT BUSINESS ACTION'
        )
        ->assertDontSee(
            'Private Capital Draft 999'
        )
        ->assertDontSee(
            'Working Change ရှိသည်'
        )
        ->assertDontSee(
            'data-capital-manager-action',
            false
        );
});
