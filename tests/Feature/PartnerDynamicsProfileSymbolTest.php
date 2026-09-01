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
