<?php

namespace App\Services\PbrTools;

use DateTimeImmutable;

final class PbrV2CapitalPlanReadinessService
{
    private const MAX_MONEY = 99999999999999;
    private const RULE_VERSION = 'capital-plan-readiness-v1';

    public function assess(string $domainKey, string $componentKey, array $payload): array
    {
        $errors = [];
        $warnings = [];

        if ($domainKey !== 'capital' || $componentKey !== 'approved_capital_plan') {
            $this->issue($errors, 'UNSUPPORTED_TARGET', 'target', 'Unsupported readiness target.');

            return $this->result($errors, $warnings);
        }

        $this->validate($payload, $this->schema(), '', $errors);

        if ($errors !== []) {
            return $this->result($errors, $warnings);
        }

        if ($payload['schema_version'] !== 'capital-plan-v1') {
            $this->issue($errors, 'UNSUPPORTED_SCHEMA', 'schema_version', 'Unsupported payload schema.');

            return $this->result($errors, $warnings);
        }

        $assessment = $payload['assessment_date'];
        $assumptions = $payload['assumptions'];
        $opening = $assumptions['opening_date'];

        if ($payload['business_stage'] === 'new' && $opening === null) {
            $this->issue($errors, 'REQUIRED_FIELD', 'assumptions.opening_date', 'Opening date is required for a new business.');
        }

        foreach (['cost_items', 'funding_sources'] as $list) {
            $seen = [];
            foreach ($payload[$list] as $index => $row) {
                if (isset($seen[$row['id']])) {
                    $this->issue($errors, 'DUPLICATE_ID', "$list.$index.id", 'Duplicate identifier in this list.');
                }
                $seen[$row['id']] = true;
            }
        }

        foreach ($payload['cost_items'] as $index => $row) {
            if ($row['basis'] === 'monthly' && $row['category'] !== 'working_capital') {
                $this->issue($errors, 'INVALID_COST_BASIS', "cost_items.$index.basis", 'Monthly basis is restricted to working capital.');
            }
        }

        foreach ($payload['funding_sources'] as $index => $row) {
            if ($row['confirmation_date'] > $assessment) {
                $this->issue($errors, 'FUTURE_CONFIRMATION', "funding_sources.$index.confirmation_date", 'Confirmation cannot follow the assessment date.');
            }
        }

        $decision = $payload['decision_record'];
        if ($decision['review_date'] < $decision['effective_date']
            || $decision['review_date'] < $assessment) {
            $this->issue($errors, 'INVALID_DATE_ORDER', 'decision_record.review_date', 'Review date cannot precede effective or assessment date.');
        }

        $response = $payload['shortfall_response'];
        if ($response !== null && $response['target_date'] < $assessment) {
            $this->issue($errors, 'INVALID_DATE_ORDER', 'shortfall_response.target_date', 'Shortfall target date cannot precede assessment.');
        }

        if ($errors !== []) {
            return $this->result($errors, $warnings);
        }

        $totals = [
            'pre_opening' => 0,
            'initial_assets_inventory' => 0,
            'working_capital' => 0,
        ];
        $base = 0;

        foreach ($payload['cost_items'] as $index => $row) {
            $amount = $this->units($row['amount']);
            $months = $row['basis'] === 'monthly' ? $assumptions['working_capital_months'] : 1;

            if ($amount > intdiv(self::MAX_MONEY, $months)) {
                $this->issue($errors, 'AMOUNT_OVERFLOW', "cost_items.$index.amount", 'Extended cost exceeds the money limit.');

                return $this->result($errors, $warnings);
            }

            $extended = $amount * $months;
            if ($extended > self::MAX_MONEY - $base) {
                $this->issue($errors, 'AMOUNT_OVERFLOW', 'cost_items', 'Total base requirement exceeds the money limit.');

                return $this->result($errors, $warnings);
            }

            $base += $extended;
            $totals[$row['category']] += $extended;
        }

        // MAX_MONEY * 10000 fits in the required 64-bit integer runtime.
        $percentage = $this->units($assumptions['contingency_percentage']);
        $reserve = intdiv($base * $percentage + 5000, 10000);

        if ($reserve > self::MAX_MONEY - $base) {
            $this->issue($errors, 'AMOUNT_OVERFLOW', 'assumptions.contingency_percentage', 'Requirement including reserve exceeds the money limit.');

            return $this->result($errors, $warnings);
        }

        $required = $base + $reserve;
        $confirmed = 0;

        foreach ($payload['funding_sources'] as $index => $row) {
            $amount = $this->units($row['confirmed_amount']);
            if ($amount > self::MAX_MONEY - $confirmed) {
                $this->issue($errors, 'AMOUNT_OVERFLOW', "funding_sources.$index.confirmed_amount", 'Confirmed funding exceeds the money limit.');

                return $this->result($errors, $warnings);
            }
            $confirmed += $amount;
        }

        $gap = max(0, $required - $confirmed);
        $surplus = max(0, $confirmed - $required);

        if ($gap > 0 && $response === null) {
            $this->issue($errors, 'SHORTFALL_RESPONSE_REQUIRED', 'shortfall_response', 'A funding gap requires a shortfall response.');
        } elseif ($gap === 0 && $response !== null) {
            $this->issue($errors, 'UNEXPECTED_SHORTFALL_RESPONSE', 'shortfall_response', 'Shortfall response must be null when there is no gap.');
        }

        if ($errors !== []) {
            return $this->result($errors, $warnings);
        }

        if ($gap > 0) {
            $this->issue($warnings, 'FUNDING_GAP', 'shortfall_response', 'Funding remains incomplete; the response does not count as confirmed funding.');
        }
        if ($opening !== null && $opening < $assessment) {
            $this->issue($warnings, 'OPENING_DATE_BEFORE_ASSESSMENT', 'assumptions.opening_date', 'Opening date precedes assessment.');
        }

        // Positive cost rows guarantee a positive denominator.
        // Integer quotient implements half-up rounding to two percentage decimals.
        $coverage = intdiv($confirmed * 10000 + intdiv($required, 2), $required);

        return $this->result($errors, $warnings, [
            'pre_opening_total' => $this->decimal($totals['pre_opening']),
            'initial_assets_inventory_total' => $this->decimal($totals['initial_assets_inventory']),
            'working_capital_total' => $this->decimal($totals['working_capital']),
            'base_requirement' => $this->decimal($base),
            'contingency_reserve' => $this->decimal($reserve),
            'total_required' => $this->decimal($required),
            'confirmed_funding' => $this->decimal($confirmed),
            'funding_gap' => $this->decimal($gap),
            'funding_surplus' => $this->decimal($surplus),
            'funded_percentage' => $this->decimal($coverage),
        ]);
    }

    private function schema(): array
    {
        return ['object', [
            'schema_version' => ['text', 120],
            'business_stage' => ['enum', ['new', 'existing']],
            'currency_code' => ['enum', ['THB', 'MMK', 'USD', 'SGD', 'MYR']],
            'plan_name' => ['text', 120],
            'scenario_type' => ['enum', ['lean', 'base', 'growth', 'custom']],
            'assessment_date' => ['date'],
            'assumptions' => ['object', [
                'opening_date' => ['nullable_date'],
                'working_capital_months' => ['integer', 1, 24],
                'contingency_percentage' => ['percentage'],
                'monthly_burn' => ['money', false],
            ]],
            'cost_items' => ['list', ['object', [
                'id' => ['identifier'],
                'category' => ['enum', ['pre_opening', 'initial_assets_inventory', 'working_capital']],
                'description' => ['text', 150],
                'amount' => ['money', true],
                'basis' => ['enum', ['one_time', 'monthly']],
                'evidence_reference' => ['text', 500],
            ]], 1, 300],
            'funding_sources' => ['list', ['object', [
                'id' => ['identifier'],
                'source_type' => ['enum', ['partner_cash', 'business_cash', 'loan', 'grant', 'other']],
                'description' => ['text', 150],
                'confirmed_amount' => ['money', true],
                'confirmation_date' => ['date'],
                'evidence_reference' => ['text', 500],
            ]], 0, 300],
            'shortfall_response' => ['nullable_object', [
                'strategy' => ['enum', ['reduce_scope', 'delay', 'borrow', 'capital_call', 'mixed']],
                'owner_reference' => ['text', 120],
                'target_date' => ['date'],
                'summary' => ['text', 2000],
            ]],
            'decision_record' => ['object', [
                'owner_reference' => ['text', 120],
                'effective_date' => ['date'],
                'review_date' => ['date'],
                'decision_summary' => ['text', 2000],
                'evidence_references' => ['list', ['text', 500], 1, 100],
            ]],
        ]];
    }

    private function validate(mixed $value, array $spec, string $path, array &$errors): void
    {
        $kind = $spec[0];

        if ($value === null && in_array($kind, ['nullable_date', 'nullable_object'], true)) {
            return;
        }

        if ($kind === 'object' || $kind === 'nullable_object') {
            if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                $this->issue($errors, 'INVALID_TYPE', $path, 'Expected an associative array.');

                return;
            }

            foreach ($spec[1] as $key => $child) {
                $childPath = $path === '' ? $key : "$path.$key";
                if (! array_key_exists($key, $value)) {
                    $this->issue($errors, 'REQUIRED_FIELD', $childPath, 'Required field is missing.');
                } else {
                    $this->validate($value[$key], $child, $childPath, $errors);
                }
            }

            $unknown = array_diff(array_keys($value), array_keys($spec[1]));
            sort($unknown, SORT_STRING);
            foreach ($unknown as $key) {
                $this->issue($errors, 'UNKNOWN_FIELD', $path === '' ? (string) $key : "$path.$key", 'Unknown field.');
            }

            return;
        }

        if ($kind === 'list') {
            if (! is_array($value) || ! array_is_list($value)) {
                $this->issue($errors, 'INVALID_TYPE', $path, 'Expected a sequential list.');

                return;
            }
            if (count($value) < $spec[2] || count($value) > $spec[3]) {
                $this->issue($errors, 'INVALID_VALUE', $path, 'List length is outside the permitted range.');

                return;
            }
            foreach ($value as $index => $item) {
                $this->validate($item, $spec[1], "$path.$index", $errors);
            }

            return;
        }

        if ($kind === 'integer') {
            if (! is_int($value)) {
                $this->issue($errors, 'INVALID_TYPE', $path, 'Expected an integer.');
            } elseif ($value < $spec[1] || $value > $spec[2]) {
                $this->issue($errors, 'INVALID_VALUE', $path, 'Integer is outside the permitted range.');
            }

            return;
        }

        if (! is_string($value)) {
            $this->issue($errors, 'INVALID_TYPE', $path, 'Expected a string.');

            return;
        }

        if ($kind === 'money' || $kind === 'percentage') {
            $code = $kind === 'money' ? 'INVALID_MONEY' : 'INVALID_PERCENTAGE';
            $valid = preg_match('/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/D', $value) === 1;

            if (! $valid) {
                $this->issue($errors, $code, $path, 'Expected a bounded non-negative decimal with exactly two places.');

                return;
            }

            $units = $this->units($value);
            if (($kind === 'percentage' && $units > 10000)
                || ($kind === 'money' && $spec[1] && $units === 0)) {
                $this->issue($errors, $code, $path, 'Decimal value is outside the permitted range.');
            }

            return;
        }

        if ($kind === 'date' || $kind === 'nullable_date') {
            $valid = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) === 1;
            $date = $valid ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if (! $date || substr($value, 0, 4) === '0000' || $date->format('Y-m-d') !== $value) {
                $this->issue($errors, 'INVALID_DATE', $path, 'Expected a valid YYYY-MM-DD calendar date.');
            }

            return;
        }

        if ($kind === 'enum') {
            if (! in_array($value, $spec[1], true)) {
                $this->issue($errors, 'INVALID_VALUE', $path, 'Value is not in the permitted vocabulary.');
            }

            return;
        }

        if ($kind === 'identifier') {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $value) !== 1) {
                $this->issue($errors, 'INVALID_VALUE', $path, 'Expected a unique ASCII identifier of 1 to 64 characters.');
            }

            return;
        }

        if (! mb_check_encoding($value, 'UTF-8')
            || trim($value) !== $value
            || preg_match('/\A[\s\p{Z}]*\z/u', $value) === 1
            || mb_strlen($value, 'UTF-8') > $spec[1]) {
            $this->issue($errors, 'INVALID_VALUE', $path, 'Expected non-blank trimmed text within the character limit.');
        }
    }

    private function units(string $value): int
    {
        [$whole, $fraction] = explode('.', $value);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function decimal(int $units): string
    {
        return intdiv($units, 100).'.'.str_pad((string) ($units % 100), 2, '0', STR_PAD_LEFT);
    }

    private function issue(array &$issues, string $code, string $path, string $message): void
    {
        $issues[] = ['code' => $code, 'path' => $path, 'message' => $message];
    }

    private function result(array $errors, array $warnings, ?array $calculations = null): array
    {
        return [
            'passed' => $errors === [],
            'rule_version' => self::RULE_VERSION,
            'errors' => $errors,
            'warnings' => $warnings,
            'calculations' => $errors === [] ? $calculations : null,
        ];
    }
}
