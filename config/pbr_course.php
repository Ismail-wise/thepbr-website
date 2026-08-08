<?php

return [

    'version' => 'v1',

    'chapters' => [

        [
            'number' => 1,
            'slug' => 'capital-contribution',
            'phase' => 'agree_build',
            'title_en' => 'Capital Contribution',
            'title_mm' => 'မတည်ငွေ ထည့်ဝင်ခြင်း စည်းမျဉ်းများ',
            'description' => 'Plan how much capital the partnership needs, what each partner contributes, and where funding gaps remain.',
            'tools' => [
                [
                    'key' => 'startup_capital_planner',
                    'slug' => 'startup-capital-planner',
                    'title' => 'Startup Capital Planner',
                    'type' => 'planner',
                    'new' => true,
                    'existing' => false,
                ],
                [
                    'key' => 'current_capital_position',
                    'slug' => 'current-capital-position',
                    'title' => 'Current Capital Position',
                    'type' => 'dashboard',
                    'new' => false,
                    'existing' => true,
                ],
                [
                    'key' => 'working_capital_calculator',
                    'slug' => 'working-capital-calculator',
                    'title' => 'Working Capital Calculator',
                    'type' => 'calculator',
                    'new' => true,
                    'existing' => true,
                ],
                [
                    'key' => 'contingency_fund_calculator',
                    'slug' => 'contingency-fund-calculator',
                    'title' => 'Contingency Fund Calculator',
                    'type' => 'calculator',
                    'new' => true,
                    'existing' => true,
                ],
                [
                    'key' => 'partner_contribution_matrix',
                    'slug' => 'partner-contribution-matrix',
                    'title' => 'Partner Contribution Matrix',
                    'type' => 'matrix',
                    'new' => true,
                    'existing' => true,
                ],
                [
                    'key' => 'funding_gap_calculator',
                    'slug' => 'funding-gap-calculator',
                    'title' => 'Funding Gap Calculator',
                    'type' => 'calculator',
                    'new' => true,
                    'existing' => true,
                ],
                [
                    'key' => 'capital_allocation_chart',
                    'slug' => 'capital-allocation-chart',
                    'title' => 'Capital Allocation Chart',
                    'type' => 'chart',
                    'new' => true,
                    'existing' => true,
                ],
            ],
        ],

        [
            'number' => 2,
            'slug' => 'ownership-share-structure',
            'phase' => 'agree_build',
            'title_en' => 'Ownership & Share Structure',
            'title_mm' => 'အစုရှယ်ယာနှင့် ပိုင်ဆိုင်မှု သတ်မှတ်ခြင်း',
            'description' => 'Model ownership, voting power, share value and dilution before locking the share structure.',
            'tools' => [
                [
                    'key' => 'equity_split_simulator',
                    'slug' => 'equity-split-simulator',
                    'title' => 'Equity Split Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'cap_table_builder',
                    'slug' => 'cap-table-builder',
                    'title' => 'Cap Table Builder',
                    'type' => 'builder',
                ],
                [
                    'key' => 'voting_power_calculator',
                    'slug' => 'voting-power-calculator',
                    'title' => 'Voting Power Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'share_value_calculator',
                    'slug' => 'share-value-calculator',
                    'title' => 'Share Value Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'future_dilution_simulator',
                    'slug' => 'future-dilution-simulator',
                    'title' => 'Future Dilution Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'ownership_chart',
                    'slug' => 'ownership-chart',
                    'title' => 'Ownership Chart',
                    'type' => 'chart',
                ],
            ],
        ],

        [
            'number' => 3,
            'slug' => 'work-value-contribution',
            'phase' => 'agree_build',
            'title_en' => 'Work & Value Contribution',
            'title_mm' => 'လုပ်အားနှင့် အခြားတန်ဖိုး ထည့်ဝင်မှု',
            'description' => 'Make time, expertise, responsibilities, non-cash value and vesting visible between partners.',
            'tools' => [
                [
                    'key' => 'sweat_equity_calculator',
                    'slug' => 'sweat-equity-calculator',
                    'title' => 'Sweat Equity Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'time_contribution_tracker',
                    'slug' => 'time-contribution-tracker',
                    'title' => 'Time Contribution Tracker',
                    'type' => 'tracker',
                ],
                [
                    'key' => 'partner_contribution_scorecard',
                    'slug' => 'partner-contribution-scorecard',
                    'title' => 'Partner Contribution Scorecard',
                    'type' => 'scorecard',
                ],
                [
                    'key' => 'role_responsibility_matrix',
                    'slug' => 'role-responsibility-matrix',
                    'title' => 'Role & Responsibility Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'vesting_calculator',
                    'slug' => 'vesting-calculator',
                    'title' => 'Vesting Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'contribution_balance_chart',
                    'slug' => 'contribution-balance-chart',
                    'title' => 'Contribution Balance Chart',
                    'type' => 'chart',
                ],
            ],
        ],

        [
            'number' => 4,
            'slug' => 'profit-loss-distribution',
            'phase' => 'agree_build',
            'title_en' => 'Profit & Loss Distribution',
            'title_mm' => 'အမြတ်အရှုံး ခွဲဝေခြင်း စည်းမျဉ်းများ',
            'description' => 'Design how profit, salary, reserves, reinvestment and losses are handled.',
            'tools' => [
                [
                    'key' => 'profit_distribution_calculator',
                    'slug' => 'profit-distribution-calculator',
                    'title' => 'Profit Distribution Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'salary_profit_share_planner',
                    'slug' => 'salary-profit-share-planner',
                    'title' => 'Salary vs Profit Share Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'retained_earnings_calculator',
                    'slug' => 'retained-earnings-calculator',
                    'title' => 'Retained Earnings Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'reserve_fund_planner',
                    'slug' => 'reserve-fund-planner',
                    'title' => 'Reserve Fund Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'loss_sharing_simulator',
                    'slug' => 'loss-sharing-simulator',
                    'title' => 'Loss Sharing Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'distribution_scenario_comparison',
                    'slug' => 'distribution-scenario-comparison',
                    'title' => 'Distribution Scenario Comparison',
                    'type' => 'simulator',
                ],
            ],
        ],

        [
            'number' => 5,
            'slug' => 'financial-management',
            'phase' => 'operate',
            'title_en' => 'Financial Management',
            'title_mm' => 'ငွေကြေး စီမံခန့်ခွဲမှု စည်းမျဉ်းများ',
            'description' => 'Build practical financial controls for cash flow, budgets, approvals and banking authority.',
            'tools' => [
                [
                    'key' => 'cashflow_dashboard',
                    'slug' => 'cashflow-dashboard',
                    'title' => 'Cash Flow Dashboard',
                    'type' => 'dashboard',
                ],
                [
                    'key' => 'monthly_budget_planner',
                    'slug' => 'monthly-budget-planner',
                    'title' => 'Monthly Budget Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'budget_actual_chart',
                    'slug' => 'budget-vs-actual-chart',
                    'title' => 'Budget vs Actual Chart',
                    'type' => 'chart',
                ],
                [
                    'key' => 'expense_approval_matrix',
                    'slug' => 'expense-approval-matrix',
                    'title' => 'Expense Approval Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'bank_authority_matrix',
                    'slug' => 'bank-authority-matrix',
                    'title' => 'Bank Authority Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'financial_control_checklist',
                    'slug' => 'financial-control-checklist',
                    'title' => 'Financial Control Checklist',
                    'type' => 'checklist',
                ],
                [
                    'key' => 'large_payment_approval_rules',
                    'slug' => 'large-payment-approval-rules',
                    'title' => 'Large Payment Approval Rules',
                    'type' => 'builder',
                ],
            ],
        ],

        [
            'number' => 6,
            'slug' => 'leadership-governance',
            'phase' => 'operate',
            'title_en' => 'Leadership & Governance',
            'title_mm' => 'လုပ်ငန်း ဦးဆောင်မှုနှင့် အုပ်ချုပ်မှု',
            'description' => 'Define roles, authority, voting, decisions and governance between partners.',
            'tools' => [
                [
                    'key' => 'partner_role_matrix',
                    'slug' => 'partner-role-matrix',
                    'title' => 'Partner Role Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'decision_rights_matrix',
                    'slug' => 'decision-rights-matrix',
                    'title' => 'Decision Rights Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'authority_level_builder',
                    'slug' => 'authority-level-builder',
                    'title' => 'Authority Level Builder',
                    'type' => 'builder',
                ],
                [
                    'key' => 'voting_simulator',
                    'slug' => 'voting-simulator',
                    'title' => 'Voting Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'meeting_decision_log',
                    'slug' => 'meeting-decision-log',
                    'title' => 'Meeting Decision Log',
                    'type' => 'log',
                ],
                [
                    'key' => 'deadlock_detector',
                    'slug' => 'deadlock-detector',
                    'title' => 'Deadlock Detector',
                    'type' => 'analysis',
                ],
                [
                    'key' => 'governance_structure_chart',
                    'slug' => 'governance-structure-chart',
                    'title' => 'Governance Structure Chart',
                    'type' => 'chart',
                ],
            ],
        ],

        [
            'number' => 7,
            'slug' => 'withdrawal-exit',
            'phase' => 'protect_exit',
            'title_en' => 'Withdrawal & Exit',
            'title_mm' => 'မိတ်ဖက် ထွက်ခွာခြင်း စည်းမျဉ်းများ',
            'description' => 'Prepare fair exit, buyout, notice, handover and business continuity rules.',
            'tools' => [
                [
                    'key' => 'partner_buyout_calculator',
                    'slug' => 'partner-buyout-calculator',
                    'title' => 'Partner Buyout Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'exit_value_simulator',
                    'slug' => 'exit-value-simulator',
                    'title' => 'Exit Value Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'notice_period_planner',
                    'slug' => 'notice-period-planner',
                    'title' => 'Notice Period Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'exit_timeline',
                    'slug' => 'exit-timeline',
                    'title' => 'Exit Timeline',
                    'type' => 'timeline',
                ],
                [
                    'key' => 'responsibility_handover_checklist',
                    'slug' => 'responsibility-handover-checklist',
                    'title' => 'Responsibility Handover Checklist',
                    'type' => 'checklist',
                ],
                [
                    'key' => 'business_continuity_planner',
                    'slug' => 'business-continuity-planner',
                    'title' => 'Business Continuity Planner',
                    'type' => 'planner',
                ],
            ],
        ],

        [
            'number' => 8,
            'slug' => 'death-disability-spouse',
            'phase' => 'protect_exit',
            'title_en' => 'Death, Disability & Spouse',
            'title_mm' => 'ကွယ်လွန်ခြင်း၊ မသန်စွမ်းခြင်းနှင့် အိမ်ရှင်ဇနီးမောင်နှံ အခွင့်အရေး',
            'description' => 'Prepare ownership, authority and continuity when a partner cannot continue in the business.',
            'tools' => [
                [
                    'key' => 'key_person_dependency_map',
                    'slug' => 'key-person-dependency-map',
                    'title' => 'Key Person Dependency Map',
                    'type' => 'map',
                ],
                [
                    'key' => 'succession_planner',
                    'slug' => 'succession-planner',
                    'title' => 'Succession Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'emergency_authority_planner',
                    'slug' => 'emergency-authority-planner',
                    'title' => 'Emergency Authority Planner',
                    'type' => 'planner',
                ],
                [
                    'key' => 'ownership_transition_simulator',
                    'slug' => 'ownership-transition-simulator',
                    'title' => 'Ownership Transition Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'continuity_checklist',
                    'slug' => 'continuity-checklist',
                    'title' => 'Continuity Checklist',
                    'type' => 'checklist',
                ],
                [
                    'key' => 'insurance_coverage_gap_calculator',
                    'slug' => 'insurance-coverage-gap-calculator',
                    'title' => 'Insurance Coverage Gap Calculator',
                    'type' => 'calculator',
                ],
            ],
        ],

        [
            'number' => 9,
            'slug' => 'share-transfer',
            'phase' => 'protect_exit',
            'title_en' => 'Share Transfer',
            'title_mm' => 'အစုရှယ်ယာ လက်လွှဲခြင်း စည်းမျဉ်းများ',
            'description' => 'Plan how shares can be valued, offered, approved and transferred.',
            'tools' => [
                [
                    'key' => 'share_transfer_simulator',
                    'slug' => 'share-transfer-simulator',
                    'title' => 'Share Transfer Simulator',
                    'type' => 'simulator',
                ],
                [
                    'key' => 'ownership_before_after_chart',
                    'slug' => 'ownership-before-after-chart',
                    'title' => 'Before / After Ownership Chart',
                    'type' => 'chart',
                ],
                [
                    'key' => 'first_refusal_workflow',
                    'slug' => 'right-of-first-refusal-workflow',
                    'title' => 'Right of First Refusal Workflow',
                    'type' => 'workflow',
                ],
                [
                    'key' => 'transfer_approval_matrix',
                    'slug' => 'transfer-approval-matrix',
                    'title' => 'Transfer Approval Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'share_valuation_calculator',
                    'slug' => 'share-valuation-calculator',
                    'title' => 'Share Valuation Calculator',
                    'type' => 'calculator',
                ],
                [
                    'key' => 'transfer_history_tracker',
                    'slug' => 'transfer-history-tracker',
                    'title' => 'Transfer History Tracker',
                    'type' => 'tracker',
                ],
            ],
        ],

        [
            'number' => 10,
            'slug' => 'dispute-resolution',
            'phase' => 'protect_exit',
            'title_en' => 'Dispute Resolution',
            'title_mm' => 'အငြင်းပွားမှု ဖြေရှင်းခြင်း စည်းမျဉ်းများ',
            'description' => 'Create a clear escalation, tracking and resolution process for disputes and deadlocks.',
            'tools' => [
                [
                    'key' => 'conflict_escalation_ladder',
                    'slug' => 'conflict-escalation-ladder',
                    'title' => 'Conflict Escalation Ladder',
                    'type' => 'builder',
                ],
                [
                    'key' => 'dispute_log',
                    'slug' => 'dispute-log',
                    'title' => 'Dispute Log',
                    'type' => 'log',
                ],
                [
                    'key' => 'resolution_tracker',
                    'slug' => 'resolution-tracker',
                    'title' => 'Resolution Tracker',
                    'type' => 'tracker',
                ],
                [
                    'key' => 'deadlock_decision_tool',
                    'slug' => 'deadlock-decision-tool',
                    'title' => 'Deadlock Decision Tool',
                    'type' => 'workflow',
                ],
                [
                    'key' => 'issue_priority_matrix',
                    'slug' => 'issue-priority-matrix',
                    'title' => 'Issue Priority Matrix',
                    'type' => 'matrix',
                ],
                [
                    'key' => 'decision_history',
                    'slug' => 'decision-history',
                    'title' => 'Decision History',
                    'type' => 'log',
                ],
                [
                    'key' => 'escalation_timeline',
                    'slug' => 'escalation-timeline',
                    'title' => 'Escalation Timeline',
                    'type' => 'timeline',
                ],
            ],
        ],

    ],

];
