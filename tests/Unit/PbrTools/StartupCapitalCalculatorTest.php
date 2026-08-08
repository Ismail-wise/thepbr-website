<?php

use App\Services\PbrTools\StartupCapitalCalculator;

test('it calculates startup capital correctly', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'registration_legal' => 25000,
        'location_renovation' => 120000,
        'equipment' => 300000,
        'initial_inventory' => 180000,
        'technology_software' => 50000,
        'branding_marketing' => 75000,
        'deposits_prepayments' => 60000,
        'other_startup_costs' => 40000,
    ]);

    expect($result['startup_cost_total'])
        ->toBe(850000.0);

    expect($result['non_zero_categories'])
        ->toBe(8);

    expect($result['largest_category']['key'])
        ->toBe('equipment');

    expect($result['largest_category']['amount'])
        ->toBe(300000.0);

    expect($result['largest_category']['percentage'])
        ->toBe(35.29);
});


test('it treats empty invalid and negative values as zero', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'registration_legal' => '1000.50',
        'location_renovation' => '',
        'equipment' => -5000,
        'initial_inventory' => 'invalid',
        'technology_software' => null,
        'other_startup_costs' => 250,
    ]);

    expect($result['startup_cost_total'])
        ->toBe(1250.5);

    expect($result['non_zero_categories'])
        ->toBe(2);

    expect(
        $result['categories']['equipment']['amount']
    )->toBe(0.0);

    expect(
        $result['categories']['initial_inventory']['amount']
    )->toBe(0.0);

    expect($result['largest_category']['key'])
        ->toBe('registration_legal');
});


test('it returns a clean zero result when no costs exist', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([]);

    expect($result['startup_cost_total'])
        ->toBe(0.0);

    expect($result['non_zero_categories'])
        ->toBe(0);

    expect($result['largest_category'])
        ->toBeNull();

    foreach ($result['categories'] as $category) {
        expect($category['amount'])->toBe(0.0);
        expect($category['percentage'])->toBe(0);
    }
});


test('category percentages total approximately one hundred', function () {
    $calculator = new StartupCapitalCalculator();

    $result = $calculator->calculate([
        'registration_legal' => 100,
        'equipment' => 200,
        'initial_inventory' => 300,
    ]);

    $percentageTotal = array_sum(
        array_column(
            $result['categories'],
            'percentage'
        )
    );

    expect($percentageTotal)
        ->toBeGreaterThanOrEqual(99.99)
        ->toBeLessThanOrEqual(100.01);
});
