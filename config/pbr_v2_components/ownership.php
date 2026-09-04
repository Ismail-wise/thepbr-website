<?php

return [

    'ownership_equity_workflow' => [
        'domain_key' => 'ownership',
        'component_type' => 'workflow',
        'operating_purpose' =>
            'Coordinate approved contribution values into proposed ownership, rights, approval and final registration.',
        'canonical_capability' => 'none',
        'approval_required' => false,
        'can_create_proposal' => true,
        'publish_authority' => 'none',
        'write_mode' => 'none',
        'can_create_actions' => false,
        'can_trigger_review' => true,
        'source_policy' =>
            'own_working_plus_approved_upstream',
        'reads_from' => [
            'contribution',
        ],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [
            [
                'tool_key' => 'voting_power_calculator',
                'treatment' => 'support',
            ],
            [
                'tool_key' => 'vesting_calculator',
                'treatment' => 'move',
            ],
        ],
        'status' => 'active',
    ],

    'share_allocation_analysis' => [
        'domain_key' => 'ownership',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Analyse share allocation from accepted contribution values without publishing ownership.',
        'canonical_capability' => 'none',
        'approval_required' => false,
        'can_create_proposal' => true,
        'publish_authority' => 'none',
        'write_mode' => 'none',
        'can_create_actions' => false,
        'can_trigger_review' => true,
        'source_policy' =>
            'own_working_plus_approved_upstream',
        'reads_from' => [
            'contribution',
        ],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [
            [
                'tool_key' => 'share_value_calculator',
                'treatment' => 'support',
            ],
        ],
        'status' => 'support',
    ],

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
                'tool_key' => 'equity_split_simulator',
                'treatment' => 'reuse',
            ],
            [
                'tool_key' => 'future_dilution_simulator',
                'treatment' => 'support',
            ],
        ],
        'status' => 'support',
    ],

    'new_share_issuance_rule' => [
        'domain_key' => 'ownership',
        'component_type' => 'rule',
        'operating_purpose' =>
            'Define the approved rule governing future issuance of new ownership shares.',
        'canonical_capability' => 'rule',
        'approval_required' => true,
        'can_create_proposal' => true,
        'publish_authority' =>
            'approval_engine_only',
        'write_mode' => 'revisioned_current',
        'can_create_actions' => true,
        'can_trigger_review' => true,
        'source_policy' =>
            'approved_upstream_only',
        'reads_from' => [],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [],
        'status' => 'active',
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
        'write_mode' => 'revisioned_current',
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
                'tool_key' => 'cap_table_builder',
                'treatment' => 'adapt',
            ],
            [
                'tool_key' => 'ownership_chart',
                'treatment' => 'support',
            ],
        ],
        'status' => 'active',
    ],

    'ownership_decision_record' => [
        'domain_key' => 'ownership',
        'component_type' => 'record',
        'operating_purpose' =>
            'Preserve completed ownership decisions as append-only operating history.',
        'canonical_capability' => 'none',
        'approval_required' => false,
        'can_create_proposal' => false,
        'publish_authority' => 'none',
        'write_mode' => 'append_only_record',
        'can_create_actions' => true,
        'can_trigger_review' => true,
        'source_policy' =>
            'approved_upstream_only',
        'reads_from' => [],
        'cross_domain_write' => false,
        'stale_source_guard' => false,
        'legacy_mappings' => [],
        'status' => 'active',
    ],

    'ownership_action_plan' => [
        'domain_key' => 'ownership',
        'component_type' => 'action',
        'operating_purpose' =>
            'Track execution of approved ownership decisions without creating or changing ownership authority.',
        'canonical_capability' => 'none',
        'approval_required' => false,
        'can_create_proposal' => false,
        'publish_authority' => 'none',
        'write_mode' => 'action_state',
        'can_create_actions' => false,
        'can_trigger_review' => true,
        'source_policy' =>
            'approved_upstream_only',
        'reads_from' => [],
        'cross_domain_write' => false,
        'stale_source_guard' => false,
        'legacy_mappings' => [],
        'status' => 'active',
    ],

];
