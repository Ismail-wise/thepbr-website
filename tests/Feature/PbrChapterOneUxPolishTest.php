<?php

test('chapter one calculators explain the working rhythm and announce results', function () {
    $page = file_get_contents(
        resource_path('views/workspaces/tools/chapter-one.blade.php')
    );

    expect($page)
        ->toContain('id="capital-calculator-workspace"')
        ->toContain('pbr-calculator-action-guide')
        ->toContain('aria-describedby="capital-action-guide"')
        ->toContain('aria-live="polite"')
        ->toContain('Result စစ်ရန်');
});

test('chapter one dynamic builders expose guidance live totals and accessible controls', function () {
    $chapterOne = resource_path('views/workspaces/tools/chapter-one');

    $contingency = file_get_contents(
        $chapterOne.'/contingency-fund-calculator.blade.php'
    );

    $partners = file_get_contents(
        $chapterOne.'/partner-contribution-matrix.blade.php'
    );

    $allocations = file_get_contents(
        $chapterOne.'/capital-allocation-chart.blade.php'
    );

    $results = file_get_contents($chapterOne.'/results.blade.php');

    expect($contingency)
        ->toContain('data-contingency-method-help')
        ->toContain('data-contingency-percentage')
        ->toContain('data-contingency-months')
        ->and($partners)
        ->toContain('data-partner-builder-summary')
        ->toContain('data-partner-grand-total')
        ->toContain('Contribution Share ≠ Ownership Share')
        ->and($allocations)
        ->toContain('data-allocation-builder-summary')
        ->toContain('data-add-allocation-preset')
        ->toContain('aria-pressed="false"')
        ->and($results)
        ->toContain('data-result-interpretation="contingency-fund"')
        ->toContain('data-result-interpretation="partner-contribution"')
        ->toContain('data-result-interpretation="funding-gap"')
        ->toContain('data-result-interpretation="capital-allocation"');
});

test('chapter one polish keeps calculator and startup capital text readable', function () {
    $calculatorCss = file_get_contents(
        public_path('css/pbr-capital-command-center.css')
    );

    $startupCss = file_get_contents(
        public_path('css/pbr-startup-capital.css')
    );

    expect($calculatorCss)
        ->toContain('Phase 3 - Chapter 1 calculator clarity and accessibility')
        ->toContain('.pbr-calculator-action-guide')
        ->toContain('.pbr-builder-live-summary')
        ->and($startupCss)
        ->toContain('Chapter 1 readability pass')
        ->toContain('.pbr-capital-plan-lead{font-size:14px')
        ->toContain('.pbr-capital-field label{font-size:10.5px');
});
