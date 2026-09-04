<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('chapters one to three expose the exact approved component identities', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        array_keys(
            $registry->forDomain('capital')
        )
    )->toBe([
        'capital_funding_workflow',
        'startup_capital_planner',
        'current_capital_position',
        'working_capital_forecast',
        'contingency_reserve_analysis',
        'funding_gap_calculator',
        'capital_plan_comparison',
        'capital_allocation_summary',
        'capital_call_rule',
        'approved_capital_plan',
        'capital_decision_record',
        'capital_action_plan',
    ]);

    expect(
        array_keys(
            $registry->forDomain('contribution')
        )
    )->toBe([
        'partner_contributions_workflow',
        'contribution_valuation_analysis',
        'contribution_evidence_assessment',
        'partner_contribution_matrix',
        'accepted_contribution_register',
        'contribution_decision_record',
        'contribution_action_plan',
    ]);

    expect(
        array_keys(
            $registry->forDomain('ownership')
        )
    )->toBe([
        'ownership_equity_workflow',
        'share_allocation_analysis',
        'equity_split_simulator',
        'new_share_issuance_rule',
        'final_share_register',
        'ownership_decision_record',
        'ownership_action_plan',
    ]);
});

test('chapters one to three preserve the approved component type and canonical contracts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $expected = [
        'capital_funding_workflow' =>
            ['capital', 'workflow', 'none'],
        'startup_capital_planner' =>
            ['capital', 'analysis', 'none'],
        'current_capital_position' =>
            ['capital', 'analysis', 'none'],
        'working_capital_forecast' =>
            ['capital', 'analysis', 'none'],
        'contingency_reserve_analysis' =>
            ['capital', 'analysis', 'none'],
        'funding_gap_calculator' =>
            ['capital', 'analysis', 'none'],
        'capital_plan_comparison' =>
            ['capital', 'scenario', 'none'],
        'capital_allocation_summary' =>
            ['capital', 'analysis', 'none'],
        'capital_call_rule' =>
            ['capital', 'rule', 'rule'],
        'approved_capital_plan' =>
            ['capital', 'plan', 'plan'],
        'capital_decision_record' =>
            ['capital', 'record', 'none'],
        'capital_action_plan' =>
            ['capital', 'action', 'none'],

        'partner_contributions_workflow' =>
            ['contribution', 'workflow', 'none'],
        'contribution_valuation_analysis' =>
            ['contribution', 'analysis', 'none'],
        'contribution_evidence_assessment' =>
            ['contribution', 'assessment', 'none'],
        'partner_contribution_matrix' =>
            ['contribution', 'analysis', 'none'],
        'accepted_contribution_register' =>
            ['contribution', 'register', 'register'],
        'contribution_decision_record' =>
            ['contribution', 'record', 'none'],
        'contribution_action_plan' =>
            ['contribution', 'action', 'none'],

        'ownership_equity_workflow' =>
            ['ownership', 'workflow', 'none'],
        'share_allocation_analysis' =>
            ['ownership', 'analysis', 'none'],
        'equity_split_simulator' =>
            ['ownership', 'scenario', 'none'],
        'new_share_issuance_rule' =>
            ['ownership', 'rule', 'rule'],
        'final_share_register' =>
            ['ownership', 'register', 'register'],
        'ownership_decision_record' =>
            ['ownership', 'record', 'none'],
        'ownership_action_plan' =>
            ['ownership', 'action', 'none'],
    ];

    expect($expected)->toHaveCount(26);

    foreach ($expected as $key => $contract) {
        $component = $registry->component($key);

        expect($component['domain_key'])
            ->toBe($contract[0])
            ->and($component['component_type'])
            ->toBe($contract[1])
            ->and($component['canonical_capability'])
            ->toBe($contract[2])
            ->and($component['cross_domain_write'])
            ->toBeFalse();
    }
});

test('chapter one to three canonical outputs require approval engine publication', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'capital_call_rule',
        'approved_capital_plan',
        'accepted_contribution_register',
        'new_share_issuance_rule',
        'final_share_register',
    ] as $componentKey) {
        $component = $registry->component(
            $componentKey
        );

        expect($component['approval_required'])
            ->toBeTrue()
            ->and($component['can_create_proposal'])
            ->toBeTrue()
            ->and($component['publish_authority'])
            ->toBe('approval_engine_only')
            ->and($component['write_mode'])
            ->toBe('revisioned_current')
            ->and($component['stale_source_guard'])
            ->toBeTrue();
    }
});

test('chapter one to three records and actions cannot become canonical authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'capital_decision_record',
        'contribution_decision_record',
        'ownership_decision_record',
    ] as $componentKey) {
        $component = $registry->component(
            $componentKey
        );

        expect($component['canonical_capability'])
            ->toBe('none')
            ->and($component['publish_authority'])
            ->toBe('none')
            ->and($component['write_mode'])
            ->toBe('append_only_record')
            ->and($component['can_create_proposal'])
            ->toBeFalse();
    }

    foreach ([
        'capital_action_plan',
        'contribution_action_plan',
        'ownership_action_plan',
    ] as $componentKey) {
        $component = $registry->component(
            $componentKey
        );

        expect($component['canonical_capability'])
            ->toBe('none')
            ->and($component['publish_authority'])
            ->toBe('none')
            ->and($component['write_mode'])
            ->toBe('action_state')
            ->and($component['can_create_proposal'])
            ->toBeFalse();
    }
});

test('chapter one to three legacy reconciliation accounts for eighteen tools and defers the operations role matrix', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $expected = [
        'startup_capital_planner' =>
            ['startup_capital_planner', 'adapt'],
        'current_capital_position' =>
            ['current_capital_position', 'reuse'],
        'working_capital_calculator' =>
            ['working_capital_forecast', 'adapt'],
        'contingency_fund_calculator' =>
            ['contingency_reserve_analysis', 'adapt'],
        'partner_contribution_matrix' =>
            ['partner_contribution_matrix', 'move'],
        'funding_gap_calculator' =>
            ['funding_gap_calculator', 'reuse'],
        'capital_allocation_chart' =>
            ['capital_allocation_summary', 'support'],

        'sweat_equity_calculator' =>
            ['contribution_valuation_analysis', 'adapt'],
        'time_contribution_tracker' =>
            ['contribution_evidence_assessment', 'support'],
        'partner_contribution_scorecard' =>
            ['contribution_evidence_assessment', 'adapt'],
        'vesting_calculator' =>
            ['ownership_equity_workflow', 'move'],
        'contribution_balance_chart' =>
            ['partner_contribution_matrix', 'support'],

        'equity_split_simulator' =>
            ['equity_split_simulator', 'reuse'],
        'cap_table_builder' =>
            ['final_share_register', 'adapt'],
        'voting_power_calculator' =>
            ['ownership_equity_workflow', 'support'],
        'share_value_calculator' =>
            ['share_allocation_analysis', 'support'],
        'future_dilution_simulator' =>
            ['equity_split_simulator', 'support'],
        'ownership_chart' =>
            ['final_share_register', 'support'],
    ];

    $actual = [];

    foreach ([
        'capital',
        'contribution',
        'ownership',
    ] as $domainKey) {
        foreach (
            $registry->forDomain($domainKey)
            as $componentKey => $component
        ) {
            foreach (
                $component['legacy_mappings']
                as $mapping
            ) {
                $actual[$mapping['tool_key']] = [
                    $componentKey,
                    $mapping['treatment'],
                ];
            }
        }
    }

    ksort($expected);
    ksort($actual);

    expect($actual)
        ->toBe($expected)
        ->and($actual)
        ->toHaveCount(18)
        ->and(
            array_key_exists(
                'role_responsibility_matrix',
                $actual
            )
        )
        ->toBeFalse();
});

test('chapter one to three dependency chain stays capital then contribution then ownership', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'partner_contributions_workflow'
        )['reads_from']
    )->toBe([
        'capital',
    ]);

    expect(
        $registry->component(
            'ownership_equity_workflow'
        )['reads_from']
    )->toBe([
        'contribution',
    ]);

    expect(
        $registry->component(
            'final_share_register'
        )['reads_from']
    )->toBe([
        'contribution',
    ]);

    expect(
        $registry->component(
            'accepted_contribution_register'
        )['reads_from']
    )->toBe([]);
});
