<?php

use App\Services\PartnerDynamics\PartnerDynamicsScoringService;
use App\Support\PartnerDynamicsDimension;

test('there are exactly eight dimensions', function () {
    expect(PartnerDynamicsDimension::keys())->toHaveCount(8);
});

test('the scoring engine and the label config agree on the keys', function () {
    // The engine keeps its own key list so that scoring never depends on
    // presentation config. That makes drift possible, so it is asserted
    // here rather than assumed.
    $constant = new ReflectionClassConstant(
        PartnerDynamicsScoringService::class,
        'DIMENSIONS'
    );

    expect($constant->getValue())->toBe(PartnerDynamicsDimension::keys());
});

test('every dimension has an english and a burmese label', function () {
    foreach (PartnerDynamicsDimension::keys() as $key) {
        expect(PartnerDynamicsDimension::label($key))->not->toBeEmpty();
        expect(PartnerDynamicsDimension::labelMm($key))->not->toBeEmpty();
    }
});

test('labels and labelsMm return one entry per key, in order', function () {
    $keys = PartnerDynamicsDimension::keys();

    expect(array_keys(PartnerDynamicsDimension::labels()))->toBe($keys);
    expect(array_keys(PartnerDynamicsDimension::labelsMm()))->toBe($keys);

    expect(PartnerDynamicsDimension::labels()['vision'])
        ->toBe('Vision & Direction');
});

test('the burmese label keeps the english term alongside it', function () {
    // These terms appear in English elsewhere in the product, so the
    // Burmese label carries both rather than replacing one with the other.
    foreach (PartnerDynamicsDimension::keys() as $key) {
        expect(PartnerDynamicsDimension::labelMm($key))
            ->toContain(PartnerDynamicsDimension::label($key));
    }
});

test('an unknown or empty dimension key degrades safely', function () {
    expect(PartnerDynamicsDimension::label('nonexistent'))->toBe('Nonexistent');
    expect(PartnerDynamicsDimension::labelMm('nonexistent'))->toBe('Nonexistent');
    expect(PartnerDynamicsDimension::label(null))->toBe('');
    expect(PartnerDynamicsDimension::labelMm(null))->toBe('');
});

test('no view or service defines its own dimension labels', function () {
    $paths = [
        app_path('Services/PartnerDynamics/PartnerDynamicsAlignmentService.php'),
        app_path('Services/PartnerDynamics/PartnerMatchRecommendationService.php'),
        resource_path('views/partner-dynamics/result.blade.php'),
        resource_path('views/workspaces/partner-dynamics-profile.blade.php'),
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)->not->toContain("'Vision & Direction'");
        expect($contents)->not->toContain('အနာဂတ်ဦးတည်ချက်');
    }
});
