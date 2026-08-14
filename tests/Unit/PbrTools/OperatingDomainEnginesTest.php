<?php

use App\Services\PbrTools\Domains\ContributionDomainEngine;
use App\Services\PbrTools\Domains\ContinuityDomainEngine;
use App\Services\PbrTools\Domains\DisputeResolutionDomainEngine;
use App\Services\PbrTools\Domains\DistributionDomainEngine;
use App\Services\PbrTools\Domains\ExitDomainEngine;
use App\Services\PbrTools\Domains\FinancialControlsDomainEngine;
use App\Services\PbrTools\Domains\GovernanceDomainEngine;
use App\Services\PbrTools\Domains\OwnershipDomainEngine;
use App\Services\PbrTools\Domains\ShareTransferDomainEngine;

test('ownership domain keeps ownership and dilution state separate', function () {
    $summary = (new OwnershipDomainEngine())
        ->summarize([
            'cap_table_builder' => [
                'data' => [
                    'issued_units' => 100,
                    'reserved_units' => 20,
                    'fully_diluted_units' => 120,
                    'holders' => [
                        [
                            'holder' => 'Owner',
                            'units' => 60,
                        ],
                    ],
                ],
            ],
            'future_dilution_simulator' => [
                'data' => [
                    'new_units' => 25,
                ],
            ],
        ]);

    expect($summary['total_units'])
        ->toBe(100)
        ->and($summary['reserved_units'])
        ->toBe(20)
        ->and($summary['fully_diluted_units'])
        ->toBe(120)
        ->and($summary['latest_dilution']['new_units'])
        ->toBe(25);
});

test('contribution domain preserves role and contribution records', function () {
    $summary = (new ContributionDomainEngine())
        ->summarize([
            'role_responsibility_matrix' => [
                'data' => [
                    'responsibilities' => [
                        [
                            'role' => 'Operations',
                        ],
                    ],
                ],
            ],
            'vesting_calculator' => [
                'data' => [
                    'vested_percentage' => 50,
                ],
            ],
        ]);

    expect($summary['responsibilities'])
        ->toHaveCount(1)
        ->and($summary['vesting']['vested_percentage'])
        ->toBe(50);
});

test('distribution domain keeps salary profit reserve and loss rules distinct', function () {
    $summary = (new DistributionDomainEngine())
        ->summarize([
            'profit_distribution_calculator' => [
                'data' => [
                    'distributable_profit' => 100000,
                ],
            ],
            'salary_profit_share_planner' => [
                'data' => [
                    'annual_salary' => 240000,
                ],
            ],
            'reserve_fund_planner' => [
                'data' => [
                    'reserve_target' => 50000,
                ],
            ],
        ]);

    expect(
        $summary['profit_distribution']['distributable_profit']
    )->toBe(100000);

    expect(
        $summary['salary_profit_plan']['annual_salary']
    )->toBe(240000);

    expect(
        $summary['reserve_fund']['reserve_target']
    )->toBe(50000);
});

test('financial controls domain preserves operating control sources', function () {
    $summary = (new FinancialControlsDomainEngine())
        ->summarize([
            'cashflow_dashboard' => [
                'data' => [
                    'net_cashflow' => 20000,
                ],
            ],
            'expense_approval_matrix' => [
                'data' => [
                    'approval_limit' => 50000,
                ],
            ],
        ]);

    expect($summary['cashflow']['net_cashflow'])
        ->toBe(20000)
        ->and(
            $summary['expense_approval']['approval_limit']
        )
        ->toBe(50000);
});

test('governance domain preserves decision voting and deadlock rules separately', function () {
    $summary = (new GovernanceDomainEngine())
        ->summarize([
            'decision_rights_matrix' => [
                'data' => [
                    'decision_count' => 5,
                ],
            ],
            'voting_simulator' => [
                'data' => [
                    'passed' => true,
                ],
            ],
            'deadlock_detector' => [
                'data' => [
                    'deadlocked' => false,
                ],
            ],
        ]);

    expect(
        $summary['decision_rights']['decision_count']
    )->toBe(5);

    expect(
        $summary['voting']['passed']
    )->toBeTrue();

    expect(
        $summary['deadlock_rule']['deadlocked']
    )->toBeFalse();
});

test('exit domain preserves buyout notice handover and continuity state', function () {
    $summary = (new ExitDomainEngine())
        ->summarize([
            'partner_buyout_calculator' => [
                'data' => [
                    'buyout_value' => 400000,
                ],
            ],
            'notice_period_planner' => [
                'data' => [
                    'notice_days' => 90,
                ],
            ],
        ]);

    expect(
        $summary['buyout']['buyout_value']
    )->toBe(400000);

    expect(
        $summary['notice_plan']['notice_days']
    )->toBe(90);
});

test('continuity domain preserves succession and emergency authority separately', function () {
    $summary = (new ContinuityDomainEngine())
        ->summarize([
            'succession_planner' => [
                'data' => [
                    'successor' => 'Partner B',
                ],
            ],
            'emergency_authority_planner' => [
                'data' => [
                    'emergency_authority' => 'Partner C',
                ],
            ],
        ]);

    expect(
        $summary['succession']['successor']
    )->toBe('Partner B');

    expect(
        $summary['emergency_authority']['emergency_authority']
    )->toBe('Partner C');
});

test('share transfer domain keeps proposed transfer separate from ownership state', function () {
    $summary = (new ShareTransferDomainEngine())
        ->summarize([
            'share_transfer_simulator' => [
                'data' => [
                    'transfer_units' => 10,
                ],
            ],
            'ownership_before_after_chart' => [
                'data' => [
                    'before_units' => 100,
                    'after_units' => 90,
                ],
            ],
        ]);

    expect(
        $summary['latest_transfer_scenario']['transfer_units']
    )->toBe(10);

    expect(
        $summary['ownership_before_after']['after_units']
    )->toBe(90);
});

test('dispute resolution domain preserves issue and resolution lifecycle state', function () {
    $summary = (new DisputeResolutionDomainEngine())
        ->summarize([
            'dispute_log' => [
                'data' => [
                    'issue' => 'Approval deadlock',
                ],
            ],
            'resolution_tracker' => [
                'data' => [
                    'status' => 'open',
                ],
            ],
        ]);

    expect(
        $summary['latest_dispute']['issue']
    )->toBe('Approval deadlock');

    expect(
        $summary['resolution']['status']
    )->toBe('open');
});
