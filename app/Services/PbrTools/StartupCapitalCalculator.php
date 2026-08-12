<?php

namespace App\Services\PbrTools;

use DateTimeImmutable;

class StartupCapitalCalculator
{
    public function calculate(array $input): array
    {
        $categories = [];
        $grandTotal = 0.0;
        $essentialTotal = 0.0;
        $optionalTotal = 0.0;
        $fundedTotal = 0.0;
        $essentialFunded = 0.0;
        $oneTimeTotal = 0.0;
        $recurringReserveTotal = 0.0;
        $monthlyRecurringCost = 0.0;
        $due30Outstanding = 0.0;
        $overdueOutstanding = 0.0;
        $itemCount = 0;
        $fundingSources = [];

        $largestCategory = null;
        $largestItem = null;
        $today = new DateTimeImmutable('today');
        $thirtyDays = $today->modify('+30 days');

        foreach ($input['categories'] ?? [] as $category) {
            if (! is_array($category)) {
                continue;
            }

            $categoryName = trim((string) ($category['name'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Others';
            }

            $items = [];
            $categorySubtotal = 0.0;
            $categoryFunded = 0.0;
            $categoryEssential = 0.0;
            $categoryOptional = 0.0;

            foreach ($category['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemName = trim((string) ($item['name'] ?? ''));
                $baseAmount = $this->normalizeAmount($item['amount'] ?? null);
                $priority = ($item['priority'] ?? 'essential') === 'optional'
                    ? 'optional'
                    : 'essential';
                $frequency = ($item['frequency'] ?? 'one_time') === 'monthly'
                    ? 'monthly'
                    : 'one_time';
                $reserveMonths = $frequency === 'monthly'
                    ? $this->normalizeMonths($item['reserve_months'] ?? 3)
                    : 1;
                $plannedCost = round($baseAmount * $reserveMonths, 2);
                $fundedAmount = min(
                    $plannedCost,
                    $this->normalizeAmount($item['funded_amount'] ?? null)
                );
                $outstanding = round(max(0, $plannedCost - $fundedAmount), 2);
                $fundingSource = trim((string) ($item['funding_source'] ?? ''));
                $dueDate = $this->normalizeDate($item['due_date'] ?? null);
                $note = trim((string) ($item['note'] ?? ''));

                if ($itemName === '' && $plannedCost <= 0 && $fundedAmount <= 0) {
                    continue;
                }

                if ($itemName === '') {
                    $itemName = 'Unnamed Item';
                }

                $fundingStatus = $plannedCost <= 0
                    ? 'not_set'
                    : ($fundedAmount >= $plannedCost
                        ? 'funded'
                        : ($fundedAmount > 0 ? 'partial' : 'unfunded'));

                $items[] = [
                    'name' => $itemName,
                    'amount' => $baseAmount,
                    'priority' => $priority,
                    'frequency' => $frequency,
                    'reserve_months' => $reserveMonths,
                    'planned_cost' => $plannedCost,
                    'funded_amount' => $fundedAmount,
                    'outstanding' => $outstanding,
                    'funding_source' => $fundingSource,
                    'funding_status' => $fundingStatus,
                    'due_date' => $dueDate,
                    'note' => $note,
                ];

                $categorySubtotal += $plannedCost;
                $categoryFunded += $fundedAmount;
                $grandTotal += $plannedCost;
                $fundedTotal += $fundedAmount;

                if ($priority === 'essential') {
                    $essentialTotal += $plannedCost;
                    $essentialFunded += $fundedAmount;
                    $categoryEssential += $plannedCost;
                } else {
                    $optionalTotal += $plannedCost;
                    $categoryOptional += $plannedCost;
                }

                if ($frequency === 'monthly') {
                    $monthlyRecurringCost += $baseAmount;
                    $recurringReserveTotal += $plannedCost;
                } else {
                    $oneTimeTotal += $plannedCost;
                }

                if ($fundedAmount > 0) {
                    $sourceKey = $fundingSource !== ''
                        ? $fundingSource
                        : 'မသတ်မှတ်ရသေး';
                    $fundingSources[$sourceKey] = round(
                        ($fundingSources[$sourceKey] ?? 0) + $fundedAmount,
                        2
                    );
                }

                if ($dueDate !== null && $outstanding > 0) {
                    $due = new DateTimeImmutable($dueDate);
                    if ($due < $today) {
                        $overdueOutstanding += $outstanding;
                    } elseif ($due <= $thirtyDays) {
                        $due30Outstanding += $outstanding;
                    }
                }

                if ($plannedCost > 0) {
                    $itemCount++;
                }

                if (
                    $plannedCost > 0
                    && ($largestItem === null || $plannedCost > $largestItem['amount'])
                ) {
                    $largestItem = [
                        'category' => $categoryName,
                        'name' => $itemName,
                        'amount' => $plannedCost,
                    ];
                }
            }

            if (empty($items)) {
                continue;
            }

            $categorySubtotal = round($categorySubtotal, 2);
            $categoryFunded = round($categoryFunded, 2);

            $categories[] = [
                'name' => $categoryName,
                'subtotal' => $categorySubtotal,
                'funded' => $categoryFunded,
                'outstanding' => round(max(0, $categorySubtotal - $categoryFunded), 2),
                'essential_total' => round($categoryEssential, 2),
                'optional_total' => round($categoryOptional, 2),
                'percentage' => 0.0,
                'item_count' => count($items),
                'items' => $items,
            ];

            if (
                $categorySubtotal > 0
                && ($largestCategory === null || $categorySubtotal > $largestCategory['subtotal'])
            ) {
                $largestCategory = [
                    'name' => $categoryName,
                    'subtotal' => $categorySubtotal,
                ];
            }
        }

        $grandTotal = round($grandTotal, 2);
        $essentialTotal = round($essentialTotal, 2);
        $optionalTotal = round($optionalTotal, 2);
        $fundedTotal = round(min($grandTotal, $fundedTotal), 2);
        $essentialFunded = round(min($essentialTotal, $essentialFunded), 2);
        $fundingGap = round(max(0, $grandTotal - $fundedTotal), 2);
        $essentialGap = round(max(0, $essentialTotal - $essentialFunded), 2);
        $due30Outstanding = round($due30Outstanding, 2);
        $overdueOutstanding = round($overdueOutstanding, 2);

        foreach ($categories as &$category) {
            $category['percentage'] = $grandTotal > 0
                ? round(($category['subtotal'] / $grandTotal) * 100, 2)
                : 0.0;
        }
        unset($category);

        if ($largestCategory !== null) {
            $largestCategory['percentage'] = $grandTotal > 0
                ? round(($largestCategory['subtotal'] / $grandTotal) * 100, 2)
                : 0.0;
        }

        if ($largestItem !== null) {
            $largestItem['percentage'] = $grandTotal > 0
                ? round(($largestItem['amount'] / $grandTotal) * 100, 2)
                : 0.0;
        }

        arsort($fundingSources);
        $fundingSourceRows = [];
        foreach ($fundingSources as $source => $amount) {
            $fundingSourceRows[] = [
                'source' => $source,
                'amount' => round($amount, 2),
                'percentage' => $fundedTotal > 0
                    ? round(($amount / $fundedTotal) * 100, 2)
                    : 0.0,
            ];
        }

        $warnings = [];
        if ($overdueOutstanding > 0) {
            $warnings[] = 'Due Date ကျော်သွားပြီး မဖြည့်ဆည်းရသေးတဲ့ ကုန်ကျစရိတ် ရှိနေပါတယ်။';
        }
        if ($due30Outstanding > 0) {
            $warnings[] = 'နောက် 30 ရက်အတွင်း ပေးချေ/ဖြည့်ဆည်းရမယ့် Funding လိုအပ်ချက် ရှိနေပါတယ်။';
        }
        if ($essentialGap > 0) {
            $warnings[] = 'မဖြစ်မနေလိုအပ်တဲ့ Essential Costs အတွက် Funding မပြည့်သေးပါ။';
        }

        return [
            // Backward-compatible keys used by Chapter 1 integration.
            'total_startup_capital' => $grandTotal,
            'category_count' => count($categories),
            'item_count' => $itemCount,
            'categories' => $categories,
            'largest_category' => $largestCategory,
            'largest_item' => $largestItem,

            // Operational capital-planning metrics.
            'essential_total' => $essentialTotal,
            'optional_total' => $optionalTotal,
            'funded_total' => $fundedTotal,
            'funding_gap' => $fundingGap,
            'essential_funding_gap' => $essentialGap,
            'funded_percentage' => $grandTotal > 0
                ? round(($fundedTotal / $grandTotal) * 100, 2)
                : 0.0,
            'one_time_total' => round($oneTimeTotal, 2),
            'recurring_reserve_total' => round($recurringReserveTotal, 2),
            'monthly_recurring_cost' => round($monthlyRecurringCost, 2),
            'due_30_days_outstanding' => $due30Outstanding,
            'overdue_outstanding' => $overdueOutstanding,
            'funding_sources' => $fundingSourceRows,
            'warnings' => $warnings,
        ];
    }

    private function normalizeAmount(mixed $value): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round(max(0, (float) $value), 2);
    }

    private function normalizeMonths(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 3;
        }

        return max(1, min(24, (int) $value));
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
