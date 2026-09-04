<?php

test('the partner name heading has room for a burmese name', function () {
    // The heading is $assessment->user->name — a person's name from the
    // database, not static copy. It can be Burmese and it can be long.
    //
    // 58px on a 1.12 line box gives a 65px line, where a Burmese name
    // needs roughly 80px at that size. A name that wrapped had its marks
    // running into the row beneath.
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    $start = strpos($css, '.pd-workspace-profile-hero h1{');

    expect($start)->not->toBeFalse();

    $rule = substr($css, $start, strpos($css, '}', $start) - $start);

    preg_match('/font-size:clamp\([^,]+,[^,]+,(\d+)px\)/', $rule, $size);
    preg_match('/line-height:([\d.]+)/', $rule, $leading);

    expect($size)->not->toBeEmpty();
    expect($leading)->not->toBeEmpty();

    $largest = (int) $size[1];
    $lineBox = $largest * (float) $leading[1];

    // Burmese with stacked marks occupies about 1.38em.
    expect($lineBox)->toBeGreaterThanOrEqual($largest * 1.38);

    expect($rule)->not->toContain('line-height:1.12');
});

test('the heading resolves without a themed wrapper', function () {
    // This page has no .pd-themed section yet, so a --pd-* token here
    // would not resolve and the browser would drop the declaration.
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    $start = strpos($css, '.pd-workspace-profile-hero h1{');
    $rule = substr($css, $start, strpos($css, '}', $start) - $start);

    expect($rule)->not->toContain('var(--pd-');

    $view = file_get_contents(
        resource_path('views/workspaces/partner-dynamics-profile.blade.php')
    );

    expect($view)->not->toContain('pd-themed');
});

test('no partner dynamics heading is left on latin leading', function () {
    // Every heading that carries Burmese and can wrap.
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    foreach ([
        '.pd-hero-copy h1,' . "\n" . '.pd-result-main h1',
        '.pd-assessment-header h1',
        '.pd-workspace-profile-hero h1',
    ] as $selector) {
        $start = strpos($css, $selector . '{');

        expect($start)->not->toBeFalse("missing selector [{$selector}]");

        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        preg_match('/line-height:([\d.]+|var\(--pd-lh-[a-z]+\))/', $rule, $found);

        expect($found)->not->toBeEmpty("no line-height on [{$selector}]");

        if (str_starts_with($found[1], 'var(')) {
            continue;
        }

        expect((float) $found[1])->toBeGreaterThanOrEqual(1.38);
    }
});
