<?php

namespace App\Services\PbrTools;

use App\Services\PbrTools\Domains\CapitalDomainEngine;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceToolOutput;

class PbrChapterStateService
{
    public function __construct(
        private readonly CapitalDomainEngine $capitalDomain
    ) {
    }

    public function build(
        PartnershipWorkspace $workspace,
        int $chapterNumber,
        string $status = 'agreed'
    ): array {
        abort_unless($chapterNumber >= 1 && $chapterNumber <= 10, 404);
        abort_unless(in_array($status, ['draft', 'agreed'], true), 422);

        $tools = ChapterTool::query()
            ->whereHas(
                'chapter',
                fn ($query) => $query->where('chapter_number', $chapterNumber)
            )
            ->get(['id', 'tool_key', 'slug', 'title_en', 'title_mm']);

        $outputQuery = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('chapter_tool_id', $tools->pluck('id'));

        if ($status === 'agreed') {
            $outputQuery->where('status', 'agreed');
        } else {
            // A working/draft chapter state should keep the newest output for
            // every tool. That means a newly changed draft can coexist with
            // previously agreed rules from other tools in the same chapter.
            // The official agreed state below never reads draft outputs.
            $outputQuery->whereIn('status', ['draft', 'agreed']);
        }

        $latestOutputs = $outputQuery
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->keyBy('chapter_tool_id');

        $toolPayload = [];

        foreach ($tools as $tool) {
            $output = $latestOutputs->get($tool->id);

            if (! $output) {
                continue;
            }

            $toolPayload[$tool->tool_key] = [
                'workspace_tool_output_id' => $output->id,
                'revision' => $output->revision,
                'status' => $output->status,
                'result' => is_array($output->output_data) ? $output->output_data : [],
                'data' => is_array($output->output_data['data'] ?? null)
                    ? $output->output_data['data']
                    : (is_array($output->output_data) ? $output->output_data : []),
                'generated_at' => $output->generated_at?->toIso8601String(),
                'agreed_at' => $output->agreed_at?->toIso8601String(),
            ];
        }

        $summary = $this->summaryForChapter(
            $workspace,
            $chapterNumber,
            $toolPayload
        );

        return [
            'payload' => [
                'chapter' => $chapterNumber,
                'domain' => PbrOperatingSystemService::DOMAINS[$chapterNumber],
                'business_stage' => $workspace->business_stage,
                'currency_code' => $workspace->currency_code,
                'source_status' => $status === 'draft'
                    ? 'working_latest_draft_or_agreed'
                    : 'agreed_only',
                'tools' => $toolPayload,
                'canonical' => $summary,
            ],
            'summary' => $summary,
        ];
    }

    private function summaryForChapter(
        PartnershipWorkspace $workspace,
        int $chapter,
        array $tools
    ): array {
        return match ($chapter) {
            1 => $this->capitalSummary($workspace, $tools),
            2 => $this->ownershipSummary($tools),
            3 => $this->contributionSummary($tools),
            4 => $this->distributionSummary($tools),
            5 => $this->financialSummary($tools),
            6 => $this->governanceSummary($tools),
            7 => $this->exitSummary($tools),
            8 => $this->continuitySummary($tools),
            9 => $this->transferSummary($tools),
            10 => $this->disputeSummary($tools),
            default => [],
        };
    }

    private function capitalSummary(
        PartnershipWorkspace $workspace,
        array $tools
    ): array {
        return $this->capitalDomain->summarize(
            $workspace,
            $tools
        );
    }

    private function ownershipSummary(array $tools): array
    {
        $cap = $tools['cap_table_builder']['data'] ?? [];
        $chart = $tools['ownership_chart']['data'] ?? [];
        $share = $tools['share_value_calculator']['data'] ?? [];
        $holders = $cap['holders'] ?? $chart['holders'] ?? [];

        return [
            'total_units' => $cap['issued_units'] ?? $chart['total_units'] ?? $share['total_units'] ?? null,
            'reserved_units' => $cap['reserved_units'] ?? null,
            'fully_diluted_units' => $cap['fully_diluted_units'] ?? null,
            'holders' => $holders,
            'per_unit_value' => $share['per_unit'] ?? null,
            'one_percent_value' => $share['one_percent_value'] ?? null,
            'latest_dilution' => $tools['future_dilution_simulator']['data'] ?? null,
        ];
    }

    private function contributionSummary(array $tools): array
    {
        return [
            'contribution_balance' => $tools['contribution_balance_chart']['data'] ?? null,
            'time_contribution' => $tools['time_contribution_tracker']['data'] ?? null,
            'scorecard' => $tools['partner_contribution_scorecard']['data'] ?? null,
            'responsibilities' => $tools['role_responsibility_matrix']['data']['responsibilities'] ?? [],
            'vesting' => $tools['vesting_calculator']['data'] ?? null,
            'sweat_equity' => $tools['sweat_equity_calculator']['data'] ?? null,
        ];
    }

    private function distributionSummary(array $tools): array
    {
        $profit = $tools['profit_distribution_calculator']['data'] ?? [];

        return [
            'profit_distribution' => $profit,
            'salary_profit_plan' => $tools['salary_profit_share_planner']['data'] ?? null,
            'retained_earnings' => $tools['retained_earnings_calculator']['data'] ?? null,
            'reserve_fund' => $tools['reserve_fund_planner']['data'] ?? null,
            'loss_sharing' => $tools['loss_sharing_simulator']['data'] ?? null,
        ];
    }

    private function financialSummary(array $tools): array
    {
        return [
            'cashflow' => $tools['cashflow_dashboard']['data'] ?? null,
            'budget' => $tools['monthly_budget_planner']['data']
                ?? $tools['budget_actual_chart']['data']
                ?? null,
            'expense_approval' => $tools['expense_approval_matrix']['data'] ?? null,
            'bank_authority' => $tools['bank_authority_matrix']['data'] ?? null,
            'financial_controls' => $tools['financial_control_checklist']['data'] ?? null,
            'large_payment_rules' => $tools['large_payment_approval_rules']['data'] ?? null,
        ];
    }

    private function governanceSummary(array $tools): array
    {
        return [
            'partner_roles' => $tools['partner_role_matrix']['data'] ?? null,
            'decision_rights' => $tools['decision_rights_matrix']['data'] ?? null,
            'authority_levels' => $tools['authority_level_builder']['data'] ?? null,
            'voting' => $tools['voting_simulator']['data'] ?? null,
            'deadlock_rule' => $tools['deadlock_detector']['data'] ?? null,
            'structure' => $tools['governance_structure_chart']['data'] ?? null,
        ];
    }

    private function exitSummary(array $tools): array
    {
        return [
            'buyout' => $tools['partner_buyout_calculator']['data'] ?? null,
            'exit_value' => $tools['exit_value_simulator']['data'] ?? null,
            'notice_plan' => $tools['notice_period_planner']['data'] ?? null,
            'exit_timeline' => $tools['exit_timeline']['data'] ?? null,
            'handover' => $tools['responsibility_handover_checklist']['data'] ?? null,
            'continuity' => $tools['business_continuity_planner']['data'] ?? null,
        ];
    }

    private function continuitySummary(array $tools): array
    {
        return [
            'key_person_dependencies' => $tools['key_person_dependency_map']['data'] ?? null,
            'succession' => $tools['succession_planner']['data'] ?? null,
            'emergency_authority' => $tools['emergency_authority_planner']['data'] ?? null,
            'ownership_transition' => $tools['ownership_transition_simulator']['data'] ?? null,
            'continuity_checklist' => $tools['continuity_checklist']['data'] ?? null,
            'insurance_gap' => $tools['insurance_coverage_gap_calculator']['data'] ?? null,
        ];
    }

    private function transferSummary(array $tools): array
    {
        return [
            'latest_transfer_scenario' => $tools['share_transfer_simulator']['data'] ?? null,
            'ownership_before_after' => $tools['ownership_before_after_chart']['data'] ?? null,
            'first_refusal' => $tools['first_refusal_workflow']['data'] ?? null,
            'approval_rules' => $tools['transfer_approval_matrix']['data'] ?? null,
            'transfer_value' => $tools['share_valuation_calculator']['data'] ?? null,
        ];
    }

    private function disputeSummary(array $tools): array
    {
        return [
            'escalation_ladder' => $tools['conflict_escalation_ladder']['data'] ?? null,
            'latest_dispute' => $tools['dispute_log']['data'] ?? null,
            'resolution' => $tools['resolution_tracker']['data'] ?? null,
            'deadlock' => $tools['deadlock_decision_tool']['data'] ?? null,
            'priorities' => $tools['issue_priority_matrix']['data'] ?? null,
            'timeline' => $tools['escalation_timeline']['data'] ?? null,
        ];
    }

    private function value(array $tools, string $toolKey, string $field): float
    {
        $data = $tools[$toolKey]['data'] ?? [];
        $value = $data[$field] ?? 0;

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
