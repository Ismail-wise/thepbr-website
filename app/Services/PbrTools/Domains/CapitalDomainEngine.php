<?php

namespace App\Services\PbrTools\Domains;

use App\Models\PartnershipWorkspace;

class CapitalDomainEngine
{
    /**
     * Build the authoritative Chapter 1 / Capital & Funding summary.
     *
     * The supplied tool state may come from:
     * - PbrChapterStateService tool payloads, or
     * - ChapterOneIntegrationService approved output payloads.
     *
     * This engine does not query the database and does not decide whether a
     * draft or agreed output is allowed. The caller controls source status.
     */
    public function summarize(
        PartnershipWorkspace $workspace,
        array $tools
    ): array {
        $startup = $this->value(
            $tools,
            'startup_capital_planner',
            'total_startup_capital'
        );

        $startupFunded = $this->value(
            $tools,
            'startup_capital_planner',
            'funded_total'
        );

        $currentNet = $this->value(
            $tools,
            'current_capital_position',
            'net_capital_position'
        );

        $working = $this->value(
            $tools,
            'working_capital_calculator',
            'working_capital_required'
        );

        $monthlyOperatingCost = $this->value(
            $tools,
            'working_capital_calculator',
            'monthly_operating_cost'
        );

        $contingency = $this->value(
            $tools,
            'contingency_fund_calculator',
            'contingency_fund'
        );

        $partnerCapital = $this->value(
            $tools,
            'partner_contribution_matrix',
            'total_contribution'
        );

        $otherFunding = $this->value(
            $tools,
            'funding_gap_calculator',
            'other_funding'
        );

        /*
         * New businesses include Startup Capital in required capital.
         *
         * Existing businesses use Current Capital Position as an operating
         * diagnostic; it is deliberately not added to or subtracted from
         * required funding because net resources may not be liquid funding.
         */
        $capitalRequired = $workspace->business_stage === 'new'
            ? round(
                $startup
                + $working
                + $contingency,
                2
            )
            : round(
                $working
                + $contingency,
                2
            );

        /*
         * Startup Capital may already contain confirmed funding before the
         * detailed Partner Contributions / Funding Position modules are set.
         *
         * Those sources can describe the same money, so they must not be
         * added together. Use the stronger confirmed funding view.
         */
        $detailedFunding = round(
            $partnerCapital + $otherFunding,
            2
        );

        $capitalSecured = round(
            max(
                $startupFunded,
                $detailedFunding
            ),
            2
        );

        $fundingGap = round(
            max(
                0,
                $capitalRequired - $capitalSecured
            ),
            2
        );

        $fundingSurplus = round(
            max(
                0,
                $capitalSecured - $capitalRequired
            ),
            2
        );

        $coveragePercentage = $capitalRequired > 0
            ? round(
                ($capitalSecured / $capitalRequired) * 100,
                2
            )
            : 0.0;

        return [
            'startup_capital' => $startup,
            'startup_funded' => $startupFunded,

            'current_net_capital_position' => $currentNet,

            'working_capital' => $working,
            'monthly_operating_cost' => $monthlyOperatingCost,

            'contingency_fund' => $contingency,

            'partner_capital' => $partnerCapital,
            'other_funding' => $otherFunding,

            'capital_required' => $capitalRequired,
            'capital_secured' => $capitalSecured,

            'funding_gap' => $fundingGap,
            'funding_surplus' => $fundingSurplus,

            'funding_coverage_percentage' =>
                $coveragePercentage,

            'funding_status' => $fundingGap > 0
                ? 'gap'
                : (
                    $fundingSurplus > 0
                        ? 'surplus'
                        : 'balanced'
                ),

            'partner_contributions' =>
                $this->toolData(
                    $tools,
                    'partner_contribution_matrix'
                )['partners'] ?? [],

            'allocations' => $this->standardAllocations(
                $workspace,
                $startup,
                $working,
                $contingency
            ),
        ];
    }

    private function standardAllocations(
        PartnershipWorkspace $workspace,
        float $startup,
        float $working,
        float $contingency
    ): array {
        $allocations = [];

        if (
            $workspace->business_stage === 'new'
            && $startup > 0
        ) {
            $allocations[] = [
                'name' => 'Startup Capital',
                'amount' => $startup,
            ];
        }

        if ($working > 0) {
            $allocations[] = [
                'name' => 'Working Capital',
                'amount' => $working,
            ];
        }

        if ($contingency > 0) {
            $allocations[] = [
                'name' => 'Contingency Reserve',
                'amount' => $contingency,
            ];
        }

        return $allocations;
    }

    private function value(
        array $tools,
        string $toolKey,
        string $field
    ): float {
        $data = $this->toolData(
            $tools,
            $toolKey
        );

        $value = $data[$field] ?? 0;

        return is_numeric($value)
            ? round((float) $value, 2)
            : 0.0;
    }

    private function toolData(
        array $tools,
        string $toolKey
    ): array {
        $entry = $tools[$toolKey] ?? [];

        if (! is_array($entry)) {
            return [];
        }

        $data = $entry['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        /*
         * Generic operating-engine results normally expose business values
         * under result['data']; dedicated Chapter 1 calculators historically
         * expose their result values directly.
         *
         * Supporting both shapes preserves compatibility while the whole
         * operating system is gradually normalized.
         */
        if (is_array($data['data'] ?? null)) {
            return $data['data'];
        }

        return $data;
    }
}
