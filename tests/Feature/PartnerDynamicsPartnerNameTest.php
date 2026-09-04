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
    preg_match(
        '/line-height:([\d.]+|var\(--pd-lh-[a-z]+\))/',
        $rule,
        $leading
    );

    expect($size)->not->toBeEmpty();
    expect($leading)->not->toBeEmpty();

    // The leading is a token now, so resolve it from the token sheet.
    $value = $leading[1];

    if (str_starts_with($value, 'var(')) {
        $name = trim($value, 'var()');
        $tokens = file_get_contents(
            public_path('css/partner-dynamics-tokens.css')
        );

        preg_match('/' . preg_quote($name, '/') . ':([\d.]+);/', $tokens, $resolved);

        expect($resolved)->not->toBeEmpty("token {$name} is not defined");

        $value = $resolved[1];
    }

    $largest = (int) $size[1];
    $lineBox = $largest * (float) $value;

    // Burmese with stacked marks occupies about 1.38em.
    expect($lineBox)->toBeGreaterThanOrEqual($largest * 1.38);

    expect($rule)->not->toContain('line-height:1.12');
});

test('the heading has a wrapper that resolves its token', function () {
    // The leading was written out while this page had no .pd-themed
    // section, because a --pd-* token would not have resolved and the
    // browser would have dropped the declaration. The page has one now,
    // so the two must stay together: token here, wrapper there.
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    $start = strpos($css, '.pd-workspace-profile-hero h1{');
    $rule = substr($css, $start, strpos($css, '}', $start) - $start);

    $view = file_get_contents(
        resource_path('views/workspaces/partner-dynamics-profile.blade.php')
    );

    if (str_contains($rule, 'var(--pd-')) {
        expect($view)->toContain('pd-themed');
    }
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
