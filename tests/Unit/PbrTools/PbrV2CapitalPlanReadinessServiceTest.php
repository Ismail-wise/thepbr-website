<?php

use App\Services\PbrTools\PbrV2CapitalPlanReadinessService;

function pbrV2CapitalReadinessPayload(): array
{
    return [
        'schema_version' => 'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => 'Base Capital Plan',
        'scenario_type' => 'base',
        'assessment_date' => '2026-09-05',
        'assumptions' => [
            'opening_date' => '2026-10-01',
            'working_capital_months' => 3,
            'contingency_percentage' => '10.00',
            'monthly_burn' => '500.00',
        ],
        'cost_items' => [
            [
                'id' => 'pre-1',
                'category' => 'pre_opening',
                'description' => 'Registration and launch',
                'amount' => '1000.00',
                'basis' => 'one_time',
                'evidence_reference' => 'estimate-pre-1',
            ],
            [
                'id' => 'asset-1',
                'category' => 'initial_assets_inventory',
                'description' => 'Equipment',
                'amount' => '2000.00',
                'basis' => 'one_time',
                'evidence_reference' => 'quote-asset-1',
            ],
            [
                'id' => 'working-1',
                'category' => 'working_capital',
                'description' => 'Operating cash',
                'amount' => '500.00',
                'basis' => 'monthly',
                'evidence_reference' => 'forecast-1',
            ],
        ],
        'funding_sources' => [
            [
                'id' => 'cash-1',
                'source_type' => 'partner_cash',
                'description' => 'Available cash',
                'confirmed_amount' => '2000.00',
                'confirmation_date' => '2026-09-01',
                'evidence_reference' => 'receipt-1',
            ],
        ],
        'shortfall_response' => [
            'strategy' => 'mixed',
            'owner_reference' => 'capital-owner',
            'target_date' => '2026-09-30',
            'summary' => 'Reduce scope or arrange additional funding before execution.',
        ],
        'decision_record' => [
            'owner_reference' => 'capital-owner',
            'effective_date' => '2026-09-05',
            'review_date' => '2026-12-05',
            'decision_summary' => 'Review the capital requirement and remaining gap.',
            'evidence_references' => ['capital-review-1'],
        ],
    ];
}

function pbrV2CapitalReadinessAssess(array $payload): array
{
    return (new PbrV2CapitalPlanReadinessService())->assess(
        'capital', 'approved_capital_plan', $payload
    );
}

function pbrV2CapitalReadinessSet(array $payload, string $path, mixed $value): array
{
    $node = &$payload;
    foreach (explode('.', $path) as $part) {
        $node = &$node[$part];
    }
    $node = $value;

    return $payload;
}

test('capital readiness exposes exactly the frozen pure public surface', function () {
    $class = new ReflectionClass(PbrV2CapitalPlanReadinessService::class);
    expect($class->isFinal())->toBeTrue()
        ->and($class->getConstructor())->toBeNull()
        ->and($class->getProperties())->toBe([]);

    $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
    expect($methods)->toHaveCount(1)
        ->and($methods[0]->getName())->toBe('assess')
        ->and((string) $methods[0]->getReturnType())->toBe('array');

    $parameters = $methods[0]->getParameters();
    expect(array_map(fn ($p) => $p->getName(), $parameters))
        ->toBe(['domainKey', 'componentKey', 'payload']);
    expect(array_map(fn ($p) => (string) $p->getType(), $parameters))
        ->toBe(['string', 'string', 'array']);

    foreach ($class->getReflectionConstants() as $constant) {
        expect($constant->isPrivate())->toBeTrue();
    }
});

test('capital readiness calculates the PDF four-part requirement exactly', function () {
    $result = pbrV2CapitalReadinessAssess(pbrV2CapitalReadinessPayload());

    expect(array_keys($result))->toBe([
        'passed', 'rule_version', 'errors', 'warnings', 'calculations',
    ])->and($result['passed'])->toBeTrue()
        ->and($result['rule_version'])->toBe('capital-plan-readiness-v1')
        ->and($result['errors'])->toBe([])
        ->and(array_column($result['warnings'], 'code'))->toBe(['FUNDING_GAP'])
        ->and($result['calculations'])->toBe([
            'pre_opening_total' => '1000.00',
            'initial_assets_inventory_total' => '2000.00',
            'working_capital_total' => '1500.00',
            'base_requirement' => '4500.00',
            'contingency_reserve' => '450.00',
            'total_required' => '4950.00',
            'confirmed_funding' => '2000.00',
            'funding_gap' => '2950.00',
            'funding_surplus' => '0.00',
            'funded_percentage' => '40.40',
        ]);
});

dataset('capital readiness invalid fields', [
    ['schema_version', 'capital-plan-v2', 'UNSUPPORTED_SCHEMA'],
    ['business_stage', 'unknown', 'INVALID_VALUE'],
    ['currency_code', 'EUR', 'INVALID_VALUE'],
    ['scenario_type', 'default', 'INVALID_VALUE'],
    ['plan_name', ' ', 'INVALID_VALUE'],
    ['plan_name', ' untrimmed ', 'INVALID_VALUE'],
    ['assessment_date', '2026-02-30', 'INVALID_DATE'],
    ['assessment_date', '0000-01-01', 'INVALID_DATE'],
    ['assumptions', 'invalid', 'INVALID_TYPE'],
    ['assumptions.opening_date', null, 'REQUIRED_FIELD'],
    ['assumptions.working_capital_months', '3', 'INVALID_TYPE'],
    ['assumptions.working_capital_months', 0, 'INVALID_VALUE'],
    ['assumptions.working_capital_months', 25, 'INVALID_VALUE'],
    ['assumptions.contingency_percentage', '100.01', 'INVALID_PERCENTAGE'],
    ['assumptions.monthly_burn', '-1.00', 'INVALID_MONEY'],
    ['cost_items', [], 'INVALID_VALUE'],
    ['cost_items.0.id', 'bad id', 'INVALID_VALUE'],
    ['cost_items.0.amount', '0.00', 'INVALID_MONEY'],
    ['cost_items.0.amount', '-1.00', 'INVALID_MONEY'],
    ['cost_items.0.amount', '1e3', 'INVALID_MONEY'],
    ['cost_items.0.amount', '1,000.00', 'INVALID_MONEY'],
    ['cost_items.0.amount', '1.001', 'INVALID_MONEY'],
    ['cost_items.0.amount', '01.00', 'INVALID_MONEY'],
    ['cost_items.0.amount', '1000000000000.00', 'INVALID_MONEY'],
    ['cost_items.0.amount', 1000.0, 'INVALID_TYPE'],
    ['cost_items.0.basis', 'monthly', 'INVALID_COST_BASIS'],
    ['cost_items.0.category', 'contingency', 'INVALID_VALUE'],
    ['cost_items.0.evidence_reference', '', 'INVALID_VALUE'],
    ['funding_sources.0.source_type', 'promised', 'INVALID_VALUE'],
    ['funding_sources.0.confirmed_amount', '0.00', 'INVALID_MONEY'],
    ['funding_sources.0.confirmation_date', '2026-09-06', 'FUTURE_CONFIRMATION'],
    ['shortfall_response', null, 'SHORTFALL_RESPONSE_REQUIRED'],
    ['shortfall_response.strategy', 'ignore', 'INVALID_VALUE'],
    ['shortfall_response.target_date', '2026-09-04', 'INVALID_DATE_ORDER'],
    ['decision_record.review_date', '2026-09-04', 'INVALID_DATE_ORDER'],
    ['decision_record.evidence_references', [], 'INVALID_VALUE'],
]);

test('capital readiness rejects invalid fields without calculations', function (
    string $path,
    mixed $value,
    string $code
) {
    $payload = pbrV2CapitalReadinessSet(pbrV2CapitalReadinessPayload(), $path, $value);
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeFalse()
        ->and($result['calculations'])->toBeNull()
        ->and(array_column($result['errors'], 'code'))->toContain($code)
        ->and(array_column($result['errors'], 'path'))->toContain($path);
})->with('capital readiness invalid fields');

test('capital readiness rejects missing and unknown fields at nested levels', function () {
    $payload = pbrV2CapitalReadinessPayload();
    unset($payload['assumptions']['monthly_burn']);
    $payload['approved_by'] = 42;
    $payload['cost_items'][0]['planned_cost'] = '1000.00';
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeFalse()
        ->and(array_column($result['errors'], 'code'))->toContain('REQUIRED_FIELD', 'UNKNOWN_FIELD')
        ->and(array_column($result['errors'], 'path'))->toContain(
            'assumptions.monthly_burn', 'approved_by', 'cost_items.0.planned_cost'
        )->and($result['calculations'])->toBeNull();
});

test('capital readiness rejects unsupported identity before payload inspection', function () {
    $service = new PbrV2CapitalPlanReadinessService();

    foreach ([
        ['capital', 'capital_call_rule'],
        ['ownership', 'approved_capital_plan'],
    ] as [$domain, $component]) {
        $result = $service->assess($domain, $component, []);
        expect(array_column($result['errors'], 'code'))->toBe(['UNSUPPORTED_TARGET'])
            ->and($result['passed'])->toBeFalse()
            ->and($result['calculations'])->toBeNull();
    }
});

test('capital readiness supports existing business without startup costs', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['business_stage'] = 'existing';
    $payload['assumptions']['opening_date'] = null;
    $payload['cost_items'] = [$payload['cost_items'][2]];
    $payload['shortfall_response'] = null;
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations']['pre_opening_total'])->toBe('0.00')
        ->and($result['calculations']['initial_assets_inventory_total'])->toBe('0.00')
        ->and($result['calculations']['total_required'])->toBe('1650.00')
        ->and($result['calculations']['funding_surplus'])->toBe('350.00');
});

test('capital readiness allows zero confirmed funding with a valid response', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['funding_sources'] = [];
    $payload['assumptions']['monthly_burn'] = '0.00';
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations']['confirmed_funding'])->toBe('0.00')
        ->and($result['calculations']['funding_gap'])->toBe('4950.00')
        ->and($result['calculations']['funded_percentage'])->toBe('0.00');
});

test('capital readiness does not clamp surplus funding', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['funding_sources'][0]['confirmed_amount'] = '9900.00';
    $payload['shortfall_response'] = null;
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['warnings'])->toBe([])
        ->and($result['calculations']['funding_surplus'])->toBe('4950.00')
        ->and($result['calculations']['funded_percentage'])->toBe('200.00');

    $payload['shortfall_response'] = pbrV2CapitalReadinessPayload()['shortfall_response'];
    $result = pbrV2CapitalReadinessAssess($payload);
    expect(array_column($result['errors'], 'code'))->toContain('UNEXPECTED_SHORTFALL_RESPONSE');
});

test('capital readiness accepts exactly funded plans without a gap', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['funding_sources'][0]['confirmed_amount'] = '4950.00';
    $payload['shortfall_response'] = null;
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations']['funding_gap'])->toBe('0.00')
        ->and($result['calculations']['funding_surplus'])->toBe('0.00')
        ->and($result['calculations']['funded_percentage'])->toBe('100.00');
});

test('capital readiness rounds a half cent reserve upward using integer arithmetic', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['cost_items'] = [$payload['cost_items'][0]];
    $payload['cost_items'][0]['amount'] = '0.01';
    $payload['assumptions']['contingency_percentage'] = '50.00';
    $payload['funding_sources'] = [];
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations']['contingency_reserve'])->toBe('0.01')
        ->and($result['calculations']['total_required'])->toBe('0.02');
});

test('capital readiness handles maximum funding against a one cent plan exactly', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['cost_items'] = [$payload['cost_items'][0]];
    $payload['cost_items'][0]['amount'] = '0.01';
    $payload['assumptions']['contingency_percentage'] = '0.00';
    $payload['funding_sources'][0]['confirmed_amount'] = '999999999999.99';
    $payload['shortfall_response'] = null;
    $result = pbrV2CapitalReadinessAssess($payload);

    expect($result['passed'])->toBeTrue()
        ->and($result['calculations']['funded_percentage'])->toBe('9999999999999900.00')
        ->and($result['calculations']['funding_surplus'])->toBe('999999999999.98');
});

test('capital readiness guards monthly aggregate reserve and funding overflow', function () {
    $monthly = pbrV2CapitalReadinessPayload();
    $monthly['cost_items'][2]['amount'] = '999999999999.99';

    $aggregate = pbrV2CapitalReadinessPayload();
    $aggregate['cost_items'][0]['amount'] = '999999999999.99';

    $reserve = pbrV2CapitalReadinessPayload();
    $reserve['cost_items'] = [$reserve['cost_items'][0]];
    $reserve['cost_items'][0]['amount'] = '999999999999.99';

    $funding = pbrV2CapitalReadinessPayload();
    $funding['funding_sources'][0]['confirmed_amount'] = '999999999999.99';
    $second = $funding['funding_sources'][0];
    $second['id'] = 'cash-2';
    $second['confirmed_amount'] = '0.01';
    $funding['funding_sources'][] = $second;

    foreach ([$monthly, $aggregate, $reserve, $funding] as $payload) {
        $result = pbrV2CapitalReadinessAssess($payload);
        expect($result['passed'])->toBeFalse()
            ->and(array_column($result['errors'], 'code'))->toContain('AMOUNT_OVERFLOW')
            ->and($result['calculations'])->toBeNull();
    }
});

test('capital readiness rejects duplicate cost and funding identifiers', function () {
    foreach (['cost_items', 'funding_sources'] as $key) {
        $payload = pbrV2CapitalReadinessPayload();
        $payload[$key][] = $payload[$key][0];
        $result = pbrV2CapitalReadinessAssess($payload);
        expect($result['passed'])->toBeFalse()
            ->and(array_column($result['errors'], 'code'))->toContain('DUPLICATE_ID');
    }
});

test('capital readiness validates list shape and row limits', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $row = $payload['cost_items'][0];
    foreach ([
        ['named' => $row],
        array_fill(0, 301, $row),
        [null],
    ] as $rows) {
        $candidate = $payload;
        $candidate['cost_items'] = $rows;
        $result = pbrV2CapitalReadinessAssess($candidate);
        expect($result['passed'])->toBeFalse()
            ->and($result['calculations'])->toBeNull();
    }
});

test('capital readiness returns deterministic warnings without changing inputs or retaining state', function () {
    $payload = pbrV2CapitalReadinessPayload();
    $payload['assumptions']['opening_date'] = '2026-08-01';
    $before = serialize($payload);
    $service = new PbrV2CapitalPlanReadinessService();
    $first = $service->assess('capital', 'approved_capital_plan', $payload);
    $service->assess('capital', 'approved_capital_plan', []);
    $second = $service->assess('capital', 'approved_capital_plan', $payload);

    expect($first)->toBe($second)
        ->and(serialize($payload))->toBe($before)
        ->and($first['passed'])->toBeTrue()
        ->and(array_column($first['warnings'], 'code'))->toBe([
            'FUNDING_GAP', 'OPENING_DATE_BEFORE_ASSESSMENT',
        ]);

    foreach ($first['warnings'] as $warning) {
        expect(array_keys($warning))->toBe(['code', 'path', 'message']);
    }
});

test('capital readiness preserves native argument type errors', function () {
    expect(fn () => (new PbrV2CapitalPlanReadinessService())->assess(
        'capital', 'approved_capital_plan', null
    ))->toThrow(TypeError::class);
});

test('capital readiness has no framework database publication or clock dependencies', function () {
    $source = file_get_contents(
        (new ReflectionClass(PbrV2CapitalPlanReadinessService::class))->getFileName()
    );

    foreach ([
        'Illuminate\\', 'App\\Models\\', 'DB::', '->save(', '->publish(',
        '->approve(', 'app(', 'config(', 'now(', "'today'", "'now'",
        'StartupCapitalCalculator', 'CapitalDomainEngine',
    ] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }
});
