<?php

test('six specialized capital tools use shared operating context and actions', function () {
    $controller = file_get_contents(
        app_path('Http/Controllers/WorkspaceChapterOneToolController.php')
    );

    $view = file_get_contents(
        resource_path('views/workspaces/tools/chapter-one.blade.php')
    );

    foreach ([
        'current_capital_position',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ] as $toolKey) {
        expect($controller)->toContain("'{$toolKey}'");
    }

    expect($controller)
        ->toContain('PbrToolOperatingContextService')
        ->toContain('WorkspaceToolAction')
        ->toContain('$this->operatingContext->rules()')
        ->toContain('$this->operatingContext->normalize(')
        ->toContain('$this->operatingContext->toolInput($input)')
        ->toContain("'operatingActions'");

    expect($view)
        ->toContain('workspaces.tools.partials.operating-context')
        ->toContain('workspaces.tools.partials.operating-action-board');
});

test('startup capital planner persists context and exposes approved actions', function () {
    $controller = file_get_contents(
        app_path('Http/Controllers/WorkspaceStartupCapitalController.php')
    );

    $draftController = file_get_contents(
        app_path('Http/Controllers/WorkspaceStartupCapitalDraftController.php')
    );

    $view = file_get_contents(
        resource_path('views/workspaces/tools/startup-capital.blade.php')
    );

    expect($controller)
        ->toContain('PbrToolOperatingContextService')
        ->toContain('WorkspaceToolAction')
        ->toContain('$this->operatingContext->withDefaults(')
        ->toContain('$this->operatingContext->normalize(')
        ->toContain('$this->operatingContext->toolInput($input)')
        ->toContain("'operatingActions'");

    expect($draftController)
        ->toContain('PbrToolOperatingContextService')
        ->toContain('$operatingContext->rules()')
        ->toContain('$operatingContext->normalize(')
        ->toContain('$operatingContext->toolInput($inputData)');

    expect($view)
        ->toContain('workspaces.tools.partials.operating-context')
        ->toContain('workspaces.tools.partials.operating-action-board');
});
