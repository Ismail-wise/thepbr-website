<?php

use App\Services\PbrTools\PbrV2ContributionValuationService;

test('time skill valuation preserves exact values without granting acceptance', function (
    array $input,
    string $gross,
    string $net,
    array $discrepancies
) {
    $result = (new PbrV2ContributionValuationService())->assessTimeSkill($input);

    expect($result)->toBe([
        'gross_work_value' => $gross,
        'cash_compensation_received' => $input['cash_compensation_received'],
        'calculated_work_value' => $net,
        'discrepancies' => $discrepancies,
    ]);
})->with([
    'ordinary work' => [[
        'hours_per_month' => '160',
        'fair_hourly_rate' => '100',
        'number_of_months' => '3',
        'cash_compensation_received' => '18000.00',
    ], '48000.00', '30000.00', []],
    'single final rounding' => [[
        'hours_per_month' => '0.01',
        'fair_hourly_rate' => '0.50',
        'number_of_months' => '2',
        'cash_compensation_received' => '0.00',
    ], '0.01', '0.01', []],
    'half cent rounds up' => [[
        'hours_per_month' => '0.01',
        'fair_hourly_rate' => '0.50',
        'number_of_months' => '1',
        'cash_compensation_received' => '0.00',
    ], '0.01', '0.01', []],
    'overpayment remains visible' => [[
        'hours_per_month' => '1',
        'fair_hourly_rate' => '10',
        'number_of_months' => '1',
        'cash_compensation_received' => '12.00',
    ], '10.00', '-2.00', ['cash_compensation_exceeds_fair_work_value']],
    'no inherited month cap' => [[
        'hours_per_month' => '1',
        'fair_hourly_rate' => '1',
        'number_of_months' => '121',
        'cash_compensation_received' => '0.00',
    ], '121.00', '121.00', []],
]);

test('time skill valuation rejects malformed numeric representations', function (mixed $value) {
    $input = [
        'hours_per_month' => $value,
        'fair_hourly_rate' => '1',
        'number_of_months' => '1',
        'cash_compensation_received' => '0',
    ];

    expect(fn () => (new PbrV2ContributionValuationService())->assessTimeSkill($input))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'float' => [1.5],
    'integer' => [1],
    'missing' => [null],
    'negative' => ['-1'],
    'exponent' => ['1e3'],
    'excess precision' => ['1.001'],
    'leading zero' => ['01'],
    'whitespace' => [' 1'],
    'trailing newline' => ["1\n"],
    'oversized' => ['1000000000000'],
]);

test('time skill valuation rejects integer overflow', function () {
    expect(fn () => (new PbrV2ContributionValuationService())->assessTimeSkill([
        'hours_per_month' => '999999999999.99',
        'fair_hourly_rate' => '999999999999.99',
        'number_of_months' => '1',
        'cash_compensation_received' => '0',
    ]))->toThrow(InvalidArgumentException::class, 'exact integer capacity');
});

test('zero duration avoids unnecessary intermediate overflow', function () {
    $result = (new PbrV2ContributionValuationService())->assessTimeSkill([
        'hours_per_month' => '999999999999.99',
        'fair_hourly_rate' => '999999999999.99',
        'number_of_months' => '0',
        'cash_compensation_received' => '0',
    ]);

    expect($result['gross_work_value'])->toBe('0.00');
});
