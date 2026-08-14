<?php

namespace App\Services\PbrTools\Domains;

class ContributionDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'contribution_balance' =>
                $tools['contribution_balance_chart']['data']
                ?? null,

            'time_contribution' =>
                $tools['time_contribution_tracker']['data']
                ?? null,

            'scorecard' =>
                $tools['partner_contribution_scorecard']['data']
                ?? null,

            'responsibilities' =>
                $tools['role_responsibility_matrix']['data']['responsibilities']
                ?? [],

            'vesting' =>
                $tools['vesting_calculator']['data']
                ?? null,

            'sweat_equity' =>
                $tools['sweat_equity_calculator']['data']
                ?? null,
        ];
    }
}
