<?php

namespace App\Services\PartnerDynamics;

use InvalidArgumentException;

class PartnerDynamicsAlignmentService
{
    private const DIMENSIONS = [
        'vision' => 'Vision & Direction',
        'execution' => 'Execution & Delivery',
        'people' => 'People & Influence',
        'analysis' => 'Analysis & Finance',
        'structure' => 'Structure & Control',
        'risk' => 'Risk & Opportunity',
        'decision' => 'Decision & Conflict',
        'adaptability' => 'Adaptability & Change',
    ];

    private const ROLE_SUGGESTIONS = [
        'visionary' => [
            'Long-term direction နဲ့ growth opportunities ကို lead လုပ်ရန်',
            'New ideas, products, services နဲ့ business model discussions ကို drive လုပ်ရန်',
            'Big-picture strategy ကို partnership ရဲ့ day-to-day work နဲ့ connect လုပ်ရန်',
        ],
        'builder' => [
            'Ideas ကို action plan အဖြစ်ပြောင်းပြီး execution ကို lead လုပ်ရန်',
            'Projects, launches နဲ့ deadlines တွေကို ownership ယူရန်',
            'Decisions ပြီးရင် follow-through သေချာစေရန်',
        ],
        'connector' => [
            'Customer, supplier နဲ့ external partner relationships ကို lead လုပ်ရန်',
            'Team communication နဲ့ stakeholder alignment ကို support လုပ်ရန်',
            'Partnership အတွင်း communication gaps ကို လျှော့ချရန်',
        ],
        'analyst' => [
            'Financial impact, numbers နဲ့ evidence ကို review လုပ်ရန်',
            'Major decisions မတိုင်ခင် assumptions နဲ့ downside ကို စစ်ဆေးရန်',
            'KPIs နဲ့ performance information ကို track လုပ်ရန်',
        ],
        'operator' => [
            'Daily operations, workflow နဲ့ responsibility clarity ကို lead လုပ်ရန်',
            'Repeated work တွေအတွက် standard processes ဖန်တီးရန်',
            'Execution consistency နဲ့ accountability ကို ထိန်းသိမ်းရန်',
        ],
        'guardian' => [
            'Risk controls, approvals နဲ့ business safeguards ကို lead လုပ်ရန်',
            'Major commitments မတိုင်ခင် downside နဲ့ exposure ကို စစ်ဆေးရန်',
            'Written rules, controls နဲ့ responsibility boundaries ကို သေချာစေရန်',
        ],
        'negotiator' => [
            'Partner disagreements နဲ့ difficult discussions ကို facilitate လုပ်ရန်',
            'Decision deadlock ဖြစ်တဲ့အခါ common ground ရှာရန်',
            'Commercial terms, responsibilities နဲ့ expectations ကို negotiate လုပ်ရန်',
        ],
        'optimizer' => [
            'Existing systems နဲ့ workflows ကို ပိုကောင်းအောင် improve လုပ်ရန်',
            'Business change ဖြစ်လာတဲ့အခါ operating approach ကို adapt လုပ်ရန်',
            'Efficiency, process improvement နဲ့ continuous improvement ကို lead လုပ်ရန်',
        ],
    ];

    public function analyze(array $participants): array
    {
        $this->validateParticipants($participants);

        $sharedStrengths = [];
        $complementaryAreas = [];
        $importantDifferences = [];
        $sharedBlindSpots = [];

        foreach (self::DIMENSIONS as $dimension => $label) {
            $scores = [];

            foreach ($participants as $participant) {
                $scores[] = [
                    'user_id' => $participant['user_id'],
                    'name' => $participant['name'],
                    'score' => round((float) $participant['dimension_scores'][$dimension], 1),
                ];
            }

            $values = array_column($scores, 'score');

            $min = min($values);
            $max = max($values);
            $average = round(array_sum($values) / count($values), 1);
            $gap = round($max - $min, 1);

            $highest = $scores[array_search($max, $values, true)];
            $lowest = $scores[array_search($min, $values, true)];

            if ($min >= 70) {
                $sharedStrengths[] = [
                    'dimension' => $dimension,
                    'label' => $label,
                    'average_score' => $average,
                    'scores' => $scores,
                    'message' => "{$label} က Partner အားလုံးအတွက် shared strength တစ်ခုဖြစ်နိုင်ပါတယ်။ ဒီအားသာချက်ကို intentional ownership နဲ့ အသုံးချပါ။",
                ];
            }

            if ($max <= 45) {
                $sharedBlindSpots[] = [
                    'dimension' => $dimension,
                    'label' => $label,
                    'average_score' => $average,
                    'scores' => $scores,
                    'message' => "{$label} မှာ Partner အားလုံးရဲ့ score နိမ့်နေပါတယ်။ Weakness လို့မသတ်မှတ်ဘဲ extra process, adviser ဒါမှမဟုတ် review checkpoint ထည့်ဖို့ စဉ်းစားပါ။",
                ];
            }

            if ($gap >= 30) {
                $importantDifferences[] = [
                    'dimension' => $dimension,
                    'label' => $label,
                    'highest_participant' => $highest,
                    'lowest_participant' => $lowest,
                    'gap' => $gap,
                    'message' => "{$label} မှာ operating preference ကွာခြားမှုကြီးရှိပါတယ်။ ဘယ်သူက ဘယ်အခြေအနေမှာ decision ownership ယူမလဲဆိုတာ ကြိုတင်သဘောတူထားသင့်ပါတယ်။",
                ];
            } elseif ($max >= 70 && $min <= 60 && $gap >= 15) {
                $complementaryAreas[] = [
                    'dimension' => $dimension,
                    'label' => $label,
                    'stronger_participant' => $highest,
                    'supporting_participant' => $lowest,
                    'gap' => $gap,
                    'message' => "{$highest['name']} ရဲ့ {$label} strength က partnership အတွက် complementary contribution တစ်ခုဖြစ်နိုင်ပါတယ်။ Role assignment ကို score တစ်ခုတည်းနဲ့ မဆုံးဖြတ်ဘဲ experience နဲ့ availability ကိုပါ ထည့်ဆွေးနွေးပါ။",
                ];
            }
        }

        $roleSuggestions = $this->buildRoleSuggestions($participants);

        $decisionRecommendations = $this->buildDecisionRecommendations(
            $importantDifferences,
            $sharedBlindSpots
        );

        $discussionPriorities = $this->buildDiscussionPriorities(
            $importantDifferences,
            $sharedBlindSpots,
            $complementaryAreas,
            $sharedStrengths
        );

        return [
            'alignment_summary' => [
                'participant_count' => count($participants),
                'participants' => array_map(
                    fn (array $participant): array => [
                        'user_id' => $participant['user_id'],
                        'name' => $participant['name'],
                        'primary_profile' => $participant['primary_profile'],
                        'secondary_profile' => $participant['secondary_profile'] ?? null,
                    ],
                    $participants
                ),
                'shared_strength_count' => count($sharedStrengths),
                'complementary_area_count' => count($complementaryAreas),
                'important_difference_count' => count($importantDifferences),
                'shared_blind_spot_count' => count($sharedBlindSpots),
                'note' => 'ဒီ report က partnership compatibility score မဟုတ်ပါဘူး။ Better discussion, clearer roles နဲ့ stronger decision rules အတွက် guide အဖြစ်အသုံးပြုရန်ဖြစ်ပါတယ်။',
            ],
            'shared_strengths' => $sharedStrengths,
            'complementary_areas' => $complementaryAreas,
            'important_differences' => $importantDifferences,
            'shared_blind_spots' => $sharedBlindSpots,
            'role_suggestions' => $roleSuggestions,
            'decision_recommendations' => $decisionRecommendations,
            'discussion_priorities' => $discussionPriorities,
        ];
    }

    private function validateParticipants(array $participants): void
    {
        if (count($participants) < 2) {
            throw new InvalidArgumentException(
                'Partner Alignment requires at least two completed assessments.'
            );
        }

        foreach ($participants as $participant) {
            foreach (['user_id', 'name', 'primary_profile', 'dimension_scores'] as $field) {
                if (! array_key_exists($field, $participant)) {
                    throw new InvalidArgumentException(
                        "Participant is missing required field: {$field}"
                    );
                }
            }

            foreach (array_keys(self::DIMENSIONS) as $dimension) {
                if (! array_key_exists($dimension, $participant['dimension_scores'])) {
                    throw new InvalidArgumentException(
                        "Participant is missing dimension score: {$dimension}"
                    );
                }

                $score = $participant['dimension_scores'][$dimension];

                if (! is_numeric($score) || $score < 0 || $score > 100) {
                    throw new InvalidArgumentException(
                        "Invalid dimension score for {$dimension}."
                    );
                }
            }
        }
    }

    private function buildRoleSuggestions(array $participants): array
    {
        return array_map(function (array $participant): array {
            $profile = strtolower($participant['primary_profile']);

            return [
                'user_id' => $participant['user_id'],
                'name' => $participant['name'],
                'primary_profile' => $profile,
                'secondary_profile' => $participant['secondary_profile'] ?? null,
                'suggestions' => self::ROLE_SUGGESTIONS[$profile] ?? [
                    'Strengths, experience နဲ့ availability ကိုအခြေခံပြီး role ownership ကို ဆွေးနွေးပါ။',
                ],
                'note' => 'ဒါက fixed job description မဟုတ်ပါဘူး။ Partnership discussion အတွက် starting point ဖြစ်ပါတယ်။',
            ];
        }, $participants);
    }

    private function buildDecisionRecommendations(
        array $importantDifferences,
        array $sharedBlindSpots
    ): array {
        $recommendations = [];

        foreach ($importantDifferences as $difference) {
            $dimension = $difference['dimension'];

            $recommendations[] = match ($dimension) {
                'risk' => [
                    'title' => 'Risk Limits ကို ကြိုတင်သတ်မှတ်ပါ',
                    'message' => 'Investment size, borrowing, guarantees နဲ့ major commitments တွေအတွက် approval thresholds ကို written rule အဖြစ်ထားပါ။',
                ],
                'decision' => [
                    'title' => 'Decision Deadlock Rule ထားပါ',
                    'message' => 'Partner တွေသဘောမတူတဲ့အခါ ဘယ်လိုဆုံးဖြတ်မလဲ၊ ဘယ်သူ့မှာ final authority ရှိမလဲ၊ ဘယ်အချိန် external adviser သုံးမလဲဆိုတာ သဘောတူထားပါ။',
                ],
                'analysis' => [
                    'title' => 'Evidence Requirement ကို သတ်မှတ်ပါ',
                    'message' => 'Major decision မချခင် ဘယ် financial information, numbers နဲ့ assumptions တွေလိုအပ်မလဲဆိုတာ သဘောတူထားပါ။',
                ],
                'structure' => [
                    'title' => 'Roles & Approval Boundaries ရေးထားပါ',
                    'message' => 'Role ownership, spending authority နဲ့ approval responsibility ကို ရေးသားသတ်မှတ်ထားပါ။',
                ],
                'vision' => [
                    'title' => 'Strategy Review Rhythm ထားပါ',
                    'message' => 'Long-term direction နဲ့ current priorities ကို monthly သို့ quarterly review လုပ်ဖို့ သတ်မှတ်ပါ။',
                ],
                'execution' => [
                    'title' => 'Decision-to-Action Owner သတ်မှတ်ပါ',
                    'message' => 'Decision တစ်ခုချပြီးတိုင်း owner, deadline နဲ့ follow-up checkpoint ရှိအောင်လုပ်ပါ။',
                ],
                'people' => [
                    'title' => 'Communication Ownership ရှင်းပါ',
                    'message' => 'Customers, staff, suppliers နဲ့ partners ကို ဘယ်သူက communicate လုပ်မလဲ သတ်မှတ်ပါ။',
                ],
                'adaptability' => [
                    'title' => 'Change Trigger သတ်မှတ်ပါ',
                    'message' => 'Plan ပြောင်းသင့်တဲ့အချိန်ကို opinion အပေါ်မထားဘဲ measurable trigger တွေနဲ့ ကြိုတင်သတ်မှတ်ပါ။',
                ],
                default => [
                    'title' => 'Decision Rule သတ်မှတ်ပါ',
                    'message' => 'ဒီကွာခြားမှုရှိတဲ့ area အတွက် ownership နဲ့ escalation rule ကို သဘောတူထားပါ။',
                ],
            };
        }

        foreach ($sharedBlindSpots as $blindSpot) {
            $recommendations[] = [
                'title' => $blindSpot['label'].' Review Checkpoint ထည့်ပါ',
                'message' => 'ဒီ area မှာ Partner အားလုံးရဲ့ natural preference နိမ့်နေတဲ့အတွက် checklist, adviser, external review ဒါမှမဟုတ် scheduled checkpoint တစ်ခုထည့်ပါ။',
            ];
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'title' => 'Clear Decision Rights ကို ထိန်းသိမ်းပါ',
                'message' => 'Alignment ကောင်းနေတယ်ဆိုရင်တောင် major decisions အတွက် owner, approval rule နဲ့ record keeping ကို ရှင်းရှင်းလင်းလင်းထားပါ။',
            ];
        }

        return array_values(array_slice($recommendations, 0, 6));
    }

    private function buildDiscussionPriorities(
        array $importantDifferences,
        array $sharedBlindSpots,
        array $complementaryAreas,
        array $sharedStrengths
    ): array {
        $priorities = [];

        foreach ($importantDifferences as $item) {
            $priorities[] = [
                'priority' => 'High',
                'topic' => $item['label'],
                'reason' => 'Partner operating preferences ကွာခြားမှုကြီးရှိတဲ့ area ဖြစ်ပါတယ်။',
            ];
        }

        foreach ($sharedBlindSpots as $item) {
            $priorities[] = [
                'priority' => 'High',
                'topic' => $item['label'],
                'reason' => 'Partner အားလုံးအတွက် extra support သို့ process လိုနိုင်တဲ့ area ဖြစ်ပါတယ်။',
            ];
        }

        foreach ($complementaryAreas as $item) {
            $priorities[] = [
                'priority' => 'Medium',
                'topic' => $item['label'],
                'reason' => 'Role ownership ကို complementary strength အဖြစ်အသုံးချနိုင်တဲ့ area ဖြစ်ပါတယ်။',
            ];
        }

        if ($priorities === [] && $sharedStrengths !== []) {
            foreach (array_slice($sharedStrengths, 0, 2) as $item) {
                $priorities[] = [
                    'priority' => 'Medium',
                    'topic' => $item['label'],
                    'reason' => 'Shared strength ကို business advantage အဖြစ် ဘယ်လိုအသုံးချမလဲ ဆွေးနွေးပါ။',
                ];
            }
        }

        if ($priorities === []) {
            $priorities[] = [
                'priority' => 'Medium',
                'topic' => 'Role Ownership',
                'reason' => 'Assessment result အပြင် experience, time availability နဲ့ legal responsibility ကိုပါထည့်ပြီး roles ကို ဆွေးနွေးပါ။',
            ];
        }

        return array_values(array_slice($priorities, 0, 8));
    }
}
