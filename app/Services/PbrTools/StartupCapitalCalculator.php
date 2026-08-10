<?php

namespace App\Services\PbrTools;

class StartupCapitalCalculator
{
    public function calculate(array $input): array
    {
        $categories = [];
        $grandTotal = 0.0;
        $itemCount = 0;

        $largestCategory = null;
        $largestItem = null;

        foreach ($input['categories'] ?? [] as $categoryIndex => $category) {
            if (! is_array($category)) {
                continue;
            }

            $categoryName = trim(
                (string) ($category['name'] ?? '')
            );

            if ($categoryName === '') {
                $categoryName = 'Others';
            }

            $items = [];
            $categorySubtotal = 0.0;

            foreach ($category['items'] ?? [] as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemName = trim(
                    (string) ($item['name'] ?? '')
                );

                $amount = $this->normalizeAmount(
                    $item['amount'] ?? null
                );

                if ($itemName === '' && $amount <= 0) {
                    continue;
                }

                if ($itemName === '') {
                    $itemName = 'Unnamed Item';
                }

                $items[] = [
                    'name' => $itemName,
                    'amount' => $amount,
                ];

                $categorySubtotal += $amount;

                if ($amount > 0) {
                    $itemCount++;
                }

                if (
                    $amount > 0
                    && (
                        $largestItem === null
                        || $amount > $largestItem['amount']
                    )
                ) {
                    $largestItem = [
                        'category' => $categoryName,
                        'name' => $itemName,
                        'amount' => $amount,
                    ];
                }
            }

            if (empty($items)) {
                continue;
            }

            $categorySubtotal = round(
                $categorySubtotal,
                2
            );

            $grandTotal += $categorySubtotal;

            $categories[] = [
                'name' => $categoryName,
                'subtotal' => $categorySubtotal,
                'percentage' => 0.0,
                'item_count' => count($items),
                'items' => $items,
            ];

            if (
                $categorySubtotal > 0
                && (
                    $largestCategory === null
                    || $categorySubtotal
                        > $largestCategory['subtotal']
                )
            ) {
                $largestCategory = [
                    'name' => $categoryName,
                    'subtotal' => $categorySubtotal,
                ];
            }
        }

        $grandTotal = round($grandTotal, 2);

        foreach ($categories as &$category) {
            $category['percentage'] =
                $grandTotal > 0
                    ? round(
                        ($category['subtotal'] / $grandTotal)
                        * 100,
                        2
                    )
                    : 0.0;
        }

        unset($category);

        if ($largestCategory !== null) {
            $largestCategory['percentage'] =
                $grandTotal > 0
                    ? round(
                        (
                            $largestCategory['subtotal']
                            / $grandTotal
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        if ($largestItem !== null) {
            $largestItem['percentage'] =
                $grandTotal > 0
                    ? round(
                        (
                            $largestItem['amount']
                            / $grandTotal
                        ) * 100,
                        2
                    )
                    : 0.0;
        }

        return [
            'total_startup_capital' => $grandTotal,
            'category_count' => count($categories),
            'item_count' => $itemCount,
            'categories' => $categories,
            'largest_category' => $largestCategory,
            'largest_item' => $largestItem,
        ];
    }

    private function normalizeAmount(mixed $value): float
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
