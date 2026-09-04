<?php

function pdProfileCss(): string
{
    return file_get_contents(public_path('css/partner-dynamics.css'));
}

function pdProfileRule(string $selector): string
{
    $css = pdProfileCss();
    $start = strpos($css, "\n" . $selector . '{');

    expect($start)->not->toBeFalse("missing selector [{$selector}]");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the page takes the partner own colour, not the reader own', function () {
    // This page shows one partner's result to the rest of the workspace.
    $view = file_get_contents(
        resource_path('views/workspaces/partner-dynamics-profile.blade.php')
    );

    expect($view)->toContain('pd-themed');
    expect($view)->toContain(
        'PartnerDynamicsTheme::variables($assessment->primary_profile)'
    );
});

test('the name heading now takes the shared leading token', function () {
    // It was written out because the page had no wrapper. It has one now.
    expect(pdProfileRule('.pd-workspace-profile-hero h1'))
        ->toContain('line-height:var(--pd-lh-display)');
});

test('the dimension bar is one colour, not a health gradient', function () {
    // Green to orange reads as a scale from healthy to attention, and the
    // panel above it says in as many words that the score is not good or
    // bad.
    $rule = pdProfileRule('.pd-workspace-dimension-track span');

    expect($rule)->toContain('background:var(--pd-primary)');
    expect($rule)->not->toContain('linear-gradient');
    expect($rule)->not->toContain('var(--orange)');
});

test('the note loses its dashed edge', function () {
    $rule = pdProfileRule('.pd-workspace-profile-note');

    expect($rule)->not->toContain('dashed');
    expect($rule)->toContain('var(--pd-card-radius)');
});

test('rules shared with the alignment page stay off the tokens', function () {
    // .pd-alignment-section and .pd-panel-heading h2 also render on
    // workspaces/partner-dynamics.blade.php, which has no .pd-themed
    // wrapper. A --pd-* token there would not resolve and the browser
    // would drop the declaration, taking the panel's padding with it.
    foreach (['.pd-alignment-section', '.pd-panel-heading h2'] as $selector) {
        expect(pdProfileRule($selector))->not->toContain('var(--pd-');
    }

    // The token versions exist, scoped to where the wrapper does.
    expect(pdProfileCss())->toContain('.pd-themed .pd-alignment-section{');
    expect(pdProfileCss())->toContain('.pd-themed .pd-panel-heading h2{');

    $alignment = file_get_contents(
        resource_path('views/workspaces/partner-dynamics.blade.php')
    );

    expect($alignment)->not->toContain('pd-themed');
});

test('every kicker on the page is eleven pixels and themed', function () {
    foreach ([
        '.pd-workspace-style-grid span',
        '.pd-workspace-profile-note strong',
        '.pd-workspace-profile-badge small',
    ] as $selector) {
        expect(pdProfileRule($selector))->toContain('var(--pd-fs-kicker)');
    }

    // It was 10px and grey.
    expect(pdProfileRule('.pd-workspace-style-grid span'))
        ->toContain('var(--pd-primary)');
});

test('the third person copy is not confused with the reader own', function () {
    // config who_you_are is written as "သင်ဟာ ..." — second person, for
    // your own result. This page describes someone else, so it keeps its
    // own third-person wording rather than reusing that config.
    $view = file_get_contents(
        resource_path('views/workspaces/partner-dynamics-profile.blade.php')
    );

    expect($view)->toContain('$profileDescriptions');
    expect($view)->not->toContain('who_you_are');
});
