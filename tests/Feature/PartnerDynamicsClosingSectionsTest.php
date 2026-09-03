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
    // pd-next-stage appears only on the result page, so its shell stays
    // scoped. pd-disclaimer also appears on the index and the workspace
    // view, and all three wanted the same treatment, so its shell moved
    // to the base rule and the scoped rule keeps only the colour.
    $nextStage = pdSharedRule('.pd-result-section .pd-next-stage');

    expect($nextStage)->toContain('padding:27px');
    expect($nextStage)->toContain('border-radius:19px');
    expect($nextStage)->toContain('box-shadow');

    $disclaimer = pdSharedRule('.pd-disclaimer');

    expect($disclaimer)->toContain('padding:27px');
    expect($disclaimer)->toContain('border-radius:19px');
    expect($disclaimer)->toContain('box-shadow');
});

test('the note loses its dashed border', function () {
    // Nothing else in Partner Dynamics is drawn with a dashed edge.
    expect(pdSharedRule('.pd-disclaimer'))->not->toContain('dashed');
    expect(pdSharedRule('.pd-disclaimer'))->toContain('border:1px solid');
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

test('the result page override carries only what differs', function () {
    // Repeating the shell in the scoped rule would mean two places to
    // edit whenever the card shape changes.
    $rule = pdSharedRule('.pd-result-section .pd-disclaimer');

    expect($rule)->not->toContain('padding:27px');
    expect($rule)->not->toContain('border-radius:19px');
    expect($rule)->toContain('var(--pd-soft)');
});

test('what belongs to the result page alone stays scoped to it', function () {
    // pd-next-stage renders only there, so its shell must not leak into
    // the base rule that other Partner Dynamics views would pick up.
    $rule = pdSharedRule('.pd-next-stage');

    expect($rule)->not->toContain('box-shadow');
    expect($rule)->not->toContain('padding:27px');
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
