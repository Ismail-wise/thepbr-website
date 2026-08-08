<?php

namespace App\Services\PbrTools;

class StartupCapitalCalculator
{
    public const CATEGORIES = [
        'registration_legal' => 'Registration & Legal',
        'location_renovation' => 'Location / Renovation',
        'equipment' => 'Equipment',
        'initial_inventory' => 'Initial Inventory',
        'technology_software' => 'Technology & Software',
        'branding_marketing' => 'Branding & Launch Marketing',
        'deposits_prepayments' => 'Deposits & Prepayments',
        'other_startup_costs' => 'Other Startup Costs',
    ];

    public function calculate(array $input): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $key => $label) {
            $amount = $this->normalizeAmount(
                $input[$key] ?? 0
            );

            $categories[$key] = [
                'label' => $label,
                'amount' => $amount,
            ];
        }

        $total = round(
            array_sum(
                array_column($categories, 'amount')
            ),
            2
        );

        foreach ($categories as $key => $category) {
            $categories[$key]['percentage'] =
                $total > 0
                    ? round(
                        ($category['amount'] / $total) * 100,
                        2
                    )
                    : 0;
        }

        $largestKey = null;
        $largestAmount = 0;

        foreach ($categories as $key => $category) {
            if ($category['amount'] > $largestAmount) {
                $largestKey = $key;
                $largestAmount = $category['amount'];
            }
        }

        return [
            'startup_cost_total' => $total,

            'categories' => $categories,

            'largest_category' => $largestKey
                ? [
                    'key' => $largestKey,
                    'label' => $categories[$largestKey]['label'],
                    'amount' => $categories[$largestKey]['amount'],
                    'percentage' =>
                        $categories[$largestKey]['percentage'],
                ]
                : null,

            'non_zero_categories' => collect($categories)
                ->where('amount', '>', 0)
                ->count(),
        ];
    }

    private function normalizeAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(
            max(0, (float) $value),
            2
        );
    }
}
