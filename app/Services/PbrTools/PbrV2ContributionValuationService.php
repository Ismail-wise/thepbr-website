<?php

namespace App\Services\PbrTools;

use InvalidArgumentException;

final class PbrV2ContributionValuationService
{
    /**
     * Decimal strings only; at most 12 whole digits and 2 decimal places.
     * These are numeric representation limits, not business valuation caps.
     * No approval, acceptance, persistence or ownership side effects.
     */
    public function assessTimeSkill(array $input): array
    {
        $hours = $this->scaled($input['hours_per_month'] ?? null, 'hours_per_month');
        $rate = $this->scaled($input['fair_hourly_rate'] ?? null, 'fair_hourly_rate');
        $months = $this->scaled($input['number_of_months'] ?? null, 'number_of_months');
        $paid = $this->scaled(
            $input['cash_compensation_received'] ?? null,
            'cash_compensation_received'
        );

        // Three inputs have scale 100. Convert product to cents only once.
        $product = ($hours === 0 || $rate === 0 || $months === 0)
            ? 0
            : $this->multiply($this->multiply($hours, $rate), $months);

        $gross = intdiv($product, 10000);
        if ($product % 10000 >= 5000) {
            $gross++;
        }

        $net = $gross - $paid;

        return [
            'gross_work_value' => $this->decimal($gross),
            'cash_compensation_received' => $this->decimal($paid),
            'calculated_work_value' => $this->decimal($net),
            'discrepancies' => $net < 0
                ? ['cash_compensation_exceeds_fair_work_value']
                : [],
        ];
    }

    private function scaled(mixed $value, string $field): int
    {
        if (! is_string($value)
            || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/', $value) !== 1) {
            throw new InvalidArgumentException(
                $field.' must be a non-negative decimal string with at most 12 whole digits and 2 decimal places.'
            );
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function multiply(int $left, int $right): int
    {
        if ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw new InvalidArgumentException(
                'Time and skill calculation exceeds exact integer capacity.'
            );
        }

        return $left * $right;
    }

    private function decimal(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        return $sign.intdiv($absolute, 100).'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
