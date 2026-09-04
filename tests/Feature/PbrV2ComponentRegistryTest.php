<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('v2 component registry preserves representative foundation components', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'funding_gap_calculator',
        'equity_split_simulator',
        'final_share_register',
        'cashflow_dashboard',
        'governance_decision_record',
    ] as $componentKey) {
        expect(
            $registry->has($componentKey)
        )->toBeTrue();
    }
});

test('v2 component registry exposes explicit populated and deferred domain metadata', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect($registry->domainSources())->toBe([
        'capital' =>
            'pbr_v2_components.capital',

        'contribution' =>
            'pbr_v2_components.contribution',

        'ownership' =>
            'pbr_v2_components.ownership',

        'financial_controls' =>
            'pbr_v2_components.financial_controls',

        'governance' =>
            'pbr_v2_components.governance',

        'operations' =>
            'pbr_v2_components.operations',

        'distribution' =>
            'pbr_v2_components.distribution',
    ]);

    expect($registry->deferredDomains())
        ->toBe([
            'dissolution',
        ]);
});

test('v2 pilot components keep legacy reuse explicit and separate from v2 identity', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'final_share_register'
        )['legacy_mappings']
    )->toBe([
        [
            'tool_key' =>
                'cap_table_builder',
            'treatment' =>
                'adapt',
        ],
        [
            'tool_key' =>
                'ownership_chart',
            'treatment' =>
                'support',
        ],
    ]);

    expect(
        $registry->component(
            'governance_decision_record'
        )['legacy_mappings']
    )->toBe([
        [
            'tool_key' =>
                'meeting_decision_log',
            'treatment' =>
                'adapt',
        ],
    ]);

    expect(
        $registry->component(
            'final_share_register'
        )['component_key']
    )->toBe('final_share_register');
});

test('pilot governance identity is normalized to the approved component key', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->has(
            'meeting_decision_record'
        )
    )->toBeFalse();

    expect(
        $registry->has(
            'governance_decision_record'
        )
    )->toBeTrue();
});

test('canonical v2 register requires approval engine publication and revisioned state', function () {
    $register = app(
        PbrV2ComponentRegistryService::class
    )->component(
        'final_share_register'
    );

    expect($register['component_type'])
        ->toBe('register')
        ->and($register['canonical_capability'])
        ->toBe('register')
        ->and($register['approval_required'])
        ->toBeTrue()
        ->and($register['publish_authority'])
        ->toBe('approval_engine_only')
        ->and($register['write_mode'])
        ->toBe('revisioned_current')
        ->and($register['stale_source_guard'])
        ->toBeTrue();
});

test('non canonical pilot components cannot publish current state', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ([
        'funding_gap_calculator',
        'equity_split_simulator',
        'cashflow_dashboard',
        'governance_decision_record',
    ] as $componentKey) {
        $component = $registry->component(
            $componentKey
        );

        expect($component['canonical_capability'])
            ->toBe('none')
            ->and($component['publish_authority'])
            ->toBe('none');
    }
});

test('pilot component dependencies remain inside approved v2 domain contracts', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    foreach ($registry->all() as $component) {
        $allowed = config(
            'pbr_canonical_data.domains_v2.'
            .$component['domain_key']
            .'.reads_from',
            []
        );

        foreach (
            $component['reads_from']
            as $dependency
        ) {
            expect(
                in_array(
                    $dependency,
                    $allowed,
                    true
                )
            )->toBeTrue();
        }

        expect(
            $component['cross_domain_write']
        )->toBeFalse();
    }
});

test('scenario cannot be given canonical authority', function () {
    $components = config(
        'pbr_v2_components.ownership'
    );

    $components[
        'equity_split_simulator'
    ]['canonical_capability'] =
        'register';

    config([
        'pbr_v2_components.ownership' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('canonical register cannot bypass explicit approval', function () {
    $components = config(
        'pbr_v2_components.ownership'
    );

    $components[
        'final_share_register'
    ]['approval_required'] = false;

    config([
        'pbr_v2_components.ownership' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('normal v2 component cannot write directly across domains', function () {
    $components = config(
        'pbr_v2_components.financial_controls'
    );

    $components[
        'cashflow_dashboard'
    ]['cross_domain_write'] = true;

    config([
        'pbr_v2_components.financial_controls' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('v2 component cannot read outside its approved domain dependencies', function () {
    $components = config(
        'pbr_v2_components.ownership'
    );

    $components[
        'equity_split_simulator'
    ]['reads_from'] = [
        'financial_controls',
    ];

    config([
        'pbr_v2_components.ownership' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('proposal capable component cannot disable stale source protection', function () {
    $components = config(
        'pbr_v2_components.ownership'
    );

    $components[
        'equity_split_simulator'
    ]['stale_source_guard'] = false;

    config([
        'pbr_v2_components.ownership' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('legacy tool cannot be assigned to more than one v2 component', function () {
    $components = config(
        'pbr_v2_components.financial_controls'
    );

    $components[
        'cashflow_dashboard'
    ]['legacy_mappings'] = [
        [
            'tool_key' =>
                'funding_gap_calculator',
            'treatment' =>
                'reuse',
        ],
    ];

    config([
        'pbr_v2_components.financial_controls' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('component domain must match the domain source that contains it', function () {
    $components = config(
        'pbr_v2_components.capital'
    );

    $components[
        'funding_gap_calculator'
    ]['domain_key'] =
        'contribution';

    $components[
        'funding_gap_calculator'
    ]['reads_from'] = [
        'capital',
    ];

    config([
        'pbr_v2_components.capital' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});

test('legacy mapping treatment must use the approved reconciliation vocabulary', function () {
    $components = config(
        'pbr_v2_components.capital'
    );

    $components[
        'funding_gap_calculator'
    ]['legacy_mappings'][0][
        'treatment'
    ] = 'copy';

    config([
        'pbr_v2_components.capital' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});
