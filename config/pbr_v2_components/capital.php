<?php

return [

    'funding_gap_calculator' => [

        'domain_key' => 'capital',

        'component_type' => 'analysis',

        'operating_purpose' =>
            'Calculate the gap between required capital and available funding.',

        'canonical_capability' => 'none',

        'approval_required' => false,

        'can_create_proposal' => true,

        'publish_authority' => 'none',

        'write_mode' => 'none',

        'can_create_actions' => false,

        'can_trigger_review' => true,

        'source_policy' =>
            'own_working_plus_approved_upstream',

        'reads_from' => [],

        'cross_domain_write' => false,

        'stale_source_guard' => true,

        'legacy_mappings' => [
            [
                'tool_key' =>
                    'funding_gap_calculator',

                'treatment' => 'reuse',
            ],
        ],

        'status' => 'support',

    ],

];
