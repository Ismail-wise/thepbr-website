<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;

final class PbrV2CapitalComparisonService
{
    private const SCENARIOS = [
        'lean',
        'base',
        'growth',
    ];

    public function __construct(
        private readonly PbrV2CapitalReadService $read,
        private readonly PbrV2CapitalPlanReadinessService $readiness
    ) {
    }

    public function compare(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $found = [];
        $beforeId = null;

        do {
            $page = $this->read->workArtifacts(
                $actor,
                $workspace,
                $beforeId
            );

            foreach ($page['items'] as $artifact) {
                $payload = is_array(
                    $artifact['payload'] ?? null
                )
                    ? $artifact['payload']
                    : [];

                $scenario = $payload[
                    'scenario_type'
                ] ?? null;

                if (
                    ! is_string($scenario)
                    || ! in_array(
                        $scenario,
                        self::SCENARIOS,
                        true
                    )
                    || isset($found[$scenario])
                ) {
                    continue;
                }

                $found[$scenario] =
                    $this->project(
                        $artifact,
                        $payload
                    );

                if (
                    count($found)
                    === count(self::SCENARIOS)
                ) {
                    break;
                }
            }

            $beforeId =
                $page['next_before_id'];

        } while (
            count($found)
                < count(self::SCENARIOS)
            && $beforeId !== null
        );

        $plans = [];
        $missing = [];

        foreach (self::SCENARIOS as $scenario) {
            if (isset($found[$scenario])) {
                $plans[] = $found[$scenario];

                continue;
            }

            $missing[] = $scenario;
        }

        return [
            'plans' => $plans,
            'missing_scenarios' => $missing,
        ];
    }

    private function project(
        array $artifact,
        array $payload
    ): array {
        $assessment =
            $this->readiness->assess(
                'capital',
                'approved_capital_plan',
                $payload
            );

        $calculations =
            $assessment['calculations'];

        $assumptions = is_array(
            $payload['assumptions'] ?? null
        )
            ? $payload['assumptions']
            : [];

        $months =
            $assumptions[
                'working_capital_months'
            ] ?? null;

        $contingency =
            $assumptions[
                'contingency_percentage'
            ] ?? null;

        return [
            'artifact_id' =>
                (int) $artifact['id'],
            'state' =>
                (string) $artifact['state'],
            'plan_name' =>
                is_string(
                    $payload['plan_name']
                    ?? null
                )
                    ? $payload['plan_name']
                    : '',
            'scenario_type' =>
                (string) $payload[
                    'scenario_type'
                ],
            'currency_code' =>
                is_string(
                    $payload['currency_code']
                    ?? null
                )
                    ? $payload['currency_code']
                    : '',
            'working_capital_months' =>
                is_int($months)
                    ? $months
                    : null,
            'contingency_percentage' =>
                is_string($contingency)
                    ? $contingency
                    : null,
            'total_required' =>
                is_array($calculations)
                    ? $calculations[
                        'total_required'
                    ]
                    : null,
            'funding_gap' =>
                is_array($calculations)
                    ? $calculations[
                        'funding_gap'
                    ]
                    : null,
            'readiness_passed' =>
                (bool) $assessment['passed'],
            'readiness_error_count' =>
                count($assessment['errors']),
        ];
    }
}
