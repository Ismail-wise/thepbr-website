<?php

use App\Services\PbrTools\StartupCapitalCalculator;

test('startup capital plan separates priorities funding and recurring reserve', function () {
    $calculator = app(StartupCapitalCalculator::class);

    $result = $calculator->calculate([
        'categories' => [
            [
                'name' => 'Premises',
                'items' => [
                    [
                        'name' => 'Shop Deposit',
                        'amount' => 30000,
                        'priority' => 'essential',
                        'frequency' => 'one_time',
                        'funded_amount' => 20000,
                        'funding_source' => 'Partner A',
                        'due_date' => now()->addDays(10)->format('Y-m-d'),
                    ],
                ],
            ],
            [
                'name' => 'Staffing',
                'items' => [
                    [
                        'name' => 'Initial Salaries',
                        'amount' => 10000,
                        'priority' => 'essential',
                        'frequency' => 'monthly',
                        'reserve_months' => 3,
                        'funded_amount' => 15000,
                        'funding_source' => 'Partner B',
                        'due_date' => now()->addDays(20)->format('Y-m-d'),
                    ],
                ],
            ],
            [
                'name' => 'Marketing',
                'items' => [
                    [
                        'name' => 'Launch Ads',
                        'amount' => 5000,
                        'priority' => 'optional',
                        'frequency' => 'one_time',
                        'funded_amount' => 0,
                    ],
                ],
            ],
        ],
    ]);

    expect($result['total_startup_capital'])->toBe(65000.0)
        ->and($result['essential_total'])->toBe(60000.0)
        ->and($result['optional_total'])->toBe(5000.0)
        ->and($result['funded_total'])->toBe(35000.0)
        ->and($result['funding_gap'])->toBe(30000.0)
        ->and($result['essential_funding_gap'])->toBe(25000.0)
        ->and($result['one_time_total'])->toBe(35000.0)
        ->and($result['recurring_reserve_total'])->toBe(30000.0)
        ->and($result['monthly_recurring_cost'])->toBe(10000.0)
        ->and($result['due_30_days_outstanding'])->toBe(25000.0)
        ->and($result['categories'][1]['items'][0]['planned_cost'])->toBe(30000.0)
        ->and($result['categories'][1]['items'][0]['funding_status'])->toBe('partial')
        ->and($result['funding_sources'])->toHaveCount(2)
        ->and($result['warnings'])->not->toBeEmpty();
});

test('startup capital planner keeps legacy total keys for connected capital systems', function () {
    $result = app(StartupCapitalCalculator::class)->calculate([
        'categories' => [[
            'name' => 'Equipment',
            'items' => [[
                'name' => 'POS System',
                'amount' => 12000,
            ]],
        ]],
    ]);

    expect($result)
        ->toHaveKeys([
            'total_startup_capital',
            'category_count',
            'item_count',
            'categories',
            'largest_category',
            'largest_item',
        ])
        ->and($result['total_startup_capital'])->toBe(12000.0)
        ->and($result['funded_total'])->toBe(0.0)
        ->and($result['funding_gap'])->toBe(12000.0);
});
