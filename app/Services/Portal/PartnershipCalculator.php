<?php

namespace App\Services\Portal;

use App\Services\PbrTools\StartupCapitalCalculator;
use Illuminate\Validation\ValidationException;

class PartnershipCalculator
{
    public function capital(array $input): array
    {
        $working = round((float) $input['monthly'] * (int) $input['months'], 2);
        $subtotal = (float) $input['pre'] + (float) $input['assets'] + $working;
        $reserve = round($subtotal * (float) $input['reserve'] / 100, 2);
        $categories = [];
        foreach (['Pre-opening' => $input['pre'], 'Initial Assets' => $input['assets'], 'Working Capital' => $working, 'Contingency' => $reserve] as $name => $amount) {
            $categories[] = ['name' => $name, 'items' => [['name' => $name, 'amount' => $amount]]];
        }
        $result = app(StartupCapitalCalculator::class)->calculate(['categories' => $categories]);
        $total = $result['total_startup_capital'];

        return [
            'currency' => $input['currency'], 'working_capital' => $working, 'contingency' => $reserve,
            'total' => $total, 'available' => (float) $input['available'],
            'gap' => round(max(0, $total - (float) $input['available']), 2),
            'surplus' => round(max(0, (float) $input['available'] - $total), 2),
        ];
    }

    public function shares(array $input): array
    {
        $unit = (int) round((float) $input['unit'] * 100);
        $total = 0;
        $rows = [];
        foreach ($input['partners'] as $partner) {
            $minor = (int) round((float) $partner['value'] * 100);
            if ($minor % $unit !== 0) {
                throw ValidationException::withMessages(['unit' => 'ရှယ်ယာအပြည့် ခွဲနိုင်အောင် ရှယ်ယာတစ်စုတန်ဖိုးကို ပြင်ပေးပါ။']);
            }
            $total += $minor;
            $rows[] = ['name' => trim($partner['name']), 'value' => $minor / 100, 'shares' => intdiv($minor, $unit)];
        }
        if ($total <= 0) {
            throw ValidationException::withMessages(['partners' => 'စုစုပေါင်းထည့်ဝင်တန်ဖိုးသည် သုညထက် များရပါမယ်။']);
        }
        foreach ($rows as &$row) {
            $row['percent'] = round($row['value'] * 10000 / $total, 2);
        }
        unset($row);

        return ['currency' => $input['currency'], 'total' => $total / 100, 'total_shares' => intdiv($total, $unit), 'rows' => $rows];
    }
}
