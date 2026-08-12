<?php

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\PbrOperatingToolEngine;

function pbrWorkspaceForToolTests(): PartnershipWorkspace
{
    $workspace = new PartnershipWorkspace();
    $workspace->business_stage = 'existing';
    $workspace->currency_code = 'THB';

    return $workspace;
}

test('chapters two to ten define exactly fifty seven operating tools', function () {
    $definitions = config('pbr_operating_tools.definitions', []);
    $course = config('pbr_course.chapters', []);

    $catalogKeys = collect($course)
        ->filter(fn (array $chapter): bool => (int) $chapter['number'] >= 2)
        ->flatMap(fn (array $chapter) => collect($chapter['tools'])->pluck('key'))
        ->values();

    expect($definitions)->toHaveCount(57)
        ->and(array_keys($definitions))->toEqualCanonicalizing($catalogKeys->all());
});

test('every chapter two to ten tool can execute its default calculation without missing handler', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $workspace = pbrWorkspaceForToolTests();

    foreach (array_keys(config('pbr_operating_tools.definitions', [])) as $toolKey) {
        $input = $engine->defaultInput($toolKey);
        $result = $engine->calculate($toolKey, $input, $workspace);

        expect($result)
            ->toBeArray()
            ->toHaveKeys([
                'headline',
                'metrics',
                'tables',
                'warnings',
                'notes',
                'data',
                'tool_key',
                'chapter',
                'currency',
            ])
            ->and($result['tool_key'])->toBe($toolKey)
            ->and($result['chapter'])->toBeGreaterThanOrEqual(2)
            ->and($result['chapter'])->toBeLessThanOrEqual(10);
    }
});

test('cap table keeps issued ownership voting and reserved units separate', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('cap_table_builder', [
        'partners' => [
            ['name' => 'A', 'units' => 600, 'voting_units' => 400],
            ['name' => 'B', 'units' => 400, 'voting_units' => 600],
        ],
        'reserved_units' => 250,
    ], pbrWorkspaceForToolTests());

    expect($result['data']['issued_units'])->toBe(1000.0)
        ->and($result['data']['fully_diluted_units'])->toBe(1250.0)
        ->and($result['data']['holders'][0]['ownership_percentage'])->toBe(60.0)
        ->and($result['data']['holders'][0]['fully_diluted_percentage'])->toBe(48.0)
        ->and($result['data']['holders'][0]['voting_percentage'])->toBe(40.0);
});

test('dilution simulator reduces existing percentages after new issuance', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('future_dilution_simulator', [
        'new_units' => 250,
        'new_holder_name' => 'Investor',
        'partners' => [
            ['name' => 'A', 'units' => 600],
            ['name' => 'B', 'units' => 400],
        ],
    ], pbrWorkspaceForToolTests());

    expect($result['data']['new_total_units'])->toBe(1250.0)
        ->and($result['data']['holders'][0]['before_percentage'])->toBe(60.0)
        ->and($result['data']['holders'][0]['after_percentage'])->toBe(48.0)
        ->and($result['data']['holders'][2]['after_percentage'])->toBe(20.0);
});

test('profit distribution separates reserve from distributable profit', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('profit_distribution_calculator', [
        'net_profit' => 100000,
        'reserve_percentage' => 20,
        'partners' => [
            ['name' => 'A', 'percentage' => 60],
            ['name' => 'B', 'percentage' => 40],
        ],
    ], pbrWorkspaceForToolTests());

    expect($result['data']['reserve_amount'])->toBe(20000.0)
        ->and($result['data']['distributable_profit'])->toBe(80000.0)
        ->and($result['data']['partners'][0]['distribution'])->toBe(48000.0)
        ->and($result['warnings'])->toBeEmpty();
});

test('profit distribution warns when partner percentages do not total one hundred', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('profit_distribution_calculator', [
        'net_profit' => 100000,
        'reserve_percentage' => 20,
        'partners' => [
            ['name' => 'A', 'percentage' => 50],
            ['name' => 'B', 'percentage' => 30],
        ],
    ], pbrWorkspaceForToolTests());

    expect($result['warnings'])->not->toBeEmpty();
});

test('vesting respects cliff and straight line vesting period', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $workspace = pbrWorkspaceForToolTests();

    $beforeCliff = $engine->calculate('vesting_calculator', [
        'grant_units' => 4800,
        'vesting_months' => 48,
        'cliff_months' => 12,
        'months_elapsed' => 11,
    ], $workspace);

    $atHalf = $engine->calculate('vesting_calculator', [
        'grant_units' => 4800,
        'vesting_months' => 48,
        'cliff_months' => 12,
        'months_elapsed' => 24,
    ], $workspace);

    expect($beforeCliff['data']['vested_units'])->toBe(0.0)
        ->and($atHalf['data']['vested_units'])->toBe(2400.0)
        ->and($atHalf['data']['unvested_units'])->toBe(2400.0);
});

test('share transfer blocks transferring more units than seller owns', function () {
    $engine = app(PbrOperatingToolEngine::class);

    expect(fn () => $engine->calculate('share_transfer_simulator', [
        'total_units' => 1000,
        'seller_name' => 'A',
        'seller_units' => 100,
        'buyer_name' => 'B',
        'buyer_units' => 200,
        'transfer_units' => 150,
    ], pbrWorkspaceForToolTests()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('buyout calculator uses equity value ownership and explicit adjustment', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('partner_buyout_calculator', [
        'business_value' => 2400000,
        'ownership_percentage' => 25,
        'adjustment' => -50000,
        'payment_months' => 11,
    ], pbrWorkspaceForToolTests());

    expect($result['data']['base_value'])->toBe(600000.0)
        ->and($result['data']['buyout_value'])->toBe(550000.0)
        ->and($result['data']['monthly_payment'])->toBe(50000.0);
});

test('issue priority ranks higher impact urgency and continuity first', function () {
    $engine = app(PbrOperatingToolEngine::class);
    $result = $engine->calculate('issue_priority_matrix', [
        'issues' => [
            ['issue' => 'Minor', 'impact' => 1, 'urgency' => 1, 'continuity' => 1],
            ['issue' => 'Critical', 'impact' => 5, 'urgency' => 5, 'continuity' => 5],
        ],
    ], pbrWorkspaceForToolTests());

    expect($result['data']['issues'][0]['issue'])->toBe('Critical')
        ->and($result['data']['issues'][0]['priority'])->toBe('Critical');
});
