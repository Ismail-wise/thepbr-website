<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('chapters eight to ten expose the exact approved component identities', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        array_keys(
            $registry->forDomain('risk')
        )
    )->toBe([
        'risk_protection_workflow',
        'risk_assessment',
        'risk_register',
        'protection_plan',
        'insurance_coverage_analysis',
        'protection_control_rule',
        'incident_record',
        'risk_action_plan',
        'risk_monitor',
    ]);

    expect(
        array_keys(
            $registry->forDomain('exit')
        )
    )->toBe([
        'exit_buyout_workflow',
        'exit_trigger_assessment',
        'buyout_valuation_analysis',
        'buyout_calculator',
        'exit_buyout_rule',
        'approved_exit_plan',
        'exit_settlement_record',
        'exit_action_plan',
    ]);

    expect(
        array_keys(
            $registry->forDomain('continuity')
        )
    )->toBe([
        'continuity_succession_workflow',
        'key_person_dependency_assessment',
        'continuity_plan',
        'emergency_authority_rule',
        'successor_register',
        'continuity_test_record',
        'continuity_action_plan',
        'continuity_monitor',
    ]);
});

test('chapters eight to ten contain exactly twenty five approved component contracts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->forDomain('risk')
    )->toHaveCount(9);

    expect(
        $registry->forDomain('exit')
    )->toHaveCount(8);

    expect(
        $registry->forDomain('continuity')
    )->toHaveCount(8);

    $total =
        count($registry->forDomain('risk'))
        + count($registry->forDomain('exit'))
        + count($registry->forDomain('continuity'));

    expect($total)->toBe(25);
});

test('chapters eight to ten canonical outputs require approval engine publication', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $canonical = [
        'risk_register' => 'register',
        'protection_plan' => 'plan',
        'protection_control_rule' => 'rule',

        'exit_buyout_rule' => 'rule',
        'approved_exit_plan' => 'plan',

        'continuity_plan' => 'plan',
        'emergency_authority_rule' => 'rule',
        'successor_register' => 'register',
    ];

    expect($canonical)->toHaveCount(8);

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

test('risk exit and continuity keep their approved dependency boundaries', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'risk_protection_workflow'
        )['reads_from']
    )->toBe([
        'governance',
        'operations',
        'financial_controls',
    ]);

    expect(
        $registry->component(
            'exit_buyout_workflow'
        )['reads_from']
    )->toBe([
        'ownership',
        'governance',
        'operations',
        'financial_controls',
    ]);

    expect(
        $registry->component(
            'continuity_succession_workflow'
        )['reads_from']
    )->toBe([
        'ownership',
        'governance',
        'operations',
        'financial_controls',
        'exit',
    ]);
});

test('insurance and buyout legacy calculators remain non canonical analysis capabilities', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'insurance_coverage_analysis',
        'buyout_valuation_analysis',
        'buyout_calculator',
    ] as $key) {
        $component = $registry->component($key);

        expect($component['component_type'])
            ->toBe('analysis')
            ->and($component['canonical_capability'])
            ->toBe('none')
            ->and($component['publish_authority'])
            ->toBe('none')
            ->and($component['cross_domain_write'])
            ->toBeFalse();
    }
});

test('moved continuity and insurance capabilities do not bypass v2 approval authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $insurance = $registry->component(
        'insurance_coverage_analysis'
    );

    expect(
        $insurance['legacy_mappings']
    )->toBe([
        [
            'tool_key' =>
                'insurance_coverage_gap_calculator',
            'treatment' =>
                'move',
        ],
    ]);

    expect($insurance['canonical_capability'])
        ->toBe('none')
        ->and($insurance['publish_authority'])
        ->toBe('none');

    $continuity = $registry->component(
        'continuity_plan'
    );

    expect(
        $continuity['legacy_mappings']
    )->toBe([
        [
            'tool_key' =>
                'business_continuity_planner',
            'treatment' =>
                'move',
        ],
    ]);

    expect($continuity['approval_required'])
        ->toBeTrue()
        ->and($continuity['publish_authority'])
        ->toBe('approval_engine_only')
        ->and($continuity['write_mode'])
        ->toBe('revisioned_current');
});

test('chapters eight to ten reconcile exactly twelve legacy tools', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $expected = [
        'insurance_coverage_gap_calculator' =>
            ['insurance_coverage_analysis', 'move'],

        'partner_buyout_calculator' =>
            ['buyout_calculator', 'reuse'],
        'exit_value_simulator' =>
            ['buyout_valuation_analysis', 'support'],
        'notice_period_planner' =>
            ['exit_buyout_workflow', 'support'],
        'exit_timeline' =>
            ['exit_buyout_workflow', 'support'],
        'responsibility_handover_checklist' =>
            ['exit_action_plan', 'support'],

        'business_continuity_planner' =>
            ['continuity_plan', 'move'],
        'key_person_dependency_map' =>
            ['key_person_dependency_assessment', 'adapt'],
        'succession_planner' =>
            ['successor_register', 'adapt'],
        'emergency_authority_planner' =>
            ['emergency_authority_rule', 'adapt'],
        'ownership_transition_simulator' =>
            ['continuity_succession_workflow', 'support'],
        'continuity_checklist' =>
            ['continuity_monitor', 'support'],
    ];

    $actual = [];

    foreach ([
        'risk',
        'exit',
        'continuity',
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
        ->toHaveCount(12);
});

test('approved legacy moves remain assigned to their v2 domains', function () {
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
                $component['domain_key'],
                $mapping['treatment'],
            ];
        }
    }

    expect(
        $assignments['business_continuity_planner']
        ?? null
    )->toBe([
        'continuity_plan',
        'continuity',
        'move',
    ]);

    expect(
        $assignments['insurance_coverage_gap_calculator']
        ?? null
    )->toBe([
        'insurance_coverage_analysis',
        'risk',
        'move',
    ]);
});

test('chapter eight to ten records actions and monitors remain outside canonical authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'incident_record',
        'exit_settlement_record',
        'continuity_test_record',
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
        'risk_action_plan',
        'exit_action_plan',
        'continuity_action_plan',
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

    foreach ([
        'risk_monitor',
        'continuity_monitor',
    ] as $key) {
        $component = $registry->component($key);

        expect($component['component_type'])
            ->toBe('monitor')
            ->and($component['canonical_capability'])
            ->toBe('none')
            ->and($component['publish_authority'])
            ->toBe('none')
            ->and($component['can_create_proposal'])
            ->toBeFalse()
            ->and($component['can_create_actions'])
            ->toBeTrue()
            ->and($component['can_trigger_review'])
            ->toBeTrue();
    }
});
