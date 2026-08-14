<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capitalCoreUxFixture(): array
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
        'name' => 'Capital Core UX Business',
        'business_name' =>
            'Capital Core UX Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

test('current capital position explains the operating meaning and offers useful quick categories', function () {
    extract(capitalCoreUxFixture());

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
            'data-capital-form="current-position"',
            false
        )
        ->assertSee('Resources ထည့်ပါ')
        ->assertSee('Liabilities ထည့်ပါ')
        ->assertSee(
            'data-add-category-preset="Cash &amp; Bank"',
            false
        )
        ->assertSee(
            'data-add-category-preset="Supplier Payables"',
            false
        )
        ->assertSee(
            'Funding Requirement အဖြစ်'
        )
        ->assertSee(
            'အလိုအလျောက် မယူပါဘူး။'
        );
});

test('working capital explains its calculation and provides reserve period shortcuts', function () {
    extract(capitalCoreUxFixture());

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.tools.chapter-one.show',
                [
                    $workspace,
                    'working-capital-calculator',
                ]
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-capital-form="working-capital"',
            false
        )
        ->assertSee('HOW IT WORKS')
        ->assertSee('Monthly Costs × Reserve Months')
        ->assertSee(
            'data-reserve-months="3"',
            false
        )
        ->assertSee(
            'data-add-category-preset="Payroll"',
            false
        )
        ->assertSee('Expected Receivables');
});

test('current capital result includes operational interpretation without changing its calculation', function () {
    extract(capitalCoreUxFixture());

    $response = $this
        ->actingAs($owner)
        ->post(
            route(
                'workspaces.tools.chapter-one.calculate',
                [
                    $workspace,
                    'current-capital-position',
                ]
            ),
            [
                'resources' => [
                    [
                        'name' => 'Cash',
                        'items' => [
                            [
                                'name' => 'Bank',
                                'amount' => 100000,
                            ],
                        ],
                    ],
                ],
                'liabilities' => [
                    [
                        'name' => 'Bills',
                        'items' => [
                            [
                                'name' => 'Supplier',
                                'amount' => 20000,
                            ],
                        ],
                    ],
                ],
            ]
        );

    $response
        ->assertOk()
        ->assertSee('THB')
        ->assertSee('80,000.00')
        ->assertSee(
            'data-result-interpretation="current-capital"',
            false
        )
        ->assertSee('POSITIVE NET POSITION');
});

test('working capital result preserves authoritative math and explains the operating buffer', function () {
    extract(capitalCoreUxFixture());

    $response = $this
        ->actingAs($owner)
        ->post(
            route(
                'workspaces.tools.chapter-one.calculate',
                [
                    $workspace,
                    'working-capital-calculator',
                ]
            ),
            [
                'monthly_costs' => [
                    [
                        'name' => 'Operations',
                        'items' => [
                            [
                                'name' => 'Monthly Cost',
                                'amount' => 10000,
                            ],
                        ],
                    ],
                ],
                'reserve_months' => 3,
                'inventory_requirement' => 5000,
                'short_term_payables' => 2000,
                'expected_receivables' => 7000,
            ]
        );

    $response
        ->assertOk()
        ->assertSee('30,000.00')
        ->assertSee(
            'data-result-interpretation="working-capital"',
            false
        )
        ->assertSee('OPERATING BUFFER')
        ->assertSee(
            'Gross need before receivables'
        );
});
