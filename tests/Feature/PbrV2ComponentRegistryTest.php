<?php

use App\Services\PbrTools\PbrV2ComponentRegistryService;

test('v2 component registry validates five representative pilot components', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect($registry->keys())->toBe([
        'funding_gap_calculator',
        'equity_split_simulator',
        'final_share_register',
        'cashflow_dashboard',
        'meeting_decision_record',
    ]);

    expect($registry->all())->toHaveCount(5);
});

test('v2 pilot components keep legacy reuse explicit and separate from v2 identity', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    expect(
        $registry->component(
            'final_share_register'
        )['legacy_tool_key']
    )->toBe('cap_table_builder');

    expect(
        $registry->component(
            'meeting_decision_record'
        )['legacy_tool_key']
    )->toBe('meeting_decision_log');

    expect(
        $registry->component(
            'final_share_register'
        )['component_key']
    )->toBe('final_share_register');
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
        'meeting_decision_record',
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

        expect($component['cross_domain_write'])
            ->toBeFalse();
    }
});

test('scenario cannot be given canonical authority', function () {
    $components = config(
        'pbr_v2_components.components'
    );

    $components[
        'equity_split_simulator'
    ]['canonical_capability'] = 'register';

    config([
        'pbr_v2_components.components' =>
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
        'pbr_v2_components.components'
    );

    $components[
        'final_share_register'
    ]['approval_required'] = false;

    config([
        'pbr_v2_components.components' =>
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
        'pbr_v2_components.components'
    );

    $components[
        'cashflow_dashboard'
    ]['cross_domain_write'] = true;

    config([
        'pbr_v2_components.components' =>
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
        'pbr_v2_components.components'
    );

    $components[
        'equity_split_simulator'
    ]['reads_from'] = [
        'financial_controls',
    ];

    config([
        'pbr_v2_components.components' =>
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
        'pbr_v2_components.components'
    );

    $components[
        'equity_split_simulator'
    ]['stale_source_guard'] = false;

    config([
        'pbr_v2_components.components' =>
            $components,
    ]);

    expect(
        fn () => app(
            PbrV2ComponentRegistryService::class
        )->all()
    )->toThrow(\LogicException::class);
});
