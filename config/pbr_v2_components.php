<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBR OS V2 Component Contract
    |--------------------------------------------------------------------------
    |
    | V2 component identity and authority stay separate from the V1 tool
    | catalog. Domain component definitions are loaded from nested config
    | sources and exposed as one validated registry by the registry service.
    |
    */

    'version' => 'pbr-v2-component-contract-v2',

    'allowed' => [

        'component_types' => [
            'rule',
            'register',
            'plan',
            'workflow',
            'record',
            'scenario',
            'analysis',
            'monitor',
            'action',
            'assessment',
        ],

        'canonical_capabilities' => [
            'none',
            'rule',
            'register',
            'plan',
        ],

        'publish_authorities' => [
            'none',
            'approval_engine_only',
        ],

        'write_modes' => [
            'none',
            'revisioned_current',
            'append_only_record',
            'action_state',
        ],

        'source_policies' => [
            'approved_upstream_only',
            'own_working_plus_approved_upstream',
        ],

        'statuses' => [
            'active',
            'support',
            'legacy',
            'retired',
        ],

        'legacy_treatments' => [
            'reuse',
            'adapt',
            'move',
            'support',
            'legacy',
            'retire',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Populated Domain Sources
    |--------------------------------------------------------------------------
    |
    | Approved domains are populated batch by batch during F4 registry
    | population while the V1 operating-tool catalog remains untouched.
    |
    */

    'domain_sources' => [
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

        'risk' =>
            'pbr_v2_components.risk',

        'exit' =>
            'pbr_v2_components.exit',

        'continuity' =>
            'pbr_v2_components.continuity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicitly Deferred Domains
    |--------------------------------------------------------------------------
    |
    | Dissolution is an approved V2 domain, but its final component contract
    | is intentionally deferred until the dedicated Chapter 14 design.
    |
    */

    'deferred_domains' => [
        'dissolution',
    ],

];
