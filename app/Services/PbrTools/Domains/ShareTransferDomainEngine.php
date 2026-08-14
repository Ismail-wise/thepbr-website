<?php

namespace App\Services\PbrTools\Domains;

class ShareTransferDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'latest_transfer_scenario' =>
                $tools['share_transfer_simulator']['data']
                ?? null,

            'ownership_before_after' =>
                $tools['ownership_before_after_chart']['data']
                ?? null,

            'first_refusal' =>
                $tools['first_refusal_workflow']['data']
                ?? null,

            'approval_rules' =>
                $tools['transfer_approval_matrix']['data']
                ?? null,

            'transfer_value' =>
                $tools['share_valuation_calculator']['data']
                ?? null,
        ];
    }
}
