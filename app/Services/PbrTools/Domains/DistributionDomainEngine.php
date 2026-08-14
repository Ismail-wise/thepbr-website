<?php

namespace App\Services\PbrTools\Domains;

class DistributionDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'profit_distribution' =>
                $tools['profit_distribution_calculator']['data']
                ?? [],

            'salary_profit_plan' =>
                $tools['salary_profit_share_planner']['data']
                ?? null,

            'retained_earnings' =>
                $tools['retained_earnings_calculator']['data']
                ?? null,

            'reserve_fund' =>
                $tools['reserve_fund_planner']['data']
                ?? null,

            'loss_sharing' =>
                $tools['loss_sharing_simulator']['data']
                ?? null,
        ];
    }
}
