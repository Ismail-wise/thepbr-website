<?php

test('every profile key renders a symbol', function () {
    foreach (array_keys(config('partner_dynamics.profiles')) as $key) {

        $svg = view('partner-dynamics.partials.profile-symbol', [
            'symbol' => $key,
        ])->render();

        expect($svg)->toContain('<svg');
        expect($svg)->toContain('</svg>');
        expect($svg)->toContain('stroke="currentColor"');
        expect($svg)->toContain('viewBox="0 0 64 64"');
    }
});

test('each profile gets a visually distinct symbol', function () {
    $rendered = [];

    foreach (array_keys(config('partner_dynamics.profiles')) as $key) {
        $rendered[] = view('partner-dynamics.partials.profile-symbol', [
            'symbol' => $key,
        ])->render();
    }

    expect($rendered)->toHaveCount(8);
    expect(array_unique($rendered))->toHaveCount(8);
});

test('an unknown or missing profile key falls back to a generic mark', function () {
    foreach (['nonexistent', null] as $key) {

        $svg = view('partner-dynamics.partials.profile-symbol', [
            'symbol' => $key,
        ])->render();

        expect($svg)->toContain('<svg');
        expect($svg)->toContain('</svg>');
    }
});

test('the symbol size is overridable', function () {
    $svg = view('partner-dynamics.partials.profile-symbol', [
        'symbol' => 'guardian',
        'size' => 64,
    ])->render();

    expect($svg)->toContain('width="64"');
    expect($svg)->toContain('height="64"');
});

test('the score card no longer uses placeholder glyphs', function () {
    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    expect($partial)->toContain('partials.profile-symbol');
    expect(substr_count($partial, '◇'))->toBe(0);
});

test('both scores are rendered at the same size', function () {
    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    // A size step between primary and secondary reads as a much larger gap
    // than the numbers usually describe, so the two must stay matched.
    expect($css)->toContain(
        ".pd-ref-score-line strong,\n.pd-ref-score-line strong.secondary{"
    );

    expect($css)->not->toContain('font-size:55px');
    expect($css)->not->toContain('font-size:43px');
});

test('the secondary mark is softened rather than ringed', function () {
    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    expect($css)->toContain('.pd-ref-score-icon.secondary .pd-ref-symbol');
    expect($css)->not->toContain('--pd-secondary-profile-color');

    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    expect($partial)->not->toContain('--pd-secondary-profile-color');
});

test('the score card names each profile in burmese as well as english', function () {
    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    expect(substr_count($partial, 'pd-ref-score-mm'))->toBe(2);
    expect($partial)->toContain("\$primary['title_mm']");
    expect($partial)->toContain("\$secondary['title_mm']");
});
