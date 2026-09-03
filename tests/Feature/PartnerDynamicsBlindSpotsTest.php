<?php

function pdBlindSpotPartial(): string
{
    return file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );
}

test('blind spots is a section of its own, not an item in the guidance column', function () {
    $partial = pdBlindSpotPartial();

    expect($partial)->toContain('pd-ref-blindspots');
    expect($partial)->toContain('pd-ref-blindspot-list');

    // It used to be a pd-ref-guidance-item titled "Watch-out Area",
    // sharing a narrow third column with three other sections.
    expect($partial)->not->toContain('Watch-out Area');
});

test('the blind spots are shown rather than folded away', function () {
    $partial = pdBlindSpotPartial();

    expect($partial)->not->toContain('See Blind Spots');

    $start = strpos($partial, 'pd-ref-blindspots');
    $end = strpos($partial, 'WORKING WITH YOU');
    $section = substr($partial, $start, $end - $start);

    expect($section)->not->toContain('<details');
});

test('the section heading carries both languages', function () {
    $partial = pdBlindSpotPartial();

    $start = strpos($partial, 'pd-ref-blindspots');
    $section = substr($partial, $start, 600);

    expect($section)->toContain('Blind Spots');
    expect($section)->toContain('မမြင်မိတတ်တဲ့ အားနည်းချက်');
});

test('the content grid is rows of two, not one row of three', function () {
    $partial = pdBlindSpotPartial();
    expect(substr_count($partial, 'class="pd-ref-content-grid'))
        ->toBeGreaterThanOrEqual(2);

    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    expect($css)->not->toContain('1.22fr .95fr .82fr');

    // Match the selector only. Asserting on the punctuation after it
    // breaks whenever the rule is merged with a neighbour.
    expect($css)->toContain('.pd-ref-content-grid-b');
});
