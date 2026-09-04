<?php

use App\Support\PartnerDynamicsProfile;
use App\Support\PartnerDynamicsTheme;

test('an unassessed reader gets the brand palette', function () {
    // There is no profile yet, so the page stays in thePBR's own green
    // rather than picking an arbitrary one.
    expect(PartnerDynamicsTheme::palette(null))
        ->toBe(PartnerDynamicsTheme::BRAND);

    expect(PartnerDynamicsTheme::palette('nonexistent'))
        ->toBe(PartnerDynamicsTheme::BRAND);

    expect(PartnerDynamicsTheme::isThemed(null))->toBeFalse();
});

test('every profile resolves to its own four colours', function () {
    foreach (PartnerDynamicsProfile::keys() as $key) {

        $palette = PartnerDynamicsTheme::palette($key);

        expect($palette)->toHaveKeys(['primary', 'secondary', 'soft', 'light']);
        expect($palette)->not->toBe(PartnerDynamicsTheme::BRAND);
        expect(PartnerDynamicsTheme::isThemed($key))->toBeTrue();

        foreach ($palette as $colour) {
            expect($colour)->toMatch('/^#[0-9A-Fa-f]{6}$/');
        }
    }
});

test('the variables render as a style attribute', function () {
    $style = PartnerDynamicsTheme::variables('builder');

    foreach (['--pd-primary:', '--pd-secondary:', '--pd-soft:', '--pd-light:'] as $property) {
        expect($style)->toContain($property);
    }

    // Only the result page needs the secondary profile's colour.
    expect($style)->not->toContain('--pd-secondary-profile');

    expect(PartnerDynamicsTheme::variables('builder', 'analyst'))
        ->toContain('--pd-secondary-profile:');
});

test('the theme never reaches the layout or the business os', function () {
    // Green, orange and red already mean healthy, attention and danger
    // there. A Visionary's red must not turn the dashboard into a warning.
    $layout = file_get_contents(
        resource_path('views/layouts/student-portal.blade.php')
    );

    expect($layout)->not->toContain('PartnerDynamicsTheme');
    expect($layout)->not->toContain('pd-themed');

    $tokens = file_get_contents(
        public_path('css/partner-dynamics-tokens.css')
    );

    // Every rule in the token sheet is scoped. Comments are stripped
    // first, then each selector is the text between one closing brace and
    // the next opening one.
    $rules = preg_replace('#/\*.*?\*/#s', '', $tokens);

    $selectors = [];

    foreach (explode('}', $rules) as $chunk) {
        if (! str_contains($chunk, '{')) {
            continue;
        }

        $selectors[] = trim(substr($chunk, 0, strpos($chunk, '{')));
    }

    expect($selectors)->not->toBeEmpty();

    foreach ($selectors as $selector) {
        expect($selector)->toStartWith('.pd-themed');
    }
});

test('the index page is themed by the reader own result', function () {
    $index = file_get_contents(
        resource_path('views/partner-dynamics/index.blade.php')
    );

    expect($index)->toContain('pd-themed');
    expect($index)->toContain('PartnerDynamicsTheme::variables');

    // Only a finished assessment supplies a profile.
    expect($index)->toContain('isCompleted()');
});

test('the shared card and marker exist for the other pages to adopt', function () {
    $tokens = file_get_contents(
        public_path('css/partner-dynamics-tokens.css')
    );

    foreach ([
        '--pd-card-radius:19px',
        '--pd-card-padding:27px',
        '--pd-fs-h2:21px',
        '--pd-fs-body:14px',
        '--pd-lh-prose:1.85',
        '--pd-marker:34px',
        '.pd-themed .pd-card{',
        '.pd-themed .pd-marker{',
    ] as $token) {
        expect($tokens)->toContain($token);
    }
});
