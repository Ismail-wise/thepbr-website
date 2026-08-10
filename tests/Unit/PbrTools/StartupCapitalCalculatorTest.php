<?php

use App\Services\PbrTools\StartupCapitalCalculator;

it('calculates custom categories and items', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'categories' => [
            [
                'name' => 'Equipment',
                'items' => [
                    ['name' => 'Coffee Machine', 'amount' => 120000],
                    ['name' => 'POS System', 'amount' => 25000],
                ],
            ],
            [
                'name' => 'Shop Setup',
                'items' => [
                    ['name' => 'Renovation', 'amount' => 80000],
                    ['name' => 'Furniture', 'amount' => 35000],
                ],
            ],
        ],
    ]);

    expect($result['total_startup_capital'])->toBe(260000.0)
        ->and($result['category_count'])->toBe(2)
        ->and($result['item_count'])->toBe(4)
        ->and($result['largest_category']['name'])->toBe('Equipment')
        ->and($result['largest_item']['name'])->toBe('Coffee Machine');
});

it('supports an Others category', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'categories' => [
            [
                'name' => 'Others',
                'items' => [
                    ['name' => 'Miscellaneous', 'amount' => 5000],
                ],
            ],
        ],
    ]);

    expect($result['total_startup_capital'])->toBe(5000.0)
        ->and($result['categories'][0]['name'])->toBe('Others');
});

it('ignores completely empty items', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'categories' => [
            [
                'name' => 'Equipment',
                'items' => [
                    ['name' => '', 'amount' => ''],
                    ['name' => 'Laptop', 'amount' => 30000],
                ],
            ],
        ],
    ]);

    expect($result['item_count'])->toBe(1)
        ->and($result['total_startup_capital'])->toBe(30000.0);
});

it('returns zero for an empty plan', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([]);

    expect($result['total_startup_capital'])->toBe(0.0)
        ->and($result['category_count'])->toBe(0)
        ->and($result['item_count'])->toBe(0)
        ->and($result['largest_category'])->toBeNull()
        ->and($result['largest_item'])->toBeNull();
});
