<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceToolOutput;

class ChapterOneIntegrationService
{
    public function latestOutputs(PartnershipWorkspace $workspace): array
    {
        $tools = ChapterTool::query()
            ->whereHas('chapter', fn ($query) => $query->where('chapter_number', 1))
            ->get(['id', 'tool_key', 'slug', 'title_en']);

        if ($tools->isEmpty()) {
            return [];
        }

        $outputs = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('chapter_tool_id', $tools->pluck('id'))
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->groupBy('chapter_tool_id')
            ->map(fn ($group) => $group->first());

        $result = [];

        foreach ($tools as $tool) {
            $output = $outputs->get($tool->id);
            if (! $output) {
                continue;
            }

            $result[$tool->tool_key] = [
                'tool' => $tool,
                'output' => $output,
                'data' => is_array($output->output_data) ? $output->output_data : [],
            ];
        }

        return $result;
    }

    public function summary(PartnershipWorkspace $workspace): array
    {
        $outputs = $this->latestOutputs($workspace);

        $startup = $this->value($outputs, 'startup_capital_planner', 'total_startup_capital');
        $startupFunded = $this->value($outputs, 'startup_capital_planner', 'funded_total');
        $currentNet = $this->value($outputs, 'current_capital_position', 'net_capital_position');
        $working = $this->value($outputs, 'working_capital_calculator', 'working_capital_required');
        $monthlyOperatingCost = $this->value($outputs, 'working_capital_calculator', 'monthly_operating_cost');
        $contingency = $this->value($outputs, 'contingency_fund_calculator', 'contingency_fund');
        $partnerCapital = $this->value($outputs, 'partner_contribution_matrix', 'total_contribution');
        $otherFunding = $this->value($outputs, 'funding_gap_calculator', 'other_funding');

        $capitalRequired = $workspace->business_stage === 'new'
            ? round($startup + $working + $contingency, 2)
            : round($working + $contingency, 2);

        // Startup Plan can hold confirmed funding before the detailed funding modules
        // are configured. Use the stronger of the two views rather than adding them,
        // because they can describe the same money and must never be double-counted.
        $detailedFunding = round($partnerCapital + $otherFunding, 2);
        $capitalSecured = round(max($startupFunded, $detailedFunding), 2);

        $fundingGap = round(max(0, $capitalRequired - $capitalSecured), 2);
        $fundingSurplus = round(max(0, $capitalSecured - $capitalRequired), 2);

        $allocations = [];
        if ($workspace->business_stage === 'new' && $startup > 0) {
            $allocations[] = ['name' => 'Startup Capital', 'amount' => $startup];
        }
        if ($working > 0) {
            $allocations[] = ['name' => 'Working Capital', 'amount' => $working];
        }
        if ($contingency > 0) {
            $allocations[] = ['name' => 'Contingency Reserve', 'amount' => $contingency];
        }

        return [
            'outputs' => $outputs,
            'startup_capital' => $startup,
            'startup_funded' => $startupFunded,
            'current_net_capital_position' => $currentNet,
            'working_capital' => $working,
            'monthly_operating_cost' => $monthlyOperatingCost,
            'contingency_fund' => $contingency,
            'partner_capital' => $partnerCapital,
            'other_funding' => $otherFunding,
            'capital_required' => $capitalRequired,
            'capital_secured' => $capitalSecured,
            'funding_gap' => $fundingGap,
            'funding_surplus' => $fundingSurplus,
            'allocations' => $allocations,
        ];
    }

    public function operatingSnapshot(PartnershipWorkspace $workspace): array
    {
        $summary = $this->summary($workspace);
        $sources = [];

        foreach ($summary['outputs'] ?? [] as $toolKey => $entry) {
            $output = $entry['output'] ?? null;
            if (! $output instanceof WorkspaceToolOutput) {
                continue;
            }

            $sources[$toolKey] = [
                'workspace_tool_output_id' => $output->id,
                'revision' => $output->revision,
                'status' => $output->status,
                'result' => $entry['data'] ?? [],
                'generated_at' => $output->generated_at?->toIso8601String(),
                'agreed_at' => $output->agreed_at?->toIso8601String(),
            ];
        }

        $capitalSummary = [
            'startup_capital' => $summary['startup_capital'],
            'startup_funded' => $summary['startup_funded'],
            'current_net_capital_position' => $summary['current_net_capital_position'],
            'working_capital' => $summary['working_capital'],
            'monthly_operating_cost' => $summary['monthly_operating_cost'],
            'contingency_fund' => $summary['contingency_fund'],
            'partner_capital' => $summary['partner_capital'],
            'other_funding' => $summary['other_funding'],
            'capital_required' => $summary['capital_required'],
            'capital_secured' => $summary['capital_secured'],
            'funding_gap' => $summary['funding_gap'],
            'funding_surplus' => $summary['funding_surplus'],
        ];

        return [
            'payload' => [
                'business_stage' => $workspace->business_stage,
                'currency_code' => $workspace->currency_code,
                'capital' => $capitalSummary,
                'allocations' => $summary['allocations'],
                'source_outputs' => $sources,
            ],
            'summary' => $capitalSummary,
        ];
    }

    public function prefill(
        PartnershipWorkspace $workspace,
        string $toolKey,
        array $input
    ): array {
        $summary = $this->summary($workspace);

        if ($toolKey === 'contingency_fund_calculator') {
            if ($this->blank($input['base_capital'] ?? null)) {
                $input['base_capital'] = round(
                    $summary['startup_capital'] + $summary['working_capital'],
                    2
                );
            }

            if (
                $this->blank($input['monthly_operating_cost'] ?? null)
                && $summary['monthly_operating_cost'] > 0
            ) {
                $input['monthly_operating_cost'] = $summary['monthly_operating_cost'];
            }
        }

        if ($toolKey === 'funding_gap_calculator') {
            if ($this->blank($input['capital_required'] ?? null)) {
                $input['capital_required'] = $summary['capital_required'];
            }

            if ($this->blank($input['partner_capital'] ?? null)) {
                $input['partner_capital'] = $summary['partner_capital'];
            }

            if (
                $this->blank($input['other_funding'] ?? null)
                && $summary['other_funding'] > 0
            ) {
                $input['other_funding'] = $summary['other_funding'];
            }
        }

        if ($toolKey === 'capital_allocation_chart') {
            $input['allocations'] = $summary['allocations'];
        }

        return $input;
    }

    private function value(array $outputs, string $toolKey, string $field): float
    {
        $value = $outputs[$toolKey]['data'][$field] ?? 0;
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
