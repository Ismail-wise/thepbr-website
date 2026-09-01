<?php

use App\Support\PartnerDynamicsDimension;

test('the dimension map shows every dimension the assessment scores', function () {
    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    // The card is titled "Partnership Operating Dimensions" but was capped
    // at the top four, so half the scores never reached the reader and
    // nothing on screen said so.
    expect($partial)->not->toContain('->take(4)');
});

test('the dimension rows stay dense enough for eight of them', function () {
    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    $start = strpos($css, '.pd-ref-dimension-list{');
    expect($start)->not->toBeFalse();

    $block = substr($css, $start, 300);
    $block = substr($block, 0, strpos($block, '}'));

    preg_match('/gap:(\d+)px/', $block, $gap);

    expect($gap)->not->toBeEmpty();
    expect((int) $gap[1])->toBeLessThanOrEqual(16);
});

test('every dimension key has a label to render', function () {
    foreach (PartnerDynamicsDimension::keys() as $key) {
        expect(PartnerDynamicsDimension::label($key))->not->toBeEmpty();
    }

    expect(PartnerDynamicsDimension::keys())->toHaveCount(8);
});
