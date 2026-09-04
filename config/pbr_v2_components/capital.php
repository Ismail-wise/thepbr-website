<?php

return [

    'capital_funding_workflow' => [
        'domain_key' => 'capital',
        'component_type' => 'workflow',
        'operating_purpose' =>
            'Coordinate capital planning from working inputs through proposal, approval, record and action.',
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
        'legacy_mappings' => [],
        'status' => 'active',
    ],

    'startup_capital_planner' => [
        'domain_key' => 'capital',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Estimate startup funding requirements without creating approved capital authority.',
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
                'tool_key' => 'startup_capital_planner',
                'treatment' => 'adapt',
            ],
        ],
        'status' => 'support',
    ],

    'current_capital_position' => [
        'domain_key' => 'capital',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Assess the business current capital position without publishing a current rule.',
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
                'tool_key' => 'current_capital_position',
                'treatment' => 'reuse',
            ],
        ],
        'status' => 'support',
    ],

    'working_capital_forecast' => [
        'domain_key' => 'capital',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Forecast working capital requirements and operating funding needs.',
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
                'tool_key' => 'working_capital_calculator',
                'treatment' => 'adapt',
            ],
        ],
        'status' => 'support',
    ],

    'contingency_reserve_analysis' => [
        'domain_key' => 'capital',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Analyse an appropriate contingency reserve without creating an approved funding rule.',
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
                'tool_key' => 'contingency_fund_calculator',
                'treatment' => 'adapt',
            ],
        ],
        'status' => 'support',
    ],

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
                'tool_key' => 'funding_gap_calculator',
                'treatment' => 'reuse',
            ],
        ],
        'status' => 'support',
    ],

    'capital_plan_comparison' => [
        'domain_key' => 'capital',
        'component_type' => 'scenario',
        'operating_purpose' =>
            'Compare alternative capital plans before a formal proposal or approval.',
        'canonical_capability' => 'none',
        'approval_required' => false,
        'can_create_proposal' => true,
        'publish_authority' => 'none',
        'write_mode' => 'none',
        'can_create_actions' => false,
        'can_trigger_review' => false,
        'source_policy' =>
            'own_working_plus_approved_upstream',
        'reads_from' => [],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [],
        'status' => 'support',
    ],

    'capital_allocation_summary' => [
        'domain_key' => 'capital',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Summarise planned capital allocation without becoming the approved capital plan.',
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
                'tool_key' => 'capital_allocation_chart',
                'treatment' => 'support',
            ],
        ],
        'status' => 'support',
    ],

    'capital_call_rule' => [
        'domain_key' => 'capital',
        'component_type' => 'rule',
        'operating_purpose' =>
            'Define the approved rule for when and how additional capital may be called.',
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

    'approved_capital_plan' => [
        'domain_key' => 'capital',
        'component_type' => 'plan',
        'operating_purpose' =>
            'Maintain the approved current capital and funding plan as revisioned canonical state.',
        'canonical_capability' => 'plan',
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

    'capital_decision_record' => [
        'domain_key' => 'capital',
        'component_type' => 'record',
        'operating_purpose' =>
            'Preserve completed capital decisions as append-only operating history.',
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

    'capital_action_plan' => [
        'domain_key' => 'capital',
        'component_type' => 'action',
        'operating_purpose' =>
            'Track execution of approved capital decisions without creating business authority.',
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
