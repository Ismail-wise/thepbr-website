<?php

return [

    'governance_decision_record' => [

        'domain_key' => 'governance',

        'component_type' => 'record',

        'operating_purpose' =>
            'Preserve completed governance decisions as append-only operating history.',

        'canonical_capability' => 'none',

        'approval_required' => false,

        'can_create_proposal' => false,

        'publish_authority' => 'none',

        'write_mode' =>
            'append_only_record',

        'can_create_actions' => true,

        'can_trigger_review' => true,

        'source_policy' =>
            'own_working_plus_approved_upstream',

        'reads_from' => [],

        'cross_domain_write' => false,

        'stale_source_guard' => false,

        'legacy_mappings' => [
            [
                'tool_key' =>
                    'meeting_decision_log',

                'treatment' => 'adapt',
            ],
        ],

        'status' => 'active',

    ],

];
