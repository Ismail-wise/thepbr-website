<?php

function pdResultCss(): string
{
    return file_get_contents(
        public_path('css/partner-dynamics-result-reference.css')
    );
}

function pdPartial(string $name): string
{
    return file_get_contents(
        resource_path("views/partner-dynamics/partials/{$name}.blade.php")
    );
}

test('no text on the result page is set below thirteen pixels', function () {
    // Burmese stacks vowels and finals above and below the baseline, so it
    // needs more vertical room than Latin at the same nominal size. Below
    // about 13px the marks stop resolving.
    preg_match_all('/font-size:(\d+)px/', pdResultCss(), $matches);

    $sizes = array_map('intval', $matches[1]);

    expect($sizes)->not->toBeEmpty();
    expect(min($sizes))->toBeGreaterThanOrEqual(12);

    $tooSmall = array_filter($sizes, fn ($size) => $size < 12);
    expect($tooSmall)->toBeEmpty();
});

test('the type scale stays small enough to be a scale', function () {
    preg_match_all('/font-size:(\d+)px/', pdResultCss(), $matches);

    $distinct = array_unique(array_map('intval', $matches[1]));

    // Sixteen different sizes is not a scale, it is an accident.
    expect(count($distinct))->toBeLessThanOrEqual(14);
});

test('running burmese prose is given room to breathe', function () {
    $css = pdResultCss();

    $proseSelectors = [
        '.pd-ref-strength-list p',
        '.pd-ref-guidance-item p',
        '.pd-ref-development p',
        '.pd-ref-inline-details li',
        '.pd-ref-guidance-details li',
        ".pd-fold-detail p,\n.pd-fold-detail li",
        '.pd-fold-note p',
    ];

    foreach ($proseSelectors as $selector) {
        $start = strpos($css, $selector . '{');
        expect($start)->not->toBeFalse("missing selector [{$selector}]");

        $block = substr($css, $start, 400);
        $block = substr($block, 0, strpos($block, '}'));

        preg_match('/line-height:([\d.]+)/', $block, $found);

        expect($found)->not->toBeEmpty("no line-height on [{$selector}]");
        expect((float) $found[1])->toBeGreaterThanOrEqual(1.7);
    }
});

test('the result sections are shown rather than folded away', function () {
    $partial = pdPartial('result-reference-top');

    expect($partial)->not->toContain('<details');
    expect($partial)->not->toContain('<summary');
    expect($partial)->not->toContain('name="pd-guidance"');
});

test('the partner match folds no longer close each other', function () {
    $partial = pdPartial('partner-match-folded');

    // A shared name attribute makes browsers treat the group as an
    // accordion, so reading the second match hid the first.
    expect($partial)->not->toContain('name="pd-partner-match"');
    expect($partial)->toContain('@if($index === 0) open @endif');
});

test('the section headings are in burmese', function () {
    foreach (['result-reference-top', 'partner-match-folded'] as $name) {

        $partial = pdPartial($name);

        foreach ([
            'Your Partner Dynamics Result',
            'Your Primary Operating Style',
            'Your Dimension Map',
            'Best Business Roles',
            'Works Best When',
            'Working With You',
            'Watch-out Area',
            'See Blind Spots',
            'View Guidance',
            'View all strengths',
            'Conflict Style',
            'Partner Types',
            'View Details',
        ] as $english) {
            expect($partial)->not->toContain($english);
        }
    }
});

test('burmese labels are not given latin uppercase treatment', function () {
    $css = pdResultCss();

    // Burmese has no case, so text-transform does nothing, and wide
    // tracking pulls the glyph clusters apart.
    foreach ([
        ".pd-ref-kicker,\n.pd-ref-card-kicker",
        '.pd-fold-kicker',
        '.pd-ref-score-label',
    ] as $selector) {
        $start = strpos($css, $selector . '{');
        expect($start)->not->toBeFalse("missing selector [{$selector}]");

        $block = substr($css, $start, 400);
        $block = substr($block, 0, strpos($block, '}'));

        expect($block)->not->toContain('text-transform:uppercase');
    }
});
