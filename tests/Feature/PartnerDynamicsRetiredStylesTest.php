<?php

test('the retired result page styles stay retired', function () {
    // The result page was rebuilt on pd-ref-* classes. The markup for the
    // old design went with commit 6495fcc but its CSS stayed behind — 187
    // selectors, 1,247 lines, on a stylesheet the layout loads for every
    // signed-in page. These are the class names that were removed; if one
    // reappears here, either it is being used again (in which case delete
    // this entry) or the old file has been restored by accident.
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    $retired = [
        'pd-blended-box',
        'pd-blended-box-v2',
        'pd-confidence',
        'pd-dimension-heading',
        'pd-dimension-list',
        'pd-dimension-row',
        'pd-dimension-track',
        'pd-illustration-kicker',
        'pd-illustration-symbol',
        'pd-match-count',
        'pd-note-icon',
        'pd-partner-card-details',
        'pd-partner-card-profile',
        'pd-partner-card-top',
        'pd-partner-discussion',
        'pd-partner-match-badge',
        'pd-partner-match-copy',
        'pd-partner-match-hero',
        'pd-partner-match-note',
        'pd-partner-needs',
        'pd-partner-needs-list',
        'pd-partner-needs-title',
        'pd-partner-rank-number',
        'pd-partner-recommendation-card',
        'pd-partner-recommendation-grid',
        'pd-partner-strengthens',
        'pd-partner-why',
        'pd-primary-score',
        'pd-profile-badge',
        'pd-profile-illustration',
        'pd-profile-illustration-glow',
        'pd-profile-illustration-image',
        'pd-profile-illustration-scene',
        'pd-result-description',
        'pd-result-eyebrow',
        'pd-result-grid',
        'pd-result-hero',
        'pd-result-hero-v2',
        'pd-result-main-v2',
        'pd-result-panel',
        'pd-score-card',
        'pd-score-card-v2',
        'pd-score-divider',
        'pd-score-label',
        'pd-score-profile',
        'pd-score-section',
        'pd-secondary-panel',
        'pd-secondary-profile-pill',
        'pd-secondary-score-section',
        'pd-workspace-entry',
        'secondary-score',
    ];

    foreach ($retired as $class) {
        expect($css)->not->toContain('.' . $class);
    }
});

test('the classes the result page actually uses are untouched', function () {
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    foreach ([
        '.pd-hero',
        '.pd-intro-card',
        '.pd-question-card',
        '.pd-info-card',
        '.pd-disclaimer',
        '.pd-next-stage',
        '.pd-meta-row',
        '.pd-priority-level',
        '.pd-panel-heading',
    ] as $class) {
        expect($css)->toContain($class);
    }
});

test('the stylesheet stays parseable', function () {
    $css = file_get_contents(public_path('css/partner-dynamics.css'));

    $withoutComments = preg_replace('#/\*.*?\*/#s', '', $css);

    expect(substr_count($withoutComments, '{'))
        ->toBe(substr_count($withoutComments, '}'));

    // Removing selectors can leave a rule with no selectors left.
    expect(preg_match('/\{\s*\}/', $withoutComments))->toBe(0);
    expect(preg_match('/@media[^{]*\{\s*\}/', $withoutComments))->toBe(0);
});
