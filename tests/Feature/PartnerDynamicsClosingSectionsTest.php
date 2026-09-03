<?php

function pdSharedCss(): string
{
    return file_get_contents(public_path('css/partner-dynamics.css'));
}

function pdSharedRule(string $selector): string
{
    $css = pdSharedCss();
    $start = strpos($css, $selector . '{');

    expect($start)->not->toBeFalse("missing selector [{$selector}]");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the closing sections use the same shell as the cards above them', function () {
    foreach ([
        '.pd-result-section .pd-next-stage',
        '.pd-result-section .pd-disclaimer',
    ] as $selector) {
        $rule = pdSharedRule($selector);

        expect($rule)->toContain('padding:27px');
        expect($rule)->toContain('border-radius:19px');
        expect($rule)->toContain('box-shadow');
    }
});

test('the note loses its dashed border', function () {
    // Nothing else on the result page is drawn with a dashed edge.
    expect(pdSharedRule('.pd-result-section .pd-disclaimer'))
        ->toContain('border:1px solid');
});

test('the closing heading matches the section headings', function () {
    expect(pdSharedRule('.pd-result-section .pd-next-stage h2'))
        ->toContain('font-size:21px');

    $reference = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    $start = strpos($reference, '.pd-ref-card h2{');
    $rule = substr($reference, $start, strpos($reference, '}', $start) - $start);

    expect($rule)->toContain('font-size:21px');
});

test('every change to the shared stylesheet is scoped to the result page', function () {
    // partner-dynamics.css is loaded by the assessment, the workspace and
    // the profile views too. Nothing here may reach them.
    $css = pdSharedCss();

    foreach ([
        '.pd-next-stage',
        '.pd-disclaimer',
    ] as $base) {
        $start = strpos($css, $base . '{');
        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        expect($rule)->not->toContain('box-shadow');
        expect($rule)->not->toContain('padding:27px');
    }
});

test('the priority chips are stacked under their label', function () {
    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    $start = strpos($css, '.pd-fold-priority{');
    $rule = substr($css, $start, strpos($css, '}', $start) - $start);

    // Side by side, the label wrapped mid-phrase and the chips spilled
    // onto a second row with one stranded on the right.
    expect($rule)->toContain('flex-direction:column');
    expect($rule)->not->toContain('justify-content:space-between');
});
