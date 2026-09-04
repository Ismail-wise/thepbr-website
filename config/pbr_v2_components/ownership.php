<?php

return [

    'equity_split_simulator' => [

        'domain_key' => 'ownership',

        'component_type' => 'scenario',

        'operating_purpose' =>
            'Compare possible ownership structures before a formal proposal.',

        'canonical_capability' => 'none',

        'approval_required' => false,

        'can_create_proposal' => true,

        'publish_authority' => 'none',

        'write_mode' => 'none',

        'can_create_actions' => false,

        'can_trigger_review' => false,

        'source_policy' =>
            'own_working_plus_approved_upstream',

        'reads_from' => [
            'contribution',
        ],

        'cross_domain_write' => false,

        'stale_source_guard' => true,

        'legacy_mappings' => [
            [
                'tool_key' =>
                    'equity_split_simulator',

                'treatment' => 'reuse',
            ],
        ],

        'status' => 'support',

    ],

    'final_share_register' => [

        'domain_key' => 'ownership',

        'component_type' => 'register',

        'operating_purpose' =>
            'Maintain the approved current ownership source of truth.',

        'canonical_capability' => 'register',

        'approval_required' => true,

        'can_create_proposal' => true,

        'publish_authority' =>
            'approval_engine_only',

        'write_mode' =>
            'revisioned_current',

        'can_create_actions' => true,

        'can_trigger_review' => true,

        'source_policy' =>
            'approved_upstream_only',

        'reads_from' => [
            'contribution',
        ],

        'cross_domain_write' => false,

        'stale_source_guard' => true,

        'legacy_mappings' => [
            [
                'tool_key' =>
                    'cap_table_builder',

                'treatment' => 'adapt',
            ],
        ],

        'status' => 'active',

    ],

];
