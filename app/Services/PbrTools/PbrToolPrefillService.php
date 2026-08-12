<?php

namespace App\Services\PbrTools;

use App\Models\BusinessValuation;
use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspacePartnerProfile;

class PbrToolPrefillService
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem
    ) {
    }

    public function prefill(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        array $input
    ): array {
        $snapshots = [];

        foreach (PbrOperatingSystemService::DOMAINS as $domain) {
            $snapshot = $this->operatingSystem->latestSnapshot(
                $workspace,
                $domain,
                'agreed'
            );

            if ($snapshot) {
                $snapshots[$domain] = $snapshot->summary ?? [];
            }
        }

        $valuation = BusinessValuation::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->first();

        $valuationResult = is_array($valuation?->result)
            ? $valuation->result
            : [];

        $partners = WorkspacePartnerProfile::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'planned'])
            ->orderBy('id')
            ->get(['display_name'])
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        $ownership = $snapshots['ownership'] ?? [];
        $capital = $snapshots['capital'] ?? [];
        $distribution = $snapshots['distribution'] ?? [];

        return match ($tool->tool_key) {
            'equity_split_simulator' => $this->prefillEquitySplit($input, $partners, $capital),
            'cap_table_builder',
            'ownership_chart' => $this->prefillOwnershipRows($input, $partners, $ownership),
            'voting_power_calculator' => $this->prefillVotingRows($input, $partners, $ownership),
            'share_value_calculator' => $this->prefillShareValue($input, $valuationResult, $ownership),
            'future_dilution_simulator' => $this->prefillDilution($input, $partners, $ownership),
            'time_contribution_tracker',
            'partner_contribution_scorecard',
            'contribution_balance_chart' => $this->prefillPartnerRows($input, $partners),
            'profit_distribution_calculator',
            'loss_sharing_simulator' => $this->prefillDistributionPartners($input, $partners, $ownership),
            'salary_profit_share_planner' => $this->prefillSalaryPartners($input, $partners, $distribution, $ownership),
            'reserve_fund_planner' => $this->fillBlank($input, 'monthly_operating_cost', $capital['monthly_operating_cost'] ?? null),
            'cashflow_dashboard' => $this->fillBlank($input, 'monthly_fixed_cost', $capital['monthly_operating_cost'] ?? null),
            'voting_simulator' => $this->prefillVotingSimulator($input, $partners, $ownership),
            'partner_buyout_calculator' => $this->prefillBuyout($input, $valuationResult),
            'exit_value_simulator' => $this->prefillExitValue($input, $valuationResult),
            'ownership_transition_simulator' => $this->fillBlank($input, 'business_value', $valuationResult['base'] ?? null),
            'share_transfer_simulator' => $this->prefillShareTransfer($input, $ownership),
            'ownership_before_after_chart' => $this->prefillBeforeAfter($input, $ownership),
            'share_valuation_calculator' => $this->prefillTransferValue($input, $valuationResult, $ownership),
            default => $input,
        };
    }

    private function prefillEquitySplit(array $input, array $partners, array $capital): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $capitalRows = collect($capital['partner_contributions'] ?? [])
            ->keyBy(fn ($row) => mb_strtolower(trim((string) ($row['name'] ?? ''))));

        $input['partners'] = collect($partners)->map(function (string $name) use ($capitalRows): array {
            $capitalRow = $capitalRows->get(mb_strtolower(trim($name)));

            return [
                'name' => $name,
                'capital' => $capitalRow['total'] ?? 0,
                'work' => 0,
                'expertise' => 0,
                'risk' => 0,
            ];
        })->all();

        return $input;
    }

    private function prefillOwnershipRows(array $input, array $partners, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        if (! empty($holders)) {
            $input['partners'] = collect($holders)->map(fn ($holder): array => [
                'name' => $holder['holder'] ?? $holder['partner'] ?? '',
                'units' => $holder['units'] ?? 0,
                'voting_units' => $holder['voting_units'] ?? $holder['units'] ?? 0,
            ])->all();

            return $input;
        }

        $input['partners'] = collect($partners)->map(fn (string $name): array => [
            'name' => $name,
            'units' => 0,
            'voting_units' => 0,
        ])->all();

        return $input;
    }

    private function prefillVotingRows(array $input, array $partners, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        $input['partners'] = ! empty($holders)
            ? collect($holders)->map(fn ($holder): array => [
                'name' => $holder['holder'] ?? $holder['partner'] ?? '',
                'voting_units' => $holder['voting_units'] ?? $holder['units'] ?? 0,
            ])->all()
            : collect($partners)->map(fn (string $name): array => ['name' => $name, 'voting_units' => 0])->all();

        return $input;
    }

    private function prefillShareValue(array $input, array $valuation, array $ownership): array
    {
        $input = $this->fillBlank($input, 'equity_value', $valuation['base'] ?? null);
        $input = $this->fillBlank(
            $input,
            'total_units',
            $ownership['total_units']
                ?? $valuation['ownership']['total_units']
                ?? null
        );

        return $input;
    }

    private function prefillDilution(array $input, array $partners, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        $input['partners'] = ! empty($holders)
            ? collect($holders)->map(fn ($holder): array => [
                'name' => $holder['holder'] ?? $holder['partner'] ?? '',
                'units' => $holder['units'] ?? 0,
            ])->all()
            : collect($partners)->map(fn (string $name): array => ['name' => $name, 'units' => 0])->all();

        return $input;
    }

    private function prefillPartnerRows(array $input, array $partners): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $input['partners'] = collect($partners)->map(fn (string $name): array => [
            'name' => $name,
        ])->all();

        return $input;
    }

    private function prefillDistributionPartners(array $input, array $partners, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        if (! empty($holders)) {
            $input['partners'] = collect($holders)->map(fn ($holder): array => [
                'name' => $holder['holder'] ?? $holder['partner'] ?? '',
                'percentage' => $holder['ownership_percentage'] ?? $holder['percentage'] ?? 0,
            ])->all();

            return $input;
        }

        $equal = count($partners) > 0 ? round(100 / count($partners), 2) : 0;
        $input['partners'] = collect($partners)->map(fn (string $name): array => [
            'name' => $name,
            'percentage' => $equal,
        ])->all();

        return $input;
    }

    private function prefillSalaryPartners(array $input, array $partners, array $distribution, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $source = $distribution['profit_distribution']['partners'] ?? [];
        if (! empty($source)) {
            $input['partners'] = collect($source)->map(fn ($row): array => [
                'name' => $row['partner'] ?? '',
                'monthly_salary' => 0,
                'profit_share' => $row['profit_share'] ?? 0,
            ])->all();

            return $input;
        }

        $distributionInput = ['partners' => []];
        $distributionInput = $this->prefillDistributionPartners($distributionInput, $partners, $ownership);
        $input['partners'] = collect($distributionInput['partners'])->map(fn ($row): array => [
            'name' => $row['name'] ?? '',
            'monthly_salary' => 0,
            'profit_share' => $row['percentage'] ?? 0,
        ])->all();

        return $input;
    }

    private function prefillVotingSimulator(array $input, array $partners, array $ownership): array
    {
        if (! empty($input['votes'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        $input['votes'] = ! empty($holders)
            ? collect($holders)->map(fn ($holder): array => [
                'name' => $holder['holder'] ?? $holder['partner'] ?? '',
                'weight' => $holder['voting_percentage'] ?? $holder['ownership_percentage'] ?? 0,
                'vote' => 'abstain',
            ])->all()
            : collect($partners)->map(fn (string $name): array => ['name' => $name, 'weight' => 0, 'vote' => 'abstain'])->all();

        return $input;
    }

    private function prefillBuyout(array $input, array $valuation): array
    {
        return $this->fillBlank($input, 'business_value', $valuation['base'] ?? null);
    }

    private function prefillExitValue(array $input, array $valuation): array
    {
        $input = $this->fillBlank($input, 'conservative_value', $valuation['conservative'] ?? null);
        $input = $this->fillBlank($input, 'base_value', $valuation['base'] ?? null);
        $input = $this->fillBlank($input, 'optimistic_value', $valuation['optimistic'] ?? null);

        return $input;
    }

    private function prefillShareTransfer(array $input, array $ownership): array
    {
        return $this->fillBlank($input, 'total_units', $ownership['total_units'] ?? null);
    }

    private function prefillBeforeAfter(array $input, array $ownership): array
    {
        if (! empty($input['partners'])) {
            return $input;
        }

        $holders = $ownership['holders'] ?? [];
        $input['partners'] = collect($holders)->map(fn ($holder): array => [
            'name' => $holder['holder'] ?? $holder['partner'] ?? '',
            'before_units' => $holder['units'] ?? 0,
            'after_units' => $holder['units'] ?? 0,
        ])->all();

        return $input;
    }

    private function prefillTransferValue(array $input, array $valuation, array $ownership): array
    {
        $input = $this->fillBlank($input, 'business_value', $valuation['base'] ?? null);
        $input = $this->fillBlank(
            $input,
            'total_units',
            $ownership['total_units']
                ?? $valuation['ownership']['total_units']
                ?? null
        );

        return $input;
    }

    private function fillBlank(array $input, string $key, mixed $value): array
    {
        if (($input[$key] ?? null) === '' || ($input[$key] ?? null) === null) {
            if ($value !== null && $value !== '') {
                $input[$key] = $value;
            }
        }

        return $input;
    }
}
