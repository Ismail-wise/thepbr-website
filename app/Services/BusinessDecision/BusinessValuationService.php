<?php

namespace App\Services\BusinessDecision;

class BusinessValuationService
{
    public function calculate(array $input): array
    {
        $cash = (float) $input['cash'];
        $debt = (float) $input['debt'];
        $methods = [];

        $ebitda = (float) $input['ebitda'];
        $ebitdaMultiple = (float) $input['ebitda_multiple'];
        if ($ebitda > 0 && $ebitdaMultiple > 0) {
            $methods['EBITDA Multiple'] = max(0, ($ebitda * $ebitdaMultiple) + $cash - $debt);
        }

        $ownerEarnings = (float) $input['owner_earnings'];
        $sdeMultiple = (float) $input['sde_multiple'];
        if ($ownerEarnings > 0 && $sdeMultiple > 0) {
            $methods['Owner Earnings / SDE'] = max(0, ($ownerEarnings * $sdeMultiple) + $cash - $debt);
        }

        $assets = (float) $input['total_assets'];
        $liabilities = (float) $input['total_liabilities'];
        if ($assets > 0) {
            $methods['Asset Based'] = max(0, $assets - $liabilities);
        }

        $fcf = (float) $input['free_cash_flow'];
        $growth = max(-.20, min(.30, (float) $input['growth_rate'] / 100));
        $discount = (float) $input['discount_rate'] / 100;
        $terminalGrowth = (float) $input['terminal_growth'] / 100;

        if ($fcf > 0 && $discount > $terminalGrowth) {
            $pv = 0.0;
            $future = $fcf;
            for ($year = 1; $year <= 5; $year++) {
                $future *= (1 + $growth);
                $pv += $future / pow(1 + $discount, $year);
            }
            $terminal = ($future * (1 + $terminalGrowth)) / ($discount - $terminalGrowth);
            $pvTerminal = $terminal / pow(1 + $discount, 5);
            $methods['Discounted Cash Flow'] = max(0, $pv + $pvTerminal + $cash - $debt);
        }

        $values = array_values(array_filter($methods, fn ($value) => $value > 0));
        sort($values);
        $base = $this->median($values);
        $conservative = $base * .85;
        $optimistic = $base * 1.15;

        $confidence = match (true) {
            count($values) >= 3 => 'HIGH',
            count($values) === 2 => 'MEDIUM',
            default => 'LOW',
        };

        $totalOwnershipUnits = max(1, (int) $input['total_ownership_units']);

        $ownership = [
            'total_units' => $totalOwnershipUnits,
            'conservative_per_unit' => round($conservative / $totalOwnershipUnits, 4),
            'base_per_unit' => round($base / $totalOwnershipUnits, 4),
            'optimistic_per_unit' => round($optimistic / $totalOwnershipUnits, 4),
            'conservative_one_percent' => round($conservative * .01, 2),
            'base_one_percent' => round($base * .01, 2),
            'optimistic_one_percent' => round($optimistic * .01, 2),
        ];

        $risks = [];
        $actions = [];

        if ((float) $input['recurring_revenue_pct'] < 30) {
            $risks[] = 'Recurring revenue နည်းနေပါတယ်။';
            $actions[] = 'Repeat purchase, subscription, contract သို့မဟုတ် recurring revenue ကိုတိုးပါ။';
        }

        if ((float) $input['customer_concentration_pct'] > 30) {
            $risks[] = 'Customer တစ်ယောက်/အုပ်စုအပေါ် Revenue မှီခိုမှုမြင့်နေပါတယ်။';
            $actions[] = 'Customer concentration ကိုလျှော့ပြီး customer base ကိုပိုဖြန့်ပါ။';
        }

        if ((int) $input['owner_dependency'] >= 4) {
            $risks[] = 'Business က Owner တစ်ယောက်အပေါ် အလွန်မှီခိုနေပါတယ်။';
            $actions[] = 'SOP, management team, delegation နဲ့ documented processes တည်ဆောက်ပါ။';
        }

        if ($ebitda > 0 && $debt > ($ebitda * 2.5)) {
            $risks[] = 'Debt level က EBITDA နဲ့ယှဉ်ရင် မြင့်နေပါတယ်။';
            $actions[] = 'Debt reduction plan နဲ့ cash-flow protection plan တည်ဆောက်ပါ။';
        }

        if ((float) $input['growth_rate'] < 0) {
            $risks[] = 'Business growth rate ကျဆင်းနေပါတယ်။';
            $actions[] = 'Revenue decline ရဲ့အကြောင်းရင်းကိုရှာပြီး growth recovery plan တည်ဆောက်ပါ။';
        }

        if ($ebitda <= 0 && $ownerEarnings <= 0) {
            $risks[] = 'Positive earnings data မရှိသေးလို့ earnings-based valuation အားနည်းပါတယ်။';
            $actions[] = 'Normalized profit / EBITDA / owner earnings data ကို ပြည့်စုံအောင်ပြင်ပါ။';
        }

        return [
            'methods' => array_map(fn ($value) => round($value, 2), $methods),
            'conservative' => round($conservative, 2),
            'base' => round($base, 2),
            'optimistic' => round($optimistic, 2),
            'confidence' => $confidence,
            'ownership' => $ownership,
            'risks' => array_values(array_unique($risks)),
            'actions' => array_values(array_unique($actions)),
            'note' => 'ဒီရလဒ်က Management Planning အတွက် indicative estimate ဖြစ်ပြီး formal independent valuation report မဟုတ်ပါ။',
        ];
    }

    private function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}
