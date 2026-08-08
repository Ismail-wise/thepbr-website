<?php

namespace App\Services\PartnerDynamics;

use InvalidArgumentException;

class PartnerDynamicsScoringService
{
    private const DIMENSIONS = [
        'vision',
        'execution',
        'people',
        'analysis',
        'structure',
        'risk',
        'decision',
        'adaptability',
    ];

    private const PROFILES = [
        'visionary',
        'builder',
        'connector',
        'analyst',
        'operator',
        'guardian',
        'negotiator',
        'optimizer',
    ];

    public function calculate(array $answers): array
    {
        $this->validateAnswers($answers);

        $dimensionScores = $this->calculateDimensionScores($answers);

        $behaviourProfileScores =
            $this->calculateBehaviourProfileScores($dimensionScores);

        $scenarioData = $this->calculateScenarioScores($answers);

        $finalProfileScores = [];

        foreach (self::PROFILES as $profile) {
            $finalProfileScores[$profile] = round(
                ($behaviourProfileScores[$profile] * 0.85)
                + ($scenarioData['scores'][$profile] * 0.15),
                2
            );
        }

        arsort($finalProfileScores);

        $profileKeys = array_keys($finalProfileScores);

        $primaryProfile = $profileKeys[0];
        $secondaryProfile = $profileKeys[1];

        $primaryScore = $finalProfileScores[$primaryProfile];
        $secondaryScore = $finalProfileScores[$secondaryProfile];

        $blendThreshold = (float) config(
            'partner_dynamics.blend_threshold',
            3
        );

        $isBlended =
            abs($primaryScore - $secondaryScore) <= $blendThreshold;

        $consistency = $this->calculateConsistency($answers);

        return [
            'dimension_scores' => $dimensionScores,

            'behaviour_profile_scores' =>
                $behaviourProfileScores,

            'scenario_scores' =>
                $scenarioData['scores'],

            'scenario_counts' =>
                $scenarioData['counts'],

            'profile_scores' =>
                $finalProfileScores,

            'primary_profile' =>
                $primaryProfile,

            'primary_score' =>
                $primaryScore,

            'secondary_profile' =>
                $secondaryProfile,

            'secondary_score' =>
                $secondaryScore,

            'is_blended' =>
                $isBlended,

            'result_confidence' =>
                $consistency['confidence'],

            'consistency_data' =>
                $consistency,
        ];
    }

    private function validateAnswers(array $answers): void
    {
        for ($question = 1; $question <= 32; $question++) {

            if (!array_key_exists($question, $answers)) {
                throw new InvalidArgumentException(
                    "Missing behaviour answer for question {$question}."
                );
            }

            $value = (int) $answers[$question];

            if ($value < 1 || $value > 5) {
                throw new InvalidArgumentException(
                    "Question {$question} must be between 1 and 5."
                );
            }
        }

        $scenarioQuestions =
            config('partner_dynamics.scenario_questions', []);

        for ($question = 33; $question <= 40; $question++) {

            if (!array_key_exists($question, $answers)) {
                throw new InvalidArgumentException(
                    "Missing scenario answer for question {$question}."
                );
            }

            $answer =
                strtoupper((string) $answers[$question]);

            if (
                !isset(
                    $scenarioQuestions[$question]
                        ['options'][$answer]
                )
            ) {
                throw new InvalidArgumentException(
                    "Question {$question} has an invalid option."
                );
            }
        }
    }

    private function calculateDimensionScores(
        array $answers
    ): array {
        $questions =
            config('partner_dynamics.behaviour_questions', []);

        $totals =
            array_fill_keys(self::DIMENSIONS, 0);

        $counts =
            array_fill_keys(self::DIMENSIONS, 0);

        foreach ($questions as $number => $question) {

            $answer = (int) $answers[$number];

            if ($question['reverse']) {
                $answer = 6 - $answer;
            }

            $dimension = $question['dimension'];

            $totals[$dimension] += $answer;
            $counts[$dimension]++;
        }

        $scores = [];

        foreach (self::DIMENSIONS as $dimension) {

            $count = $counts[$dimension];

            $minimum = $count;
            $maximum = $count * 5;

            $normalized =
                (($totals[$dimension] - $minimum)
                / ($maximum - $minimum)) * 100;

            $scores[$dimension] =
                round($normalized, 2);
        }

        return $scores;
    }

    private function calculateBehaviourProfileScores(
        array $dimensions
    ): array {
        $profiles =
            config('partner_dynamics.profiles', []);

        $scores = [];

        foreach ($profiles as $profileKey => $profile) {

            $score = 0;

            foreach (
                $profile['weights']
                as $dimension => $weight
            ) {

                $dimensionScore = match ($dimension) {

                    'cautious_risk' =>
                        100 - $dimensions['risk'],

                    default =>
                        $dimensions[$dimension],
                };

                $score +=
                    $dimensionScore * $weight;
            }

            $scores[$profileKey] =
                round($score, 2);
        }

        return $scores;
    }

    private function calculateScenarioScores(
        array $answers
    ): array {
        $questions =
            config('partner_dynamics.scenario_questions', []);

        $counts =
            array_fill_keys(self::PROFILES, 0);

        foreach ($questions as $number => $scenario) {

            $choice =
                strtoupper((string) $answers[$number]);

            $profile =
                $scenario['options'][$choice]['profile'];

            $counts[$profile]++;
        }

        $scoreMap = [
            0 => 25,
            1 => 50,
            2 => 67,
            3 => 83,
            4 => 100,
        ];

        $scores = [];

        foreach ($counts as $profile => $count) {

            $scores[$profile] =
                $scoreMap[min($count, 4)];
        }

        return [
            'counts' => $counts,
            'scores' => $scores,
        ];
    }

    private function calculateConsistency(
        array $answers
    ): array {
        $pairs = [

            'vision' => [
                [1, 25],
                [9, 25],
                [17, 25],
            ],

            'execution' => [
                [2, 26],
                [10, 26],
                [18, 26],
            ],

            'people' => [
                [3, 27],
                [11, 27],
                [19, 27],
            ],

            'analysis' => [
                [4, 28],
                [12, 28],
                [20, 28],
            ],

            'structure' => [
                [5, 29],
                [13, 29],
                [21, 29],
            ],

            'risk' => [
                [6, 30],
                [14, 30],
                [22, 30],
            ],

            'decision' => [
                [7, 31],
                [15, 31],
                [23, 31],
            ],

            'adaptability' => [
                [8, 32],
                [16, 32],
                [24, 32],
            ],
        ];

        $differences = [];

        foreach (
            $pairs
            as $dimension => $dimensionPairs
        ) {

            $dimensionDifferences = [];

            foreach (
                $dimensionPairs
                as [$positiveQuestion, $reverseQuestion]
            ) {

                $positive =
                    (int) $answers[$positiveQuestion];

                $reverseAligned =
                    6 - (int) $answers[$reverseQuestion];

                $dimensionDifferences[] =
                    abs($positive - $reverseAligned);
            }

            $differences[$dimension] =
                round(
                    array_sum($dimensionDifferences)
                    / count($dimensionDifferences),
                    2
                );
        }

        $averageDifference =
            round(
                array_sum($differences)
                / count($differences),
                2
            );

        $confidence =
            $averageDifference <= 1.5
                ? 'strong'
                : 'moderate';

        return [
            'confidence' =>
                $confidence,

            'average_difference' =>
                $averageDifference,

            'dimension_differences' =>
                $differences,
        ];
    }
}
