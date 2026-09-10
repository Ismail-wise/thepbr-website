<?php

test('partner contributions uses a route scoped business experience layer', function () {
    $layout = file_get_contents(
        resource_path('views/layouts/student-portal.blade.php')
    );

    $css = file_get_contents(
        public_path('css/pbr-v2-contribution-experience.css')
    );

    expect($layout)
        ->toContain("routeIs('workspaces.tools.v2.contribution.*')")
        ->toContain('pbr-v2-contribution-experience.css');

    expect($css)
        ->toContain('PBR Partner Contributions — Business Experience')
        ->toContain('.c2-page-hero')
        ->toContain('.c2-flow-grid')
        ->toContain('.c2-boundary')
        ->not->toContain('position: fixed');
});

test('partner contributions presents the official seven step workflow', function () {
    $view = file_get_contents(
        resource_path('views/workspaces/v2/contribution.blade.php')
    );

    expect($view)
        ->toContain('Contribution Planning Flow')
        ->toContain('>01</span>')
        ->toContain('>02</span>')
        ->toContain('>03</span>')
        ->toContain('>04</span>')
        ->toContain('>05</span>')
        ->toContain('>06</span>')
        ->toContain('>07</span>')
        ->toContain('Evidence &amp; Conditions')
        ->toContain('Contribution Matrix');
});

test('partner contributions visibly presents all four contribution types', function () {
    $view = file_get_contents(
        resource_path('views/workspaces/v2/contribution.blade.php')
    );

    expect($view)
        ->toContain('Cash')
        ->toContain('Time &amp; Skill')
        ->toContain('Property &amp; Asset')
        ->toContain('IP &amp; Intangible');
});

test('partner contributions uses business facing primary language', function () {
    $view = file_get_contents(
        resource_path('views/workspaces/v2/contribution.blade.php')
    );

    expect($view)
        ->toContain('Contribution Workspace')
        ->toContain('Approved Contributions')
        ->toContain('New Contribution Plan')
        ->toContain('Accepted Value First')
        ->not->toContain('<h2>Approved Current Contribution Register</h2>')
        ->not->toContain('Create Contribution Draft');
});

test('partner approval view avoids exposing a raw technical url field', function () {
    $view = file_get_contents(
        resource_path(
            'views/workspaces/v2/contribution-lifecycle.blade.php'
        )
    );

    expect($view)
        ->toContain('Review Contribution Approval')
        ->toContain('c2-approval-note')
        ->not->toContain(
            '<input type="text" readonly value="{{ route('
        );
});

test('chapter two still refuses ownership and share calculation authority', function () {
    $config = config('pbr_v2_contribution');

    expect($config['equity_handoff']['calculates_shares'])
        ->toBeFalse();

    expect($config['equity_handoff']['writes_ownership'])
        ->toBeFalse();

    expect($config['excluded_business_behaviors'])
        ->toContain('share_value_setting')
        ->toContain('share_allocation')
        ->toContain('ownership_percentage_calculation')
        ->toContain('share_issuance')
        ->toContain('dilution_calculation');
});
