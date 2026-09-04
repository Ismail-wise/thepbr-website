<?php

function pdAssessCss(): string
{
    return file_get_contents(public_path('css/partner-dynamics.css'));
}

function pdAssessRule(string $selector): string
{
    $css = pdAssessCss();
    $start = strpos($css, "\n" . $selector . '{');

    expect($start)->not->toBeFalse("missing selector [{$selector}]");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the assessment is drawn with the brand palette, never a profile', function () {
    // The controller sends a completed assessment straight to the result
    // page, so this view is only ever reached before a profile exists.
    $view = file_get_contents(
        resource_path('views/partner-dynamics/assessment.blade.php')
    );

    expect($view)->toContain('pd-themed');
    expect($view)->toContain('PartnerDynamicsTheme::variables(null)');
    expect($view)->not->toContain('primary_profile');
});

test('the assessment heading has room for burmese between its lines', function () {
    // clamp(29px,4vw,43px) on 1.28 is the setting the index hero carried
    // before it was fixed, and this heading wraps on narrow screens too.
    $rule = pdAssessRule('.pd-assessment-header h1');

    expect($rule)->not->toContain('clamp(29px,4vw,43px)');
    expect($rule)->toContain('var(--pd-lh-heading)');
});

test('every marker on the assessment is a themed circle', function () {
    foreach (['.pd-radio-ui > b', '.pd-scenario-ui b'] as $selector) {
        $rule = pdAssessRule($selector);

        expect($rule)->toContain('var(--pd-marker-sm)');
        expect($rule)->toContain('border-radius:50%');
        expect($rule)->toContain('var(--pd-primary)');
        expect($rule)->not->toContain('30px');
    }
});

test('the assessment chrome follows the theme rather than the brand', function () {
    foreach ([
        '.pd-step-number',
        '.pd-progress-track span',
        '.pd-question-number',
    ] as $selector) {
        expect(pdAssessRule($selector))->not->toContain('var(--forest)');
    }

    expect(pdAssessRule('.pd-progress-track span'))
        ->toContain('background:var(--pd-primary)');
});

test('the selected answer is shown in the theme colour', function () {
    foreach ([
        '.pd-scale-option input:checked + .pd-radio-ui',
        '.pd-scenario-option input:checked + .pd-scenario-ui',
    ] as $selector) {
        $rule = pdAssessRule($selector);

        expect($rule)->toContain('var(--pd-primary)');
        expect($rule)->toContain('var(--pd-soft)');
        expect($rule)->not->toContain('#f1faed');
    }
});

test('no text on the assessment falls below eleven pixels', function () {
    // The scale captions were 10px and the option text 12px. Burmese
    // needs 13px; the English captions beneath it can sit at 11px.
    foreach ([
        '.pd-radio-ui > span' => 'var(--pd-fs-meta)',
        '.pd-scenario-ui span' => 'var(--pd-fs-body)',
        '.pd-question-error' => 'var(--pd-fs-meta)',
        '.pd-save-message' => 'var(--pd-fs-meta)',
    ] as $selector => $token) {
        expect(pdAssessRule($selector))->toContain($token);
    }

    expect(pdAssessRule('.pd-radio-ui small'))->not->toContain('10px');
});

test('the primary button follows the theme on every partner dynamics page', function () {
    // It was themed on the result page only.
    expect(pdAssessCss())->toContain('.pd-themed .pd-primary-button');
});
