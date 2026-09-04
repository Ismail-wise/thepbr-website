<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;
use App\Services\PbrTools\PbrV2LifecycleService;

test('v2 lifecycle exposes the exact frozen canonical and action states', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    expect($lifecycle->states())
        ->toBe([
            'working_draft',
            'proposed',
            'approved_current',
            'superseded',
        ])
        ->and($lifecycle->actionStates())
        ->toBe([
            'open',
            'in_progress',
            'blocked',
            'completed',
            'superseded',
        ]);
});

test('all thirty two canonical components are lifecycle eligible', function () {
    $registry =
        app(PbrV2ComponentRegistryService::class);

    $lifecycle =
        app(PbrV2LifecycleService::class);

    $canonical = 0;
    $eligible = 0;

    foreach (
        $registry->all()
        as $key => $component
    ) {
        if (
            $component['canonical_capability']
            !== 'none'
        ) {
            $canonical++;
        }

        if (
            $lifecycle
                ->canEnterCanonicalLifecycle(
                    $key
                )
        ) {
            $eligible++;
        }
    }

    expect($canonical)
        ->toBe(32)
        ->and($eligible)
        ->toBe(32);
});

test('proposal capability remains separate from canonical lifecycle eligibility', function () {
    $registry =
        app(PbrV2ComponentRegistryService::class);

    $lifecycle =
        app(PbrV2LifecycleService::class);

    $proposalCapable = 0;
    $nonCanonicalProducers = 0;

    foreach (
        $registry->all()
        as $key => $component
    ) {
        if (
            $lifecycle->canCreateProposal(
                $key
            )
        ) {
            $proposalCapable++;

            if (
                ! $lifecycle
                    ->canEnterCanonicalLifecycle(
                        $key
                    )
            ) {
                $nonCanonicalProducers++;
            }
        }
    }

    expect($proposalCapable)
        ->toBe(73)
        ->and($nonCanonicalProducers)
        ->toBe(41);
});

test('canonical working draft may become a proposal without becoming current', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $decision =
        $lifecycle->transitionDecision(
            'share_transfer_rule',
            'working_draft',
            'proposed'
        );

    expect($decision['decision'])
        ->toBe('allowed')
        ->and($decision['authority'])
        ->toBe('none')
        ->and(
            $decision['publication_owner']
        )
        ->toBeNull()
        ->and($lifecycle->canTransition(
            'share_transfer_rule',
            'working_draft',
            'proposed'
        ))
        ->toBeTrue();
});

test('proposal may be returned to working draft for revision', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $decision =
        $lifecycle->transitionDecision(
            'share_transfer_rule',
            'proposed',
            'working_draft'
        );

    expect($decision['decision'])
        ->toBe('allowed')
        ->and($decision['reason'])
        ->toBe(
            'proposal_revision_allowed'
        );
});

test('proposal approval is reserved for f6 and canonical publication for f7', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $decision =
        $lifecycle->transitionDecision(
            'share_transfer_rule',
            'proposed',
            'approved_current'
        );

    expect($decision['decision'])
        ->toBe('reserved')
        ->and($decision['authority'])
        ->toBe('approval_engine')
        ->and(
            $decision['publication_owner']
        )
        ->toBe(
            'canonical_output_foundation'
        )
        ->and($lifecycle->canTransition(
            'share_transfer_rule',
            'proposed',
            'approved_current'
        ))
        ->toBeFalse()
        ->and(
            $lifecycle->transitionAuthority(
                'proposed',
                'approved_current'
            )
        )
        ->toBe('approval_engine');
});

test('current canonical state may only be superseded by f7 publication authority', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $decision =
        $lifecycle->transitionDecision(
            'share_transfer_rule',
            'approved_current',
            'superseded'
        );

    expect($decision['decision'])
        ->toBe('reserved')
        ->and($decision['authority'])
        ->toBe(
            'canonical_output_foundation'
        )
        ->and(
            $decision['publication_owner']
        )
        ->toBe(
            'canonical_output_foundation'
        );
});

test('approval bypass and mutation of current or historical state are forbidden', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $forbidden = [
        [
            'working_draft',
            'approved_current',
        ],
        [
            'working_draft',
            'superseded',
        ],
        [
            'proposed',
            'superseded',
        ],
        [
            'approved_current',
            'working_draft',
        ],
        [
            'approved_current',
            'proposed',
        ],
        [
            'superseded',
            'working_draft',
        ],
        [
            'superseded',
            'proposed',
        ],
        [
            'superseded',
            'approved_current',
        ],
    ];

    foreach ($forbidden as [$from, $to]) {
        expect(
            $lifecycle
                ->transitionDecision(
                    'share_transfer_rule',
                    $from,
                    $to
                )['decision']
        )->toBe('forbidden');
    }
});

test('same state transitions are rejected rather than treated as authority changes', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    foreach ($lifecycle->states() as $state) {
        $decision =
            $lifecycle
                ->transitionDecision(
                    'share_transfer_rule',
                    $state,
                    $state
                );

        expect($decision['decision'])
            ->toBe('forbidden')
            ->and($decision['reason'])
            ->toBe(
                'same_state_transition'
            );
    }
});

test('scenario analysis assessment and workflow producers never enter canonical lifecycle', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $examples = [
        'transfer_scenario_simulator',
        'deadlock_analysis',
        'transfer_eligibility_assessment',
        'share_transfer_workflow',
    ];

    foreach ($examples as $componentKey) {
        expect(
            $lifecycle
                ->canCreateProposal(
                    $componentKey
                )
        )->toBeTrue()
        ->and(
            $lifecycle
                ->canEnterCanonicalLifecycle(
                    $componentKey
                )
        )->toBeFalse()
        ->and(
            $lifecycle
                ->transitionDecision(
                    $componentKey,
                    'working_draft',
                    'proposed'
                )['decision']
        )->toBe('forbidden')
        ->and(
            $lifecycle
                ->transitionDecision(
                    $componentKey,
                    'scenario',
                    'approved_current'
                )['decision']
        )->toBe('forbidden');
    }
});

test('record action and monitor components remain outside canonical authority', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    foreach ([
        'share_transfer_record',
        'transfer_action_plan',
        'transfer_review_monitor',
    ] as $componentKey) {
        expect(
            $lifecycle
                ->canEnterCanonicalLifecycle(
                    $componentKey
                )
        )->toBeFalse()
        ->and(
            $lifecycle
                ->transitionDecision(
                    $componentKey,
                    'working_draft',
                    'approved_current'
                )['decision']
        )->toBe('forbidden');
    }
});

test('action lifecycle matrix matches the frozen execution contract', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $rules =
        $lifecycle->transitionRules();

    expect($rules['actions'])
        ->toBe([
            'open' => [
                'in_progress',
                'blocked',
                'completed',
                'superseded',
            ],

            'in_progress' => [
                'blocked',
                'completed',
                'superseded',
            ],

            'blocked' => [
                'in_progress',
                'completed',
                'superseded',
            ],

            'completed' => [],

            'superseded' => [],
        ]);
});

test('scenario and record artifact rules remain non canonical', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $artifacts =
        $lifecycle
            ->transitionRules()['artifacts'];

    expect(
        $artifacts['scenario']['canonical']
    )
        ->toBeFalse()
        ->and(
            $artifacts['scenario'][
                'may_inform_proposal'
            ]
        )
        ->toBeTrue()
        ->and(
            $artifacts['scenario'][
                'direct_current_publication'
            ]
        )
        ->toBeFalse()
        ->and(
            $artifacts['record']['canonical']
        )
        ->toBeFalse()
        ->and(
            $artifacts['record']['append_only']
        )
        ->toBeTrue()
        ->and(
            $artifacts['record']['terminal']
        )
        ->toBeTrue();
});

test('assert transition allowed rejects reserved approval and publication paths', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    expect(
        fn () =>
            $lifecycle
                ->assertTransitionAllowed(
                    'share_transfer_rule',
                    'proposed',
                    'approved_current'
                )
    )->toThrow(DomainException::class);

    expect(
        fn () =>
            $lifecycle
                ->assertTransitionAllowed(
                    'share_transfer_rule',
                    'approved_current',
                    'superseded'
                )
    )->toThrow(DomainException::class);
});

test('unknown canonical lifecycle states are rejected for canonical components', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    expect(
        fn () =>
            $lifecycle
                ->transitionDecision(
                    'share_transfer_rule',
                    'scenario',
                    'approved_current'
                )
    )->toThrow(
        InvalidArgumentException::class
    );
});

test('f5 service contains none of the reserved f6 f7 or f8 authority methods', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    foreach (
        config(
            'pbr_v2_lifecycle.forbidden_service_methods'
        )
        as $method
    ) {
        expect(
            method_exists(
                $lifecycle,
                $method
            )
        )->toBeFalse();
    }
});
