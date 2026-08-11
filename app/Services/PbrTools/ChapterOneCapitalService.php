<?php

namespace App\Services\PbrTools;

class ChapterOneCapitalService
{
    public function currentCapitalPosition(array $input): array
    {
        $resources = $this->categoryCollection(
            $input['resources'] ?? []
        );

        $liabilities = $this->categoryCollection(
            $input['liabilities'] ?? []
        );

        $netPosition = round(
            $resources['total'] - $liabilities['total'],
            2
        );

        return [
            'total_resources' => $resources['total'],
            'total_liabilities' => $liabilities['total'],
            'net_capital_position' => $netPosition,
            'position_status' => $netPosition >= 0
                ? 'positive'
                : 'negative',
            'resources' => $resources,
            'liabilities' => $liabilities,
        ];
    }

    public function workingCapital(array $input): array
    {
        $monthlyCosts = $this->categoryCollection(
            $input['monthly_costs'] ?? []
        );

        $reserveMonths = max(
            0,
            min(
                24,
                (float) ($input['reserve_months'] ?? 0)
            )
        );

        $inventory = $this->amount(
            $input['inventory_requirement'] ?? 0
        );

        $payables = $this->amount(
            $input['short_term_payables'] ?? 0
        );

        $receivables = $this->amount(
            $input['expected_receivables'] ?? 0
        );

        $operatingReserve = round(
            $monthlyCosts['total'] * $reserveMonths,
            2
        );

        $grossRequirement = round(
            $operatingReserve
            + $inventory
            + $payables,
            2
        );

        $workingCapitalRequired = round(
            max(
                0,
                $grossRequirement - $receivables
            ),
            2
        );

        return [
            'monthly_operating_cost' =>
                $monthlyCosts['total'],

            'reserve_months' => $reserveMonths,

            'operating_reserve' =>
                $operatingReserve,

            'inventory_requirement' =>
                $inventory,

            'short_term_payables' =>
                $payables,

            'expected_receivables' =>
                $receivables,

            'gross_working_capital_need' =>
                $grossRequirement,

            'working_capital_required' =>
                $workingCapitalRequired,

            'monthly_costs' => $monthlyCosts,
        ];
    }

    public function contingencyFund(array $input): array
    {
        $method = (
            ($input['method'] ?? 'percentage')
            === 'months'
        )
            ? 'months'
            : 'percentage';

        $baseCapital = $this->amount(
            $input['base_capital'] ?? 0
        );

        if ($method === 'months') {
            $monthlyCost = $this->amount(
                $input['monthly_operating_cost'] ?? 0
            );

            $months = max(
                0,
                min(
                    24,
                    (float) ($input['months'] ?? 0)
                )
            );

            $fund = round(
                $monthlyCost * $months,
                2
            );

            $methodDetails = [
                'monthly_operating_cost' =>
                    $monthlyCost,

                'months' => $months,
            ];
        } else {
            $percentage = max(
                0,
                min(
                    100,
                    (float) ($input['percentage'] ?? 0)
                )
            );

            $fund = round(
                $baseCapital
                * ($percentage / 100),
                2
            );

            $methodDetails = [
                'percentage' => $percentage,
            ];
        }

        return [
            'method' => $method,
            'base_capital' => $baseCapital,
            'contingency_fund' => $fund,
            'total_with_contingency' => round(
                $baseCapital + $fund,
                2
            ),
            'method_details' => $methodDetails,
        ];
    }

    public function partnerContributions(array $input): array
    {
        $partners = [];
        $totalContribution = 0.0;

        foreach ($input['partners'] ?? [] as $partner) {
            if (! is_array($partner)) {
                continue;
            }

            $name = trim(
                (string) ($partner['name'] ?? '')
            );

            if ($name === '') {
                $name = 'Unnamed Partner';
            }

            $items = [];
            $partnerTotal = 0.0;

            foreach (
                $partner['contributions'] ?? []
                as $contribution
            ) {
                if (! is_array($contribution)) {
                    continue;
                }

                $itemName = trim(
                    (string) (
                        $contribution['name']
                        ?? ''
                    )
                );

                $amount = $this->amount(
                    $contribution['amount'] ?? 0
                );

                if (
                    $itemName === ''
                    && $amount <= 0
                ) {
                    continue;
                }

                if ($itemName === '') {
                    $itemName = 'Other Contribution';
                }

                $items[] = [
                    'name' => $itemName,
                    'amount' => $amount,
                ];

                $partnerTotal += $amount;
            }

            $partnerTotal = round(
                $partnerTotal,
                2
            );

            $partners[] = [
                'name' => $name,
                'total' => $partnerTotal,
                'share_percentage' => 0.0,
                'contributions' => $items,
            ];

            $totalContribution += $partnerTotal;
        }

        $totalContribution = round(
            $totalContribution,
            2
        );

        foreach ($partners as &$partner) {
            $partner['share_percentage'] =
                $totalContribution > 0
                    ? round(
                        (
                            $partner['total']
                            / $totalContribution
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        unset($partner);

        return [
            'total_contribution' =>
                $totalContribution,

            'partner_count' =>
                count($partners),

            'partners' => $partners,

            'important_note' =>
                'Contribution share is not ownership share.',
        ];
    }

    public function fundingGap(array $input): array
    {
        $required = $this->amount(
            $input['capital_required'] ?? 0
        );

        $partnerCapital = $this->amount(
            $input['partner_capital'] ?? 0
        );

        $otherFunding = $this->amount(
            $input['other_funding'] ?? 0
        );

        $secured = round(
            $partnerCapital + $otherFunding,
            2
        );

        $gap = round(
            max(0, $required - $secured),
            2
        );

        $surplus = round(
            max(0, $secured - $required),
            2
        );

        $coverage = $required > 0
            ? round(
                ($secured / $required) * 100,
                2
            )
            : 0.0;

        return [
            'capital_required' => $required,
            'partner_capital' => $partnerCapital,
            'other_funding' => $otherFunding,
            'capital_secured' => $secured,
            'funding_gap' => $gap,
            'funding_surplus' => $surplus,
            'coverage_percentage' => $coverage,
            'status' => $gap > 0
                ? 'gap'
                : ($surplus > 0
                    ? 'surplus'
                    : 'balanced'),
        ];
    }

    public function capitalAllocation(array $input): array
    {
        $items = [];
        $total = 0.0;
        $largest = null;

        foreach (
            $input['allocations'] ?? []
            as $allocation
        ) {
            if (! is_array($allocation)) {
                continue;
            }

            $name = trim(
                (string) ($allocation['name'] ?? '')
            );

            $amount = $this->amount(
                $allocation['amount'] ?? 0
            );

            if ($name === '' && $amount <= 0) {
                continue;
            }

            if ($name === '') {
                $name = 'Other Capital Use';
            }

            $items[] = [
                'name' => $name,
                'amount' => $amount,
                'percentage' => 0.0,
            ];

            $total += $amount;

            if (
                $amount > 0
                && (
                    $largest === null
                    || $amount > $largest['amount']
                )
            ) {
                $largest = [
                    'name' => $name,
                    'amount' => $amount,
                ];
            }
        }

        $total = round($total, 2);

        foreach ($items as &$item) {
            $item['percentage'] =
                $total > 0
                    ? round(
                        (
                            $item['amount']
                            / $total
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        unset($item);

        if ($largest !== null) {
            $largest['percentage'] =
                $total > 0
                    ? round(
                        (
                            $largest['amount']
                            / $total
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        return [
            'total_allocated' => $total,
            'allocation_count' => count($items),
            'allocations' => $items,
            'largest_allocation' => $largest,
        ];
    }

    private function categoryCollection(
        array $categories
    ): array {
        $result = [];
        $total = 0.0;
        $itemCount = 0;

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $name = trim(
                (string) ($category['name'] ?? '')
            );

            if ($name === '') {
                $name = 'Others';
            }

            $items = [];
            $subtotal = 0.0;

            foreach (
                $category['items'] ?? []
                as $item
            ) {
                if (! is_array($item)) {
                    continue;
                }

                $itemName = trim(
                    (string) ($item['name'] ?? '')
                );

                $amount = $this->amount(
                    $item['amount'] ?? 0
                );

                if (
                    $itemName === ''
                    && $amount <= 0
                ) {
                    continue;
                }

                if ($itemName === '') {
                    $itemName = 'Unnamed Item';
                }

                $items[] = [
                    'name' => $itemName,
                    'amount' => $amount,
                ];

                $subtotal += $amount;

                if ($amount > 0) {
                    $itemCount++;
                }
            }

            if (empty($items)) {
                continue;
            }

            $subtotal = round(
                $subtotal,
                2
            );

            $result[] = [
                'name' => $name,
                'subtotal' => $subtotal,
                'percentage' => 0.0,
                'items' => $items,
            ];

            $total += $subtotal;
        }

        $total = round($total, 2);

        foreach ($result as &$category) {
            $category['percentage'] =
                $total > 0
                    ? round(
                        (
                            $category['subtotal']
                            / $total
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        unset($category);

        return [
            'total' => $total,
            'category_count' => count($result),
            'item_count' => $itemCount,
            'categories' => $result,
        ];
    }

    private function amount(mixed $value): float
    {
        if (
            $value === null
            || $value === ''
            || ! is_numeric($value)
        ) {
            return 0.0;
        }

        return round(
            max(0, (float) $value),
            2
        );
    }
}
