<?php

function pdIndexCss(): string
{
    return file_get_contents(public_path('css/partner-dynamics.css'));
}

function pdIndexRule(string $selector): string
{
    $css = pdIndexCss();
    $start = strpos($css, $selector . '{');

    expect($start)->not->toBeFalse("missing selector [{$selector}]");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the hero headline gives burmese room between lines', function () {
    // Burmese carries stacked vowels and medials well above and below the
    // baseline. On a 1.16 line box at 68px the marks of one line reached
    // into the line beneath it.
    $rule = pdIndexRule(".pd-hero-copy h1,\n.pd-result-main h1");

    preg_match('/line-height:([\d.]+)/', $rule, $leading);

    expect($leading)->not->toBeEmpty();
    expect((float) $leading[1])->toBeGreaterThanOrEqual(1.35);

    expect($rule)->not->toContain('clamp(38px,5.5vw,68px)');
});

test('the info card numbers are circles like the rest of partner dynamics', function () {
    $rule = pdIndexRule('.pd-info-card > span');

    expect($rule)->toContain('border-radius:50%');
    expect($rule)->toContain('width:34px');
});

test('the note is no longer drawn with a dashed edge', function () {
    // Nothing else on these pages uses one.
    $rule = pdIndexRule('.pd-disclaimer');

    expect($rule)->not->toContain('dashed');
    expect($rule)->toContain('border-radius:19px');
    expect($rule)->toContain('padding:27px');
});

test('the result page override carries only what differs from the base', function () {
    // The base now supplies the shell, so repeating it in the scoped rule
    // would mean two places to edit.
    $rule = pdIndexRule('.pd-result-section .pd-disclaimer');

    expect($rule)->not->toContain('padding:27px');
    expect($rule)->not->toContain('border-radius:19px');
    expect($rule)->toContain('var(--pd-soft)');
});

test('the note text is readable in burmese', function () {
    $rule = pdIndexRule('.pd-disclaimer p');

    expect($rule)->toContain('font-size:14px');

    preg_match('/line-height:([\d.]+)/', $rule, $leading);

    expect($leading)->not->toBeEmpty();
    expect((float) $leading[1])->toBeGreaterThanOrEqual(1.7);
});
