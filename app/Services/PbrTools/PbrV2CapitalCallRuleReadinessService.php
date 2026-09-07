<?php

namespace App\Services\PbrTools;

final class PbrV2CapitalCallRuleReadinessService
{
    private const DOMAIN_KEY = 'capital';

    private const COMPONENT_KEY =
        'capital_call_rule';

    private const SCHEMA_VERSION =
        'capital-call-rule-v1';

    private const RULE_VERSION =
        'capital-call-rule-readiness-v1';

    public function __construct()
    {
    }

    public function assess(
        string $domainKey,
        string $componentKey,
        array $payload
    ): array {
        $errors = [];
        $warnings = [];

        if (
            $domainKey !== self::DOMAIN_KEY
            || $componentKey !== self::COMPONENT_KEY
        ) {
            $this->issue(
                $errors,
                'UNSUPPORTED_TARGET',
                'target',
                'Capital-call rule readiness requires capital / capital_call_rule.'
            );

            return $this->result(
                $errors,
                $warnings
            );
        }

        $this->validate(
            $payload,
            $this->schema(),
            '',
            $errors
        );

        if ($errors !== []) {
            return $this->result(
                $errors,
                $warnings
            );
        }

        if (
            $payload['schema_version']
            !== self::SCHEMA_VERSION
        ) {
            $this->issue(
                $errors,
                'INVALID_SCHEMA_VERSION',
                'schema_version',
                'Capital-call rule schema_version must be capital-call-rule-v1.'
            );
        }

        if (
            $payload['review_date']
            < $payload['effective_date']
        ) {
            $this->issue(
                $errors,
                'INVALID_DATE_ORDER',
                'review_date',
                'Review date cannot precede effective date.'
            );
        }

        return $this->result(
            $errors,
            $warnings
        );
    }

    private function schema(): array
    {
        return [
            'object',
            [
                'schema_version' => [
                    'text',
                    120,
                ],
                'rule_name' => [
                    'text',
                    120,
                ],
                'owner_reference' => [
                    'text',
                    120,
                ],
                'effective_date' => [
                    'date',
                ],
                'review_date' => [
                    'date',
                ],
                'rule_summary' => [
                    'text',
                    2000,
                ],
                'trigger_conditions' => [
                    'list',
                    [
                        'text',
                        500,
                    ],
                    1,
                    50,
                ],
                'procedure_steps' => [
                    'list',
                    [
                        'text',
                        1000,
                    ],
                    1,
                    50,
                ],
                'evidence_references' => [
                    'list',
                    [
                        'text',
                        500,
                    ],
                    1,
                    100,
                ],
            ],
        ];
    }

    private function validate(
        mixed $value,
        array $spec,
        string $path,
        array &$errors
    ): void {
        $kind = $spec[0];

        if ($kind === 'object') {
            if (
                ! is_array($value)
                || (
                    $value !== []
                    && array_is_list($value)
                )
            ) {
                $this->issue(
                    $errors,
                    'INVALID_TYPE',
                    $path,
                    'Expected an associative array.'
                );

                return;
            }

            foreach (
                $spec[1]
                as $key => $child
            ) {
                $childPath =
                    $path === ''
                        ? $key
                        : $path.'.'.$key;

                if (
                    ! array_key_exists(
                        $key,
                        $value
                    )
                ) {
                    $this->issue(
                        $errors,
                        'REQUIRED_FIELD',
                        $childPath,
                        'Required field is missing.'
                    );

                    continue;
                }

                $this->validate(
                    $value[$key],
                    $child,
                    $childPath,
                    $errors
                );
            }

            $unknown = array_diff(
                array_keys($value),
                array_keys($spec[1])
            );

            sort(
                $unknown,
                SORT_STRING
            );

            foreach ($unknown as $key) {
                $this->issue(
                    $errors,
                    'UNKNOWN_FIELD',
                    $path === ''
                        ? (string) $key
                        : $path.'.'.$key,
                    'Unknown field.'
                );
            }

            return;
        }

        if ($kind === 'list') {
            if (
                ! is_array($value)
                || ! array_is_list($value)
            ) {
                $this->issue(
                    $errors,
                    'INVALID_TYPE',
                    $path,
                    'Expected a sequential list.'
                );

                return;
            }

            if (
                count($value) < $spec[2]
                || count($value) > $spec[3]
            ) {
                $this->issue(
                    $errors,
                    'INVALID_VALUE',
                    $path,
                    'List length is outside the permitted range.'
                );

                return;
            }

            foreach (
                $value
                as $index => $item
            ) {
                $this->validate(
                    $item,
                    $spec[1],
                    $path.'.'.$index,
                    $errors
                );
            }

            return;
        }

        if (! is_string($value)) {
            $this->issue(
                $errors,
                'INVALID_TYPE',
                $path,
                'Expected a string.'
            );

            return;
        }

        if ($kind === 'text') {
            $trimmed = trim($value);

            if (
                $trimmed === ''
                || $value !== $trimmed
                || strlen($trimmed) > $spec[1]
            ) {
                $this->issue(
                    $errors,
                    'INVALID_VALUE',
                    $path,
                    'Text must be non-empty, trimmed and within the permitted length.'
                );
            }

            return;
        }

        if ($kind === 'date') {
            $date =
                \DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $value
                );

            if (
                ! $date
                || $date->format('Y-m-d')
                    !== $value
            ) {
                $this->issue(
                    $errors,
                    'INVALID_DATE',
                    $path,
                    'Expected a valid YYYY-MM-DD calendar date.'
                );
            }

            return;
        }

        $this->issue(
            $errors,
            'INVALID_SCHEMA',
            $path,
            'Unsupported readiness schema validator.'
        );
    }

    private function issue(
        array &$issues,
        string $code,
        string $path,
        string $message
    ): void {
        $issues[] = [
            'code' => $code,
            'path' => $path,
            'message' => $message,
        ];
    }

    private function result(
        array $errors,
        array $warnings
    ): array {
        return [
            'passed' => $errors === [],
            'rule_version' =>
                self::RULE_VERSION,
            'errors' => $errors,
            'warnings' => $warnings,
            'calculations' => null,
        ];
    }
}
