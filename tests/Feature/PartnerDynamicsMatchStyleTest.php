<?php

function pdMatchCss(): string
{
    return file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );
}

function pdRule(string $selector): string
{
    $css = pdMatchCss();
    $start = strpos($css, $selector . '{');

    expect($start)->not->toBeFalse("missing selector [{$selector}]");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the partner match heading is a section heading, not a display headline', function () {
    // It was clamp(28px,3.3vw,46px) — more than twice the 21px used by
    // every other section heading, and at that size the Burmese wrapped
    // mid-word with the vowel marks colliding with the line beneath.
    $rule = pdRule('.pd-fold-match-header h2');

    expect($rule)->not->toContain('clamp(');
    expect($rule)->toContain('font-size:21px');

    expect(pdRule('.pd-ref-card h2'))->toContain('font-size:21px');
});

test('the partner match panel uses the same shell as the cards above it', function () {
    $panel = pdRule('.pd-fold-match');
    $card = pdRule('.pd-ref-card');

    foreach (['padding:27px', 'border-radius:19px', 'border:1px solid #e7e9ec'] as $property) {
        expect($panel)->toContain($property);
        expect($card)->toContain($property);
    }
});

test('the rank badge is a circle like every other marker on the page', function () {
    $rule = pdRule('.pd-fold-rank');

    expect($rule)->toContain('border-radius:50%');
    expect($rule)->toContain('width:34px');
});

test('no text in the partner match section is smaller than eleven pixels', function () {
    $css = pdMatchCss();

    preg_match_all(
        '/(\.pd-fold[^{}]*)\{([^}]*)\}/',
        preg_replace('#/\*.*?\*/#s', '', $css),
        $matches,
        PREG_SET_ORDER
    );

    $sizes = [];

    foreach ($matches as $match) {
        if (preg_match('/font-size:(\d+)px/', $match[2], $found)) {
            $sizes[trim($match[1])] = (int) $found[1];
        }
    }

    expect($sizes)->not->toBeEmpty();

    // The section previously ran down to 8px.
    foreach ($sizes as $selector => $size) {
        expect($size)->toBeGreaterThanOrEqual(11, "[{$selector}] is {$size}px");
    }
});
