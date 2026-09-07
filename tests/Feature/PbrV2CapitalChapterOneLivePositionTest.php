<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test(
    'v2 capital manager renders chapter one quick start presets without expanding the frozen cost category vocabulary',
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
                    'C2B Quick Start Workspace',
                'business_name' =>
                    'C2B Quick Start Business',
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
            ->assertSee('Quick Start')
            ->assertSee('Registration / Legal')
            ->assertSee('Deposit')
            ->assertSee('Renovation')
            ->assertSee('Launch Marketing')
            ->assertSee('Training')
            ->assertSee('Equipment')
            ->assertSee('Furniture')
            ->assertSee('Technology')
            ->assertSee('Opening Stock')
            ->assertSee('Salary')
            ->assertSee('Rent')
            ->assertSee('Utilities')
            ->assertSee('Software')
            ->assertSee('Monthly Marketing')
            ->assertSee('Admin')
            ->assertSee('Contingency Buffer')
            ->assertSee(
                'data-v2-category="pre_opening"',
                false
            )
            ->assertSee(
                'data-v2-category="initial_assets_inventory"',
                false
            )
            ->assertSee(
                'data-v2-category="working_capital"',
                false
            )
            ->assertSee(
                'data-v2-contingency-guide',
                false
            )
            ->assertDontSee(
                'data-v2-category="contingency"',
                false
            );
    }
);

test(
    'v2 capital live position is wired to the complete frozen readiness calculation projection',
    function () {
        $source = file_get_contents(
            resource_path(
                'views/workspaces/v2/capital.blade.php'
            )
        );

        foreach ([
            'Total Capital Required',
            'Pre-opening Cost',
            'Assets/Inventory',
            'Working Capital',
            'Contingency Reserve',
            'Confirmed Funding',
            'Funding Gap',
            '% Funded',
            'Monthly Burn',
        ] as $label) {
            expect($source)->toContain($label);
        }

        foreach ([
            'pre_opening_total',
            'initial_assets_inventory_total',
            'working_capital_total',
            'contingency_reserve',
            'total_required',
            'confirmed_funding',
            'funding_gap',
            'funded_percentage',
        ] as $key) {
            expect($source)
                ->toContain(
                    "\$currentCalc['{$key}']"
                )
                ->toContain(
                    "\$draftCalc['{$key}']"
                );
        }

        expect($source)
            ->toContain(
                "\$currentPayload['assumptions']['monthly_burn']"
            )
            ->toContain(
                "\$payload['assumptions']['monthly_burn']"
            )
            ->not->toContain(
                'runway_months'
            );
    }
);

test(
    'v2 capital quick start only fills existing category and description fields while contingency remains percentage driven',
    function () {
        $source = file_get_contents(
            resource_path(
                'views/workspaces/v2/capital.blade.php'
            )
        );

        $mapping = [
            'Registration / Legal' => 'pre_opening',
            'Deposit' => 'pre_opening',
            'Renovation' => 'pre_opening',
            'Launch Marketing' => 'pre_opening',
            'Training' => 'pre_opening',
            'Equipment' =>
                'initial_assets_inventory',
            'Furniture' =>
                'initial_assets_inventory',
            'Technology' =>
                'initial_assets_inventory',
            'Opening Stock' =>
                'initial_assets_inventory',
            'Salary' => 'working_capital',
            'Rent' => 'working_capital',
            'Utilities' => 'working_capital',
            'Software' => 'working_capital',
            'Monthly Marketing' =>
                'working_capital',
            'Admin' => 'working_capital',
        ];

        foreach ($mapping as $description => $category) {
            expect($source)
                ->toContain(
                    'data-v2-category="'
                    .$category
                    .'"'
                )
                ->toContain(
                    'data-v2-description="'
                    .$description
                    .'"'
                );
        }

        expect($source)
            ->toContain(
                "event.target.closest(\n"
                ."                    '[data-v2-cost-preset]'"
            )
            ->toContain(
                "select[name$=\"[category]\"]"
            )
            ->toContain(
                "input[name$=\"[description]\"]"
            )
            ->toContain(
                '[data-v2-contingency-guide]'
            )
            ->toContain(
                '[name="contingency_percentage"]'
            )
            ->not->toContain(
                'data-v2-category="contingency"'
            );
    }
);
