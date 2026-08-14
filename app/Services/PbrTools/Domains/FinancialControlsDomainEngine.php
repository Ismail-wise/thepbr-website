<?php

namespace App\Services\PbrTools\Domains;

class FinancialControlsDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'cashflow' =>
                $tools['cashflow_dashboard']['data']
                ?? null,

            'budget' =>
                $tools['monthly_budget_planner']['data']
                ?? $tools['budget_actual_chart']['data']
                ?? null,

            'expense_approval' =>
                $tools['expense_approval_matrix']['data']
                ?? null,

            'bank_authority' =>
                $tools['bank_authority_matrix']['data']
                ?? null,

            'financial_controls' =>
                $tools['financial_control_checklist']['data']
                ?? null,

            'large_payment_rules' =>
                $tools['large_payment_approval_rules']['data']
                ?? null,
        ];
    }
}
