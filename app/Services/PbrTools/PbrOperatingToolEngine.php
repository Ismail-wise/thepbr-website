<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PbrOperatingToolEngine
{
    public function definition(string $toolKey): array
    {
        $definition = config('pbr_operating_tools.definitions.'.$toolKey);

        abort_unless(is_array($definition), 404);

        return $definition;
    }

    public function rules(string $toolKey): array
    {
        $rules = [];

        foreach ($this->definition($toolKey)['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($name === '') {
                continue;
            }

            if ($type === 'repeater') {
                $rules[$name] = ['nullable', 'array', 'max:'.(int) ($field['max'] ?? 30)];

                foreach ($field['columns'] ?? [] as $column) {
                    $columnName = (string) ($column['name'] ?? '');

                    if ($columnName === '') {
                        continue;
                    }

                    $rules[$name.'.*.'.$columnName] = $this->fieldRules($column, true);
                }

                continue;
            }

            if ($type === 'checklist') {
                $rules[$name] = ['nullable', 'array'];

                foreach (array_keys($field['items'] ?? []) as $itemKey) {
                    $rules[$name.'.'.$itemKey] = ['nullable', 'boolean'];
                }

                continue;
            }

            $rules[$name] = $this->fieldRules($field);
        }

        return $rules;
    }

    public function defaultInput(string $toolKey): array
    {
        $input = [];

        foreach ($this->definition($toolKey)['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($name === '') {
                continue;
            }

            if ($type === 'repeater') {
                $input[$name] = [];
                continue;
            }

            if ($type === 'checklist') {
                $input[$name] = collect(array_keys($field['items'] ?? []))
                    ->mapWithKeys(fn (string $key): array => [$key => false])
                    ->all();
                continue;
            }

            $input[$name] = $field['default'] ?? '';
        }

        return $input;
    }

    public function calculate(
        string $toolKey,
        array $input,
        PartnershipWorkspace $workspace
    ): array {
        $definition = $this->definition($toolKey);
        $handler = (string) ($definition['handler'] ?? '');

        $result = match ($handler) {
            'equity_split' => $this->equitySplit($input),
            'cap_table' => $this->capTable($input),
            'voting_power' => $this->votingPower($input),
            'share_value' => $this->shareValue($input),
            'dilution' => $this->dilution($input),
            'ownership_chart' => $this->ownershipChart($input),
            'sweat_equity' => $this->sweatEquity($input),
            'time_contribution' => $this->timeContribution($input),
            'contribution_scorecard' => $this->contributionScorecard($input),
            'role_matrix' => $this->roleMatrix($input),
            'vesting' => $this->vesting($input),
            'contribution_balance' => $this->contributionBalance($input),
            'profit_distribution' => $this->profitDistribution($input),
            'salary_profit' => $this->salaryProfit($input),
            'retained_earnings' => $this->retainedEarnings($input),
            'reserve_fund' => $this->reserveFund($input),
            'loss_sharing' => $this->lossSharing($input),
            'distribution_compare' => $this->distributionCompare($input),
            'cashflow' => $this->cashflow($input),
            'budget' => $this->budget($input),
            'approval_matrix' => $this->approvalMatrix($input),
            'bank_authority' => $this->bankAuthority($input),
            'checklist' => $this->checklist($input, $definition),
            'decision_rights' => $this->decisionRights($input),
            'authority_levels' => $this->authorityLevels($input),
            'voting_simulator' => $this->votingSimulator($input),
            'decision_log' => $this->decisionLog($input),
            'deadlock' => $this->deadlock($input),
            'governance_chart' => $this->governanceChart($input),
            'buyout' => $this->buyout($input),
            'exit_value' => $this->exitValue($input),
            'notice_plan' => $this->noticePlan($input),
            'exit_timeline' => $this->exitTimeline($input),
            'continuity_plan' => $this->continuityPlan($input),
            'key_person' => $this->keyPerson($input),
            'succession' => $this->succession($input),
            'emergency_authority' => $this->emergencyAuthority($input),
            'ownership_transition' => $this->ownershipTransition($input),
            'insurance_gap' => $this->insuranceGap($input),
            'share_transfer' => $this->shareTransfer($input),
            'before_after' => $this->beforeAfter($input),
            'rofr' => $this->rofr($input),
            'transfer_approval' => $this->transferApproval($input),
            'transfer_value' => $this->transferValue($input),
            'transfer_history' => $this->transferHistory($input),
            'escalation_ladder' => $this->escalationLadder($input),
            'dispute_log' => $this->disputeLog($input),
            'resolution_tracker' => $this->resolutionTracker($input),
            'issue_priority' => $this->issuePriority($input),
            'escalation_timeline' => $this->escalationTimeline($input),
            default => throw ValidationException::withMessages([
                'tool' => 'ဒီ tool အတွက် calculation engine မတပ်ဆင်ရသေးပါ။',
            ]),
        };

        $result['tool_key'] = $toolKey;
        $result['chapter'] = (int) ($definition['chapter'] ?? 0);
        $result['currency'] = $workspace->currency_code ?: 'THB';
        $result['calculated_at'] = now()->toIso8601String();
        $result['planning_note_mm'] = config(
            'pbr_operating_tools.shared_notes.planning_only_mm'
        );

        return $result;
    }

    private function fieldRules(array $field, bool $repeaterColumn = false): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $rules = ['nullable'];

        if ($type === 'number') {
            $rules[] = 'numeric';

            if (array_key_exists('min', $field)) {
                $rules[] = 'min:'.$field['min'];
            }

            if (array_key_exists('max', $field)) {
                $rules[] = 'max:'.$field['max'];
            }

            return $rules;
        }

        if ($type === 'date') {
            return ['nullable', 'date'];
        }

        if ($type === 'select') {
            $options = array_keys($field['options'] ?? []);
            return empty($options)
                ? ['nullable', 'string', 'max:120']
                : ['nullable', 'string', 'in:'.implode(',', $options)];
        }

        $rules[] = 'string';
        $rules[] = $type === 'textarea' ? 'max:5000' : 'max:255';

        return $rules;
    }

    private function equitySplit(array $input): array
    {
        $weights = [
            'capital' => $this->pct($input['capital_weight'] ?? 0),
            'work' => $this->pct($input['work_weight'] ?? 0),
            'expertise' => $this->pct($input['expertise_weight'] ?? 0),
            'risk' => $this->pct($input['risk_weight'] ?? 0),
        ];
        $weightTotal = round(array_sum($weights), 2);
        $partners = $this->rows($input['partners'] ?? []);
        $categoryTotals = ['capital' => 0.0, 'work' => 0.0, 'expertise' => 0.0, 'risk' => 0.0];

        foreach ($partners as $partner) {
            foreach (array_keys($categoryTotals) as $category) {
                $categoryTotals[$category] += $this->amount($partner[$category] ?? 0);
            }
        }

        $table = [];
        foreach ($partners as $partner) {
            $score = 0.0;
            foreach ($weights as $category => $weight) {
                $value = $this->amount($partner[$category] ?? 0);
                $share = $categoryTotals[$category] > 0
                    ? $value / $categoryTotals[$category]
                    : 0;
                $score += $share * $weight;
            }

            $table[] = [
                'partner' => $this->name($partner['name'] ?? ''),
                'capital' => $this->amount($partner['capital'] ?? 0),
                'work' => $this->amount($partner['work'] ?? 0),
                'expertise' => $this->amount($partner['expertise'] ?? 0),
                'risk' => $this->amount($partner['risk'] ?? 0),
                'reference_percentage' => round($weightTotal > 0 ? ($score / $weightTotal) * 100 : 0, 2),
            ];
        }

        $warnings = [];
        if (abs($weightTotal - 100) > 0.01) {
            $warnings[] = 'Weight စုစုပေါင်းက 100% မဟုတ်သေးပါ။ Result ကို normalized reference အဖြစ်သာပြထားပါတယ်။';
        }
        if (count($table) < 2) {
            $warnings[] = 'Partnership comparison အတွက် အနည်းဆုံး Partner ၂ ယောက်ထည့်ပါ။';
        }

        return $this->result(
            'Reference Equity Split',
            count($table).' Partners',
            'text',
            [
                $this->metric('Weight Total', $weightTotal, 'percent'),
                $this->metric('Partners', count($table), 'number'),
            ],
            [$this->table('Weighted Contribution Reference', [
                'partner' => 'Partner',
                'capital' => 'Capital',
                'work' => 'Work Value',
                'expertise' => 'Expertise',
                'risk' => 'Risk / Responsibility',
                'reference_percentage' => 'Reference %',
            ], $table)],
            $warnings,
            ['ဒီ percentage ကို “fair ownership အတိအကျ” လို့မယူပါနဲ့။ Negotiation, legal structure, tax, vesting နဲ့ future funding ကိုပါ review လုပ်ပါ။'],
            ['weights' => $weights, 'weight_total' => $weightTotal, 'partners' => $table]
        );
    }

    private function capTable(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $reserved = $this->amount($input['reserved_units'] ?? 0);
        $issued = 0.0;
        $votes = 0.0;

        foreach ($partners as $partner) {
            $issued += $this->amount($partner['units'] ?? 0);
            $votes += $this->amount($partner['voting_units'] ?? 0);
        }

        $fullyDiluted = $issued + $reserved;
        $table = [];
        foreach ($partners as $partner) {
            $units = $this->amount($partner['units'] ?? 0);
            $votingUnits = $this->amount($partner['voting_units'] ?? 0);
            $table[] = [
                'holder' => $this->name($partner['name'] ?? ''),
                'units' => $units,
                'voting_units' => $votingUnits,
                'ownership_percentage' => $issued > 0 ? round($units / $issued * 100, 2) : 0,
                'fully_diluted_percentage' => $fullyDiluted > 0 ? round($units / $fullyDiluted * 100, 2) : 0,
                'voting_percentage' => $votes > 0 ? round($votingUnits / $votes * 100, 2) : 0,
            ];
        }

        return $this->result(
            'Current Cap Table',
            $issued,
            'units',
            [
                $this->metric('Issued Units', $issued, 'number'),
                $this->metric('Reserved Units', $reserved, 'number'),
                $this->metric('Fully Diluted Units', $fullyDiluted, 'number'),
                $this->metric('Voting Units', $votes, 'number'),
            ],
            [$this->table('Ownership Structure', [
                'holder' => 'Holder', 'units' => 'Units', 'ownership_percentage' => 'Issued %',
                'fully_diluted_percentage' => 'Fully Diluted %', 'voting_percentage' => 'Voting %',
            ], $table)],
            [],
            ['Ownership units, legal shares and voting rights can differ by entity type and agreement.'],
            ['issued_units' => $issued, 'reserved_units' => $reserved, 'fully_diluted_units' => $fullyDiluted, 'holders' => $table]
        );
    }

    private function votingPower(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $threshold = $this->pct($input['approval_threshold'] ?? 50);
        $total = array_sum(array_map(fn ($row) => $this->amount($row['voting_units'] ?? 0), $partners));
        $table = [];
        $warnings = [];

        foreach ($partners as $partner) {
            $units = $this->amount($partner['voting_units'] ?? 0);
            $percentage = $total > 0 ? round($units / $total * 100, 2) : 0;
            $table[] = [
                'partner' => $this->name($partner['name'] ?? ''),
                'voting_units' => $units,
                'voting_percentage' => $percentage,
                'can_reach_threshold_alone' => $percentage >= $threshold ? 'Yes' : 'No',
            ];

            if ($threshold > 0 && $percentage >= $threshold) {
                $warnings[] = $this->name($partner['name'] ?? '').' တစ်ယောက်တည်းက configured threshold ကိုရောက်နိုင်ပါတယ်။ ဒီဟာက intentional governance design ဟုတ်/မဟုတ် review လုပ်ပါ။';
            }
        }

        return $this->result(
            'Voting Threshold', $threshold, 'percent',
            [$this->metric('Total Voting Units', $total, 'number'), $this->metric('Approval Threshold', $threshold, 'percent')],
            [$this->table('Voting Power', ['partner' => 'Partner', 'voting_units' => 'Units', 'voting_percentage' => 'Voting %', 'can_reach_threshold_alone' => 'Alone?'], $table)],
            array_values(array_unique($warnings)),
            ['Voting power is a governance setting; confirm it matches the partnership/company agreement and applicable law.'],
            ['threshold' => $threshold, 'total_voting_units' => $total, 'partners' => $table]
        );
    }

    private function shareValue(array $input): array
    {
        $value = $this->amount($input['equity_value'] ?? 0);
        $units = max(1, $this->amount($input['total_units'] ?? 1));
        $stake = $this->pct($input['stake_percentage'] ?? 1);
        $perUnit = round($value / $units, 6);
        $stakeValue = round($value * ($stake / 100), 2);

        return $this->result(
            'Estimated Price per Share / Ownership Unit', $perUnit, 'money',
            [
                $this->metric('Business Equity Value', $value, 'money'),
                $this->metric('Total Units', $units, 'number'),
                $this->metric('1% Ownership', round($value * 0.01, 2), 'money'),
                $this->metric($stake.'% Stake', $stakeValue, 'money'),
            ], [], [],
            ['Indicative value only. Legal share price, transaction price and certified valuation may differ.'],
            ['equity_value' => $value, 'total_units' => $units, 'per_unit' => $perUnit, 'one_percent_value' => round($value * 0.01, 2), 'stake_percentage' => $stake, 'stake_value' => $stakeValue]
        );
    }

    private function dilution(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $newUnits = $this->amount($input['new_units'] ?? 0);
        $oldTotal = array_sum(array_map(fn ($row) => $this->amount($row['units'] ?? 0), $partners));
        $newTotal = $oldTotal + $newUnits;
        $table = [];

        foreach ($partners as $partner) {
            $units = $this->amount($partner['units'] ?? 0);
            $before = $oldTotal > 0 ? $units / $oldTotal * 100 : 0;
            $after = $newTotal > 0 ? $units / $newTotal * 100 : 0;
            $table[] = [
                'holder' => $this->name($partner['name'] ?? ''),
                'units' => $units,
                'before_percentage' => round($before, 2),
                'after_percentage' => round($after, 2),
                'dilution_points' => round(max(0, $before - $after), 2),
            ];
        }

        if ($newUnits > 0) {
            $table[] = [
                'holder' => $this->name($input['new_holder_name'] ?? 'New Holder'),
                'units' => $newUnits,
                'before_percentage' => 0,
                'after_percentage' => $newTotal > 0 ? round($newUnits / $newTotal * 100, 2) : 0,
                'dilution_points' => 0,
            ];
        }

        $newHolderPct = $newTotal > 0 ? round($newUnits / $newTotal * 100, 2) : 0;

        return $this->result(
            'New Holder Ownership', $newHolderPct, 'percent',
            [$this->metric('Old Total Units', $oldTotal, 'number'), $this->metric('New Units', $newUnits, 'number'), $this->metric('Post-Issue Units', $newTotal, 'number')],
            [$this->table('Before / After Dilution', ['holder' => 'Holder', 'units' => 'Units', 'before_percentage' => 'Before %', 'after_percentage' => 'After %', 'dilution_points' => 'Dilution pts'], $table)],
            [], ['Issuing new units changes percentages but not necessarily economic rights if classes/terms differ.'],
            ['old_total_units' => $oldTotal, 'new_units' => $newUnits, 'new_total_units' => $newTotal, 'holders' => $table]
        );
    }

    private function ownershipChart(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $total = array_sum(array_map(fn ($row) => $this->amount($row['units'] ?? 0), $partners));
        $rows = [];
        foreach ($partners as $partner) {
            $units = $this->amount($partner['units'] ?? 0);
            $rows[] = [
                'partner' => $this->name($partner['name'] ?? ''),
                'units' => $units,
                'percentage' => $total > 0 ? round($units / $total * 100, 2) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['percentage'] <=> $a['percentage']);
        $largest = $rows[0] ?? null;

        return $this->result(
            'Total Ownership Units', $total, 'number',
            [$this->metric('Holders', count($rows), 'number'), $this->metric('Largest Holding', $largest['percentage'] ?? 0, 'percent')],
            [$this->table('Ownership Chart Data', ['partner' => 'Partner', 'units' => 'Units', 'percentage' => 'Ownership %'], $rows)],
            [], [], ['total_units' => $total, 'holders' => $rows]
        );
    }

    private function sweatEquity(array $input): array
    {
        $hours = $this->amount($input['hours_per_month'] ?? 0);
        $rate = $this->amount($input['fair_hourly_rate'] ?? 0);
        $months = min(120, $this->amount($input['months'] ?? 0));
        $paid = $this->amount($input['cash_compensation'] ?? 0);
        $gross = round($hours * $rate * $months, 2);
        $uncompensated = round(max(0, $gross - $paid), 2);

        return $this->result(
            'Estimated Uncompensated Work Value', $uncompensated, 'money',
            [$this->metric('Gross Fair Work Value', $gross, 'money'), $this->metric('Cash Compensation', $paid, 'money'), $this->metric('Months', $months, 'number')],
            [], [],
            ['Hourly rate and time assumptions should be documented. Sweat-equity value is not automatically ownership entitlement.'],
            ['partner_name' => $input['partner_name'] ?? null, 'gross_work_value' => $gross, 'cash_compensation' => $paid, 'uncompensated_value' => $uncompensated]
        );
    }

    private function timeContribution(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $actualTotal = 0.0;
        $rows = [];
        foreach ($partners as $partner) {
            $actual = $this->amount($partner['actual_hours'] ?? 0);
            $target = $this->amount($partner['target_hours'] ?? 0);
            $actualTotal += $actual;
            $rows[] = [
                'partner' => $this->name($partner['name'] ?? ''),
                'actual_hours' => $actual,
                'target_hours' => $target,
                'target_completion' => $target > 0 ? round($actual / $target * 100, 2) : null,
                'time_share' => 0,
            ];
        }
        foreach ($rows as &$row) {
            $row['time_share'] = $actualTotal > 0 ? round($row['actual_hours'] / $actualTotal * 100, 2) : 0;
        }
        unset($row);

        return $this->result(
            'Total Partner Hours', $actualTotal, 'number',
            [$this->metric('Partners Tracked', count($rows), 'number')],
            [$this->table('Time Contribution', ['partner' => 'Partner', 'actual_hours' => 'Actual', 'target_hours' => 'Target', 'target_completion' => 'Target %', 'time_share' => 'Time Share %'], $rows)],
            [], ['Hours show time contribution, not quality, output or ownership entitlement.'], ['total_actual_hours' => $actualTotal, 'partners' => $rows]
        );
    }

    private function contributionScorecard(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $rows = [];
        foreach ($partners as $partner) {
            $scores = [
                $this->score5($partner['execution'] ?? 0),
                $this->score5($partner['expertise'] ?? 0),
                $this->score5($partner['relationships'] ?? 0),
                $this->score5($partner['responsibility'] ?? 0),
            ];
            $rows[] = [
                'partner' => $this->name($partner['name'] ?? ''),
                'execution' => $scores[0], 'expertise' => $scores[1], 'relationships' => $scores[2], 'responsibility' => $scores[3],
                'average' => round(array_sum($scores) / 4, 2),
            ];
        }

        return $this->result(
            'Contribution Profiles', count($rows), 'number', [],
            [$this->table('Discussion Scorecard', ['partner' => 'Partner', 'execution' => 'Execution', 'expertise' => 'Expertise', 'relationships' => 'Network', 'responsibility' => 'Responsibility', 'average' => 'Average'], $rows)],
            [], ['Scores are discussion inputs, not a judgment of a partner’s overall value. Use evidence and agreed expectations.'], ['partners' => $rows]
        );
    }

    private function roleMatrix(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['responsibilities'] ?? []) as $row) {
            $area = trim((string) ($row['area'] ?? ''));
            if ($area === '') {
                continue;
            }
            $owner = trim((string) ($row['owner'] ?? ''));
            $backup = trim((string) ($row['backup'] ?? ''));
            $rows[] = ['area' => $area, 'owner' => $owner ?: 'Not assigned', 'backup' => $backup ?: 'Not assigned', 'kpi' => trim((string) ($row['kpi'] ?? '')) ?: '—'];
            if ($owner === '') {
                $warnings[] = $area.' မှာ Primary Owner မသတ်မှတ်ရသေးပါ။';
            }
            if ($backup === '') {
                $warnings[] = $area.' မှာ Backup မရှိသေးပါ။';
            }
        }

        return $this->result(
            'Responsibilities Mapped', count($rows), 'number',
            [$this->metric('Uncovered Alerts', count($warnings), 'number')],
            [$this->table('Role & Responsibility Matrix', ['area' => 'Area', 'owner' => 'Primary', 'backup' => 'Backup', 'kpi' => 'Expected Result / KPI'], $rows)],
            $warnings, ['Critical responsibilities should always have a clear accountable owner and practical backup.'], ['responsibilities' => $rows, 'coverage_alerts' => $warnings]
        );
    }

    private function vesting(array $input): array
    {
        $grant = max(0, $this->amount($input['grant_units'] ?? 0));
        $vestingMonths = max(1, min(240, $this->amount($input['vesting_months'] ?? 48)));
        $cliff = min($vestingMonths, max(0, $this->amount($input['cliff_months'] ?? 0)));
        $elapsed = max(0, min($vestingMonths, $this->amount($input['months_elapsed'] ?? 0)));
        $vestedPct = $elapsed < $cliff ? 0 : min(100, round($elapsed / $vestingMonths * 100, 4));
        $vestedUnits = round($grant * $vestedPct / 100, 4);
        $unvested = round(max(0, $grant - $vestedUnits), 4);

        return $this->result(
            'Vested Units', $vestedUnits, 'units',
            [$this->metric('Vested', $vestedPct, 'percent'), $this->metric('Unvested Units', $unvested, 'number'), $this->metric('Cliff', $cliff, 'months')],
            [], [], ['This is a straight-line planning model. Actual vesting terms, acceleration, forfeiture and tax treatment must follow the executed agreement and local rules.'],
            ['grant_units' => $grant, 'vesting_months' => $vestingMonths, 'cliff_months' => $cliff, 'months_elapsed' => $elapsed, 'vested_percentage' => $vestedPct, 'vested_units' => $vestedUnits, 'unvested_units' => $unvested]
        );
    }

    private function contributionBalance(array $input): array
    {
        $rows = [];
        $grand = 0.0;
        foreach ($this->rows($input['partners'] ?? []) as $partner) {
            $cash = $this->amount($partner['cash'] ?? 0);
            $work = $this->amount($partner['work'] ?? 0);
            $other = $this->amount($partner['other'] ?? 0);
            $total = round($cash + $work + $other, 2);
            $grand += $total;
            $rows[] = ['partner' => $this->name($partner['name'] ?? ''), 'cash' => $cash, 'work' => $work, 'other' => $other, 'total' => $total, 'relative_share' => 0];
        }
        foreach ($rows as &$row) {
            $row['relative_share'] = $grand > 0 ? round($row['total'] / $grand * 100, 2) : 0;
        }
        unset($row);

        return $this->result('Total Recorded Contribution Value', $grand, 'money', [], [$this->table('Contribution Balance', ['partner' => 'Partner', 'cash' => 'Cash', 'work' => 'Work', 'other' => 'Other', 'total' => 'Total', 'relative_share' => 'Relative %'], $rows)], [], ['Recorded contribution value is not automatically ownership or profit share.'], ['total_recorded_value' => $grand, 'partners' => $rows]);
    }

    private function profitDistribution(array $input): array
    {
        $profit = $this->amount($input['net_profit'] ?? 0);
        $reservePct = $this->pct($input['reserve_percentage'] ?? 0);
        $reserve = round($profit * $reservePct / 100, 2);
        $distributable = round(max(0, $profit - $reserve), 2);
        $partners = $this->rows($input['partners'] ?? []);
        $percentageTotal = array_sum(array_map(fn ($row) => $this->pct($row['percentage'] ?? 0), $partners));
        $rows = [];
        foreach ($partners as $partner) {
            $pct = $this->pct($partner['percentage'] ?? 0);
            $rows[] = ['partner' => $this->name($partner['name'] ?? ''), 'profit_share' => $pct, 'distribution' => round($distributable * $pct / 100, 2)];
        }
        $warnings = abs($percentageTotal - 100) > 0.01 ? ['Profit Share % စုစုပေါင်းက 100% မဖြစ်သေးပါ။'] : [];

        return $this->result('Distributable Profit', $distributable, 'money', [$this->metric('Net Profit', $profit, 'money'), $this->metric('Reserve', $reserve, 'money'), $this->metric('Profit Share Total', $percentageTotal, 'percent')], [$this->table('Partner Distribution', ['partner' => 'Partner', 'profit_share' => 'Share %', 'distribution' => 'Distribution'], $rows)], $warnings, ['Salary, ownership, profit distribution and tax treatment are separate concepts.'], ['net_profit' => $profit, 'reserve_percentage' => $reservePct, 'reserve_amount' => $reserve, 'distributable_profit' => $distributable, 'partners' => $rows]);
    }

    private function salaryProfit(array $input): array
    {
        $profit = $this->amount($input['annual_distributable_profit'] ?? 0);
        $partners = $this->rows($input['partners'] ?? []);
        $rows = [];
        $shareTotal = 0.0;
        foreach ($partners as $partner) {
            $salary = $this->amount($partner['monthly_salary'] ?? 0) * 12;
            $pct = $this->pct($partner['profit_share'] ?? 0);
            $shareTotal += $pct;
            $profitAmount = round($profit * $pct / 100, 2);
            $rows[] = ['partner' => $this->name($partner['name'] ?? ''), 'annual_salary' => round($salary, 2), 'profit_share' => $pct, 'profit_amount' => $profitAmount, 'total_compensation' => round($salary + $profitAmount, 2)];
        }
        $warnings = abs($shareTotal - 100) > 0.01 ? ['Profit Share % စုစုပေါင်းက 100% မဖြစ်သေးပါ။'] : [];

        return $this->result('Annual Distributable Profit', $profit, 'money', [$this->metric('Profit Share Total', $shareTotal, 'percent')], [$this->table('Salary vs Profit Share', ['partner' => 'Partner', 'annual_salary' => 'Annual Salary', 'profit_share' => 'Profit %', 'profit_amount' => 'Profit Amount', 'total_compensation' => 'Total Compensation'], $rows)], $warnings, ['Compensation should reflect roles and market context; distributions should follow the agreed structure and applicable tax/accounting treatment.'], ['annual_distributable_profit' => $profit, 'partners' => $rows]);
    }

    private function retainedEarnings(array $input): array
    {
        $profit = $this->amount($input['net_profit'] ?? 0);
        $pct = $this->pct($input['retained_percentage'] ?? 0);
        $fixed = $this->amount($input['mandatory_reserve'] ?? 0);
        $percentageReserve = round($profit * $pct / 100, 2);
        $retained = min($profit, round($percentageReserve + $fixed, 2));
        $distributable = round(max(0, $profit - $retained), 2);

        return $this->result('Retained Earnings', $retained, 'money', [$this->metric('Net Profit', $profit, 'money'), $this->metric('Percentage Reserve', $percentageReserve, 'money'), $this->metric('Fixed Reserve', $fixed, 'money'), $this->metric('Distributable', $distributable, 'money')], [], $fixed + $percentageReserve > $profit ? ['Requested retained amount က Net Profit ထက်များလို့ available profit အထိပဲ cap လုပ်ထားပါတယ်။'] : [], [], ['net_profit' => $profit, 'retained_percentage' => $pct, 'retained_amount' => $retained, 'distributable_profit' => $distributable]);
    }

    private function reserveFund(array $input): array
    {
        $monthly = $this->amount($input['monthly_operating_cost'] ?? 0);
        $months = min(24, $this->amount($input['target_months'] ?? 0));
        $current = $this->amount($input['current_reserve'] ?? 0);
        $target = round($monthly * $months, 2);
        $gap = round(max(0, $target - $current), 2);
        $surplus = round(max(0, $current - $target), 2);

        return $this->result('Reserve Gap', $gap, 'money', [$this->metric('Target Reserve', $target, 'money'), $this->metric('Current Reserve', $current, 'money'), $this->metric('Surplus', $surplus, 'money')], [], [], ['Target months should reflect cash-flow volatility, access to financing and business risk rather than a universal fixed rule.'], ['monthly_operating_cost' => $monthly, 'target_months' => $months, 'target_reserve' => $target, 'current_reserve' => $current, 'gap' => $gap, 'surplus' => $surplus]);
    }

    private function lossSharing(array $input): array
    {
        $loss = $this->amount($input['total_loss'] ?? 0);
        $partners = $this->rows($input['partners'] ?? []);
        $pctTotal = 0.0;
        $rows = [];
        foreach ($partners as $partner) {
            $pct = $this->pct($partner['percentage'] ?? 0);
            $pctTotal += $pct;
            $rows[] = ['partner' => $this->name($partner['name'] ?? ''), 'loss_share' => $pct, 'allocated_loss' => round($loss * $pct / 100, 2)];
        }
        $warnings = abs($pctTotal - 100) > 0.01 ? ['Loss Share % စုစုပေါင်းက 100% မဖြစ်သေးပါ။'] : [];

        return $this->result('Total Loss', $loss, 'money', [$this->metric('Loss Share Total', $pctTotal, 'percent')], [$this->table('Loss Allocation Scenario', ['partner' => 'Partner', 'loss_share' => 'Loss %', 'allocated_loss' => 'Allocated Loss'], $rows)], $warnings, ['Actual legal responsibility for losses/debts may differ from an internal allocation rule depending on entity type, guarantees and law.'], ['total_loss' => $loss, 'partners' => $rows]);
    }

    private function distributionCompare(array $input): array
    {
        $profit = $this->amount($input['net_profit'] ?? 0);
        $a = $this->pct($input['scenario_a_reserve'] ?? 0);
        $b = $this->pct($input['scenario_b_reserve'] ?? 0);
        $aReserve = round($profit * $a / 100, 2);
        $bReserve = round($profit * $b / 100, 2);
        $rows = [
            ['scenario' => 'A', 'reserve_percentage' => $a, 'reserve_amount' => $aReserve, 'distributable' => round($profit - $aReserve, 2)],
            ['scenario' => 'B', 'reserve_percentage' => $b, 'reserve_amount' => $bReserve, 'distributable' => round($profit - $bReserve, 2)],
        ];

        return $this->result('Distribution Difference', abs($rows[0]['distributable'] - $rows[1]['distributable']), 'money', [], [$this->table('Scenario Comparison', ['scenario' => 'Scenario', 'reserve_percentage' => 'Reserve %', 'reserve_amount' => 'Reserve', 'distributable' => 'Distributable'], $rows)], [], [], ['net_profit' => $profit, 'scenarios' => $rows]);
    }

    private function cashflow(array $input): array
    {
        $opening = $this->amount($input['opening_cash'] ?? 0);
        $inflows = $this->amount($input['cash_inflows'] ?? 0);
        $outflows = $this->amount($input['cash_outflows'] ?? 0);
        $fixed = $this->amount($input['monthly_fixed_cost'] ?? 0);
        $net = round($inflows - $outflows, 2);
        $closing = round($opening + $net, 2);
        $runway = $fixed > 0 ? round(max(0, $closing) / $fixed, 2) : null;
        $warnings = $closing < 0 ? ['Closing cash negative ဖြစ်နေပါတယ်။ Cash gap financing / collections / cost actions ကိုချက်ချင်း review လုပ်ပါ။'] : [];

        return $this->result('Closing Cash', $closing, 'money', [$this->metric('Net Cash Flow', $net, 'money'), $this->metric('Monthly Fixed Cost', $fixed, 'money'), $this->metric('Approx. Runway', $runway, 'months')], [], $warnings, ['Runway is a simple planning ratio and does not replace a detailed cash-flow forecast.'], ['opening_cash' => $opening, 'cash_inflows' => $inflows, 'cash_outflows' => $outflows, 'net_cashflow' => $net, 'closing_cash' => $closing, 'monthly_fixed_cost' => $fixed, 'runway_months' => $runway]);
    }

    private function budget(array $input): array
    {
        $rows = [];
        $plannedTotal = 0.0;
        $actualTotal = 0.0;
        foreach ($this->rows($input['categories'] ?? []) as $category) {
            $name = trim((string) ($category['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $planned = $this->amount($category['planned'] ?? 0);
            $actual = $this->amount($category['actual'] ?? 0);
            $variance = round($actual - $planned, 2);
            $plannedTotal += $planned;
            $actualTotal += $actual;
            $rows[] = ['category' => $name, 'planned' => $planned, 'actual' => $actual, 'variance' => $variance, 'variance_percentage' => $planned > 0 ? round($variance / $planned * 100, 2) : null];
        }
        $totalVariance = round($actualTotal - $plannedTotal, 2);

        return $this->result('Total Budget Variance', $totalVariance, 'money', [$this->metric('Planned', $plannedTotal, 'money'), $this->metric('Actual', $actualTotal, 'money')], [$this->table('Budget vs Actual', ['category' => 'Category', 'planned' => 'Budget', 'actual' => 'Actual', 'variance' => 'Variance', 'variance_percentage' => 'Variance %'], $rows)], [], ['Positive variance here means actual spending is above budget.'], ['planned_total' => $plannedTotal, 'actual_total' => $actualTotal, 'variance' => $totalVariance, 'categories' => $rows]);
    }

    private function approvalMatrix(array $input): array
    {
        $rules = $this->rows($input['rules'] ?? []);
        $rows = [];
        $warnings = [];
        foreach ($rules as $index => $rule) {
            $min = $this->amount($rule['min_amount'] ?? 0);
            $max = $this->amount($rule['max_amount'] ?? 0);
            $approver = trim((string) ($rule['approver'] ?? ''));
            $required = max(1, (int) $this->amount($rule['approvals_required'] ?? 1));
            if ($max > 0 && $max < $min) {
                $warnings[] = 'Rule '.($index + 1).' မှာ To amount က From amount ထက်နည်းနေပါတယ်။';
            }
            if ($approver === '') {
                $warnings[] = 'Rule '.($index + 1).' မှာ approver မသတ်မှတ်ရသေးပါ။';
            }
            $rows[] = ['from' => $min, 'to' => $max > 0 ? $max : 'No limit', 'approver' => $approver ?: 'Not assigned', 'approvals_required' => $required];
        }
        $numeric = collect($rows)->filter(fn ($row) => is_numeric($row['to']))->sortBy('from')->values();
        for ($i = 1; $i < $numeric->count(); $i++) {
            $previous = $numeric[$i - 1];
            $current = $numeric[$i];
            if ((float) $current['from'] > (float) $previous['to']) {
                $warnings[] = 'Approval ranges ကြားမှာ gap ရှိနိုင်ပါတယ်: '.$previous['to'].' → '.$current['from'];
            }
            if ((float) $current['from'] < (float) $previous['to']) {
                $warnings[] = 'Approval ranges overlap ဖြစ်နိုင်ပါတယ်: '.$previous['to'].' / '.$current['from'];
            }
        }

        return $this->result('Approval Rules', count($rows), 'number', [$this->metric('Review Alerts', count($warnings), 'number')], [$this->table('Approval Matrix', ['from' => 'From', 'to' => 'To', 'approver' => 'Approver', 'approvals_required' => 'Approvals #'], $rows)], $warnings, ['Thresholds should match business size, fraud risk, operational speed and segregation-of-duties needs.'], ['rules' => $rows, 'alerts' => $warnings]);
    }

    private function bankAuthority(array $input): array
    {
        $rows = [];
        $dualCount = 0;
        foreach ($this->rows($input['authorities'] ?? []) as $authority) {
            $rule = (string) ($authority['signing_rule'] ?? 'restricted');
            if ($rule === 'dual') {
                $dualCount++;
            }
            $rows[] = ['person_or_role' => $this->name($authority['person_or_role'] ?? ''), 'daily_limit' => $this->amount($authority['daily_limit'] ?? 0), 'signing_rule' => ucfirst($rule)];
        }

        return $this->result('Bank Authorities', count($rows), 'number', [$this->metric('Dual-Signing Rules', $dualCount, 'number')], [$this->table('Bank Authority Matrix', ['person_or_role' => 'Person / Role', 'daily_limit' => 'Daily Limit', 'signing_rule' => 'Signing Rule'], $rows)], [], ['Use least-privilege access, review access when roles change, and align bank mandates with actual bank capabilities.'], ['authorities' => $rows]);
    }

    private function checklist(array $input, array $definition): array
    {
        $field = collect($definition['fields'] ?? [])->firstWhere('type', 'checklist');
        $name = (string) ($field['name'] ?? 'checks');
        $configured = $field['items'] ?? [];
        $values = is_array($input[$name] ?? null) ? $input[$name] : [];
        $rows = [];
        $complete = 0;
        foreach ($configured as $key => $label) {
            $done = filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($done) {
                $complete++;
            }
            $rows[] = ['item' => $label, 'status' => $done ? 'Complete' : 'Needs attention'];
        }
        $total = count($configured);
        $pct = $total > 0 ? round($complete / $total * 100, 2) : 0;
        $warnings = collect($rows)->where('status', 'Needs attention')->pluck('item')->map(fn ($item) => $item.' — မပြီးသေးပါ။')->values()->all();

        return $this->result('Checklist Completion', $pct, 'percent', [$this->metric('Complete', $complete, 'number'), $this->metric('Total', $total, 'number')], [$this->table('Readiness Checklist', ['item' => 'Control / Readiness Item', 'status' => 'Status'], $rows)], $warnings, [], ['completion_percentage' => $pct, 'complete_count' => $complete, 'total_count' => $total, 'items' => $rows]);
    }

    private function decisionRights(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['decisions'] ?? []) as $row) {
            $decision = trim((string) ($row['decision'] ?? ''));
            if ($decision === '') {
                continue;
            }
            $owner = trim((string) ($row['owner'] ?? ''));
            $rule = (string) ($row['approval_rule'] ?? 'majority');
            if ($owner === '') {
                $warnings[] = $decision.' မှာ Decision Owner မသတ်မှတ်ရသေးပါ။';
            }
            $rows[] = ['decision' => $decision, 'owner' => $owner ?: 'Not assigned', 'approval_rule' => ucfirst($rule), 'notes' => trim((string) ($row['notes'] ?? '')) ?: '—'];
        }

        return $this->result('Decision Rights', count($rows), 'number', [$this->metric('Unassigned Alerts', count($warnings), 'number')], [$this->table('Decision Rights Matrix', ['decision' => 'Decision', 'owner' => 'Owner', 'approval_rule' => 'Approval Rule', 'notes' => 'Conditions'], $rows)], $warnings, ['Reserved matters should be clearly identified and consistent with legal documents.'], ['decisions' => $rows]);
    }

    private function authorityLevels(array $input): array
    {
        $rows = [];
        foreach ($this->rows($input['levels'] ?? []) as $row) {
            $role = trim((string) ($row['role'] ?? ''));
            if ($role === '') {
                continue;
            }
            $rows[] = ['role' => $role, 'financial_limit' => $this->amount($row['financial_limit'] ?? 0), 'scope' => trim((string) ($row['scope'] ?? '')) ?: '—', 'escalates_to' => trim((string) ($row['escalates_to'] ?? '')) ?: 'Not specified'];
        }
        return $this->result('Authority Levels', count($rows), 'number', [], [$this->table('Authority Structure', ['role' => 'Role', 'financial_limit' => 'Financial Limit', 'scope' => 'Scope', 'escalates_to' => 'Escalates To'], $rows)], [], ['Authority should be proportional to responsibility and include escalation for exceptions.'], ['levels' => $rows]);
    }

    private function votingSimulator(array $input): array
    {
        $threshold = $this->pct($input['threshold'] ?? 50);
        $rows = [];
        $yes = $no = $abstain = $total = 0.0;
        foreach ($this->rows($input['votes'] ?? []) as $row) {
            $weight = $this->amount($row['weight'] ?? 0);
            $vote = (string) ($row['vote'] ?? 'abstain');
            $total += $weight;
            if ($vote === 'yes') { $yes += $weight; }
            elseif ($vote === 'no') { $no += $weight; }
            else { $abstain += $weight; }
            $rows[] = ['partner' => $this->name($row['name'] ?? ''), 'weight' => $weight, 'vote' => ucfirst($vote)];
        }
        $yesPct = $total > 0 ? round($yes / $total * 100, 2) : 0;
        $passed = $yesPct >= $threshold;

        return $this->result('Vote Result', $passed ? 'PASS' : 'NOT PASSED', 'text', [$this->metric('Yes', $yesPct, 'percent'), $this->metric('Threshold', $threshold, 'percent'), $this->metric('Abstain Weight', $abstain, 'number')], [$this->table('Votes', ['partner' => 'Partner', 'weight' => 'Weight', 'vote' => 'Vote'], $rows)], [], ['This simulator applies the configured threshold only; quorum, class rights and special legal rules may change the real outcome.'], ['threshold' => $threshold, 'yes_weight' => $yes, 'no_weight' => $no, 'abstain_weight' => $abstain, 'yes_percentage' => $yesPct, 'passed' => $passed, 'votes' => $rows]);
    }

    private function decisionLog(array $input): array
    {
        $decision = trim((string) ($input['decision'] ?? ''));
        $date = $input['decision_date'] ?? null;
        return $this->result('Decision Recorded', $decision !== '' ? $decision : 'Draft decision', 'text', [$this->metric('Decision Date', $date ?: 'Not set', 'text'), $this->metric('Owner / Approver', $input['owner'] ?? 'Not set', 'text')], [], [], ['Keep evidence, minutes and approval records outside PBR where legally required.'], ['decision_date' => $date, 'decision' => $decision, 'owner' => $input['owner'] ?? null, 'rationale' => $input['rationale'] ?? null, 'follow_up' => $input['follow_up'] ?? null]);
    }

    private function deadlock(array $input): array
    {
        $threshold = $this->pct($input['threshold'] ?? 50);
        $yes = $this->amount($input['yes_weight'] ?? 0);
        $no = $this->amount($input['no_weight'] ?? 0);
        $abstain = $this->amount($input['abstain_weight'] ?? 0);
        $total = $yes + $no + $abstain;
        $yesPct = $total > 0 ? round($yes / $total * 100, 2) : 0;
        $passed = $yesPct >= $threshold;
        $deadlocked = ! $passed && $yes > 0 && $no > 0;
        $fallback = (string) ($input['fallback_rule'] ?? 'discussion');
        $label = $deadlocked ? 'DEADLOCK SIGNAL' : ($passed ? 'APPROVED UNDER INPUT RULE' : 'NOT APPROVED');

        return $this->result('Decision Status', $label, 'text', [$this->metric('Yes', $yesPct, 'percent'), $this->metric('Threshold', $threshold, 'percent')], [], $deadlocked ? ['Configured voting result က approval threshold မပြည့်ဘဲ opposing votes ရှိတာကြောင့် deadlock signal ဖြစ်ပါတယ်။'] : [], ['Fallback rule: '.str_replace('_', ' ', ucfirst($fallback)).'. This is an internal workflow suggestion, not a legal determination.'], ['threshold' => $threshold, 'yes_weight' => $yes, 'no_weight' => $no, 'abstain_weight' => $abstain, 'yes_percentage' => $yesPct, 'passed' => $passed, 'deadlock_signal' => $deadlocked, 'fallback_rule' => $fallback]);
    }

    private function governanceChart(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['nodes'] ?? []) as $node) {
            $role = trim((string) ($node['role'] ?? ''));
            if ($role === '') { continue; }
            $reports = trim((string) ($node['reports_to'] ?? ''));
            if ($reports === '') { $warnings[] = $role.' အတွက် escalation/reporting path မသတ်မှတ်ရသေးပါ။'; }
            $rows[] = ['role' => $role, 'reports_to' => $reports ?: 'Not specified', 'mandate' => trim((string) ($node['mandate'] ?? '')) ?: '—'];
        }
        return $this->result('Governance Roles', count($rows), 'number', [$this->metric('Structure Alerts', count($warnings), 'number')], [$this->table('Governance Structure', ['role' => 'Role', 'reports_to' => 'Reports / Escalates To', 'mandate' => 'Mandate'], $rows)], $warnings, [], ['nodes' => $rows]);
    }

    private function buyout(array $input): array
    {
        $value = $this->amount($input['business_value'] ?? 0);
        $pct = $this->pct($input['ownership_percentage'] ?? 0);
        $adjustment = is_numeric($input['adjustment'] ?? null) ? (float) $input['adjustment'] : 0.0;
        $months = max(1, min(120, (int) $this->amount($input['payment_months'] ?? 1)));
        $base = round($value * $pct / 100, 2);
        $adjusted = round(max(0, $base + $adjustment), 2);
        $monthly = round($adjusted / $months, 2);

        return $this->result('Indicative Buyout Value', $adjusted, 'money', [$this->metric('Stake Base Value', $base, 'money'), $this->metric('Adjustment', $adjustment, 'money'), $this->metric('Illustrative Monthly Payment', $monthly, 'money')], [], [], ['This is a planning estimate. Buyout price, discounts/premiums, debt treatment, tax and payment terms must follow the agreement and applicable law.'], ['business_value' => $value, 'ownership_percentage' => $pct, 'base_value' => $base, 'adjustment' => $adjustment, 'buyout_value' => $adjusted, 'payment_months' => $months, 'monthly_payment' => $monthly]);
    }

    private function exitValue(array $input): array
    {
        $pct = $this->pct($input['ownership_percentage'] ?? 0);
        $rows = [];
        foreach (['conservative', 'base', 'optimistic'] as $scenario) {
            $value = $this->amount($input[$scenario.'_value'] ?? 0);
            $rows[] = ['scenario' => ucfirst($scenario), 'business_value' => $value, 'stake_percentage' => $pct, 'stake_value' => round($value * $pct / 100, 2)];
        }
        return $this->result('Base Exit Stake Value', $rows[1]['stake_value'], 'money', [], [$this->table('Exit Value Scenarios', ['scenario' => 'Scenario', 'business_value' => 'Business Value', 'stake_percentage' => 'Stake %', 'stake_value' => 'Stake Value'], $rows)], [], ['Valuation scenarios are indicative planning ranges, not guaranteed transaction prices.'], ['ownership_percentage' => $pct, 'scenarios' => $rows]);
    }

    private function noticePlan(array $input): array
    {
        $date = $this->dateOrNull($input['notice_date'] ?? null);
        $noticeDays = max(0, (int) $this->amount($input['notice_days'] ?? 0));
        $handoverDays = max(0, (int) $this->amount($input['handover_days'] ?? 0));
        $noticeEnd = $date?->copy()->addDays($noticeDays);
        $handoverStart = $noticeEnd?->copy()->subDays(min($noticeDays, $handoverDays));

        return $this->result('Planned Notice End', $noticeEnd?->toDateString() ?? 'Date required', 'text', [$this->metric('Notice Days', $noticeDays, 'days'), $this->metric('Handover Window', $handoverDays, 'days'), $this->metric('Suggested Handover Start', $handoverStart?->toDateString() ?? '—', 'text')], [], [], ['Confirm contractual notice requirements and local law before relying on this timeline.'], ['notice_date' => $date?->toDateString(), 'notice_days' => $noticeDays, 'notice_end' => $noticeEnd?->toDateString(), 'handover_days' => $handoverDays, 'handover_start' => $handoverStart?->toDateString(), 'critical_handover' => $input['critical_handover'] ?? null]);
    }

    private function exitTimeline(array $input): array
    {
        $start = $this->dateOrNull($input['start_date'] ?? null);
        $notice = (int) $this->amount($input['notice_days'] ?? 0);
        $valuation = (int) $this->amount($input['valuation_days'] ?? 0);
        $settlement = (int) $this->amount($input['settlement_days'] ?? 0);
        $noticeEnd = $start?->copy()->addDays($notice);
        $priceTarget = $noticeEnd?->copy()->addDays($valuation);
        $settlementTarget = $priceTarget?->copy()->addDays($settlement);
        $rows = [
            ['milestone' => 'Process Start', 'target_date' => $start?->toDateString() ?? '—'],
            ['milestone' => 'Notice Period Ends', 'target_date' => $noticeEnd?->toDateString() ?? '—'],
            ['milestone' => 'Valuation / Price Target', 'target_date' => $priceTarget?->toDateString() ?? '—'],
            ['milestone' => 'Settlement Target', 'target_date' => $settlementTarget?->toDateString() ?? '—'],
        ];
        return $this->result('Target Settlement', $settlementTarget?->toDateString() ?? 'Date required', 'text', [], [$this->table('Exit Timeline', ['milestone' => 'Milestone', 'target_date' => 'Target Date'], $rows)], [], ['These are planning dates; agreement terms, disputes, valuation work and legal steps can change timing.'], ['milestones' => $rows]);
    }

    private function continuityPlan(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['functions'] ?? []) as $function) {
            $name = trim((string) ($function['function'] ?? ''));
            if ($name === '') { continue; }
            $owner = trim((string) ($function['current_owner'] ?? ''));
            $backup = trim((string) ($function['backup_owner'] ?? ''));
            $downtime = $this->amount($function['max_downtime_hours'] ?? 0);
            if ($backup === '') { $warnings[] = $name.' အတွက် Backup Owner မရှိသေးပါ။'; }
            $rows[] = ['function' => $name, 'current_owner' => $owner ?: 'Not assigned', 'backup_owner' => $backup ?: 'Not assigned', 'max_downtime_hours' => $downtime];
        }
        return $this->result('Critical Functions', count($rows), 'number', [$this->metric('Continuity Gaps', count($warnings), 'number')], [$this->table('Business Continuity Map', ['function' => 'Function', 'current_owner' => 'Current Owner', 'backup_owner' => 'Backup', 'max_downtime_hours' => 'Max Downtime (h)'], $rows)], $warnings, ['Prioritize functions with short tolerated downtime and no trained backup.'], ['functions' => $rows, 'gaps' => $warnings]);
    }

    private function keyPerson(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['dependencies'] ?? []) as $row) {
            $person = $this->name($row['person'] ?? '');
            $function = trim((string) ($row['critical_function'] ?? ''));
            if ($function === '') { continue; }
            $backup = trim((string) ($row['backup'] ?? ''));
            $impact = $this->score5($row['impact'] ?? 1);
            $level = $impact >= 5 && $backup === '' ? 'Critical' : ($impact >= 4 && $backup === '' ? 'High' : ($impact >= 4 ? 'Elevated' : 'Managed'));
            if ($backup === '' && $impact >= 4) { $warnings[] = $function.' — high dependency with no backup.'; }
            $rows[] = ['person' => $person, 'function' => $function, 'backup' => $backup ?: 'Not assigned', 'impact' => $impact, 'dependency_signal' => $level];
        }
        return $this->result('Key Dependencies', count($rows), 'number', [$this->metric('High/Critical Gaps', count($warnings), 'number')], [$this->table('Dependency Map', ['person' => 'Person', 'function' => 'Critical Function', 'backup' => 'Backup', 'impact' => 'Impact', 'dependency_signal' => 'Signal'], $rows)], $warnings, ['This is a planning signal based on user-entered impact and backup status, not a formal risk certification.'], ['dependencies' => $rows, 'gaps' => $warnings]);
    }

    private function succession(array $input): array
    {
        $rows = [];
        $warnings = [];
        foreach ($this->rows($input['roles'] ?? []) as $row) {
            $role = trim((string) ($row['role'] ?? ''));
            if ($role === '') { continue; }
            $successor = trim((string) ($row['successor'] ?? ''));
            $readiness = $this->score5($row['readiness'] ?? 1);
            if ($successor === '') { $warnings[] = $role.' အတွက် successor မသတ်မှတ်ရသေးပါ။'; }
            elseif ($readiness <= 2) { $warnings[] = $role.' successor readiness နည်းနေပါတယ်။ Development plan လိုပါတယ်။'; }
            $rows[] = ['role' => $role, 'successor' => $successor ?: 'Not identified', 'readiness' => $readiness, 'development_need' => trim((string) ($row['development_need'] ?? '')) ?: '—'];
        }
        return $this->result('Succession Roles', count($rows), 'number', [$this->metric('Readiness Gaps', count($warnings), 'number')], [$this->table('Succession Plan', ['role' => 'Critical Role', 'successor' => 'Successor', 'readiness' => 'Readiness 1–5', 'development_need' => 'Development Need'], $rows)], $warnings, [], ['roles' => $rows, 'gaps' => $warnings]);
    }

    private function emergencyAuthority(array $input): array
    {
        $limit = $this->amount($input['financial_limit'] ?? 0);
        $days = max(1, min(365, (int) $this->amount($input['valid_days'] ?? 30)));
        return $this->result('Temporary Authority Period', $days, 'days', [$this->metric('Financial Limit', $limit, 'money'), $this->metric('Acting Role', $input['acting_role'] ?? 'Not set', 'text')], [], empty($input['acting_role']) ? ['Temporary acting person / role မသတ်မှတ်ရသေးပါ။'] : [], ['Emergency authority should be documented and aligned with bank mandates, company/partnership documents and applicable law.'], ['trigger' => $input['trigger'] ?? null, 'acting_role' => $input['acting_role'] ?? null, 'financial_limit' => $limit, 'valid_days' => $days, 'restrictions' => $input['restrictions'] ?? null]);
    }

    private function ownershipTransition(array $input): array
    {
        $value = $this->amount($input['business_value'] ?? 0);
        $pct = $this->pct($input['ownership_percentage'] ?? 0);
        $stakeValue = round($value * $pct / 100, 2);
        $path = (string) ($input['transition_path'] ?? 'other');
        return $this->result('Affected Stake Value', $stakeValue, 'money', [$this->metric('Ownership', $pct, 'percent'), $this->metric('Transition Path', ucwords(str_replace('_', ' ', $path)), 'text')], [], [], ['Inheritance, spouse rights and ownership transfer rules vary by jurisdiction and legal structure. Treat this as a continuity scenario only.'], ['business_value' => $value, 'ownership_percentage' => $pct, 'stake_value' => $stakeValue, 'transition_path' => $path]);
    }

    private function insuranceGap(array $input): array
    {
        $buyout = $this->amount($input['buyout_need'] ?? 0);
        $debt = $this->amount($input['debt_need'] ?? 0);
        $continuity = $this->amount($input['continuity_cost'] ?? 0);
        $coverage = $this->amount($input['existing_coverage'] ?? 0);
        $need = round($buyout + $debt + $continuity, 2);
        $gap = round(max(0, $need - $coverage), 2);
        $surplus = round(max(0, $coverage - $need), 2);
        return $this->result('Planning Coverage Gap', $gap, 'money', [$this->metric('Estimated Funding Need', $need, 'money'), $this->metric('Existing Coverage', $coverage, 'money'), $this->metric('Coverage Surplus', $surplus, 'money')], [], [], ['Insurance needs and policy suitability require licensed/professional review where applicable; PBR does not recommend a specific policy.'], ['funding_need' => $need, 'existing_coverage' => $coverage, 'gap' => $gap, 'surplus' => $surplus]);
    }

    private function shareTransfer(array $input): array
    {
        $total = max(1, $this->amount($input['total_units'] ?? 1));
        $sellerBefore = $this->amount($input['seller_units'] ?? 0);
        $buyerBefore = $this->amount($input['buyer_units'] ?? 0);
        $transfer = $this->amount($input['transfer_units'] ?? 0);
        if ($transfer > $sellerBefore) {
            throw ValidationException::withMessages(['transfer_units' => 'Transfer Units က Seller Current Units ထက်မများရပါ။']);
        }
        $sellerAfter = round($sellerBefore - $transfer, 4);
        $buyerAfter = round($buyerBefore + $transfer, 4);
        $rows = [
            ['holder' => $this->name($input['seller_name'] ?? 'Seller'), 'before_units' => $sellerBefore, 'after_units' => $sellerAfter, 'before_percentage' => round($sellerBefore / $total * 100, 2), 'after_percentage' => round($sellerAfter / $total * 100, 2)],
            ['holder' => $this->name($input['buyer_name'] ?? 'Buyer'), 'before_units' => $buyerBefore, 'after_units' => $buyerAfter, 'before_percentage' => round($buyerBefore / $total * 100, 2), 'after_percentage' => round($buyerAfter / $total * 100, 2)],
        ];
        return $this->result('Transfer Units', $transfer, 'units', [$this->metric('Total Units', $total, 'number')], [$this->table('Before / After Transfer', ['holder' => 'Holder', 'before_units' => 'Before Units', 'after_units' => 'After Units', 'before_percentage' => 'Before %', 'after_percentage' => 'After %'], $rows)], [], ['This assumes a transfer of existing units; it does not model issuance of new units or class-specific rights.'], ['total_units' => $total, 'transfer_units' => $transfer, 'holders' => $rows]);
    }

    private function beforeAfter(array $input): array
    {
        $partners = $this->rows($input['partners'] ?? []);
        $beforeTotal = array_sum(array_map(fn ($row) => $this->amount($row['before_units'] ?? 0), $partners));
        $afterTotal = array_sum(array_map(fn ($row) => $this->amount($row['after_units'] ?? 0), $partners));
        $rows = [];
        foreach ($partners as $partner) {
            $before = $this->amount($partner['before_units'] ?? 0);
            $after = $this->amount($partner['after_units'] ?? 0);
            $rows[] = ['partner' => $this->name($partner['name'] ?? ''), 'before_units' => $before, 'before_percentage' => $beforeTotal > 0 ? round($before / $beforeTotal * 100, 2) : 0, 'after_units' => $after, 'after_percentage' => $afterTotal > 0 ? round($after / $afterTotal * 100, 2) : 0, 'change_points' => ($afterTotal > 0 ? round($after / $afterTotal * 100, 2) : 0) - ($beforeTotal > 0 ? round($before / $beforeTotal * 100, 2) : 0)];
        }
        return $this->result('After Total Units', $afterTotal, 'number', [$this->metric('Before Total', $beforeTotal, 'number')], [$this->table('Ownership Change', ['partner' => 'Partner', 'before_units' => 'Before Units', 'before_percentage' => 'Before %', 'after_units' => 'After Units', 'after_percentage' => 'After %', 'change_points' => 'Change pts'], $rows)], [], [], ['before_total_units' => $beforeTotal, 'after_total_units' => $afterTotal, 'partners' => $rows]);
    }

    private function rofr(array $input): array
    {
        $offer = $this->dateOrNull($input['offer_date'] ?? null);
        $days = max(1, min(365, (int) $this->amount($input['response_days'] ?? 14)));
        $deadline = $offer?->copy()->addDays($days);
        $units = $this->amount($input['transfer_units'] ?? 0);
        $price = $this->amount($input['price_per_unit'] ?? 0);
        $total = round($units * $price, 2);
        return $this->result('Internal Response Deadline', $deadline?->toDateString() ?? 'Offer date required', 'text', [$this->metric('Units Offered', $units, 'number'), $this->metric('Price / Unit', $price, 'money'), $this->metric('Offer Value', $total, 'money')], [], [], ['Right-of-first-refusal / pre-emption rights depend on the executed agreement, entity documents and local law.'], ['offer_date' => $offer?->toDateString(), 'response_days' => $days, 'response_deadline' => $deadline?->toDateString(), 'transfer_units' => $units, 'price_per_unit' => $price, 'offer_value' => $total, 'conditions' => $input['conditions'] ?? null]);
    }

    private function transferApproval(array $input): array
    {
        $rows = [];
        foreach ($this->rows($input['rules'] ?? []) as $row) {
            $type = trim((string) ($row['transfer_type'] ?? ''));
            if ($type === '') { continue; }
            $rows[] = ['transfer_type' => $type, 'approval_rule' => ucfirst((string) ($row['approval_rule'] ?? 'none')), 'first_offer' => (($row['preemption'] ?? 'no') === 'yes') ? 'Yes' : 'No', 'conditions' => trim((string) ($row['notes'] ?? '')) ?: '—'];
        }
        return $this->result('Transfer Rule Types', count($rows), 'number', [], [$this->table('Transfer Approval Matrix', ['transfer_type' => 'Transfer Type', 'approval_rule' => 'Approval', 'first_offer' => 'First Offer?', 'conditions' => 'Conditions'], $rows)], [], ['Approval and pre-emption rules should be mirrored in the actual legal documents where required.'], ['rules' => $rows]);
    }

    private function transferValue(array $input): array
    {
        $value = $this->amount($input['business_value'] ?? 0);
        $total = max(1, $this->amount($input['total_units'] ?? 1));
        $transfer = min($total, $this->amount($input['transfer_units'] ?? 0));
        $perUnit = round($value / $total, 6);
        $transferValue = round($perUnit * $transfer, 2);
        return $this->result('Indicative Transfer Value', $transferValue, 'money', [$this->metric('Indicative Value / Unit', $perUnit, 'money'), $this->metric('Transfer Units', $transfer, 'number'), $this->metric('Transfer Stake', round($transfer / $total * 100, 2), 'percent')], [], [], ['Actual transaction price may include negotiated discounts/premiums, debt/cash adjustments, taxes and legal constraints.'], ['business_value' => $value, 'total_units' => $total, 'per_unit_value' => $perUnit, 'transfer_units' => $transfer, 'transfer_value' => $transferValue]);
    }

    private function transferHistory(array $input): array
    {
        $units = $this->amount($input['units'] ?? 0);
        $price = $this->amount($input['price_per_unit'] ?? 0);
        return $this->result('Recorded Transfer Value', round($units * $price, 2), 'money', [$this->metric('Units', $units, 'number'), $this->metric('Price / Unit', $price, 'money'), $this->metric('Transfer Date', $input['transfer_date'] ?? 'Not set', 'text')], [], [], ['Keep executed transfer documents and official registers separately where required.'], ['transfer_date' => $input['transfer_date'] ?? null, 'from_holder' => $input['from_holder'] ?? null, 'to_holder' => $input['to_holder'] ?? null, 'units' => $units, 'price_per_unit' => $price, 'total_value' => round($units * $price, 2), 'reference' => $input['reference'] ?? null]);
    }

    private function escalationLadder(array $input): array
    {
        $rows = [];
        $totalDays = 0;
        foreach ($this->rows($input['steps'] ?? []) as $row) {
            $step = trim((string) ($row['step'] ?? ''));
            if ($step === '') { continue; }
            $days = max(0, (int) $this->amount($row['days'] ?? 0));
            $totalDays += $days;
            $rows[] = ['step' => $step, 'owner' => trim((string) ($row['owner'] ?? '')) ?: 'Not assigned', 'max_days' => $days, 'cumulative_days' => $totalDays, 'success_condition' => trim((string) ($row['success_condition'] ?? '')) ?: '—'];
        }
        return $this->result('Escalation Window', $totalDays, 'days', [$this->metric('Steps', count($rows), 'number')], [$this->table('Escalation Ladder', ['step' => 'Step', 'owner' => 'Owner', 'max_days' => 'Max Days', 'cumulative_days' => 'Cumulative Days', 'success_condition' => 'Resolution / Exit Condition'], $rows)], [], ['Mediation, arbitration and court options depend on agreement wording and jurisdiction.'], ['total_days' => $totalDays, 'steps' => $rows]);
    }

    private function disputeLog(array $input): array
    {
        $severity = (string) ($input['severity'] ?? 'medium');
        $status = (string) ($input['status'] ?? 'open');
        $warnings = trim((string) ($input['facts'] ?? '')) === '' ? ['Known facts / evidence ကိုဖြည့်ပါ။ Opinion နဲ့ facts ကိုခွဲထားဖို့အရေးကြီးပါတယ်။'] : [];
        return $this->result('Dispute Status', ucfirst(str_replace('_', ' ', $status)), 'text', [$this->metric('Severity', ucfirst($severity), 'text'), $this->metric('Issue Date', $input['issue_date'] ?? 'Not set', 'text')], [], $warnings, ['PBR does not decide who is legally right or wrong. Use structured facts and escalate to qualified professionals where needed.'], ['issue_date' => $input['issue_date'] ?? null, 'issue_title' => $input['issue_title'] ?? null, 'facts' => $input['facts'] ?? null, 'parties' => $input['parties'] ?? null, 'severity' => $severity, 'status' => $status]);
    }

    private function resolutionTracker(array $input): array
    {
        $target = $this->dateOrNull($input['target_date'] ?? null);
        $days = $target ? now()->startOfDay()->diffInDays($target, false) : null;
        $status = (string) ($input['status'] ?? 'not_started');
        $warnings = $days !== null && $days < 0 && $status !== 'done' ? ['Target date ကျော်သွားပြီး status မပြီးသေးပါ။ Next action ကို review လုပ်ပါ။'] : [];
        return $this->result('Resolution Action Status', ucwords(str_replace('_', ' ', $status)), 'text', [$this->metric('Target Date', $target?->toDateString() ?? 'Not set', 'text'), $this->metric('Days to Target', $days, 'days')], [], $warnings, [], ['issue_title' => $input['issue_title'] ?? null, 'current_stage' => $input['current_stage'] ?? null, 'next_action' => $input['next_action'] ?? null, 'action_owner' => $input['action_owner'] ?? null, 'target_date' => $target?->toDateString(), 'status' => $status]);
    }

    private function issuePriority(array $input): array
    {
        $rows = [];
        foreach ($this->rows($input['issues'] ?? []) as $row) {
            $issue = trim((string) ($row['issue'] ?? ''));
            if ($issue === '') { continue; }
            $impact = $this->score5($row['impact'] ?? 1);
            $urgency = $this->score5($row['urgency'] ?? 1);
            $continuity = $this->score5($row['continuity'] ?? 1);
            $score = round(($impact * 0.4) + ($urgency * 0.35) + ($continuity * 0.25), 2);
            $priority = $score >= 4.25 ? 'Critical' : ($score >= 3.5 ? 'High' : ($score >= 2.5 ? 'Medium' : 'Lower'));
            $rows[] = ['issue' => $issue, 'impact' => $impact, 'urgency' => $urgency, 'continuity' => $continuity, 'priority_score' => $score, 'priority' => $priority];
        }
        usort($rows, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);
        return $this->result('Issues Prioritized', count($rows), 'number', [], [$this->table('Issue Priority Matrix', ['issue' => 'Issue', 'impact' => 'Impact', 'urgency' => 'Urgency', 'continuity' => 'Continuity', 'priority_score' => 'Score', 'priority' => 'Priority'], $rows)], [], ['Priority score is a PBR triage aid based only on user-entered values; it is not a legal or ISO-certified risk rating.'], ['issues' => $rows]);
    }

    private function escalationTimeline(array $input): array
    {
        $start = $this->dateOrNull($input['start_date'] ?? null);
        $discussion = (int) $this->amount($input['discussion_days'] ?? 0);
        $mediation = (int) $this->amount($input['mediation_days'] ?? 0);
        $external = (int) $this->amount($input['external_review_days'] ?? 0);
        $discussionEnd = $start?->copy()->addDays($discussion);
        $mediationEnd = $discussionEnd?->copy()->addDays($mediation);
        $externalTarget = $mediationEnd?->copy()->addDays($external);
        $rows = [
            ['stage' => 'Issue / Process Start', 'target_date' => $start?->toDateString() ?? '—'],
            ['stage' => 'Direct Discussion Window Ends', 'target_date' => $discussionEnd?->toDateString() ?? '—'],
            ['stage' => 'Mediation Window Ends', 'target_date' => $mediationEnd?->toDateString() ?? '—'],
            ['stage' => 'External Review Preparation Target', 'target_date' => $externalTarget?->toDateString() ?? '—'],
        ];
        return $this->result('Escalation Target', $externalTarget?->toDateString() ?? 'Start date required', 'text', [], [$this->table('Escalation Timeline', ['stage' => 'Stage', 'target_date' => 'Target Date'], $rows)], [], ['Actual procedural deadlines may come from contracts, arbitration rules or law and can override this planning timeline.'], ['milestones' => $rows]);
    }

    private function result(
        string $headlineLabel,
        mixed $headlineValue,
        string $headlineFormat,
        array $metrics = [],
        array $tables = [],
        array $warnings = [],
        array $notes = [],
        array $data = []
    ): array {
        return [
            'headline' => [
                'label' => $headlineLabel,
                'value' => $headlineValue,
                'format' => $headlineFormat,
            ],
            'metrics' => array_values($metrics),
            'tables' => array_values($tables),
            'warnings' => array_values(array_filter($warnings)),
            'notes' => array_values(array_filter($notes)),
            'data' => $data,
        ];
    }

    private function metric(string $label, mixed $value, string $format = 'text'): array
    {
        return ['label' => $label, 'value' => $value, 'format' => $format];
    }

    private function table(string $title, array $columns, array $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => array_values($rows)];
    }

    private function rows(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_array'))
            : [];
    }

    private function amount(mixed $value): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 6);
    }

    private function pct(mixed $value): float
    {
        return round(max(0, min(100, $this->amount($value))), 4);
    }

    private function score5(mixed $value): float
    {
        return round(max(1, min(5, $this->amount($value))), 2);
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);
        return $name !== '' ? $name : 'Unnamed Partner';
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
