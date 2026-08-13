<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Business Operating Areas
    |--------------------------------------------------------------------------
    |
    | Chapter numbers remain an internal catalog mapping only. They are never
    | intended to be presented as learning progress inside the logged-in
    | product. The customer-facing language below describes real business
    | operating areas.
    |
    */
    'areas' => [
        1 => [
            'domain' => 'capital',
            'slug' => 'capital',
            'name_mm' => 'မတည်ငွေနှင့် ရင်းနှီးငွေ',
            'name_en' => 'Capital & Funding',
            'short_mm' => 'လိုအပ်ငွေ၊ ရရှိငွေ၊ Partner ထည့်ဝင်ငွေနဲ့ Funding Gap ကို စီမံပါ။',
            'purpose_mm' => 'လုပ်ငန်းစတင်ရန်နဲ့ ဆက်လက်လည်ပတ်ရန် လိုအပ်တဲ့ မတည်ငွေ၊ လည်ပတ်ငွေ၊ Partner ထည့်ဝင်ငွေ၊ အရေးပေါ်အရန်ငွေနဲ့ Funding Gap ကို တစ်နေရာတည်းမှာ စီမံပါ။',
            'priority' => 1,
        ],
        2 => [
            'domain' => 'ownership',
            'slug' => 'ownership',
            'name_mm' => 'ပိုင်ဆိုင်မှုနှင့် အစုရှယ်ယာ',
            'name_en' => 'Ownership & Equity',
            'short_mm' => 'Ownership၊ Units၊ Voting Rights နဲ့ Dilution ကို တစ်နေရာတည်းမှာ ထိန်းချုပ်ပါ။',
            'purpose_mm' => 'Partner တစ်ဦးချင်းစီရဲ့ ပိုင်ဆိုင်မှုရာခိုင်နှုန်း၊ Voting Power၊ Share Value၊ Cap Table နဲ့ နောင်ဖြစ်နိုင်တဲ့ Dilution ကို သေချာသတ်မှတ်ပါ။',
            'priority' => 2,
        ],
        3 => [
            'domain' => 'contribution',
            'slug' => 'contribution',
            'name_mm' => 'Partner တာဝန်နှင့် တန်ဖိုးထည့်ဝင်မှု',
            'name_en' => 'Partner Roles & Contributions',
            'short_mm' => 'Partner တစ်ဦးချင်းစီရဲ့ တာဝန်၊ အချိန်၊ Skill နဲ့ တန်ဖိုးထည့်ဝင်မှုကို ရှင်းလင်းပါ။',
            'purpose_mm' => 'အချိန်၊ ကျွမ်းကျင်မှု၊ တာဝန်၊ အလုပ်အား၊ IP၊ Network နဲ့ ငွေမဟုတ်တဲ့ တန်ဖိုးထည့်ဝင်မှုတွေကို မှတ်တမ်းတင်ပြီး Partner တစ်ဦးချင်းစီရဲ့ Role နဲ့ Accountability ကို ရှင်းလင်းပါ။',
            'priority' => 3,
        ],
        4 => [
            'domain' => 'distribution',
            'slug' => 'distribution',
            'name_mm' => 'အမြတ်၊ လစာနှင့် အရှုံး ခွဲဝေမှု',
            'name_en' => 'Profit, Salary & Distribution',
            'short_mm' => 'Salary၊ Profit Share၊ Reserve နဲ့ Loss Sharing Rule တွေကို သီးခြားသတ်မှတ်ပါ။',
            'purpose_mm' => 'Ownership၊ Salary၊ Work Compensation နဲ့ Profit Share ကို မရောဘဲ Retained Earnings၊ Reserve Fund၊ Reinvestment နဲ့ အရှုံးခွဲဝေမှုအထိ ကြိုတင်သတ်မှတ်ပါ။',
            'priority' => 4,
        ],
        5 => [
            'domain' => 'financial_controls',
            'slug' => 'financial-controls',
            'name_mm' => 'ငွေကြေး စီမံခန့်ခွဲမှုနှင့် ထိန်းချုပ်မှု',
            'name_en' => 'Finance & Controls',
            'short_mm' => 'Budget၊ Cash Flow၊ Expense Approval နဲ့ Bank Authority ကို စနစ်တကျထားပါ။',
            'purpose_mm' => 'Budget၊ Cash Flow၊ Budget vs Actual၊ Expense Approval၊ Bank Authority နဲ့ ကြီးမားတဲ့ Payment တွေအတွက် Approval Threshold တွေကို နေ့စဉ်အသုံးပြုနိုင်အောင် တည်ဆောက်ပါ။',
            'priority' => 5,
        ],
        6 => [
            'domain' => 'governance',
            'slug' => 'governance',
            'name_mm' => 'အုပ်ချုပ်မှုနှင့် ဆုံးဖြတ်ချက် စနစ်',
            'name_en' => 'Governance & Decisions',
            'short_mm' => 'ဘယ်သူက ဘာဆုံးဖြတ်နိုင်သလဲ၊ ဘယ်အချိန် Vote လိုသလဲကို ရှင်းလင်းပါ။',
            'purpose_mm' => 'Decision Rights၊ Authority Level၊ Voting Rule၊ Approval Threshold၊ Decision Log နဲ့ Deadlock ဖြစ်လာရင် အသုံးပြုမယ့် Process ကို ရှင်းလင်းပါ။',
            'priority' => 6,
        ],
        7 => [
            'domain' => 'exit',
            'slug' => 'exit',
            'name_mm' => 'Partner ထွက်ခွာမှုနှင့် Buyout',
            'name_en' => 'Exit & Buyout',
            'short_mm' => 'Partner ထွက်ခွာချိန်မှာ Value၊ Notice၊ Payment နဲ့ Handover ကို ကြိုတင်သတ်မှတ်ပါ။',
            'purpose_mm' => 'Partner တစ်ဦး ထွက်ခွာချင်တဲ့အခါ Indicative Buyout Value၊ Notice Period၊ Payment Terms၊ Handover၊ Access နဲ့ ဆက်လက်တာဝန်တွေကို ကြိုတင်စီမံပါ။',
            'priority' => 7,
        ],
        8 => [
            'domain' => 'continuity',
            'slug' => 'continuity',
            'name_mm' => 'လုပ်ငန်းဆက်လက်မှုနှင့် ဆက်ခံမှု',
            'name_en' => 'Continuity & Succession',
            'short_mm' => 'Key Person Risk၊ Emergency Authority၊ Successor နဲ့ Access Continuity ကို စီမံပါ။',
            'purpose_mm' => 'သေဆုံးမှု၊ မသန်စွမ်းမှု၊ Key Person မရှိတော့မှု၊ Emergency Authority၊ Successor၊ Access၊ Insurance နဲ့ Business Continuity အတွက် ကြိုတင်ပြင်ဆင်ပါ။',
            'priority' => 8,
        ],
        9 => [
            'domain' => 'share_transfer',
            'slug' => 'share-transfer',
            'name_mm' => 'အစုရှယ်ယာ လွှဲပြောင်းမှုနှင့် Partner အသစ်',
            'name_en' => 'Share Transfers & New Partners',
            'short_mm' => 'Share Transfer၊ ROFR၊ Approval၊ Valuation နဲ့ Ownership Change ကို မှတ်တမ်းတင်ပါ။',
            'purpose_mm' => 'Share လွှဲပြောင်းခြင်း၊ Right of First Refusal၊ Approval၊ Valuation၊ Partner အသစ်ဝင်ရောက်မှုနဲ့ Before/After Ownership ကို စည်းမျဉ်းတကျ စီမံပါ။',
            'priority' => 9,
        ],
        10 => [
            'domain' => 'dispute_resolution',
            'slug' => 'disputes',
            'name_mm' => 'Partner အငြင်းပွားမှုနှင့် ဖြေရှင်းရေး',
            'name_en' => 'Conflict & Resolution',
            'short_mm' => 'Issue၊ Evidence၊ Escalation၊ Mediation နဲ့ Resolution History ကို စနစ်တကျထားပါ။',
            'purpose_mm' => 'Issue Facts၊ Severity၊ Urgency၊ သက်ဆိုင်ရာ Agreement၊ Discussion၊ Internal Escalation၊ Mediation နဲ့ လိုအပ်ရင် Arbitration/Legal အဆင့်အထိ အသုံးပြုမယ့် Process ကို ကြိုတင်ထားပါ။',
            'priority' => 10,
        ],
    ],

    'states' => [
        'active' => [
            'label_mm' => 'အသုံးပြုနေသော Rule ရှိသည်',
            'label_en' => 'Active',
            'detail_mm' => 'လက်ရှိ Partnership မှာ အသုံးပြုနေတဲ့ အတည်ပြုထားသော Rule / Record ရှိပါတယ်။',
        ],
        'review' => [
            'label_mm' => 'ပြန်လည်စစ်ဆေးရန်ရှိသည်',
            'label_en' => 'Needs review',
            'detail_mm' => 'Working Draft သို့မဟုတ် ပြောင်းလဲမှုအသစ် ရှိနေပြီး အသုံးမပြုခင် Review / Approval လိုပါတယ်။',
        ],
        'setup' => [
            'label_mm' => 'မသတ်မှတ်ရသေး',
            'label_en' => 'Not configured',
            'detail_mm' => 'ဒီ Business Area အတွက် အတည်ပြုထားတဲ့ Rule သို့မဟုတ် လက်ရှိစီမံချက် မရှိသေးပါ။',
        ],
    ],

    'capital_module_overrides' => [
        'startup_capital_planner' => [
            'title_mm' => 'စတင်မတည်ငွေ အစီအစဉ်',
            'title_en' => 'Startup Capital Plan',
            'purpose_mm' => 'လုပ်ငန်းစတင်ဖို့ တကယ်လိုအပ်မယ့် ကုန်ကျစရိတ်၊ ပစ္စည်း၊ Deposit နဲ့ Opening Cash လိုအပ်ချက်တွေကို စုစည်းပါ။',
            'action_mm' => 'မတည်ငွေ အစီအစဉ် စီမံရန် →',
        ],
        'current_capital_position' => [
            'title_mm' => 'လက်ရှိ မတည်ငွေ အခြေအနေ',
            'title_en' => 'Current Capital Position',
            'purpose_mm' => 'ရှိပြီးသားလုပ်ငန်းရဲ့ လက်ရှိ Capital၊ Partner Funding နဲ့ အသုံးပြုပြီးသား ရင်းနှီးငွေအခြေအနေကို စစ်ဆေးပါ။',
            'action_mm' => 'လက်ရှိ Capital စီမံရန် →',
        ],
        'working_capital_calculator' => [
            'title_mm' => 'လည်ပတ်ငွေ လိုအပ်ချက်',
            'title_en' => 'Working Capital Requirement',
            'purpose_mm' => 'လုပ်ငန်းရဲ့ နေ့စဉ်လည်ပတ်မှုအတွက် လိုအပ်မယ့် Cash Buffer နဲ့ Operating Cost ကို သတ်မှတ်ပါ။',
            'action_mm' => 'လည်ပတ်ငွေ စီမံရန် →',
        ],
        'contingency_fund_calculator' => [
            'title_mm' => 'အရေးပေါ်အရန်ငွေ',
            'title_en' => 'Contingency Reserve',
            'purpose_mm' => 'မမျှော်လင့်ထားတဲ့ ကုန်ကျစရိတ်နဲ့ လုပ်ငန်းအနှောင့်အယှက်အတွက် Reserve ကို သတ်မှတ်ပါ။',
            'action_mm' => 'Reserve စီမံရန် →',
        ],
        'partner_contribution_matrix' => [
            'title_mm' => 'Partner ထည့်ဝင်ငွေ မှတ်တမ်း',
            'title_en' => 'Partner Capital Contributions',
            'purpose_mm' => 'Partner တစ်ဦးချင်းစီရဲ့ Cash၊ Asset နဲ့ အခြား Capital Contribution ကို တစ်နေရာတည်းမှာ မှတ်တမ်းတင်ပါ။',
            'action_mm' => 'Partner ထည့်ဝင်ငွေ စီမံရန် →',
        ],
        'funding_gap_calculator' => [
            'title_mm' => 'လိုအပ်နေသေးသော ရင်းနှီးငွေ',
            'title_en' => 'Funding Position',
            'purpose_mm' => 'လိုအပ်တဲ့ မတည်ငွေနဲ့ လက်ရှိရရှိထားတဲ့ Funding ကို နှိုင်းယှဉ်ပြီး ဘယ်လောက်လိုနေသေးလဲ ကြည့်ပါ။',
            'action_mm' => 'Funding Position စစ်ဆေးရန် →',
        ],
        'capital_allocation_chart' => [
            'title_mm' => 'မတည်ငွေ ခွဲဝေသုံးစွဲမှု',
            'title_en' => 'Capital Allocation',
            'purpose_mm' => 'ရရှိထားတဲ့ မတည်ငွေကို ဘယ်လုပ်ငန်းအပိုင်းတွေမှာ ဘယ်လောက်သုံးမလဲဆိုတာ ခွဲဝေစီမံပါ။',
            'action_mm' => 'ငွေခွဲဝေမှု စီမံရန် →',
        ],
    ],

    'legal_note_mm' => 'PBR က Business Planning နဲ့ Partnership Governance အတွက် decision-support system ဖြစ်ပါတယ်။ ဥပဒေ၊ အခွန်၊ အမွေဆက်ခံမှု၊ စာချုပ်အတည်ပြုမှုလို ကိစ္စတွေမှာ သက်ဆိုင်ရာ နိုင်ငံက qualified lawyer / accountant နဲ့ အတည်ပြုစစ်ဆေးပါ။',
];
