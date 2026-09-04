<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('chapters four to seven expose the exact approved component identities', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        array_keys(
            $registry->forDomain('governance')
        )
    )->toBe([
        'governance_decision_workflow',
        'decision_rights_register',
        'reserved_matters_rule',
        'voting_quorum_rule',
        'governance_decision_record',
        'governance_action_plan',
        'governance_review_monitor',
    ]);

    expect(
        array_keys(
            $registry->forDomain('operations')
        )
    )->toBe([
        'operations_role_workflow',
        'role_assignment_analysis',
        'role_owner_register',
        'delegation_escalation_rule',
        'operations_decision_record',
        'operations_action_plan',
        'operations_review_monitor',
    ]);

    expect(
        array_keys(
            $registry->forDomain('financial_controls')
        )
    )->toBe([
        'financial_control_workflow',
        'financial_position_analysis',
        'cashflow_dashboard',
        'payment_approval_rule',
        'bank_access_register',
        'reconciliation_close_plan',
        'tax_audit_compliance_plan',
        'financial_exception_record',
        'financial_control_action_plan',
    ]);

    expect(
        array_keys(
            $registry->forDomain('distribution')
        )
    )->toBe([
        'distribution_workflow',
        'distributable_profit_analysis',
        'profit_distribution_calculator',
        'partner_compensation_rule',
        'profit_distribution_rule',
        'approved_distribution_plan',
        'distribution_payment_record',
        'distribution_action_plan',
    ]);
});

test('chapters four to seven contain exactly thirty one approved component contracts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->forDomain('governance')
    )->toHaveCount(7);

    expect(
        $registry->forDomain('operations')
    )->toHaveCount(7);

    expect(
        $registry->forDomain('financial_controls')
    )->toHaveCount(9);

    expect(
        $registry->forDomain('distribution')
    )->toHaveCount(8);

    $total =
        count($registry->forDomain('governance'))
        + count($registry->forDomain('operations'))
        + count($registry->forDomain('financial_controls'))
        + count($registry->forDomain('distribution'));

    expect($total)->toBe(31);
});

test('chapters four to seven canonical outputs require approval engine publication', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $canonical = [
        'decision_rights_register' => 'register',
        'reserved_matters_rule' => 'rule',
        'voting_quorum_rule' => 'rule',

        'role_owner_register' => 'register',
        'delegation_escalation_rule' => 'rule',

        'payment_approval_rule' => 'rule',
        'bank_access_register' => 'register',
        'reconciliation_close_plan' => 'plan',
        'tax_audit_compliance_plan' => 'plan',

        'partner_compensation_rule' => 'rule',
        'profit_distribution_rule' => 'rule',
        'approved_distribution_plan' => 'plan',
    ];

    foreach ($canonical as $key => $capability) {
        $component = $registry->component($key);

        expect($component['canonical_capability'])
            ->toBe($capability)
            ->and($component['approval_required'])
            ->toBeTrue()
            ->and($component['can_create_proposal'])
            ->toBeTrue()
            ->and($component['publish_authority'])
            ->toBe('approval_engine_only')
            ->and($component['write_mode'])
            ->toBe('revisioned_current')
            ->and($component['cross_domain_write'])
            ->toBeFalse()
            ->and($component['stale_source_guard'])
            ->toBeTrue();
    }
});

test('governance operations finance and distribution keep their approved dependency boundaries', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'governance_decision_workflow'
        )['reads_from']
    )->toBe([
        'ownership',
    ]);

    expect(
        $registry->component(
            'operations_role_workflow'
        )['reads_from']
    )->toBe([
        'governance',
    ]);

    expect(
        $registry->component(
            'financial_control_workflow'
        )['reads_from']
    )->toBe([
        'capital',
        'governance',
        'operations',
    ]);

    expect(
        $registry->component(
            'distribution_workflow'
        )['reads_from']
    )->toBe([
        'ownership',
        'governance',
        'operations',
        'financial_controls',
    ]);
});

test('cashflow dashboard remains monitoring and profit calculator remains scenario only', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $cashflow = $registry->component(
        'cashflow_dashboard'
    );

    expect($cashflow['component_type'])
        ->toBe('monitor')
        ->and($cashflow['canonical_capability'])
        ->toBe('none')
        ->and($cashflow['publish_authority'])
        ->toBe('none')
        ->and($cashflow['can_create_proposal'])
        ->toBeFalse()
        ->and($cashflow['can_create_actions'])
        ->toBeTrue()
        ->and($cashflow['can_trigger_review'])
        ->toBeTrue();

    $calculator = $registry->component(
        'profit_distribution_calculator'
    );

    expect($calculator['component_type'])
        ->toBe('scenario')
        ->and($calculator['canonical_capability'])
        ->toBe('none')
        ->and($calculator['publish_authority'])
        ->toBe('none')
        ->and($calculator['can_create_actions'])
        ->toBeFalse()
        ->and($calculator['can_trigger_review'])
        ->toBeFalse();
});

test('operations receives the approved moved role tools without creating role authority directly', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $analysis = $registry->component(
        'role_assignment_analysis'
    );

    expect($analysis['component_type'])
        ->toBe('analysis')
        ->and($analysis['canonical_capability'])
        ->toBe('none')
        ->and($analysis['legacy_mappings'])
        ->toBe([
            [
                'tool_key' =>
                    'role_responsibility_matrix',
                'treatment' =>
                    'move',
            ],
            [
                'tool_key' =>
                    'partner_role_matrix',
                'treatment' =>
                    'move',
            ],
        ]);

    $register = $registry->component(
        'role_owner_register'
    );

    expect($register['component_type'])
        ->toBe('register')
        ->and($register['canonical_capability'])
        ->toBe('register')
        ->and($register['legacy_mappings'])
        ->toBe([]);
});

test('chapters four to seven reconcile twenty legacy tools and defer deadlock detector', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $expected = [
        'decision_rights_matrix' =>
            ['decision_rights_register', 'adapt'],
        'authority_level_builder' =>
            ['decision_rights_register', 'adapt'],
        'voting_simulator' =>
            ['voting_quorum_rule', 'support'],
        'meeting_decision_log' =>
            ['governance_decision_record', 'adapt'],
        'governance_structure_chart' =>
            ['decision_rights_register', 'support'],

        'role_responsibility_matrix' =>
            ['role_assignment_analysis', 'move'],
        'partner_role_matrix' =>
            ['role_assignment_analysis', 'move'],

        'financial_control_checklist' =>
            ['financial_control_workflow', 'support'],
        'monthly_budget_planner' =>
            ['financial_position_analysis', 'support'],
        'budget_actual_chart' =>
            ['financial_position_analysis', 'support'],
        'cashflow_dashboard' =>
            ['cashflow_dashboard', 'reuse'],
        'expense_approval_matrix' =>
            ['payment_approval_rule', 'adapt'],
        'large_payment_approval_rules' =>
            ['payment_approval_rule', 'adapt'],
        'bank_authority_matrix' =>
            ['bank_access_register', 'adapt'],

        'retained_earnings_calculator' =>
            ['distributable_profit_analysis', 'support'],
        'reserve_fund_planner' =>
            ['distributable_profit_analysis', 'support'],
        'profit_distribution_calculator' =>
            ['profit_distribution_calculator', 'adapt'],
        'loss_sharing_simulator' =>
            ['profit_distribution_calculator', 'support'],
        'distribution_scenario_comparison' =>
            ['profit_distribution_calculator', 'support'],
        'salary_profit_share_planner' =>
            ['partner_compensation_rule', 'adapt'],
    ];

    $actual = [];

    foreach ([
        'governance',
        'operations',
        'financial_controls',
        'distribution',
    ] as $domain) {
        foreach (
            $registry->forDomain($domain)
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
        ->toHaveCount(20)
        ->and(
            array_key_exists(
                'deadlock_detector',
                $actual
            )
        )
        ->toBeFalse();
});

test('operations role move remains assigned while deadlock remains deferred', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $assignments = [];

    foreach (
        $registry->all()
        as $componentKey => $component
    ) {
        foreach (
            $component['legacy_mappings']
            as $mapping
        ) {
            $assignments[$mapping['tool_key']] = [
                $componentKey,
                $mapping['treatment'],
            ];
        }
    }

    expect(
        $assignments['role_responsibility_matrix']
        ?? null
    )->toBe([
        'role_assignment_analysis',
        'move',
    ]);

    expect(
        array_key_exists(
            'deadlock_detector',
            $assignments
        )
    )->toBeFalse();
});

test('chapter four to seven records and actions remain non canonical execution history', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'governance_decision_record',
        'operations_decision_record',
        'financial_exception_record',
        'distribution_payment_record',
    ] as $key) {
        $component = $registry->component($key);

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
        'governance_action_plan',
        'operations_action_plan',
        'financial_control_action_plan',
        'distribution_action_plan',
    ] as $key) {
        $component = $registry->component($key);

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
