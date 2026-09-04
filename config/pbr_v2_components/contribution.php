<?php

return [

    'partner_contributions_workflow' => [
        'domain_key' => 'contribution',
        'component_type' => 'workflow',
        'operating_purpose' =>
            'Coordinate partner contribution setup, valuation, evidence, approval and accepted-value recording.',
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
            'capital',
        ],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [],
        'status' => 'active',
    ],

    'contribution_valuation_analysis' => [
        'domain_key' => 'contribution',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Analyse proposed contribution value without converting that value into ownership.',
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
                'tool_key' => 'sweat_equity_calculator',
                'treatment' => 'adapt',
            ],
        ],
        'status' => 'support',
    ],

    'contribution_evidence_assessment' => [
        'domain_key' => 'contribution',
        'component_type' => 'assessment',
        'operating_purpose' =>
            'Assess contribution evidence, delivery conditions and readiness for acceptance.',
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
                'tool_key' => 'time_contribution_tracker',
                'treatment' => 'support',
            ],
            [
                'tool_key' => 'partner_contribution_scorecard',
                'treatment' => 'adapt',
            ],
        ],
        'status' => 'support',
    ],

    'partner_contribution_matrix' => [
        'domain_key' => 'contribution',
        'component_type' => 'analysis',
        'operating_purpose' =>
            'Summarise partner contribution positions without becoming the accepted contribution source of truth.',
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
            'capital',
        ],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [
            [
                'tool_key' => 'partner_contribution_matrix',
                'treatment' => 'move',
            ],
            [
                'tool_key' => 'contribution_balance_chart',
                'treatment' => 'support',
            ],
        ],
        'status' => 'support',
    ],

    'accepted_contribution_register' => [
        'domain_key' => 'contribution',
        'component_type' => 'register',
        'operating_purpose' =>
            'Maintain approved accepted contribution values as the authoritative contribution register.',
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
        'reads_from' => [],
        'cross_domain_write' => false,
        'stale_source_guard' => true,
        'legacy_mappings' => [],
        'status' => 'active',
    ],

    'contribution_decision_record' => [
        'domain_key' => 'contribution',
        'component_type' => 'record',
        'operating_purpose' =>
            'Preserve completed contribution acceptance decisions as append-only operating history.',
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

    'contribution_action_plan' => [
        'domain_key' => 'contribution',
        'component_type' => 'action',
        'operating_purpose' =>
            'Track execution of approved contribution decisions without changing accepted values.',
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
