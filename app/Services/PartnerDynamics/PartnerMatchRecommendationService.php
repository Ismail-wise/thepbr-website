<?php

namespace App\Services\PartnerDynamics;

class PartnerMatchRecommendationService
{
    private const DIMENSIONS = [
        'vision' =>
            'အနာဂတ်ဦးတည်ချက် (Vision & Direction)',

        'execution' =>
            'အကောင်အထည်ဖော်မှု (Execution & Delivery)',

        'people' =>
            'လူမှုဆက်ဆံရေး (People & Influence)',

        'analysis' =>
            'ဒေတာနှင့် ဘဏ္ဍာရေး (Analysis & Finance)',

        'structure' =>
            'ဖွဲ့စည်းပုံနှင့် ထိန်းချုပ်မှု (Structure & Control)',

        'risk' =>
            'အခွင့်အလမ်းနှင့် အန္တရာယ် (Risk & Opportunity)',

        'decision' =>
            'ဆုံးဖြတ်ချက်နှင့် ပဋိပက္ခ (Decision & Conflict)',

        'adaptability' =>
            'အပြောင်းအလဲနှင့် လိုက်လျောညီထွေမှု (Adaptability & Change)',
    ];

    private const PROFILE_DESCRIPTIONS = [
        'visionary' =>
            'အနာဂတ် Direction နဲ့ Opportunity အသစ်တွေကို မြင်ပေးနိုင်ပြီး လုပ်ငန်းကို ဘယ်ဘက်သွားမလဲဆိုတာ ဖော်ထုတ်ရာမှာ အားဖြည့်ပေးနိုင်ပါတယ်။',

        'builder' =>
            'Idea နဲ့ Plan တွေကို လက်တွေ့ Action အဖြစ်ပြောင်းပြီး အလုပ်တွေပြီးမြောက်အောင် တွန်းပို့ရာမှာ အားကောင်းပါတယ်။',

        'connector' =>
            'Customer, Team နဲ့ Business Relationship တွေကို တည်ဆောက်ပြီး Communication ပိုကောင်းလာအောင် ကူညီပေးနိုင်ပါတယ်။',

        'analyst' =>
            'Numbers, Data နဲ့ Financial Impact တွေကို သေချာစစ်ဆေးပြီး ဆုံးဖြတ်ချက်တွေ ပိုခိုင်မာလာအောင် ကူညီပေးနိုင်ပါတယ်။',

        'operator' =>
            'နေ့စဉ် Operations, Process နဲ့ Responsibility တွေကို စနစ်တကျထားပြီး Execution တည်ငြိမ်လာအောင် ကူညီပေးနိုင်ပါတယ်။',

        'guardian' =>
            'Risk, Control နဲ့ Downside တွေကို သေချာကြည့်ပြီး လုပ်ငန်းရဲ့ အရေးကြီးတဲ့ဆုံးဖြတ်ချက်တွေမှာ Safeguard ပေးနိုင်ပါတယ်။',

        'negotiator' =>
            'Partner တွေအကြား မတူညီတဲ့အမြင်၊ Conflict နဲ့ ခက်ခဲတဲ့ Decision တွေကို ဆွေးနွေးညှိနှိုင်းပြီး အဖြေရှာရာမှာ အားသာပါတယ်။',

        'optimizer' =>
            'ရှိပြီးသား System နဲ့ Workflow တွေကို ပိုထိရောက်အောင် ပြင်ဆင်ပြီး Business ပြောင်းလဲလာတဲ့အခါ Adapt လုပ်နိုင်အောင် ကူညီပေးနိုင်ပါတယ်။',
    ];

    private const DISCUSSION_POINTS = [
        'vision' =>
            'လုပ်ငန်းရဲ့ ရေရှည် Direction နဲ့ Opportunity အသစ်တွေကို ဘယ်လိုရွေးချယ်မလဲဆိုတာ ကြိုတင်သဘောတူထားပါ။',

        'execution' =>
            'ဘယ်သူက Follow-up လုပ်မလဲ၊ Deadline နဲ့ Delivery ကို ဘယ်သူတာဝန်ယူမလဲဆိုတာ ရှင်းလင်းထားပါ။',

        'people' =>
            'Customer, Team နဲ့ External Partner Relationship တွေကို ဘယ်သူဦးဆောင်မလဲဆိုတာ သတ်မှတ်ထားပါ။',

        'analysis' =>
            'အရေးကြီးတဲ့ Spending နဲ့ Financial Decision တွေမှာ Data ဘယ်လောက်လိုအပ်မလဲဆိုတာ သဘောတူထားပါ။',

        'structure' =>
            'Role, Responsibility, Approval Authority နဲ့ Process တွေကို Partnership မစခင် ရှင်းရှင်းလင်းလင်းသတ်မှတ်ထားပါ။',

        'risk' =>
            'Business Risk ဘယ်လောက်အထိ လက်ခံနိုင်မလဲ၊ ဘယ်အခြေအနေမှာ Partner နှစ်ယောက်လုံး Approval လိုမလဲဆိုတာ သဘောတူထားပါ။',

        'decision' =>
            'Major Decision နဲ့ Conflict ဖြစ်လာတဲ့အခါ ဘယ်လိုဆုံးဖြတ်မလဲ၊ Deadlock ကို ဘယ်လိုဖြေရှင်းမလဲဆိုတာ ကြိုတင်သတ်မှတ်ထားပါ။',

        'adaptability' =>
            'မူလ Plan ကို ဘယ်အခြေအနေမှာ ပြောင်းနိုင်မလဲ၊ အရေးကြီးတဲ့ Change ကို ဘယ်သူ Approve လုပ်နိုင်မလဲဆိုတာ သဘောတူထားပါ။',
    ];

    public function recommend(
        array $dimensionScores,
        ?string $primaryProfile = null,
        ?string $secondaryProfile = null
    ): array {
        $scores = $this->normalizeDimensions(
            $dimensionScores
        );

        $needs = $this->buildNeeds($scores);

        $profileDefinitions =
            config('partner_dynamics.profiles', []);

        $excluded = array_filter([
            strtolower((string) $primaryProfile),
            strtolower((string) $secondaryProfile),
        ]);

        $candidates = [];

        foreach (
            $profileDefinitions
            as $profileKey => $profile
        ) {
            if (in_array(
                $profileKey,
                $excluded,
                true
            )) {
                continue;
            }

            $coverage = [];
            $score = 0.0;

            foreach (
                $profile['weights'] ?? []
                as $dimension => $weight
            ) {
                $needKey = $dimension;

                if (
                    $dimension === 'cautious_risk'
                ) {
                    $needKey = 'cautious_risk';
                }

                $need =
                    $needs[$needKey]['need']
                    ?? 0;

                $contribution =
                    $need * (float) $weight;

                $score += $contribution;

                if ($contribution > 0) {
                    $coverage[] = [
                        'dimension' => $needKey,
                        'label' =>
                            $needs[$needKey]['label']
                            ?? $needKey,
                        'need' => $need,
                        'weight' => (float) $weight,
                        'contribution' =>
                            round(
                                $contribution,
                                2
                            ),
                    ];
                }
            }

            usort(
                $coverage,
                fn (array $a, array $b) =>
                    $b['contribution']
                    <=> $a['contribution']
            );

            $candidates[] = [
                'profile_key' => $profileKey,
                'name' =>
                    $profile['name']
                    ?? ucfirst($profileKey),
                'score' => round($score, 2),
                'coverage' => $coverage,
            ];
        }

        usort(
            $candidates,
            fn (array $a, array $b) =>
                $b['score'] <=> $a['score']
        );

        $top = array_slice(
            $candidates,
            0,
            3
        );

        $labels = [
            'Strong Complement',
            'Balanced Complement',
            'Supporting Complement',
        ];

        foreach ($top as $index => &$candidate) {
            $candidate['recommendation_label'] =
                $labels[$index]
                ?? 'Complement';

            $candidate['strengthens'] =
                array_slice(
                    $candidate['coverage'],
                    0,
                    3
                );

            $candidate['description'] =
                self::PROFILE_DESCRIPTIONS[
                    $candidate['profile_key']
                ]
                ?? '';

            $candidate['reason'] =
                $this->buildReason(
                    $candidate
                );

            $candidate['discussion_points'] =
                $this->discussionPoints(
                    $candidate
                );

            unset(
                $candidate['score'],
                $candidate['coverage']
            );
        }

        unset($candidate);

        return [
            'priority_needs' =>
                $this->priorityNeeds($needs),

            'recommendations' => $top,

            'note' =>
                'ဒီ Recommendation တွေက သင့် working style ကို ဖြည့်ဆည်းပေးနိုင်မယ့် Partner Type တွေကို Suggest လုပ်တာဖြစ်ပြီး အောင်မြင်မယ့် Partnership ကို အာမခံတာမဟုတ်ပါဘူး။ Values, Trust, Business Goals, Financial Expectations နဲ့ Working History တွေကိုလည်း အတူတူစဉ်းစားဖို့လိုပါတယ်။',
        ];
    }

    private function normalizeDimensions(
        array $dimensionScores
    ): array {
        $normalized = [];

        foreach (
            self::DIMENSIONS
            as $key => $label
        ) {
            $value = $dimensionScores[$key] ?? 50;

            $normalized[$key] = max(
                0,
                min(
                    100,
                    (float) $value
                )
            );
        }

        return $normalized;
    }

    private function buildNeeds(
        array $scores
    ): array {
        $needs = [];

        foreach (
            self::DIMENSIONS
            as $key => $label
        ) {
            if ($key === 'risk') {
                continue;
            }

            $needs[$key] = [
                'dimension' => $key,
                'label' => $label,
                'current_score' =>
                    round($scores[$key], 1),
                'need' => round(
                    max(
                        0,
                        70 - $scores[$key]
                    ),
                    2
                ),
            ];
        }

        $riskScore = $scores['risk'];

        /*
         * Risk is different from the other dimensions.
         *
         * A high score can benefit from caution and control.
         * A low score can benefit from opportunity-taking.
         * Mid-range scores do not create a large matching need.
         */
        $needs['risk'] = [
            'dimension' => 'risk',
            'label' =>
                'အခွင့်အလမ်းကို ရဲရဲယူနိုင်မှု (Calculated Risk)',
            'current_score' =>
                round($riskScore, 1),
            'need' =>
                $riskScore < 40
                    ? round(
                        (40 - $riskScore)
                        * 1.5,
                        2
                    )
                    : 0,
        ];

        $needs['cautious_risk'] = [
            'dimension' => 'cautious_risk',
            'label' =>
                'အန္တရာယ်ထိန်းချုပ်မှု (Risk Control)',
            'current_score' =>
                round($riskScore, 1),
            'need' =>
                $riskScore > 60
                    ? round(
                        ($riskScore - 60)
                        * 1.5,
                        2
                    )
                    : 0,
        ];

        return $needs;
    }

    private function priorityNeeds(
        array $needs
    ): array {
        $rows = array_values(
            array_filter(
                $needs,
                fn (array $need) =>
                    $need['need'] > 0
            )
        );

        usort(
            $rows,
            fn (array $a, array $b) =>
                $b['need'] <=> $a['need']
        );

        return array_slice(
            $rows,
            0,
            4
        );
    }

    private function buildReason(
        array $candidate
    ): string {
        $labels = array_values(
            array_unique(
                array_map(
                    fn (array $row) =>
                        $row['label'],
                    array_slice(
                        $candidate['strengthens'],
                        0,
                        2
                    )
                )
            )
        );

        if (count($labels) >= 2) {
            return sprintf(
                '%s Type က သင့်လက်ရှိ operating pattern ကို %s နဲ့ %s ဘက်မှာ ပိုအားကောင်းလာအောင် ဖြည့်ဆည်းပေးနိုင်ပါတယ်။',
                $candidate['name'],
                $labels[0],
                $labels[1]
            );
        }

        if (count($labels) === 1) {
            return sprintf(
                '%s Type က သင့်လက်ရှိ operating pattern ကို %s ဘက်မှာ ပိုအားကောင်းလာအောင် ဖြည့်ဆည်းပေးနိုင်ပါတယ်။',
                $candidate['name'],
                $labels[0]
            );
        }

        return sprintf(
            '%s Type က သင့်ရဲ့လက်ရှိ working style နဲ့ မတူညီတဲ့ အားသာချက်တွေယူဆောင်လာပြီး Partnership ကို ပို Balance ဖြစ်စေနိုင်ပါတယ်။',
            $candidate['name']
        );
    }

    private function discussionPoints(
        array $candidate
    ): array {
        $points = [];

        foreach (
            $candidate['strengthens']
            as $strength
        ) {
            $dimension =
                $strength['dimension'];

            if ($dimension === 'cautious_risk') {
                $dimension = 'risk';
            }

            $point =
                self::DISCUSSION_POINTS[
                    $dimension
                ]
                ?? null;

            if ($point) {
                $points[] = $point;
            }
        }

        return array_values(
            array_unique(
                array_slice(
                    $points,
                    0,
                    3
                )
            )
        );
    }
}
