<?php

namespace App\Services\PartnerDynamics;

class PartnerMatchRecommendationService
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

    private const PROFILE_DESCRIPTIONS = [
        'visionary' =>
            'Brings future direction, opportunity thinking and new possibilities.',

        'builder' =>
            'Turns plans into action and helps maintain execution momentum.',

        'connector' =>
            'Strengthens communication, relationships and stakeholder alignment.',

        'analyst' =>
            'Adds evidence, financial thinking and careful analysis to decisions.',

        'operator' =>
            'Creates structure, process discipline and consistent execution.',

        'guardian' =>
            'Adds control, downside awareness and stronger business safeguards.',

        'negotiator' =>
            'Helps navigate disagreement, difficult decisions and commercial discussions.',

        'optimizer' =>
            'Improves systems, efficiency and adaptability as the business changes.',
    ];

    private const DISCUSSION_POINTS = [
        'vision' =>
            'Agree how long-term direction and new opportunities will be evaluated.',

        'execution' =>
            'Define who owns follow-through, deadlines and delivery accountability.',

        'people' =>
            'Agree who leads customer, team and external partner relationships.',

        'analysis' =>
            'Set rules for financial review, evidence and major spending decisions.',

        'structure' =>
            'Clarify roles, approval authority, responsibilities and operating processes.',

        'risk' =>
            'Agree how much business risk is acceptable and when extra approval is required.',

        'decision' =>
            'Define how major decisions, disagreements and deadlocks will be handled.',

        'adaptability' =>
            'Agree when plans can change and who can approve important operational changes.',
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
                'These recommendations show complementary operating styles, not guaranteed compatibility. Partnership fit should also consider values, trust, goals, financial expectations and working history.',
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
                'Opportunity & Calculated Risk',
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
                'Risk Control & Downside Awareness',
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
                '%s may complement your current pattern by adding more strength in %s and %s.',
                $candidate['name'],
                $labels[0],
                $labels[1]
            );
        }

        if (count($labels) === 1) {
            return sprintf(
                '%s may complement your current pattern by adding more strength in %s.',
                $candidate['name'],
                $labels[0]
            );
        }

        return sprintf(
            '%s offers a different operating style that may provide useful balance alongside your current strengths.',
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
