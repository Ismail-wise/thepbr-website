<?php

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\Domains\CapitalDomainEngine;

function capitalToolState(array $overrides = []): array
{
    return array_replace_recursive(
        [
            'startup_capital_planner' => [
                'data' => [
                    'total_startup_capital' => 300000,
                    'funded_total' => 150000,
                ],
            ],

            'current_capital_position' => [
                'data' => [
                    'net_capital_position' => 90000,
                ],
            ],

            'working_capital_calculator' => [
                'data' => [
                    'working_capital_required' => 200000,
                    'monthly_operating_cost' => 50000,
                ],
            ],

            'contingency_fund_calculator' => [
                'data' => [
                    'contingency_fund' => 50000,
                ],
            ],

            'partner_contribution_matrix' => [
                'data' => [
                    'total_contribution' => 180000,
                    'partners' => [
                        [
                            'name' => 'Owner',
                            'total' => 100000,
                        ],
                        [
                            'name' => 'Partner',
                            'total' => 80000,
                        ],
                    ],
                ],
            ],

            'funding_gap_calculator' => [
                'data' => [
                    'other_funding' => 70000,
                ],
            ],
        ],
        $overrides
    );
}

test('new business capital summary has one authoritative calculation', function () {
    $workspace = new PartnershipWorkspace([
        'business_stage' => 'new',
        'currency_code' => 'THB',
    ]);

    $summary = app(
        CapitalDomainEngine::class
    )->summarize(
        $workspace,
        capitalToolState()
    );

    expect($summary)
        ->toMatchArray([
            'startup_capital' => 300000.0,
            'startup_funded' => 150000.0,
            'working_capital' => 200000.0,
            'monthly_operating_cost' => 50000.0,
            'contingency_fund' => 50000.0,
            'partner_capital' => 180000.0,
            'other_funding' => 70000.0,
            'capital_required' => 550000.0,
            'capital_secured' => 250000.0,
            'funding_gap' => 300000.0,
            'funding_surplus' => 0.0,
        ]);

    expect(
        $summary['funding_coverage_percentage']
    )->toBe(45.45);

    expect(
        $summary['funding_status']
    )->toBe('gap');

    expect(
        $summary['allocations']
    )->toBe([
        [
            'name' => 'Startup Capital',
            'amount' => 300000.0,
        ],
        [
            'name' => 'Working Capital',
            'amount' => 200000.0,
        ],
        [
            'name' => 'Contingency Reserve',
            'amount' => 50000.0,
        ],
    ]);
});

test('capital engine never double counts startup funding and detailed funding', function () {
    $workspace = new PartnershipWorkspace([
        'business_stage' => 'new',
    ]);

    $tools = capitalToolState([
        'startup_capital_planner' => [
            'data' => [
                'funded_total' => 400000,
            ],
        ],

        'partner_contribution_matrix' => [
            'data' => [
                'total_contribution' => 200000,
            ],
        ],

        'funding_gap_calculator' => [
            'data' => [
                'other_funding' => 100000,
            ],
        ],
    ]);

    $summary = app(
        CapitalDomainEngine::class
    )->summarize(
        $workspace,
        $tools
    );

    expect(
        $summary['capital_secured']
    )->toBe(400000.0);

    expect(
        $summary['capital_secured']
    )->not->toBe(700000.0);
});

test('existing business does not treat startup capital or net position as required funding', function () {
    $workspace = new PartnershipWorkspace([
        'business_stage' => 'existing',
    ]);

    $summary = app(
        CapitalDomainEngine::class
    )->summarize(
        $workspace,
        capitalToolState()
    );

    expect(
        $summary['capital_required']
    )->toBe(250000.0);

    expect(
        $summary['current_net_capital_position']
    )->toBe(90000.0);

    expect(
        $summary['allocations']
    )->toBe([
        [
            'name' => 'Working Capital',
            'amount' => 200000.0,
        ],
        [
            'name' => 'Contingency Reserve',
            'amount' => 50000.0,
        ],
    ]);
});

test('capital engine supports generic nested tool result data', function () {
    $workspace = new PartnershipWorkspace([
        'business_stage' => 'existing',
    ]);

    $summary = app(
        CapitalDomainEngine::class
    )->summarize(
        $workspace,
        [
            'working_capital_calculator' => [
                'data' => [
                    'data' => [
                        'working_capital_required' => 120000,
                        'monthly_operating_cost' => 30000,
                    ],
                ],
            ],

            'contingency_fund_calculator' => [
                'data' => [
                    'data' => [
                        'contingency_fund' => 30000,
                    ],
                ],
            ],
        ]
    );

    expect(
        $summary['capital_required']
    )->toBe(150000.0);

    expect(
        $summary['monthly_operating_cost']
    )->toBe(30000.0);
});
