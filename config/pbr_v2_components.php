<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBR OS V2 Component Contract
    |--------------------------------------------------------------------------
    |
    | This registry is separate from the legacy V1 operating-tool catalog.
    | A V2 component declares its business purpose and authority explicitly.
    |
    */

    'version' => 'pbr-v2-component-contract-v1',

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

    ],

    /*
    |--------------------------------------------------------------------------
    | Pilot Components
    |--------------------------------------------------------------------------
    |
    | These five representative components prove the V2 authority model
    | before the registry is populated chapter by chapter.
    |
    */

    'components' => [

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

            'legacy_tool_key' => 'funding_gap_calculator',

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

            'legacy_tool_key' => 'equity_split_simulator',

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

            'legacy_tool_key' => 'cap_table_builder',

            'status' => 'active',

        ],

        'cashflow_dashboard' => [

            'domain_key' => 'financial_controls',

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

            'legacy_tool_key' => 'cashflow_dashboard',

            'status' => 'support',

        ],

        'meeting_decision_record' => [

            'domain_key' => 'governance',

            'component_type' => 'record',

            'operating_purpose' =>
                'Preserve completed meeting decisions as append-only operating history.',

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

            'legacy_tool_key' => 'meeting_decision_log',

            'status' => 'active',

        ],

    ],

];
