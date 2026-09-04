<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('chapters eleven to thirteen expose the exact approved component identities', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        array_keys(
            $registry->forDomain('share_transfer')
        )
    )->toBe([
        'share_transfer_workflow',
        'transfer_eligibility_assessment',
        'transfer_scenario_simulator',
        'share_transfer_rule',
        'new_partner_onboarding_plan',
        'share_transfer_record',
        'transfer_action_plan',
        'transfer_review_monitor',
    ]);

    expect(
        array_keys(
            $registry->forDomain('dispute_resolution')
        )
    )->toBe([
        'dispute_resolution_workflow',
        'dispute_intake_assessment',
        'deadlock_analysis',
        'dispute_resolution_rule',
        'dispute_case_register',
        'resolution_plan',
        'resolution_record',
        'resolution_action_plan',
        'dispute_review_monitor',
    ]);

    expect(
        array_keys(
            $registry->forDomain('implementation_review')
        )
    )->toBe([
        'implementation_review_workflow',
        'implementation_readiness_assessment',
        'implementation_plan',
        'review_schedule_rule',
        'implementation_status_monitor',
        'review_record',
        'implementation_action_plan',
        'amendment_control_workflow',
    ]);
});

test('chapters eleven to thirteen contain exactly twenty five approved component contracts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->forDomain('share_transfer')
    )->toHaveCount(8);

    expect(
        $registry->forDomain('dispute_resolution')
    )->toHaveCount(9);

    expect(
        $registry->forDomain('implementation_review')
    )->toHaveCount(8);

    $batchTotal =
        count($registry->forDomain('share_transfer'))
        + count($registry->forDomain('dispute_resolution'))
        + count($registry->forDomain('implementation_review'));

    expect($batchTotal)->toBe(25);

    $chapterOneToThirteenTotal = 0;

    foreach ([
        'capital',
        'contribution',
        'ownership',
        'governance',
        'operations',
        'financial_controls',
        'distribution',
        'risk',
        'exit',
        'continuity',
        'share_transfer',
        'dispute_resolution',
        'implementation_review',
    ] as $domain) {
        $chapterOneToThirteenTotal += count(
            $registry->forDomain($domain)
        );
    }

    expect(
        $chapterOneToThirteenTotal
    )->toBe(107);
});

test('chapters eleven to thirteen canonical outputs require approval engine publication', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $canonical = [
        'share_transfer_rule' => 'rule',
        'new_partner_onboarding_plan' => 'plan',

        'dispute_resolution_rule' => 'rule',
        'dispute_case_register' => 'register',
        'resolution_plan' => 'plan',

        'implementation_plan' => 'plan',
        'review_schedule_rule' => 'rule',
    ];

    expect($canonical)->toHaveCount(7);

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

test('share transfer dispute and implementation keep their approved dependency boundaries', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'share_transfer_workflow'
        )['reads_from']
    )->toBe([
        'ownership',
        'governance',
        'exit',
    ]);

    expect(
        $registry->component(
            'dispute_resolution_workflow'
        )['reads_from']
    )->toBe([
        'governance',
    ]);

    expect(
        $registry->component(
            'implementation_review_workflow'
        )['reads_from']
    )->toBe([
        'capital',
        'contribution',
        'ownership',
        'governance',
        'operations',
        'financial_controls',
        'distribution',
        'risk',
        'exit',
        'continuity',
        'share_transfer',
        'dispute_resolution',
    ]);
});

test('share transfer scenario cannot become current ownership or transfer authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $scenario = $registry->component(
        'transfer_scenario_simulator'
    );

    expect($scenario['component_type'])
        ->toBe('scenario')
        ->and($scenario['canonical_capability'])
        ->toBe('none')
        ->and($scenario['publish_authority'])
        ->toBe('none')
        ->and($scenario['can_create_actions'])
        ->toBeFalse()
        ->and($scenario['can_trigger_review'])
        ->toBeFalse()
        ->and($scenario['cross_domain_write'])
        ->toBeFalse();
});

test('deadlock legacy capabilities move into analysis without gaining decision authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $analysis = $registry->component(
        'deadlock_analysis'
    );

    expect($analysis['component_type'])
        ->toBe('analysis')
        ->and($analysis['canonical_capability'])
        ->toBe('none')
        ->and($analysis['publish_authority'])
        ->toBe('none')
        ->and($analysis['legacy_mappings'])
        ->toBe([
            [
                'tool_key' => 'deadlock_detector',
                'treatment' => 'move',
            ],
            [
                'tool_key' => 'deadlock_decision_tool',
                'treatment' => 'adapt',
            ],
        ]);
});

test('chapter thirteen can coordinate review but cannot write across domains', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'implementation_review_workflow',
        'implementation_readiness_assessment',
        'implementation_plan',
        'review_schedule_rule',
        'implementation_status_monitor',
        'amendment_control_workflow',
    ] as $key) {
        expect(
            $registry->component($key)[
                'cross_domain_write'
            ]
        )->toBeFalse();
    }

    $monitor = $registry->component(
        'implementation_status_monitor'
    );

    expect($monitor['component_type'])
        ->toBe('monitor')
        ->and($monitor['canonical_capability'])
        ->toBe('none')
        ->and($monitor['publish_authority'])
        ->toBe('none')
        ->and($monitor['can_create_actions'])
        ->toBeTrue()
        ->and($monitor['can_trigger_review'])
        ->toBeTrue();
});

test('chapters eleven and twelve reconcile exactly the final fourteen legacy tools', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $expected = [
        'share_transfer_simulator' =>
            ['transfer_scenario_simulator', 'reuse'],
        'ownership_before_after_chart' =>
            ['transfer_scenario_simulator', 'support'],
        'first_refusal_workflow' =>
            ['share_transfer_workflow', 'adapt'],
        'transfer_approval_matrix' =>
            ['share_transfer_rule', 'adapt'],
        'share_valuation_calculator' =>
            ['transfer_scenario_simulator', 'support'],
        'transfer_history_tracker' =>
            ['share_transfer_record', 'adapt'],

        'deadlock_detector' =>
            ['deadlock_analysis', 'move'],
        'conflict_escalation_ladder' =>
            ['dispute_resolution_rule', 'adapt'],
        'dispute_log' =>
            ['dispute_case_register', 'adapt'],
        'resolution_tracker' =>
            ['resolution_action_plan', 'adapt'],
        'deadlock_decision_tool' =>
            ['deadlock_analysis', 'adapt'],
        'issue_priority_matrix' =>
            ['dispute_intake_assessment', 'support'],
        'decision_history' =>
            ['resolution_record', 'adapt'],
        'escalation_timeline' =>
            ['dispute_resolution_workflow', 'support'],
    ];

    $actual = [];

    foreach ([
        'share_transfer',
        'dispute_resolution',
        'implementation_review',
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
        ->toHaveCount(14);
});

test('all sixty four v1 legacy tools have exactly one v2 primary mapping', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $chapters = config(
        'pbr_course.chapters',
        []
    );

    $legacy = [];

    foreach ($chapters as $chapter) {
        foreach (($chapter['tools'] ?? []) as $tool) {
            if (isset($tool['key'])) {
                $legacy[$tool['key']] = true;
            }
        }
    }

    $mapped = [];

    foreach (
        $registry->all()
        as $componentKey => $component
    ) {
        foreach (
            $component['legacy_mappings']
            as $mapping
        ) {
            $toolKey = $mapping['tool_key'];

            expect(
                array_key_exists(
                    $toolKey,
                    $mapped
                )
            )->toBeFalse();

            $mapped[$toolKey] = [
                $componentKey,
                $component['domain_key'],
                $mapping['treatment'],
            ];
        }
    }

    ksort($legacy);
    ksort($mapped);

    expect($legacy)
        ->toHaveCount(64);

    expect($mapped)
        ->toHaveCount(64);

    expect(
        array_keys($mapped)
    )->toBe(
        array_keys($legacy)
    );
});

test('final legacy reconciliation treatment totals match the approved frozen inventory', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $totals = [
        'reuse' => 0,
        'adapt' => 0,
        'move' => 0,
        'support' => 0,
        'legacy' => 0,
        'retire' => 0,
    ];

    foreach (
        $registry->all()
        as $component
    ) {
        foreach (
            $component['legacy_mappings']
            as $mapping
        ) {
            $totals[$mapping['treatment']]++;
        }
    }

    expect($totals)->toBe([
        'reuse' => 6,
        'adapt' => 25,
        'move' => 7,
        'support' => 26,
        'legacy' => 0,
        'retire' => 0,
    ]);
});

test('chapter eleven to thirteen records actions and monitors remain outside canonical authority', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'share_transfer_record',
        'resolution_record',
        'review_record',
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
        'transfer_action_plan',
        'resolution_action_plan',
        'implementation_action_plan',
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
        'transfer_review_monitor',
        'dispute_review_monitor',
        'implementation_status_monitor',
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
