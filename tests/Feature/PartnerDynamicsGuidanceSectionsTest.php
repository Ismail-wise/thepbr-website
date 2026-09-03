<?php

function pdTopPartial(): string
{
    return file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );
}

function pdMatchPartial(): string
{
    return file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/partner-match-folded.blade.php'
        )
    );
}

test('the guidance drawer is gone', function () {
    $partial = pdTopPartial();

    // It held four separate sections of the result as icon items in a
    // narrow third column.
    expect($partial)->not->toContain('pd-ref-guidance-item');
    expect($partial)->not->toContain('pd-ref-guidance-icon');
    expect($partial)->not->toContain('pd-ref-card pd-ref-guidance');
});

test('working with you, under stress and conflict style are sections', function () {
    $partial = pdTopPartial();

    expect(substr_count($partial, 'pd-ref-card pd-ref-section'))->toBe(3);

    foreach ([
        'Working With You',
        'သင်နဲ့ အလုပ်လုပ်သူများ သိထားသင့်တာ',
        'Under Stress',
        'ဖိအားအောက်မှာ ဘယ်လိုပြောင်းလဲတတ်လဲ',
        'Conflict Style',
        'သဘောထားကွဲလွဲတဲ့အခါ',
    ] as $heading) {
        expect($partial)->toContain($heading);
    }
});

test('nothing on the result page is folded away any more', function () {
    $partial = pdTopPartial();

    expect($partial)->not->toContain('<details');
    expect($partial)->not->toContain('View Guidance');

    // The last fold was over the strengths list, hiding part of a section
    // rather than an aside.
    expect($partial)->not->toContain('View all strengths');
    expect($partial)->not->toContain('extraStrengths');
});

test('the best partner principle moved into the partner match section', function () {
    expect(pdTopPartial())->not->toContain('best_partner_principle');

    $match = pdMatchPartial();

    expect($match)->toContain('best_partner_principle');
    expect($match)->toContain('pd-fold-principle');
});

test('the content grid is three rows of two', function () {
    expect(substr_count(pdTopPartial(), 'class="pd-ref-content-grid'))->toBe(3);

    $css = file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );

    expect($css)->toContain('.pd-ref-content-grid-c');

    // The drawer's own rules are gone. The prose comment explaining what
    // used to be there is allowed to mention the old class name.
    $rules = preg_replace('#/\*.*?\*/#s', '', $css);

    expect($rules)->not->toContain('pd-ref-guidance-item');
    expect($rules)->not->toContain('pd-ref-guidance-icon');
});
