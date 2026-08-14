<?php

namespace App\Services\PbrTools\Domains;

class ExitDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'buyout' =>
                $tools['partner_buyout_calculator']['data']
                ?? null,

            'exit_value' =>
                $tools['exit_value_simulator']['data']
                ?? null,

            'notice_plan' =>
                $tools['notice_period_planner']['data']
                ?? null,

            'exit_timeline' =>
                $tools['exit_timeline']['data']
                ?? null,

            'handover' =>
                $tools['responsibility_handover_checklist']['data']
                ?? null,

            'continuity' =>
                $tools['business_continuity_planner']['data']
                ?? null,
        ];
    }
}
