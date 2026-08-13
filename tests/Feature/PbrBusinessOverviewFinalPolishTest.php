<?php

test('business overview copy stays operational and avoids duplicate action arrows', function () {
    $view = file_get_contents(resource_path('views/workspaces/show.blade.php'));

    expect($view)
        ->not->toContain('Course progress')
        ->not->toContain("{{ \$action['action_mm'] }} →")
        ->toContain('Business Operating Areas 10 ခု')
        ->toContain("{{ \$action['action_mm'] }}");
});
