<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBR Canonical Data Contract
    |--------------------------------------------------------------------------
    |
    | This file defines the authoritative operating-data relationships for
    | PBR Business Operating System.
    |
    | Approved operating snapshots are the source of truth for current
    | business rules. Drafts are working changes only.
    |
    */

    'schema_version' => 'pbr-canonical-v1',

    'source_policy' => [
        'current_business_rule' => 'latest_agreed_snapshot',
        'working_change' => 'draft_only_for_managers',
        'partner_read_scope' => 'approved_current_only',
        'ai_operating_scope' => 'approved_current_only',
    ],

    'domains' => [

        'capital' => [
            'chapter' => 1,
            'name' => 'Capital & Funding',
            'reads_from' => [],
        ],

        'ownership' => [
            'chapter' => 2,
            'name' => 'Ownership & Equity',
            'reads_from' => [
                'capital',
            ],
        ],

        'contribution' => [
            'chapter' => 3,
            'name' => 'Partner Roles & Contributions',
            'reads_from' => [
                'capital',
                'ownership',
            ],
        ],

        'distribution' => [
            'chapter' => 4,
            'name' => 'Profit, Salary & Distribution',
            'reads_from' => [
                'capital',
                'ownership',
                'contribution',
            ],
        ],

        'financial_controls' => [
            'chapter' => 5,
            'name' => 'Finance & Controls',
            'reads_from' => [
                'capital',
                'distribution',
            ],
        ],

        'governance' => [
            'chapter' => 6,
            'name' => 'Governance & Decisions',
            'reads_from' => [
                'ownership',
                'contribution',
            ],
        ],

        'exit' => [
            'chapter' => 7,
            'name' => 'Exit & Buyout',
            'reads_from' => [
                'ownership',
                'financial_controls',
                'governance',
            ],
        ],

        'continuity' => [
            'chapter' => 8,
            'name' => 'Continuity & Succession',
            'reads_from' => [
                'contribution',
                'governance',
                'exit',
            ],
        ],

        'share_transfer' => [
            'chapter' => 9,
            'name' => 'Share Transfers & New Partners',
            'reads_from' => [
                'ownership',
                'governance',
                'exit',
            ],
        ],

        'dispute_resolution' => [
            'chapter' => 10,
            'name' => 'Conflict & Resolution',
            'reads_from' => [
                'governance',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Prefill Dependency Contracts
    |--------------------------------------------------------------------------
    |
    | Cross-domain prefills are explicit and deny-by-default.
    |
    | A tool may read:
    | - its own approved domain state, or
    | - an approved domain declared in reads_from.
    |
    | Advisory sources are separate and can never silently become canonical
    | operating state.
    |
    */

    'prefill_contracts' => [

        'equity_split_simulator' => [
            'consumer' => 'ownership',
            'sources' => ['capital'],
            'advisory' => [],
        ],

        'cap_table_builder' => [
            'consumer' => 'ownership',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'ownership_chart' => [
            'consumer' => 'ownership',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'voting_power_calculator' => [
            'consumer' => 'ownership',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'share_value_calculator' => [
            'consumer' => 'ownership',
            'sources' => ['ownership'],
            'advisory' => ['business_valuation'],
        ],

        'future_dilution_simulator' => [
            'consumer' => 'ownership',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'time_contribution_tracker' => [
            'consumer' => 'contribution',
            'sources' => [],
            'advisory' => [],
        ],

        'partner_contribution_scorecard' => [
            'consumer' => 'contribution',
            'sources' => [],
            'advisory' => [],
        ],

        'contribution_balance_chart' => [
            'consumer' => 'contribution',
            'sources' => [],
            'advisory' => [],
        ],

        'profit_distribution_calculator' => [
            'consumer' => 'distribution',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'loss_sharing_simulator' => [
            'consumer' => 'distribution',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'salary_profit_share_planner' => [
            'consumer' => 'distribution',
            'sources' => [
                'distribution',
                'ownership',
            ],
            'advisory' => [],
        ],

        'reserve_fund_planner' => [
            'consumer' => 'distribution',
            'sources' => ['capital'],
            'advisory' => [],
        ],

        'cashflow_dashboard' => [
            'consumer' => 'financial_controls',
            'sources' => ['capital'],
            'advisory' => [],
        ],

        'voting_simulator' => [
            'consumer' => 'governance',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'partner_buyout_calculator' => [
            'consumer' => 'exit',
            'sources' => [],
            'advisory' => ['business_valuation'],
        ],

        'exit_value_simulator' => [
            'consumer' => 'exit',
            'sources' => [],
            'advisory' => ['business_valuation'],
        ],

        'ownership_transition_simulator' => [
            'consumer' => 'continuity',
            'sources' => [],
            'advisory' => ['business_valuation'],
        ],

        'share_transfer_simulator' => [
            'consumer' => 'share_transfer',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'ownership_before_after_chart' => [
            'consumer' => 'share_transfer',
            'sources' => ['ownership'],
            'advisory' => [],
        ],

        'share_valuation_calculator' => [
            'consumer' => 'share_transfer',
            'sources' => ['ownership'],
            'advisory' => ['business_valuation'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Integrity Rules
    |--------------------------------------------------------------------------
    */

    'invariants' => [
        'contribution_is_not_ownership',
        'ownership_is_not_voting_power',
        'salary_is_not_profit_share',
        'profit_share_is_not_ownership',
        'draft_is_not_current_rule',
        'scenario_is_not_executed_transaction',
        'indicative_valuation_is_not_certified_valuation',
        'proposed_transfer_is_not_executed_transfer',
        'partner_access_is_not_student_entitlement',
    ],

    /*
    |--------------------------------------------------------------------------
    | Advisory Data
    |--------------------------------------------------------------------------
    |
    | These sources may assist calculations but MUST NOT silently become a
    | current operating rule.
    |
    */

    'advisory_sources' => [

        'business_valuation' => [
            'model' => App\Models\BusinessValuation::class,
            'policy' => 'advisory_until_adopted_into_approved_rule',
        ],

        'business_feasibility' => [
            'model' => App\Models\BusinessFeasibilityAssessment::class,
            'policy' => 'decision_support_only',
        ],

        'partner_dynamics' => [
            'model' => App\Models\PartnerDynamicsReport::class,
            'policy' => 'decision_support_only',
        ],
    ],
];
