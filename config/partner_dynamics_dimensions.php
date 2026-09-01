<?php

/*
|--------------------------------------------------------------------------
| Partner Dynamics — Operating Dimensions
|--------------------------------------------------------------------------
|
| The eight dimensions the assessment scores, and what each is called.
|
| 'name'    is the English label used in the result page, the workspace
|           profile view, and the alignment report.
| 'name_mm' is the fuller Burmese label used by the partner match
|           recommendations, which keeps the English in parentheses
|           because the terms appear that way elsewhere in the product.
|
| The key order is the order these appear on screen, and it must match the
| DIMENSIONS list inside PartnerDynamicsScoringService. That list stays
| where it is — the scoring engine should not depend on presentation
| config — and a test asserts the two agree.
|
*/

return [

    'vision' => [
        'name' => 'Vision & Direction',
        'name_mm' => 'အနာဂတ်ဦးတည်ချက် (Vision & Direction)',
    ],

    'execution' => [
        'name' => 'Execution & Delivery',
        'name_mm' => 'အကောင်အထည်ဖော်မှု (Execution & Delivery)',
    ],

    'people' => [
        'name' => 'People & Influence',
        'name_mm' => 'လူမှုဆက်ဆံရေး (People & Influence)',
    ],

    'analysis' => [
        'name' => 'Analysis & Finance',
        'name_mm' => 'ဒေတာနှင့် ဘဏ္ဍာရေး (Analysis & Finance)',
    ],

    'structure' => [
        'name' => 'Structure & Control',
        'name_mm' => 'ဖွဲ့စည်းပုံနှင့် ထိန်းချုပ်မှု (Structure & Control)',
    ],

    'risk' => [
        'name' => 'Risk & Opportunity',
        'name_mm' => 'အခွင့်အလမ်းနှင့် အန္တရာယ် (Risk & Opportunity)',
    ],

    'decision' => [
        'name' => 'Decision & Conflict',
        'name_mm' => 'ဆုံးဖြတ်ချက်နှင့် ပဋိပက္ခ (Decision & Conflict)',
    ],

    'adaptability' => [
        'name' => 'Adaptability & Change',
        'name_mm' => 'အပြောင်းအလဲနှင့် လိုက်လျောညီထွေမှု (Adaptability & Change)',
    ],

];
