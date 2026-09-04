<?php

return [

    'cashflow_dashboard' => [

        'domain_key' =>
            'financial_controls',

        'component_type' => 'monitor',

        'operating_purpose' =>
            'Monitor cash position, cash movement and runway without creating financial authority.',

        'canonical_capability' => 'none',

        'approval_required' => false,

        'can_create_proposal' => false,

        'publish_authority' => 'none',

        'write_mode' => 'none',

        'can_create_actions' => true,

        'can_trigger_review' => true,

        'source_policy' =>
            'own_working_plus_approved_upstream',

        'reads_from' => [
            'capital',
        ],

        'cross_domain_write' => false,

        'stale_source_guard' => false,

        'legacy_mappings' => [
            [
                'tool_key' =>
                    'cashflow_dashboard',

                'treatment' => 'reuse',
            ],
        ],

        'status' => 'support',

    ],

];
