<?php

use App\Services\PbrTools\PbrV2CapitalCallRuleReadinessService;

function c2dReadinessPayload(
    array $overrides = []
): array {
    return array_replace(
        [
            'schema_version' =>
                'capital-call-rule-v1',
            'rule_name' =>
                'Emergency Capital Call Rule',
            'owner_reference' =>
                'Managing Partner',
            'effective_date' =>
                '2026-09-07',
            'review_date' =>
                '2027-09-07',
            'rule_summary' =>
                'Use the agreed capital-call procedure when additional approved capital is required.',
            'trigger_conditions' => [
                'Confirmed funding is insufficient for an approved requirement.',
                'Partners decide that additional capital should be considered.',
            ],
            'procedure_steps' => [
                'Document the capital requirement and reason for the proposed call.',
                'Follow the partnership approval and communication process before execution.',
            ],
            'evidence_references' => [
                'Capital policy meeting note',
                'Partnership agreement reference',
            ],
        ],
        $overrides
    );
}

test(
    'capital call rule readiness exposes the exact pure public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalCallRuleReadinessService::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

        $public = array_values(
            array_map(
                fn (ReflectionMethod $method) =>
                    $method->getName(),
                array_filter(
                    $reflection->getMethods(
                        ReflectionMethod::IS_PUBLIC
                    ),
                    fn (ReflectionMethod $method) =>
                        $method->getDeclaringClass()
                            ->getName()
                        === PbrV2CapitalCallRuleReadinessService::class
                )
            )
        );

        expect($public)->toBe([
            '__construct',
            'assess',
        ]);

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull()
            ->and(
                $constructor
                    ->getNumberOfParameters()
            )
            ->toBe(0);

        $method =
            $reflection->getMethod('assess');

        expect(
            array_map(
                fn (ReflectionParameter $parameter) =>
                    [
                        $parameter->getName(),
                        (string) $parameter->getType(),
                        $parameter->isOptional(),
                    ],
                $method->getParameters()
            )
        )->toBe([
            [
                'domainKey',
                'string',
                false,
            ],
            [
                'componentKey',
                'string',
                false,
            ],
            [
                'payload',
                'array',
                false,
            ],
        ])
            ->and(
                (string) $method->getReturnType()
            )
            ->toBe('array');
    }
);

test(
    'valid capital call rule passes with no calculations',
    function () {
        $result = app(
            PbrV2CapitalCallRuleReadinessService::class
        )->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload()
        );

        expect(array_keys($result))->toBe([
            'passed',
            'rule_version',
            'errors',
            'warnings',
            'calculations',
        ])
            ->and($result['passed'])
            ->toBeTrue()
            ->and($result['rule_version'])
            ->toBe(
                'capital-call-rule-readiness-v1'
            )
            ->and($result['errors'])
            ->toBe([])
            ->and($result['warnings'])
            ->toBe([])
            ->and($result['calculations'])
            ->toBeNull();
    }
);

test(
    'readiness fails closed on unsupported canonical identity',
    function (
        string $domain,
        string $component
    ) {
        $result = app(
            PbrV2CapitalCallRuleReadinessService::class
        )->assess(
            $domain,
            $component,
            c2dReadinessPayload()
        );

        expect($result['passed'])
            ->toBeFalse()
            ->and($result['errors'][0])
            ->toBe([
                'code' =>
                    'UNSUPPORTED_TARGET',
                'path' => 'target',
                'message' =>
                    'Capital-call rule readiness requires capital / capital_call_rule.',
            ])
            ->and($result['calculations'])
            ->toBeNull();
    }
)->with([
    [
        'capital',
        'approved_capital_plan',
    ],
    [
        'ownership',
        'capital_call_rule',
    ],
]);

test(
    'readiness requires the exact schema version',
    function () {
        $result = app(
            PbrV2CapitalCallRuleReadinessService::class
        )->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'schema_version' => 'other',
            ])
        );

        expect($result['passed'])
            ->toBeFalse()
            ->and($result['errors'])
            ->toContain([
                'code' =>
                    'INVALID_SCHEMA_VERSION',
                'path' =>
                    'schema_version',
                'message' =>
                    'Capital-call rule schema_version must be capital-call-rule-v1.',
            ]);
    }
);

test(
    'readiness rejects missing and unknown fields deterministically',
    function () {
        $payload =
            c2dReadinessPayload();

        unset(
            $payload['rule_summary']
        );

        $payload['forbidden_field'] =
            'not allowed';

        $result = app(
            PbrV2CapitalCallRuleReadinessService::class
        )->assess(
            'capital',
            'capital_call_rule',
            $payload
        );

        expect($result['passed'])
            ->toBeFalse()
            ->and($result['errors'])
            ->toContain([
                'code' =>
                    'REQUIRED_FIELD',
                'path' =>
                    'rule_summary',
                'message' =>
                    'Required field is missing.',
            ])
            ->and($result['errors'])
            ->toContain([
                'code' =>
                    'UNKNOWN_FIELD',
                'path' =>
                    'forbidden_field',
                'message' =>
                    'Unknown field.',
            ]);
    }
);

test(
    'readiness rejects blank or oversized rule text',
    function (
        string $field,
        string $value
    ) {
        $result = app(
            PbrV2CapitalCallRuleReadinessService::class
        )->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                $field => $value,
            ])
        );

        expect($result['passed'])
            ->toBeFalse()
            ->and(
                array_column(
                    $result['errors'],
                    'path'
                )
            )
            ->toContain($field);
    }
)->with([
    [
        'rule_name',
        '   ',
    ],
    [
        'owner_reference',
        str_repeat('x', 121),
    ],
    [
        'rule_summary',
        str_repeat('x', 2001),
    ],
]);

test(
    'readiness rejects untrimmed text at root and list levels',
    function () {
        $service = app(
            PbrV2CapitalCallRuleReadinessService::class
        );

        $root = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'rule_name' =>
                    ' Untrimmed Rule ',
            ])
        );

        expect($root['passed'])
            ->toBeFalse()
            ->and($root['errors'])
            ->toContain([
                'code' =>
                    'INVALID_VALUE',
                'path' =>
                    'rule_name',
                'message' =>
                    'Text must be non-empty, trimmed and within the permitted length.',
            ]);

        $list = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'trigger_conditions' => [
                    ' Untrimmed trigger ',
                ],
            ])
        );

        expect($list['passed'])
            ->toBeFalse()
            ->and($list['errors'])
            ->toContain([
                'code' =>
                    'INVALID_VALUE',
                'path' =>
                    'trigger_conditions.0',
                'message' =>
                    'Text must be non-empty, trimmed and within the permitted length.',
            ]);
    }
);

test(
    'readiness enforces strict calendar dates and date order',
    function () {
        $service = app(
            PbrV2CapitalCallRuleReadinessService::class
        );

        $invalidDate = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'effective_date' =>
                    '2026-02-30',
            ])
        );

        expect($invalidDate['passed'])
            ->toBeFalse()
            ->and($invalidDate['errors'])
            ->toContain([
                'code' =>
                    'INVALID_DATE',
                'path' =>
                    'effective_date',
                'message' =>
                    'Expected a valid YYYY-MM-DD calendar date.',
            ]);

        $invalidOrder = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'effective_date' =>
                    '2026-09-07',
                'review_date' =>
                    '2026-09-06',
            ])
        );

        expect($invalidOrder['passed'])
            ->toBeFalse()
            ->and($invalidOrder['errors'])
            ->toContain([
                'code' =>
                    'INVALID_DATE_ORDER',
                'path' =>
                    'review_date',
                'message' =>
                    'Review date cannot precede effective date.',
            ]);
    }
);

test(
    'readiness requires sequential non empty rule lists',
    function () {
        $service = app(
            PbrV2CapitalCallRuleReadinessService::class
        );

        $empty = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'trigger_conditions' => [],
            ])
        );

        expect($empty['passed'])
            ->toBeFalse()
            ->and($empty['errors'])
            ->toContain([
                'code' =>
                    'INVALID_VALUE',
                'path' =>
                    'trigger_conditions',
                'message' =>
                    'List length is outside the permitted range.',
            ]);

        $associative = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'procedure_steps' => [
                    'first' =>
                        'Not sequential',
                ],
            ])
        );

        expect($associative['passed'])
            ->toBeFalse()
            ->and($associative['errors'])
            ->toContain([
                'code' =>
                    'INVALID_TYPE',
                'path' =>
                    'procedure_steps',
                'message' =>
                    'Expected a sequential list.',
            ]);

        $blankItem = $service->assess(
            'capital',
            'capital_call_rule',
            c2dReadinessPayload([
                'evidence_references' => [
                    '   ',
                ],
            ])
        );

        expect($blankItem['passed'])
            ->toBeFalse()
            ->and(
                array_column(
                    $blankItem['errors'],
                    'path'
                )
            )
            ->toContain(
                'evidence_references.0'
            );
    }
);

test(
    'readiness source contains no financial ownership or persistence authority',
    function () {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/PbrTools/PbrV2CapitalCallRuleReadinessService.php'
        );

        foreach ([
            'DB::',
            'Auth::',
            'now(',
            'WorkspaceV2',
            'partner_percentage',
            'share_percentage',
            'equity_percentage',
            'dilution',
            'interest_rate',
            'default_penalty',
            'voting_threshold',
            'notice_days',
            'payment_deadline',
            'approved_capital_plan',
        ] as $forbidden) {
            expect($source)
                ->not->toContain(
                    $forbidden
                );
        }
    }
);
