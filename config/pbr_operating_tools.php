<?php

/*
|--------------------------------------------------------------------------
| PBR Operating Tools — Chapters 2 to 10
|--------------------------------------------------------------------------
|
| This config is intentionally data-driven. The controller and UI use these
| definitions to provide one consistent Burmese-first experience while the
| engine provides tool-specific business logic. Chapter 1 keeps its richer
| capital builders, but shares the same operating-system snapshots.
|
*/

$number = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    ?string $helpMm = null,
    float|int|null $default = null,
    float|int $min = 0,
    float|int|null $max = null,
    string $step = '0.01'
): array => array_filter([
    'name' => $name,
    'type' => 'number',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'help_mm' => $helpMm,
    'default' => $default,
    'min' => $min,
    'max' => $max,
    'step' => $step,
], static fn ($value) => $value !== null);

$text = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    ?string $helpMm = null,
    ?string $default = null
): array => array_filter([
    'name' => $name,
    'type' => 'text',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'help_mm' => $helpMm,
    'default' => $default,
], static fn ($value) => $value !== null);

$textarea = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    ?string $helpMm = null
): array => array_filter([
    'name' => $name,
    'type' => 'textarea',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'help_mm' => $helpMm,
], static fn ($value) => $value !== null);

$date = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    ?string $helpMm = null
): array => array_filter([
    'name' => $name,
    'type' => 'date',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'help_mm' => $helpMm,
], static fn ($value) => $value !== null);

$select = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    array $options,
    ?string $helpMm = null,
    ?string $default = null
): array => array_filter([
    'name' => $name,
    'type' => 'select',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'options' => $options,
    'help_mm' => $helpMm,
    'default' => $default,
], static fn ($value) => $value !== null);

$repeater = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    array $columns,
    ?string $helpMm = null,
    int $max = 30
): array => array_filter([
    'name' => $name,
    'type' => 'repeater',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'columns' => $columns,
    'help_mm' => $helpMm,
    'max' => $max,
], static fn ($value) => $value !== null);

$checklist = static fn (
    string $name,
    string $labelMm,
    string $labelEn,
    array $items,
    ?string $helpMm = null
): array => array_filter([
    'name' => $name,
    'type' => 'checklist',
    'label_mm' => $labelMm,
    'label_en' => $labelEn,
    'items' => $items,
    'help_mm' => $helpMm,
], static fn ($value) => $value !== null);

$partnerMoneyColumns = [
    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner အမည်', 'label_en' => 'Partner'],
    ['name' => 'amount', 'type' => 'number', 'label_mm' => 'ပမာဏ', 'label_en' => 'Amount', 'min' => 0, 'step' => '0.01'],
];

$partnerPercentColumns = [
    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner အမည်', 'label_en' => 'Partner'],
    ['name' => 'percentage', 'type' => 'number', 'label_mm' => 'ရာခိုင်နှုန်း', 'label_en' => 'Percentage', 'min' => 0, 'max' => 100, 'step' => '0.01'],
];

return [
    'version' => 'v1',

    'definitions' => [
        // -----------------------------------------------------------------
        // Chapter 2 — Ownership & Share Structure
        // -----------------------------------------------------------------
        'equity_split_simulator' => [
            'chapter' => 2,
            'handler' => 'equity_split',
            'title_mm' => 'ပိုင်ဆိုင်မှု ခွဲဝေမှု စမ်းသပ်ကိရိယာ',
            'purpose_mm' => 'Capital တစ်မျိုးတည်းမဟုတ်ဘဲ လုပ်အား၊ တာဝန်ယူမှု၊ Risk နဲ့ အခြားတန်ဖိုးတွေကို user သတ်မှတ်ထားတဲ့ weights နဲ့ scenario တစ်ခုအဖြစ် နှိုင်းယှဉ်ပါ။',
            'fields' => [
                $number('capital_weight', 'Capital အလေးချိန် (%)', 'Capital Weight %', 'အားလုံးပေါင်း 100% ဖြစ်သင့်ပါတယ်။', 40, 0, 100),
                $number('work_weight', 'Work / Time အလေးချိန် (%)', 'Work Weight %', null, 30, 0, 100),
                $number('expertise_weight', 'Skill / Expertise အလေးချိန် (%)', 'Expertise Weight %', null, 15, 0, 100),
                $number('risk_weight', 'Risk / Responsibility အလေးချိန် (%)', 'Risk Weight %', null, 15, 0, 100),
                $repeater('partners', 'Partner Contribution Inputs', 'Partner Inputs', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'capital', 'type' => 'number', 'label_mm' => 'Capital', 'label_en' => 'Capital', 'min' => 0],
                    ['name' => 'work', 'type' => 'number', 'label_mm' => 'Work Value', 'label_en' => 'Work Value', 'min' => 0],
                    ['name' => 'expertise', 'type' => 'number', 'label_mm' => 'Expertise Value', 'label_en' => 'Expertise Value', 'min' => 0],
                    ['name' => 'risk', 'type' => 'number', 'label_mm' => 'Risk / Responsibility Value', 'label_en' => 'Risk Value', 'min' => 0],
                ], 'ဒီ result က negotiated ownership အတွက် reference scenario ဖြစ်ပြီး legal entitlement မဟုတ်ပါ။'),
            ],
        ],

        'cap_table_builder' => [
            'chapter' => 2,
            'handler' => 'cap_table',
            'title_mm' => 'Cap Table တည်ဆောက်ခြင်း',
            'purpose_mm' => 'Ownership Units နဲ့ Voting Units ကို partner တစ်ယောက်ချင်းစီအလိုက် မှတ်တမ်းတင်ပြီး current ownership structure ကို source-of-truth အဖြစ်ပြပါ။',
            'fields' => [
                $repeater('partners', 'ပိုင်ဆိုင်မှုစာရင်း', 'Ownership Holders', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner / Holder', 'label_en' => 'Holder'],
                    ['name' => 'units', 'type' => 'number', 'label_mm' => 'Ownership Units', 'label_en' => 'Ownership Units', 'min' => 0, 'step' => '1'],
                    ['name' => 'voting_units', 'type' => 'number', 'label_mm' => 'Voting Units', 'label_en' => 'Voting Units', 'min' => 0, 'step' => '1'],
                ]),
                $number('reserved_units', 'မခွဲဝေရသေးသော Units', 'Reserved / Unallocated Units', null, 0, 0, null, '1'),
            ],
        ],

        'voting_power_calculator' => [
            'chapter' => 2,
            'handler' => 'voting_power',
            'title_mm' => 'Voting Power တွက်ချက်ခြင်း',
            'purpose_mm' => 'Ownership % နဲ့ Voting Power % မတူနိုင်တဲ့ structure တွေကိုရှင်းရှင်းလင်းလင်းတွက်ပါ။',
            'fields' => [
                $repeater('partners', 'Voting Units', 'Voting Units', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'voting_units', 'type' => 'number', 'label_mm' => 'Voting Units', 'label_en' => 'Voting Units', 'min' => 0, 'step' => '1'],
                ]),
                $number('approval_threshold', 'ဆုံးဖြတ်ချက်အတည်ပြု Threshold (%)', 'Approval Threshold %', 'ဥပမာ Majority 50%, Supermajority 75% စသည်။', 50, 0, 100),
            ],
        ],

        'share_value_calculator' => [
            'chapter' => 2,
            'handler' => 'share_value',
            'title_mm' => 'Share / Ownership Unit တန်ဖိုးတွက်ချက်ခြင်း',
            'purpose_mm' => 'Business Equity Value ကို Total Ownership Units နဲ့ခွဲပြီး indicative unit value ကိုတွက်ပါ။',
            'fields' => [
                $number('equity_value', 'Business Equity Value', 'Business Equity Value', 'Valuation Center ရဲ့ Base Estimate ကို prefill လုပ်နိုင်ပါတယ်။'),
                $number('total_units', 'Total Ownership Units', 'Total Ownership Units', null, 100, 1, 1000000000, '1'),
                $number('stake_percentage', 'စမ်းတွက်မည့် Stake (%)', 'Stake Percentage %', null, 1, 0, 100),
            ],
        ],

        'future_dilution_simulator' => [
            'chapter' => 2,
            'handler' => 'dilution',
            'title_mm' => 'Future Dilution စမ်းသပ်ခြင်း',
            'purpose_mm' => 'New Partner / Investor အတွက် units အသစ်ထုတ်တဲ့အခါ existing owners တွေရဲ့ percentage ဘယ်လောက်လျော့သွားမလဲ စမ်းကြည့်ပါ။',
            'fields' => [
                $number('new_units', 'အသစ်ထုတ်မည့် Units', 'New Units to Issue', null, 0, 0, 1000000000, '1'),
                $repeater('partners', 'Current Ownership', 'Current Ownership', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'units', 'type' => 'number', 'label_mm' => 'Current Units', 'label_en' => 'Current Units', 'min' => 0, 'step' => '1'],
                ]),
                $text('new_holder_name', 'New Partner / Investor အမည်', 'New Holder Name'),
            ],
        ],

        'ownership_chart' => [
            'chapter' => 2,
            'handler' => 'ownership_chart',
            'title_mm' => 'Ownership Structure Chart',
            'purpose_mm' => 'Current ownership units ကို percentage အဖြစ်ပြောင်းပြီး ownership concentration ကိုမြင်လွယ်အောင်ပြပါ။',
            'fields' => [
                $repeater('partners', 'Ownership Units', 'Ownership Units', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'units', 'type' => 'number', 'label_mm' => 'Units', 'label_en' => 'Units', 'min' => 0, 'step' => '1'],
                ]),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 3 — Work & Value Contribution
        // -----------------------------------------------------------------
        'sweat_equity_calculator' => [
            'chapter' => 3,
            'handler' => 'sweat_equity',
            'title_mm' => 'Sweat Equity တန်ဖိုးတွက်ချက်ခြင်း',
            'purpose_mm' => 'Partner တစ်ယောက်ရဲ့ unpaid / underpaid work ကို hours, fair rate နဲ့ period အပေါ်မူတည်ပြီး planning value အဖြစ်တွက်ပါ။',
            'fields' => [
                $text('partner_name', 'Partner အမည်', 'Partner Name'),
                $number('hours_per_month', 'တစ်လအလုပ်လုပ်ချိန် (Hours)', 'Hours per Month', null, 160),
                $number('fair_hourly_rate', 'Fair Hourly Rate', 'Fair Hourly Rate'),
                $number('months', 'ကာလ (Months)', 'Months', null, 12, 0, 120, '1'),
                $number('cash_compensation', 'ရပြီးသား Cash Compensation', 'Cash Compensation Already Paid', null, 0),
            ],
        ],

        'time_contribution_tracker' => [
            'chapter' => 3,
            'handler' => 'time_contribution',
            'title_mm' => 'Partner Time Contribution Tracker',
            'purpose_mm' => 'Partner တစ်ယောက်ချင်းစီရဲ့ actual hours နဲ့ agreed target hours ကိုနှိုင်းယှဉ်ပါ။',
            'fields' => [
                $repeater('partners', 'Partner Time', 'Partner Time', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'actual_hours', 'type' => 'number', 'label_mm' => 'Actual Hours', 'label_en' => 'Actual Hours', 'min' => 0],
                    ['name' => 'target_hours', 'type' => 'number', 'label_mm' => 'Target Hours', 'label_en' => 'Target Hours', 'min' => 0],
                ]),
            ],
        ],

        'partner_contribution_scorecard' => [
            'chapter' => 3,
            'handler' => 'contribution_scorecard',
            'title_mm' => 'Partner Contribution Scorecard',
            'purpose_mm' => 'Capital မဟုတ်တဲ့ contribution dimensions ကို evidence-based discussion အတွက် 1–5 scale နဲ့မှတ်တမ်းတင်ပါ။',
            'fields' => [
                $repeater('partners', 'Contribution Scorecard', 'Contribution Scorecard', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'execution', 'type' => 'number', 'label_mm' => 'Execution 1–5', 'label_en' => 'Execution', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'expertise', 'type' => 'number', 'label_mm' => 'Expertise 1–5', 'label_en' => 'Expertise', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'relationships', 'type' => 'number', 'label_mm' => 'Network 1–5', 'label_en' => 'Relationships', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'responsibility', 'type' => 'number', 'label_mm' => 'Responsibility 1–5', 'label_en' => 'Responsibility', 'min' => 1, 'max' => 5, 'step' => '1'],
                ], 'Score က လူတစ်ယောက်ရဲ့ worth ကိုဆုံးဖြတ်တာမဟုတ်ဘဲ discussion aid သာဖြစ်ပါတယ်။'),
            ],
        ],

        'role_responsibility_matrix' => [
            'chapter' => 3,
            'handler' => 'role_matrix',
            'title_mm' => 'Role & Responsibility Matrix',
            'purpose_mm' => 'ဘယ် Partner က ဘာကို Owner လုပ်မလဲ၊ ဘယ်သူက Backup ဖြစ်မလဲကို မရှုပ်ထွေးအောင်သတ်မှတ်ပါ။',
            'fields' => [
                $repeater('responsibilities', 'တာဝန်ခွဲဝေမှု', 'Responsibilities', [
                    ['name' => 'area', 'type' => 'text', 'label_mm' => 'Area / Task', 'label_en' => 'Area'],
                    ['name' => 'owner', 'type' => 'text', 'label_mm' => 'Primary Owner', 'label_en' => 'Primary Owner'],
                    ['name' => 'backup', 'type' => 'text', 'label_mm' => 'Backup', 'label_en' => 'Backup'],
                    ['name' => 'kpi', 'type' => 'text', 'label_mm' => 'Expected Result / KPI', 'label_en' => 'KPI'],
                ]),
            ],
        ],

        'vesting_calculator' => [
            'chapter' => 3,
            'handler' => 'vesting',
            'title_mm' => 'Vesting Calculator',
            'purpose_mm' => 'Granted ownership units ကို cliff နဲ့ vesting period အပေါ်မူတည်ပြီး earned / unvested units အဖြစ်တွက်ပါ။',
            'fields' => [
                $number('grant_units', 'Granted Units', 'Granted Units', null, 100, 1, 1000000000, '1'),
                $number('vesting_months', 'Full Vesting Period (Months)', 'Vesting Months', null, 48, 1, 240, '1'),
                $number('cliff_months', 'Cliff (Months)', 'Cliff Months', null, 12, 0, 120, '1'),
                $number('months_elapsed', 'ပြီးသွားသော Months', 'Months Elapsed', null, 0, 0, 240, '1'),
            ],
        ],

        'contribution_balance_chart' => [
            'chapter' => 3,
            'handler' => 'contribution_balance',
            'title_mm' => 'Contribution Balance Chart',
            'purpose_mm' => 'Partner contribution values ကိုစုစည်းပြီး relative balance ကိုမြင်လွယ်အောင်ပြပါ။',
            'fields' => [
                $repeater('partners', 'Contribution Values', 'Contribution Values', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'cash', 'type' => 'number', 'label_mm' => 'Cash / Capital', 'label_en' => 'Cash', 'min' => 0],
                    ['name' => 'work', 'type' => 'number', 'label_mm' => 'Work Value', 'label_en' => 'Work', 'min' => 0],
                    ['name' => 'other', 'type' => 'number', 'label_mm' => 'Other Value', 'label_en' => 'Other', 'min' => 0],
                ]),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 4 — Profit & Loss Distribution
        // -----------------------------------------------------------------
        'profit_distribution_calculator' => [
            'chapter' => 4,
            'handler' => 'profit_distribution',
            'title_mm' => 'Profit Distribution Calculator',
            'purpose_mm' => 'Net Profit ထဲက reserve / reinvestment ကိုခွဲပြီးမှ Partner distribution ကိုတွက်ပါ။ Ownership နဲ့ Profit Share ကိုသီးခြားထားနိုင်ပါတယ်။',
            'fields' => [
                $number('net_profit', 'ခွဲဝေမယ့် Period ရဲ့ Net Profit', 'Net Profit'),
                $number('reserve_percentage', 'Reserve / Retained (%)', 'Reserve Percentage', null, 20, 0, 100),
                $repeater('partners', 'Profit Sharing Rules', 'Profit Sharing', $partnerPercentColumns, 'Profit share percentages ပေါင်း 100% ဖြစ်သင့်ပါတယ်။'),
            ],
        ],

        'salary_profit_share_planner' => [
            'chapter' => 4,
            'handler' => 'salary_profit',
            'title_mm' => 'Salary vs Profit Share Planner',
            'purpose_mm' => 'အလုပ်လုပ်တဲ့အတွက်ရတဲ့ Salary နဲ့ Owner ဖြစ်တဲ့အတွက်ရတဲ့ Profit Share ကိုသီးခြားကြည့်ပါ။',
            'fields' => [
                $number('annual_distributable_profit', 'Annual Distributable Profit', 'Annual Distributable Profit'),
                $repeater('partners', 'Partner Compensation', 'Partner Compensation', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'monthly_salary', 'type' => 'number', 'label_mm' => 'Monthly Salary', 'label_en' => 'Monthly Salary', 'min' => 0],
                    ['name' => 'profit_share', 'type' => 'number', 'label_mm' => 'Profit Share %', 'label_en' => 'Profit Share %', 'min' => 0, 'max' => 100],
                ]),
            ],
        ],

        'retained_earnings_calculator' => [
            'chapter' => 4,
            'handler' => 'retained_earnings',
            'title_mm' => 'Retained Earnings Calculator',
            'purpose_mm' => 'Profit ထဲက business ထဲမှာထားမယ့် amount နဲ့ distribute လုပ်နိုင်တဲ့ amount ကိုရှင်းရှင်းခွဲပါ။',
            'fields' => [
                $number('net_profit', 'Net Profit', 'Net Profit'),
                $number('retained_percentage', 'Retained Earnings (%)', 'Retained Percentage', null, 20, 0, 100),
                $number('mandatory_reserve', 'Mandatory / Agreed Fixed Reserve', 'Fixed Reserve', null, 0),
            ],
        ],

        'reserve_fund_planner' => [
            'chapter' => 4,
            'handler' => 'reserve_fund',
            'title_mm' => 'Reserve Fund Planner',
            'purpose_mm' => 'Operating cost အပေါ်မူတည်ပြီး target reserve နဲ့ current reserve gap ကိုတွက်ပါ။',
            'fields' => [
                $number('monthly_operating_cost', 'Monthly Operating Cost', 'Monthly Operating Cost'),
                $number('target_months', 'Target Reserve Months', 'Target Months', null, 3, 0, 24),
                $number('current_reserve', 'Current Reserve Balance', 'Current Reserve'),
            ],
        ],

        'loss_sharing_simulator' => [
            'chapter' => 4,
            'handler' => 'loss_sharing',
            'title_mm' => 'Loss Sharing Simulator',
            'purpose_mm' => 'Loss allocation rule ကို Partner တစ်ယောက်ချင်းစီအလိုက် simulation လုပ်ပါ။ Legal/tax treatment နဲ့မရောပါ။',
            'fields' => [
                $number('total_loss', 'Total Loss to Allocate', 'Total Loss'),
                $repeater('partners', 'Loss Sharing Rule', 'Loss Sharing', $partnerPercentColumns, 'Percentages ပေါင်း 100% ဖြစ်သင့်ပါတယ်။'),
            ],
        ],

        'distribution_scenario_comparison' => [
            'chapter' => 4,
            'handler' => 'distribution_compare',
            'title_mm' => 'Distribution Scenario Comparison',
            'purpose_mm' => 'Profit တူတူကို reserve policy မတူတဲ့ Scenario A / B နဲ့နှိုင်းယှဉ်ပါ။',
            'fields' => [
                $number('net_profit', 'Net Profit', 'Net Profit'),
                $number('scenario_a_reserve', 'Scenario A Reserve (%)', 'Scenario A Reserve %', null, 20, 0, 100),
                $number('scenario_b_reserve', 'Scenario B Reserve (%)', 'Scenario B Reserve %', null, 40, 0, 100),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 5 — Financial Management
        // -----------------------------------------------------------------
        'cashflow_dashboard' => [
            'chapter' => 5,
            'handler' => 'cashflow',
            'title_mm' => 'Cash Flow Dashboard',
            'purpose_mm' => 'Opening cash, inflows, outflows နဲ့ recurring burn ကိုအသုံးပြုပြီး closing cash နဲ့ runway ကိုကြည့်ပါ။',
            'fields' => [
                $number('opening_cash', 'Opening Cash', 'Opening Cash'),
                $number('cash_inflows', 'Cash Inflows', 'Cash Inflows'),
                $number('cash_outflows', 'Cash Outflows', 'Cash Outflows'),
                $number('monthly_fixed_cost', 'Monthly Fixed Cost', 'Monthly Fixed Cost'),
            ],
        ],

        'monthly_budget_planner' => [
            'chapter' => 5,
            'handler' => 'budget',
            'title_mm' => 'Monthly Budget Planner',
            'purpose_mm' => 'Budget category တစ်ခုချင်းစီအတွက် planned နဲ့ actual ကိုထည့်ပြီး variance ကိုကြည့်ပါ။',
            'fields' => [
                $repeater('categories', 'Budget Categories', 'Budget Categories', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Category', 'label_en' => 'Category'],
                    ['name' => 'planned', 'type' => 'number', 'label_mm' => 'Planned', 'label_en' => 'Planned', 'min' => 0],
                    ['name' => 'actual', 'type' => 'number', 'label_mm' => 'Actual', 'label_en' => 'Actual', 'min' => 0],
                ]),
            ],
        ],

        'budget_actual_chart' => [
            'chapter' => 5,
            'handler' => 'budget',
            'title_mm' => 'Budget vs Actual Chart',
            'purpose_mm' => 'Planned spending နဲ့ actual spending ကို category အလိုက် variance chart data အဖြစ်တည်ဆောက်ပါ။',
            'fields' => [
                $repeater('categories', 'Budget vs Actual', 'Budget vs Actual', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Category', 'label_en' => 'Category'],
                    ['name' => 'planned', 'type' => 'number', 'label_mm' => 'Budget', 'label_en' => 'Budget', 'min' => 0],
                    ['name' => 'actual', 'type' => 'number', 'label_mm' => 'Actual', 'label_en' => 'Actual', 'min' => 0],
                ]),
            ],
        ],

        'expense_approval_matrix' => [
            'chapter' => 5,
            'handler' => 'approval_matrix',
            'title_mm' => 'Expense Approval Matrix',
            'purpose_mm' => 'Payment amount range တစ်ခုချင်းစီအတွက် ဘယ်သူအတည်ပြုရမလဲကို သတ်မှတ်ပြီး approval gaps/overlaps ကိုစစ်ပါ။',
            'fields' => [
                $repeater('rules', 'Expense Approval Rules', 'Approval Rules', [
                    ['name' => 'min_amount', 'type' => 'number', 'label_mm' => 'From', 'label_en' => 'From', 'min' => 0],
                    ['name' => 'max_amount', 'type' => 'number', 'label_mm' => 'To', 'label_en' => 'To', 'min' => 0],
                    ['name' => 'approver', 'type' => 'text', 'label_mm' => 'Approver / Role', 'label_en' => 'Approver'],
                    ['name' => 'approvals_required', 'type' => 'number', 'label_mm' => 'Approvals #', 'label_en' => 'Approvals Required', 'min' => 1, 'max' => 20, 'step' => '1'],
                ]),
            ],
        ],

        'bank_authority_matrix' => [
            'chapter' => 5,
            'handler' => 'bank_authority',
            'title_mm' => 'Bank Authority Matrix',
            'purpose_mm' => 'Bank account access, signing authority နဲ့ transaction limits ကို role အလိုက်သတ်မှတ်ပါ။',
            'fields' => [
                $repeater('authorities', 'Bank Authorities', 'Bank Authorities', [
                    ['name' => 'person_or_role', 'type' => 'text', 'label_mm' => 'Person / Role', 'label_en' => 'Person / Role'],
                    ['name' => 'daily_limit', 'type' => 'number', 'label_mm' => 'Daily Limit', 'label_en' => 'Daily Limit', 'min' => 0],
                    ['name' => 'signing_rule', 'type' => 'select', 'label_mm' => 'Signing Rule', 'label_en' => 'Signing Rule', 'options' => ['single' => 'Single', 'dual' => 'Dual', 'restricted' => 'Restricted']],
                ]),
            ],
        ],

        'financial_control_checklist' => [
            'chapter' => 5,
            'handler' => 'checklist',
            'title_mm' => 'Financial Control Checklist',
            'purpose_mm' => 'SME partnership မှာ အခြေခံ financial controls တွေ တကယ်ထားရှိပြီးပြီလား စစ်ပါ။',
            'fields' => [
                $checklist('checks', 'Financial Controls', 'Financial Controls', [
                    'bank_reconciliation' => 'Bank reconciliation ပုံမှန်လုပ်ခြင်း',
                    'approval_limits' => 'Payment approval limits ရှိခြင်း',
                    'separate_business_account' => 'Business bank account သီးခြားထားခြင်း',
                    'receipt_evidence' => 'Expense evidence / receipt requirement ရှိခြင်း',
                    'monthly_reporting' => 'Monthly financial reporting လုပ်ခြင်း',
                    'cash_count' => 'Cash count / petty cash control ရှိခြင်း',
                    'access_review' => 'Bank/account access ကိုပုံမှန် review လုပ်ခြင်း',
                ]),
            ],
        ],

        'large_payment_approval_rules' => [
            'chapter' => 5,
            'handler' => 'approval_matrix',
            'title_mm' => 'Large Payment Approval Rules',
            'purpose_mm' => 'ကြီးမားတဲ့ payment တွေအတွက် escalating approval rules ကို amount thresholds နဲ့တည်ဆောက်ပါ။',
            'fields' => [
                $repeater('rules', 'Large Payment Rules', 'Large Payment Rules', [
                    ['name' => 'min_amount', 'type' => 'number', 'label_mm' => 'From', 'label_en' => 'From', 'min' => 0],
                    ['name' => 'max_amount', 'type' => 'number', 'label_mm' => 'To', 'label_en' => 'To', 'min' => 0],
                    ['name' => 'approver', 'type' => 'text', 'label_mm' => 'Required Approver(s)', 'label_en' => 'Approver'],
                    ['name' => 'approvals_required', 'type' => 'number', 'label_mm' => 'Approvals #', 'label_en' => 'Approvals Required', 'min' => 1, 'max' => 20, 'step' => '1'],
                ]),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 6 — Leadership & Governance
        // -----------------------------------------------------------------
        'partner_role_matrix' => [
            'chapter' => 6,
            'handler' => 'role_matrix',
            'title_mm' => 'Partner Role Matrix',
            'purpose_mm' => 'Partner တစ်ယောက်ချင်းစီရဲ့ primary role, decision scope နဲ့ backup ကိုရှင်းရှင်းသတ်မှတ်ပါ။',
            'fields' => [
                $repeater('responsibilities', 'Partner Roles', 'Partner Roles', [
                    ['name' => 'area', 'type' => 'text', 'label_mm' => 'Function / Area', 'label_en' => 'Area'],
                    ['name' => 'owner', 'type' => 'text', 'label_mm' => 'Primary Partner', 'label_en' => 'Primary Partner'],
                    ['name' => 'backup', 'type' => 'text', 'label_mm' => 'Backup', 'label_en' => 'Backup'],
                    ['name' => 'kpi', 'type' => 'text', 'label_mm' => 'Accountability / KPI', 'label_en' => 'KPI'],
                ]),
            ],
        ],

        'decision_rights_matrix' => [
            'chapter' => 6,
            'handler' => 'decision_rights',
            'title_mm' => 'Decision Rights Matrix',
            'purpose_mm' => 'Routine, strategic, reserved decisions တွေအတွက် decision owner နဲ့ approval rule ကိုသတ်မှတ်ပါ။',
            'fields' => [
                $repeater('decisions', 'Decision Rights', 'Decision Rights', [
                    ['name' => 'decision', 'type' => 'text', 'label_mm' => 'Decision', 'label_en' => 'Decision'],
                    ['name' => 'owner', 'type' => 'text', 'label_mm' => 'Decision Owner', 'label_en' => 'Owner'],
                    ['name' => 'approval_rule', 'type' => 'select', 'label_mm' => 'Approval Rule', 'label_en' => 'Approval Rule', 'options' => ['individual' => 'Individual', 'majority' => 'Majority', 'supermajority' => 'Supermajority', 'unanimous' => 'Unanimous']],
                    ['name' => 'notes', 'type' => 'text', 'label_mm' => 'Conditions / Notes', 'label_en' => 'Notes'],
                ]),
            ],
        ],

        'authority_level_builder' => [
            'chapter' => 6,
            'handler' => 'authority_levels',
            'title_mm' => 'Authority Level Builder',
            'purpose_mm' => 'Role တစ်ခုချင်းစီရဲ့ financial နဲ့ operational authority limits ကိုတည်ဆောက်ပါ။',
            'fields' => [
                $repeater('levels', 'Authority Levels', 'Authority Levels', [
                    ['name' => 'role', 'type' => 'text', 'label_mm' => 'Role', 'label_en' => 'Role'],
                    ['name' => 'financial_limit', 'type' => 'number', 'label_mm' => 'Financial Limit', 'label_en' => 'Financial Limit', 'min' => 0],
                    ['name' => 'scope', 'type' => 'text', 'label_mm' => 'Operational Scope', 'label_en' => 'Scope'],
                    ['name' => 'escalates_to', 'type' => 'text', 'label_mm' => 'Escalates To', 'label_en' => 'Escalates To'],
                ]),
            ],
        ],

        'voting_simulator' => [
            'chapter' => 6,
            'handler' => 'voting_simulator',
            'title_mm' => 'Voting Simulator',
            'purpose_mm' => 'Actual voting weights နဲ့ proposed vote ကိုထည့်ပြီး threshold ပြည့်/မပြည့်ကိုတွက်ပါ။',
            'fields' => [
                $number('threshold', 'Approval Threshold (%)', 'Approval Threshold %', null, 50, 0, 100),
                $repeater('votes', 'Votes', 'Votes', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'weight', 'type' => 'number', 'label_mm' => 'Voting Weight', 'label_en' => 'Voting Weight', 'min' => 0],
                    ['name' => 'vote', 'type' => 'select', 'label_mm' => 'Vote', 'label_en' => 'Vote', 'options' => ['yes' => 'Yes', 'no' => 'No', 'abstain' => 'Abstain']],
                ]),
            ],
        ],

        'meeting_decision_log' => [
            'chapter' => 6,
            'handler' => 'decision_log',
            'record_type' => 'meeting_decision',
            'title_mm' => 'Meeting Decision Log',
            'purpose_mm' => 'Meeting decision ကို date, owner, rationale နဲ့ follow-up အပါအဝင်မှတ်တမ်းတင်ပါ။',
            'fields' => [
                $date('decision_date', 'Decision Date', 'Decision Date'),
                $text('decision', 'ဆုံးဖြတ်ချက်', 'Decision'),
                $text('owner', 'Action Owner', 'Action Owner'),
                $textarea('rationale', 'ဘာကြောင့်ဒီလိုဆုံးဖြတ်ခဲ့သလဲ', 'Rationale'),
                $text('follow_up', 'Next Action / Follow-up', 'Follow-up'),
            ],
        ],

        'deadlock_detector' => [
            'chapter' => 6,
            'handler' => 'deadlock',
            'title_mm' => 'Deadlock Detector',
            'purpose_mm' => 'Voting structure နဲ့ threshold ကိုကြည့်ပြီး decision deadlock ဖြစ်နေမနေကို signal ပေးပါ။',
            'fields' => [
                $number('threshold', 'Required Threshold (%)', 'Required Threshold %', null, 50, 0, 100),
                $number('yes_weight', 'Yes Voting Weight', 'Yes Weight'),
                $number('no_weight', 'No Voting Weight', 'No Weight'),
                $number('abstain_weight', 'Abstain Weight', 'Abstain Weight'),
                $select('fallback_rule', 'Deadlock ဖြစ်ရင် အသုံးပြုမည့် Rule', 'Fallback Rule', [
                    'discussion' => 'Re-discussion / Cooling-off',
                    'mediator' => 'Mediator / Neutral Advisor',
                    'casting_vote' => 'Casting Vote (only if legally/agreed valid)',
                    'buy_sell' => 'Buy/Sell or Exit Procedure',
                ]),
            ],
        ],

        'governance_structure_chart' => [
            'chapter' => 6,
            'handler' => 'governance_chart',
            'title_mm' => 'Governance Structure Chart',
            'purpose_mm' => 'Roles, reporting lines နဲ့ escalation paths ကို structure တစ်ခုအဖြစ်ပြပါ။',
            'fields' => [
                $repeater('nodes', 'Governance Structure', 'Governance Structure', [
                    ['name' => 'role', 'type' => 'text', 'label_mm' => 'Role', 'label_en' => 'Role'],
                    ['name' => 'reports_to', 'type' => 'text', 'label_mm' => 'Reports / Escalates To', 'label_en' => 'Reports To'],
                    ['name' => 'mandate', 'type' => 'text', 'label_mm' => 'Mandate', 'label_en' => 'Mandate'],
                ]),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 7 — Withdrawal & Exit
        // -----------------------------------------------------------------
        'partner_buyout_calculator' => [
            'chapter' => 7,
            'handler' => 'buyout',
            'title_mm' => 'Partner Buyout Calculator',
            'purpose_mm' => 'Indicative business value နဲ့ ownership stake အပေါ်မူတည်ပြီး planning buyout value နဲ့ installment scenario ကိုတွက်ပါ။',
            'fields' => [
                $number('business_value', 'Business Equity Value', 'Business Equity Value'),
                $number('ownership_percentage', 'Exiting Partner Ownership (%)', 'Ownership %', null, 0, 0, 100),
                $number('adjustment', 'Agreed Adjustment (+/-)', 'Agreed Adjustment', 'Discount/premium ကို contract/legal review လုပ်ပြီးမှသုံးပါ။', 0, -999999999999, 999999999999),
                $number('payment_months', 'Payment Period (Months)', 'Payment Months', null, 1, 1, 120, '1'),
            ],
        ],

        'exit_value_simulator' => [
            'chapter' => 7,
            'handler' => 'exit_value',
            'title_mm' => 'Exit Value Simulator',
            'purpose_mm' => 'Conservative / Base / Optimistic business value scenarios အောက်မှာ exiting stake ရဲ့ indicative value ကိုနှိုင်းယှဉ်ပါ။',
            'fields' => [
                $number('conservative_value', 'Conservative Business Value', 'Conservative Value'),
                $number('base_value', 'Base Business Value', 'Base Value'),
                $number('optimistic_value', 'Optimistic Business Value', 'Optimistic Value'),
                $number('ownership_percentage', 'Ownership (%)', 'Ownership %', null, 0, 0, 100),
            ],
        ],

        'notice_period_planner' => [
            'chapter' => 7,
            'handler' => 'notice_plan',
            'title_mm' => 'Notice Period Planner',
            'purpose_mm' => 'Exit notice period အတွင်း handover, customer/vendor transition နဲ့ access transfer အတွက် timeline တည်ဆောက်ပါ။',
            'fields' => [
                $date('notice_date', 'Notice Date', 'Notice Date'),
                $number('notice_days', 'Notice Period (Days)', 'Notice Days', null, 30, 0, 730, '1'),
                $number('handover_days', 'Required Handover Days', 'Handover Days', null, 14, 0, 365, '1'),
                $textarea('critical_handover', 'အရေးကြီးဆုံး Handover အရာများ', 'Critical Handover Items'),
            ],
        ],

        'exit_timeline' => [
            'chapter' => 7,
            'handler' => 'exit_timeline',
            'title_mm' => 'Exit Timeline',
            'purpose_mm' => 'Notice ကနေ final settlement / handover အထိ milestone dates ကိုတွက်ပါ။',
            'fields' => [
                $date('start_date', 'Exit Process Start Date', 'Start Date'),
                $number('notice_days', 'Notice Days', 'Notice Days', null, 30, 0, 730, '1'),
                $number('valuation_days', 'Valuation / Price Agreement Days', 'Valuation Days', null, 14, 0, 365, '1'),
                $number('settlement_days', 'Settlement Days After Price Agreement', 'Settlement Days', null, 30, 0, 730, '1'),
            ],
        ],

        'responsibility_handover_checklist' => [
            'chapter' => 7,
            'handler' => 'checklist',
            'title_mm' => 'Responsibility Handover Checklist',
            'purpose_mm' => 'Exiting partner ထွက်မသွားခင် business continuity အတွက် မဖြစ်မနေ handover လုပ်ရမည့်အရာတွေစစ်ပါ။',
            'fields' => [
                $checklist('checks', 'Exit Handover', 'Exit Handover', [
                    'role_handover' => 'Roles / responsibilities handover',
                    'passwords_access' => 'Systems, passwords and access transfer',
                    'customers' => 'Customer relationship handover',
                    'suppliers' => 'Supplier / vendor handover',
                    'documents' => 'Contracts and business documents handover',
                    'bank_authority' => 'Bank/signing authority update',
                    'company_property' => 'Company property return',
                    'final_accounting' => 'Final accounting / settlement review',
                ]),
            ],
        ],

        'business_continuity_planner' => [
            'chapter' => 7,
            'handler' => 'continuity_plan',
            'title_mm' => 'Business Continuity Planner',
            'purpose_mm' => 'Partner တစ်ယောက်ထွက်သွားရင် ရပ်တန့်နိုင်တဲ့ critical functions တွေကို backup owner နဲ့ recovery plan တစ်ခုချင်းစီထားပါ။',
            'fields' => [
                $repeater('functions', 'Critical Functions', 'Critical Functions', [
                    ['name' => 'function', 'type' => 'text', 'label_mm' => 'Critical Function', 'label_en' => 'Function'],
                    ['name' => 'current_owner', 'type' => 'text', 'label_mm' => 'Current Owner', 'label_en' => 'Current Owner'],
                    ['name' => 'backup_owner', 'type' => 'text', 'label_mm' => 'Backup Owner', 'label_en' => 'Backup Owner'],
                    ['name' => 'max_downtime_hours', 'type' => 'number', 'label_mm' => 'Max Downtime Hours', 'label_en' => 'Max Downtime', 'min' => 0],
                ]),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 8 — Death, Disability & Spouse
        // -----------------------------------------------------------------
        'key_person_dependency_map' => [
            'chapter' => 8,
            'handler' => 'key_person',
            'title_mm' => 'Key Person Dependency Map',
            'purpose_mm' => 'Person တစ်ယောက်မရှိရင် ရပ်နိုင်တဲ့ functions နဲ့ backup coverage ကို map လုပ်ပါ။',
            'fields' => [
                $repeater('dependencies', 'Key Person Dependencies', 'Key Person Dependencies', [
                    ['name' => 'person', 'type' => 'text', 'label_mm' => 'Key Person', 'label_en' => 'Person'],
                    ['name' => 'critical_function', 'type' => 'text', 'label_mm' => 'Critical Function', 'label_en' => 'Function'],
                    ['name' => 'backup', 'type' => 'text', 'label_mm' => 'Backup', 'label_en' => 'Backup'],
                    ['name' => 'impact', 'type' => 'number', 'label_mm' => 'Impact 1–5', 'label_en' => 'Impact', 'min' => 1, 'max' => 5, 'step' => '1'],
                ]),
            ],
        ],

        'succession_planner' => [
            'chapter' => 8,
            'handler' => 'succession',
            'title_mm' => 'Succession Planner',
            'purpose_mm' => 'Critical role တစ်ခုချင်းစီအတွက် successor readiness နဲ့ development gap ကိုသတ်မှတ်ပါ။',
            'fields' => [
                $repeater('roles', 'Succession Roles', 'Succession Roles', [
                    ['name' => 'role', 'type' => 'text', 'label_mm' => 'Critical Role', 'label_en' => 'Role'],
                    ['name' => 'successor', 'type' => 'text', 'label_mm' => 'Proposed Successor', 'label_en' => 'Successor'],
                    ['name' => 'readiness', 'type' => 'number', 'label_mm' => 'Readiness 1–5', 'label_en' => 'Readiness', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'development_need', 'type' => 'text', 'label_mm' => 'Development Need', 'label_en' => 'Development Need'],
                ]),
            ],
        ],

        'emergency_authority_planner' => [
            'chapter' => 8,
            'handler' => 'emergency_authority',
            'title_mm' => 'Emergency Authority Planner',
            'purpose_mm' => 'Partner မလုပ်နိုင်တော့တဲ့အခါ temporary authority ဘယ်သူ့ကိုဘယ်လောက်ပေးမလဲကို ကြိုသတ်မှတ်ပါ။',
            'fields' => [
                $text('trigger', 'Emergency Trigger', 'Emergency Trigger', 'ဥပမာ incapacity, hospitalization, unreachable စသည်။'),
                $text('acting_role', 'Temporary Acting Person / Role', 'Acting Person / Role'),
                $number('financial_limit', 'Temporary Financial Limit', 'Financial Limit'),
                $number('valid_days', 'Authority Validity (Days)', 'Valid Days', null, 30, 1, 365, '1'),
                $textarea('restrictions', 'မလုပ်ရသောအရာ / Reserved Decisions', 'Restrictions'),
            ],
        ],

        'ownership_transition_simulator' => [
            'chapter' => 8,
            'handler' => 'ownership_transition',
            'title_mm' => 'Ownership Transition Simulator',
            'purpose_mm' => 'Partner တစ်ယောက်ရဲ့ stake ကို estate/successor/business buyout scenario တစ်ခုအဖြစ် indicative value နဲ့စမ်းကြည့်ပါ။',
            'fields' => [
                $number('business_value', 'Business Equity Value', 'Business Equity Value'),
                $number('ownership_percentage', 'Affected Ownership (%)', 'Ownership %', null, 0, 0, 100),
                $select('transition_path', 'Transition Path', 'Transition Path', [
                    'estate_hold' => 'Estate / heirs temporarily hold',
                    'business_buyout' => 'Business / partners buy out',
                    'named_successor' => 'Named successor (subject to law/agreement)',
                    'other' => 'Other agreed path',
                ]),
            ],
        ],

        'continuity_checklist' => [
            'chapter' => 8,
            'handler' => 'checklist',
            'title_mm' => 'Continuity Checklist',
            'purpose_mm' => 'Death/disability emergency မှာ business မရပ်စေဖို့ core readiness items တွေကိုစစ်ပါ။',
            'fields' => [
                $checklist('checks', 'Continuity Readiness', 'Continuity Readiness', [
                    'successor' => 'Critical roles အတွက် successor / backup ရှိခြင်း',
                    'bank_access' => 'Emergency bank authority plan ရှိခြင်း',
                    'system_access' => 'Critical systems access recovery plan ရှိခြင်း',
                    'contracts' => 'Key contracts / documents central storage ရှိခြင်း',
                    'customer_continuity' => 'Major customer relationship backup ရှိခြင်း',
                    'insurance_review' => 'Key-person / life / business insurance needs review လုပ်ထားခြင်း',
                    'legal_review' => 'Ownership/inheritance implications ကို local professional နဲ့ review လုပ်ထားခြင်း',
                ]),
            ],
        ],

        'insurance_coverage_gap_calculator' => [
            'chapter' => 8,
            'handler' => 'insurance_gap',
            'title_mm' => 'Insurance Coverage Gap Calculator',
            'purpose_mm' => 'Potential buyout, debt payoff နဲ့ continuity costs ကို current relevant coverage နဲ့နှိုင်းပြီး planning gap ကိုတွက်ပါ။',
            'fields' => [
                $number('buyout_need', 'Estimated Buyout Funding Need', 'Buyout Need'),
                $number('debt_need', 'Debt / Guarantee Funding Need', 'Debt Need'),
                $number('continuity_cost', 'Continuity / Replacement Cost', 'Continuity Cost'),
                $number('existing_coverage', 'Existing Relevant Coverage', 'Existing Coverage'),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 9 — Share Transfer
        // -----------------------------------------------------------------
        'share_transfer_simulator' => [
            'chapter' => 9,
            'handler' => 'share_transfer',
            'title_mm' => 'Share Transfer Simulator',
            'purpose_mm' => 'Seller က Buyer ဆီ units လွှဲပြီးနောက် before/after ownership percentages ကိုတွက်ပါ။',
            'fields' => [
                $number('total_units', 'Total Ownership Units', 'Total Units', null, 100, 1, 1000000000, '1'),
                $text('seller_name', 'Seller', 'Seller'),
                $number('seller_units', 'Seller Current Units', 'Seller Current Units', null, 0, 0, 1000000000, '1'),
                $text('buyer_name', 'Buyer', 'Buyer'),
                $number('buyer_units', 'Buyer Current Units', 'Buyer Current Units', null, 0, 0, 1000000000, '1'),
                $number('transfer_units', 'Transfer Units', 'Transfer Units', null, 0, 0, 1000000000, '1'),
            ],
        ],

        'ownership_before_after_chart' => [
            'chapter' => 9,
            'handler' => 'before_after',
            'title_mm' => 'Before / After Ownership Chart',
            'purpose_mm' => 'Ownership change တစ်ခုမလုပ်ခင်နဲ့လုပ်ပြီးနောက် percentage ကို partner တစ်ယောက်ချင်းစီအလိုက်နှိုင်းပါ။',
            'fields' => [
                $repeater('partners', 'Before / After Units', 'Before / After Units', [
                    ['name' => 'name', 'type' => 'text', 'label_mm' => 'Partner', 'label_en' => 'Partner'],
                    ['name' => 'before_units', 'type' => 'number', 'label_mm' => 'Before Units', 'label_en' => 'Before Units', 'min' => 0, 'step' => '1'],
                    ['name' => 'after_units', 'type' => 'number', 'label_mm' => 'After Units', 'label_en' => 'After Units', 'min' => 0, 'step' => '1'],
                ]),
            ],
        ],

        'first_refusal_workflow' => [
            'chapter' => 9,
            'handler' => 'rofr',
            'title_mm' => 'Right of First Refusal Workflow',
            'purpose_mm' => 'Existing partners ကို first offer ပေးဖို့ internal workflow ကို timeline နဲ့ record လုပ်ပါ။ Enforceability ကို local agreement/law နဲ့စစ်ရပါမယ်။',
            'fields' => [
                $date('offer_date', 'Offer Date', 'Offer Date'),
                $number('response_days', 'Response Period (Days)', 'Response Days', null, 14, 1, 365, '1'),
                $number('transfer_units', 'Units Offered', 'Units Offered', null, 0, 0, 1000000000, '1'),
                $number('price_per_unit', 'Offer Price per Unit', 'Price per Unit'),
                $textarea('conditions', 'Offer Conditions', 'Offer Conditions'),
            ],
        ],

        'transfer_approval_matrix' => [
            'chapter' => 9,
            'handler' => 'transfer_approval',
            'title_mm' => 'Transfer Approval Matrix',
            'purpose_mm' => 'Transfer type အလိုက် approval requirements ကိုသတ်မှတ်ပါ။',
            'fields' => [
                $repeater('rules', 'Transfer Rules', 'Transfer Rules', [
                    ['name' => 'transfer_type', 'type' => 'text', 'label_mm' => 'Transfer Type', 'label_en' => 'Transfer Type'],
                    ['name' => 'approval_rule', 'type' => 'select', 'label_mm' => 'Approval', 'label_en' => 'Approval', 'options' => ['none' => 'No internal approval', 'majority' => 'Majority', 'supermajority' => 'Supermajority', 'unanimous' => 'Unanimous']],
                    ['name' => 'preemption', 'type' => 'select', 'label_mm' => 'First Offer?', 'label_en' => 'First Offer', 'options' => ['yes' => 'Yes', 'no' => 'No']],
                    ['name' => 'notes', 'type' => 'text', 'label_mm' => 'Conditions', 'label_en' => 'Conditions'],
                ]),
            ],
        ],

        'share_valuation_calculator' => [
            'chapter' => 9,
            'handler' => 'transfer_value',
            'title_mm' => 'Share Transfer Value Calculator',
            'purpose_mm' => 'Business equity estimate နဲ့ units အပေါ်မူတည်ပြီး transfer stake ရဲ့ indicative value ကိုတွက်ပါ။',
            'fields' => [
                $number('business_value', 'Business Equity Value', 'Business Equity Value'),
                $number('total_units', 'Total Ownership Units', 'Total Units', null, 100, 1, 1000000000, '1'),
                $number('transfer_units', 'Transfer Units', 'Transfer Units', null, 1, 0, 1000000000, '1'),
            ],
        ],

        'transfer_history_tracker' => [
            'chapter' => 9,
            'handler' => 'transfer_history',
            'record_type' => 'share_transfer',
            'title_mm' => 'Transfer History Tracker',
            'purpose_mm' => 'Ownership transfer event တစ်ခုချင်းစီကို audit trail အဖြစ်မှတ်တမ်းတင်ပါ။',
            'fields' => [
                $date('transfer_date', 'Transfer Date', 'Transfer Date'),
                $text('from_holder', 'From', 'From Holder'),
                $text('to_holder', 'To', 'To Holder'),
                $number('units', 'Transferred Units', 'Units', null, 0, 0, 1000000000, '1'),
                $number('price_per_unit', 'Price per Unit', 'Price per Unit'),
                $textarea('reference', 'Agreement / Reference Notes', 'Reference'),
            ],
        ],

        // -----------------------------------------------------------------
        // Chapter 10 — Dispute Resolution
        // -----------------------------------------------------------------
        'conflict_escalation_ladder' => [
            'chapter' => 10,
            'handler' => 'escalation_ladder',
            'title_mm' => 'Conflict Escalation Ladder',
            'purpose_mm' => 'Dispute ဖြစ်တဲ့အခါ direct discussion ကနေ mediation/legal route အထိ internal escalation sequence ကိုကြိုသတ်မှတ်ပါ။',
            'fields' => [
                $repeater('steps', 'Escalation Steps', 'Escalation Steps', [
                    ['name' => 'step', 'type' => 'text', 'label_mm' => 'Step', 'label_en' => 'Step'],
                    ['name' => 'owner', 'type' => 'text', 'label_mm' => 'Responsible Person / Role', 'label_en' => 'Owner'],
                    ['name' => 'days', 'type' => 'number', 'label_mm' => 'Max Days', 'label_en' => 'Max Days', 'min' => 0, 'max' => 365, 'step' => '1'],
                    ['name' => 'success_condition', 'type' => 'text', 'label_mm' => 'Resolution / Exit Condition', 'label_en' => 'Success Condition'],
                ]),
            ],
        ],

        'dispute_log' => [
            'chapter' => 10,
            'handler' => 'dispute_log',
            'record_type' => 'dispute',
            'title_mm' => 'Dispute Log',
            'purpose_mm' => 'Issue တစ်ခုကို facts, parties, severity နဲ့ current stage အလိုက် structured record တစ်ခုအဖြစ်သိမ်းပါ။',
            'fields' => [
                $date('issue_date', 'Issue Date', 'Issue Date'),
                $text('issue_title', 'Issue ခေါင်းစဉ်', 'Issue Title'),
                $textarea('facts', 'သိရှိထားသော Facts', 'Known Facts', 'Opinion မဟုတ်ဘဲ facts / evidence ကိုသီးခြားရေးပါ။'),
                $text('parties', 'Involved Parties', 'Parties'),
                $select('severity', 'Severity', 'Severity', ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']),
                $select('status', 'Status', 'Status', ['open' => 'Open', 'discussion' => 'Discussion', 'mediation' => 'Mediation', 'resolved' => 'Resolved', 'external' => 'External / Professional Route']),
            ],
        ],

        'resolution_tracker' => [
            'chapter' => 10,
            'handler' => 'resolution_tracker',
            'record_type' => 'resolution_action',
            'title_mm' => 'Resolution Tracker',
            'purpose_mm' => 'Dispute ဖြေရှင်းရေး next action, owner, target date နဲ့ status ကို track လုပ်ပါ။',
            'fields' => [
                $text('issue_title', 'Issue', 'Issue'),
                $text('current_stage', 'Current Stage', 'Current Stage'),
                $text('next_action', 'Next Action', 'Next Action'),
                $text('action_owner', 'Action Owner', 'Action Owner'),
                $date('target_date', 'Target Date', 'Target Date'),
                $select('status', 'Status', 'Status', ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'blocked' => 'Blocked', 'done' => 'Done']),
            ],
        ],

        'deadlock_decision_tool' => [
            'chapter' => 10,
            'handler' => 'deadlock',
            'title_mm' => 'Deadlock Decision Tool',
            'purpose_mm' => 'Vote result က threshold မပြည့်ဘဲ ဆုံးဖြတ်မရတဲ့အခါ agreed fallback process ကိုပြပါ။',
            'fields' => [
                $number('threshold', 'Required Threshold (%)', 'Required Threshold %', null, 50, 0, 100),
                $number('yes_weight', 'Yes Weight', 'Yes Weight'),
                $number('no_weight', 'No Weight', 'No Weight'),
                $number('abstain_weight', 'Abstain Weight', 'Abstain Weight'),
                $select('fallback_rule', 'Fallback Rule', 'Fallback Rule', [
                    'discussion' => 'Cooling-off + Re-discussion',
                    'mediator' => 'Neutral Mediator',
                    'expert' => 'Independent Expert for Technical Issue',
                    'exit' => 'Exit / Buy-Sell Procedure',
                ]),
            ],
        ],

        'issue_priority_matrix' => [
            'chapter' => 10,
            'handler' => 'issue_priority',
            'title_mm' => 'Issue Priority Matrix',
            'purpose_mm' => 'Impact, urgency နဲ့ business continuity effect ကိုအသုံးပြုပြီး dispute handling priority ကိုတန်းစီပါ။',
            'fields' => [
                $repeater('issues', 'Issues', 'Issues', [
                    ['name' => 'issue', 'type' => 'text', 'label_mm' => 'Issue', 'label_en' => 'Issue'],
                    ['name' => 'impact', 'type' => 'number', 'label_mm' => 'Impact 1–5', 'label_en' => 'Impact', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'urgency', 'type' => 'number', 'label_mm' => 'Urgency 1–5', 'label_en' => 'Urgency', 'min' => 1, 'max' => 5, 'step' => '1'],
                    ['name' => 'continuity', 'type' => 'number', 'label_mm' => 'Continuity Effect 1–5', 'label_en' => 'Continuity Effect', 'min' => 1, 'max' => 5, 'step' => '1'],
                ]),
            ],
        ],

        'decision_history' => [
            'chapter' => 10,
            'handler' => 'decision_log',
            'record_type' => 'dispute_decision',
            'title_mm' => 'Decision History',
            'purpose_mm' => 'Dispute တစ်ခုအတွင်း ယခင်ဆုံးဖြတ်ချက်တွေ၊ rationale နဲ့ follow-up ကို chronology အဖြစ်သိမ်းပါ။',
            'fields' => [
                $date('decision_date', 'Decision Date', 'Decision Date'),
                $text('decision', 'Decision', 'Decision'),
                $text('owner', 'Decision Owner / Approvers', 'Owner / Approvers'),
                $textarea('rationale', 'Rationale', 'Rationale'),
                $text('follow_up', 'Follow-up', 'Follow-up'),
            ],
        ],

        'escalation_timeline' => [
            'chapter' => 10,
            'handler' => 'escalation_timeline',
            'title_mm' => 'Escalation Timeline',
            'purpose_mm' => 'Dispute start date ကနေ discussion, mediation, external professional review အထိ target milestone dates ကိုတွက်ပါ။',
            'fields' => [
                $date('start_date', 'Dispute Start Date', 'Start Date'),
                $number('discussion_days', 'Direct Discussion Days', 'Discussion Days', null, 7, 0, 365, '1'),
                $number('mediation_days', 'Mediation Window Days', 'Mediation Days', null, 14, 0, 365, '1'),
                $number('external_review_days', 'External Review / Legal Preparation Days', 'External Review Days', null, 30, 0, 730, '1'),
            ],
        ],
    ],

    'shared_notes' => [
        'planning_only_mm' => 'PBR က business planning / management decision support system ဖြစ်ပါတယ်။ Legal, tax, accounting, insurance သို့မဟုတ် certified valuation advice ကို အစားမထိုးပါ။',
        'agreement_mm' => 'Scenario တစ်ခုကို calculate လုပ်တာနဲ့ business agreement မဖြစ်သေးပါ။ Owner/Admin က review လုပ်ပြီး Agreed Business Rule အဖြစ် approve လုပ်မှ operating system ထဲက current rule ဖြစ်ပါမယ်။',
    ],
];
