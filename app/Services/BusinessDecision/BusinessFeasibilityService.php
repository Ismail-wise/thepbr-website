<?php

namespace App\Services\BusinessDecision;

class BusinessFeasibilityService
{
    public function calculate(array $input): array
    {
        $score5 = fn ($value): float => max(0, min(100, ((float) $value / 5) * 100));

        $market = round(($score5($input['market_demand']) + $score5($input['customer_validation']) + $score5($input['competitive_advantage'])) / 3, 1);

        $startupCost = (float) $input['startup_cost'];
        $availableCapital = (float) $input['available_capital'];
        $fixedCost = (float) $input['monthly_fixed_cost'];
        $revenue = (float) $input['monthly_expected_revenue'];
        $reserveMonths = (float) $input['reserve_months'];

        $capitalCoverage = $startupCost > 0 ? $availableCapital / $startupCost : 1;
        $capitalScore = min(100, max(0, $capitalCoverage * 100));
        $reserveScore = min(100, max(0, ($reserveMonths / 6) * 100));
        $operatingScore = $fixedCost > 0 ? min(100, max(0, (($revenue / $fixedCost) / 1.5) * 100)) : 100;
        $financial = round(($capitalScore + $reserveScore + $operatingScore) / 3, 1);

        $operations = round(($score5($input['team_experience']) + $score5($input['operational_readiness'])) / 2, 1);
        $partner = round($score5($input['partner_alignment']), 1);
        $risk = round($score5($input['legal_readiness']), 1);
        $sales = round($score5($input['sales_readiness']), 1);

        $dimensions = [
            'Market Viability' => $market,
            'Financial Readiness' => $financial,
            'Operational Readiness' => $operations,
            'Partner Alignment' => $partner,
            'Risk & Compliance' => $risk,
            'Sales Readiness' => $sales,
        ];

        $score = round(($market * .25) + ($financial * .25) + ($operations * .15) + ($partner * .15) + ($risk * .10) + ($sales * .10), 1);

        $blockers = [];
        $risks = [];
        $actions = [];

        if ((int) $input['market_demand'] <= 1) {
            $blockers[] = 'Market demand အထောက်အထား အလွန်နည်းနေပါတယ်။';
            $actions[] = 'Customer interview, pre-order, pilot sale သို့မဟုတ် market test လုပ်ပြီး demand ကို အရင်အတည်ပြုပါ။';
        }

        if ((int) $input['customer_validation'] <= 1) {
            $blockers[] = 'Customer validation မလုံလောက်သေးပါ။';
            $actions[] = 'Target customer အစစ်တွေနဲ့ product/service validation လုပ်ပါ။';
        }

        if ($capitalCoverage < .5) {
            $shortfall = max(0, $startupCost - $availableCapital);
            $blockers[] = 'လိုအပ်တဲ့ Startup Capital ရဲ့ 50% မပြည့်သေးပါ။';
            $actions[] = 'Funding Gap '.number_format($shortfall, 2).' ကို ဖြည့်နိုင်မယ့် funding plan တည်ဆောက်ပါ။';
        } elseif ($capitalCoverage < 1) {
            $shortfall = max(0, $startupCost - $availableCapital);
            $risks[] = 'Startup Capital မှာ '.number_format($shortfall, 2).' funding gap ရှိနေပါတယ်။';
            $actions[] = 'Capital shortfall ကို funding, partner contribution သို့မဟုတ် cost reduction နဲ့ ဖြည့်ပါ။';
        }

        if ($reserveMonths < 3) {
            $risks[] = 'Emergency / Contingency Reserve က 3 months မပြည့်သေးပါ။';
            $actions[] = 'အနည်းဆုံး 3 months operating reserve ရအောင်ပြင်ပါ။';
        }

        if ($fixedCost > 0 && $revenue < $fixedCost) {
            $risks[] = 'Expected monthly revenue က fixed cost ထက်နည်းနေပါတယ်။';
            $actions[] = 'Revenue assumption, pricing, sales volume နဲ့ fixed cost ကို ပြန်စစ်ပါ။';
        }

        if ((int) $input['partner_alignment'] <= 1) {
            $blockers[] = 'Partner roles / expectations / decisions တွေ မရှင်းသေးပါ။';
            $actions[] = 'Partner roles, authority, contribution နဲ့ decision rules ကို စတင်မလုပ်ခင် အတည်ပြုပါ။';
        } elseif ((int) $input['partner_alignment'] <= 2) {
            $risks[] = 'Partner alignment အားနည်းနေပါတယ်။';
            $actions[] = 'Partner expectations နဲ့ responsibilities ကို စာဖြင့်သတ်မှတ်ပါ။';
        }

        if ((int) $input['legal_readiness'] <= 1) {
            $blockers[] = 'Legal / licensing / compliance requirements မပြည့်စုံသေးပါ။';
            $actions[] = 'လိုအပ်တဲ့ registration, license, tax နဲ့ legal requirements တွေကို စစ်ဆေးပြီး ပြည့်စုံအောင်လုပ်ပါ။';
        }

        if ((int) $input['sales_readiness'] <= 2) {
            $risks[] = 'Customer ရယူမယ့် Sales Channel မသေချာသေးပါ။';
            $actions[] = 'ပထမဆုံး customer တွေရဖို့ sales channel နဲ့ acquisition plan တိတိကျကျရေးပါ။';
        }

        if ((int) $input['operational_readiness'] <= 2) {
            $risks[] = 'Operations, supplier, process သို့မဟုတ် delivery readiness အားနည်းနေပါတယ်။';
            $actions[] = 'Supplier, workflow, staffing နဲ့ delivery process ကို launch မတိုင်ခင် ready ဖြစ်အောင်လုပ်ပါ။';
        }

        if ((int) $input['competitive_advantage'] <= 2) {
            $risks[] = 'Competitors နဲ့ယှဉ်ရင် ကွဲပြားတဲ့ advantage မရှင်းသေးပါ။';
            $actions[] = 'Customer က ကိုယ့် Business ကို ဘာကြောင့်ရွေးသင့်သလဲဆိုတာ value proposition တစ်ခုသတ်မှတ်ပါ။';
        }

        $strengths = [];
        foreach ($dimensions as $name => $value) {
            if ($value >= 75) {
                $strengths[] = $name;
            }
        }

        if ($score >= 80 && count($blockers) === 0) {
            $decision = 'GO';
            $decisionMm = 'လက်ရှိ Data အရ စတင်နိုင်တဲ့အခြေအနေကောင်းပါတယ်။';
        } elseif ($score >= 65 && count($blockers) === 0) {
            $decision = 'CONDITIONAL GO';
            $decisionMm = 'စတင်နိုင်ပေမယ့် Risk အချက်တွေကို အရင်ပြင်သင့်ပါတယ်။';
        } elseif ($score < 50) {
            $decision = 'NO-GO AT CURRENT CONDITIONS';
            $decisionMm = 'လက်ရှိ Data နဲ့တော့ စတင်ဖို့မသင့်သေးပါ။ Business မအောင်မြင်နိုင်ဘူးလို့ဆိုလိုတာမဟုတ်ပါ။';
        } else {
            $decision = 'HOLD / IMPROVE FIRST';
            $decisionMm = 'အခုချက်ချင်းမစတင်သေးဘဲ အဓိကအားနည်းချက်တွေကို ပြင်ပြီး ပြန်တွက်သင့်ပါတယ်။';
        }

        return [
            'score' => $score,
            'decision' => $decision,
            'decision_mm' => $decisionMm,
            'dimensions' => $dimensions,
            'strengths' => array_values(array_unique($strengths)),
            'blockers' => array_values(array_unique($blockers)),
            'risks' => array_values(array_unique($risks)),
            'actions' => array_values(array_unique($actions)),
            'capital_coverage_percent' => round($capitalCoverage * 100, 1),
        ];
    }
}
