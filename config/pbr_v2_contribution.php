<?php

return [
    // Domain contract only. Services must enforce these rules.
    'schema_version' => 'contribution-register-v1',
    'chapter_number' => 2,
    'domain_key' => 'contribution',

    'components' => [
        'workflow' => 'partner_contributions_workflow',
        'valuation' => 'contribution_valuation_analysis',
        'evidence' => 'contribution_evidence_assessment',
        'matrix' => 'partner_contribution_matrix',
        'canonical' => 'accepted_contribution_register',
        'decision_record' => 'contribution_decision_record',
        'action_plan' => 'contribution_action_plan',
    ],

    'steps' => [
        'setup',
        'partners',
        'contributions',
        'valuation',
        'evidence_conditions',
        'approval',
        'contribution_matrix',
    ],

    'contribution_types' => [
        'cash',
        'time_skill',
        'property_asset',
        'ip_intangible',
    ],

    'intangible_subtypes' => [
        'ip',
        'brand',
        'software_system',
        'customer_database',
        'network_introductions',
        'customer_access',
        'know_how',
        'business_process',
    ],

    // Item states are not generic canonical artifact states.
    'item_states' => [
        'proposed',
        'reviewed',
        'approved',
        'delivered',
        'accepted',
        'rejected',
        'cancelled',
        'defaulted',
    ],

    // These lists describe source fields, not blanket "required" rules.
    // Draft completeness and stage-specific validation are separate.
    'setup_fields' => [
        'valuation_date',
        'currency_code',
        'contribution_period',
        'valuation_owner_reference',
        'approver_user_ids',
    ],

    'common_item_fields' => [
        'id',
        'partner_key',
        'partner_snapshot',
        'contribution_type',
        'description',
        'owner_reference',
        'proposed_value',
        'valuation_method',
        'evidence_references',
        'reviewed_value',
        'agreed_value',
        'accepted_value',
        'committed_date',
        'due_date',
        'delivered_date',
        'conditions',
        'status',
        'approval_records',
        'notes',
    ],

    'type_fields' => [
        'cash' => [
            'amount_committed',
            'amount_received',
            'payment_date',
        ],
        'time_skill' => [
            'role_work',
            'hours_per_month',
            'fair_hourly_rate',
            'number_of_months',
            'cash_compensation_received',
            'start_date',
            'end_date',
            'performance_condition',
            'vesting_rule',
        ],
        'property_asset' => [
            'asset_description',
            'asset_owner_reference',
            'ownership_transferred',
            'usage_period',
            'market_value',
            'fair_rental_use_value',
        ],
        'ip_intangible' => [
            'subtype',
            'legal_beneficial_owner_reference',
            'contribution_form',
            'period',
        ],
    ],

    'decision_record_fields' => [
        'owner_reference',
        'status',
        'effective_date',
        'review_date',
        'decision_summary',
        'evidence_references',
        'approved_by',
    ],

    // Explicit implementation policies, not verbatim PDF requirements.
    'implementation_policies' => [
        'approval_policy' => 'all_designated_approvers',
        'approver_requires_active_workspace_access' => true,
        'designation_grants_cross_chapter_permissions' => false,
        'material_change_invalidates_item_approval' => true,
        'approval_records_are_server_generated' => true,
        'event_history_is_server_generated' => true,
        'stale_working_updates_are_rejected' => true,
        'published_partner_snapshots_are_preserved' => true,
        'money_representation' => 'decimal_string',
        'negative_work_result' => 'report_discrepancy',
        'negative_accepted_value_allowed' => false,
        'automatic_partial_delivery_proration' => false,
    ],

    'partial_delivery_fields' => [
        'accepted_scope',
        'adjustment_basis',
    ],

    'publication_rules' => [
        'target_component_key' => 'accepted_contribution_register',
        'publish_authority' => 'approval_engine_only',
        'write_mode' => 'revisioned_current',
        'readiness_from_locked_stored_payload' => true,
        'accepted_item_state' => 'accepted',
        'approved_status_alone_enters_accepted_total' => false,
        'accepted_total_from_server_calculation' => true,
        'direct_current_payload_edit' => false,
        'amendment_requires_review' => true,
        'canonical_source_revision_ids' => [],
    ],

    'execution_rules' => [
        'decision_record_requires_published_source' => true,
        'action_requires_published_source' => true,
        'action_completion_changes_accepted_value' => false,
        'prepublication_item_history_is_decision_record' => false,
    ],

    'equity_handoff' => [
        'source_component_key' => 'accepted_contribution_register',
        'source_state' => 'approved_current',
        'item_state' => 'accepted',
        'fields' => [
            'workspace_id',
            'canonical_revision_id',
            'revision',
            'currency_code',
            'partner_key',
            'partner_snapshot',
            'accepted_contribution_value',
            'contribution_status',
        ],
        'calculates_shares' => false,
        'writes_ownership' => false,
    ],

    // Asset ownership transfer and contribution vesting remain in scope.
    'excluded_business_behaviors' => [
        'share_value_setting',
        'share_allocation',
        'ownership_percentage_calculation',
        'share_class_rights',
        'share_vesting_calculation',
        'share_issuance',
        'dilution_calculation',
        'profit_distribution',
    ],
];
