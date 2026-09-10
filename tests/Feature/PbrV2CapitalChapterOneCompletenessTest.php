<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test(
    'v2 capital manager renders the exact chapter one six step decision path and source labels',
    function () {
        $owner = User::factory()->create([
            'role' => 'student',
            'is_admin' => false,
            'account_status' => 'active',
            'portal_access_expires_at' =>
                now()->addYear(),
        ]);

        $workspace =
            PartnershipWorkspace::query()->create([
                'owner_user_id' => $owner->id,
                'name' =>
                    'Chapter One Completeness Workspace',
                'business_name' =>
                    'Chapter One Completeness Business',
                'business_stage' => 'new',
                'currency_code' => 'THB',
                'status' => 'active',
            ]);

        $response = $this
            ->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee('Approved Plan')
            ->assertSee('Business Tools')
            ->assertSee(
                'PLANNING FLOW'
            )
            ->assertSeeInOrder([
                'Startup Cost Plan',
                'Initial Assets &amp; Opening Inventory',
                'Working Capital Forecast',
                'Contingency Reserve',
                'Funding Position &amp; Gap',
                'Capital Rule &amp; Allocation',
            ], false)
            ->assertSee(
                'Calculate → Compare → Approve → Record → Act'
            )
            ->assertSee(
                'CAPITAL PLAN COMPARISON'
            )
            ->assertDontSee(
                'Capital Cost Items'
            );

        $capitalSource = file_get_contents(
            resource_path(
                'views/workspaces/v2/capital.blade.php'
            )
        );

        expect($capitalSource)
            ->toContain(
                '<h2>Approved Plan</h2>'
            );
    }
);

test(
    'shared premium command bar preserves legacy result and all tools defaults when overrides are absent',
    function () {
        $owner = User::factory()->create([
            'role' => 'student',
            'is_admin' => false,
            'account_status' => 'active',
            'portal_access_expires_at' =>
                now()->addYear(),
        ]);

        $workspace =
            PartnershipWorkspace::query()->create([
                'owner_user_id' => $owner->id,
                'name' =>
                    'Shared Toolbar Default Workspace',
                'business_name' =>
                    'Shared Toolbar Default Business',
                'business_stage' => 'existing',
                'currency_code' => 'THB',
                'status' => 'active',
            ]);

        $html = view(
            'workspaces.tools.partials.premium-tool-command-bar',
            [
                'workspace' => $workspace,
                'tool' => (object) [
                    'title_mm' =>
                        'Shared Toolbar Tool',
                    'title_en' =>
                        'Shared Toolbar Tool',
                ],
                'canManage' => true,
                'activeSession' => false,
                'latestAgreedOutput' => null,
            ]
        )->render();

        expect($html)
            ->toContain('Result')
            ->toContain('All Tools')
            ->not->toContain(
                'Approved Plan'
            )
            ->not->toContain(
                'Business Tools'
            );

        $source = file_get_contents(
            resource_path(
                'views/workspaces/tools/partials/premium-tool-command-bar.blade.php'
            )
        );

        expect($source)
            ->toContain(
                '$premiumToolResultLabel'
            )
            ->toContain(
                "?? 'Result';"
            )
            ->toContain(
                '$premiumToolIndexLabel'
            )
            ->toContain(
                "?? 'All Tools';"
            );
    }
);

test(
    'accepted partner receives chapter one read only guidance without gaining capital management authority',
    function () {
        $owner = User::factory()->create([
            'role' => 'student',
            'is_admin' => false,
            'account_status' => 'active',
            'portal_access_expires_at' =>
                now()->addYear(),
        ]);

        $workspace =
            PartnershipWorkspace::query()->create([
                'owner_user_id' => $owner->id,
                'name' =>
                    'Partner Completeness Workspace',
                'business_name' =>
                    'Partner Completeness Business',
                'business_stage' => 'new',
                'currency_code' => 'THB',
                'status' => 'active',
            ]);

        $partner = User::factory()->create([
            'role' => 'public',
            'is_admin' => false,
            'account_status' => 'active',
        ]);

        WorkspaceMember::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $partner->id,
            'member_role' => 'partner',
            'invitation_status' => 'accepted',
            'permissions' => [
                'approved_workspace_read_only',
            ],
        ]);

        $response = $this
            ->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'PLANNING FLOW'
            )
            ->assertSee(
                'Calculate → Compare → Approve → Record → Act'
            )
            ->assertSee(
                'Approved Plan'
            )
            ->assertSee(
                'Business Tools'
            )
            ->assertDontSee(
                'Your Plans'
            )
            ->assertDontSee(
                'Create Draft Plan'
            );
    }
);
