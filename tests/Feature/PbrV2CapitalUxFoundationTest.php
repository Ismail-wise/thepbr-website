<?php

test(
    'v2 capital uses a route-scoped experience layer instead of the shared sticky result rail',
    function () {
        $layout = file_get_contents(
            resource_path('views/layouts/student-portal.blade.php')
        );

        $capital = file_get_contents(
            resource_path('views/workspaces/v2/capital.blade.php')
        );

        $css = file_get_contents(
            public_path('css/pbr-v2-capital-experience.css')
        );

        expect($layout)
            ->toContain(
                "request()->routeIs('workspaces.tools.v2.capital.*')"
            )
            ->toContain(
                "asset('css/pbr-v2-capital-experience.css')"
            );

        expect($capital)
            ->toContain('pbr-v2-capital-experience')
            ->toContain('data-pbr-capital-experience');

        expect($css)
            ->toContain(
                '.pbr-v2-capital-experience .pbr-os-main:has(.pbr-os-result-panel)'
            )
            ->toContain(
                'grid-template-columns: minmax(0, 1fr) !important;'
            )
            ->toContain('position: static !important;')
            ->toContain('grid-row: auto !important;')
            ->toContain('overflow-x: auto !important;');
    }
);

test(
    'v2 capital experience remains presentation-only and does not redefine business authority',
    function () {
        $css = file_get_contents(
            public_path('css/pbr-v2-capital-experience.css')
        );

        expect($css)
            ->not->toContain('total_required =')
            ->not->toContain('working_capital_total =')
            ->not->toContain('funding_gap =')
            ->not->toContain('fetch(')
            ->not->toContain('XMLHttpRequest')
            ->not->toContain('localStorage')
            ->not->toContain('sessionStorage');
    }
);

test(
    'capital workspace navigation opens the real editor and exposes new plan access at the top',
    function () {
        $capital = file_get_contents(
            resource_path('views/workspaces/v2/capital.blade.php')
        );

        $css = file_get_contents(
            public_path('css/pbr-v2-capital-experience.css')
        );

        expect($capital)
            ->toContain(
                "'premiumToolWorkspaceTarget' => 'v2-capital-plan-editor'"
            )
            ->toContain('id="v2-capital-plan-editor"')
            ->toContain('pbr-v2-capital-actions')
            ->toContain('New Plan')
            ->not->toContain(
                "'premiumToolWorkspaceTarget' => 'v2-capital-workspace'"
            );

        expect($css)
            ->toContain('.pbr-v2-capital-actions')
            ->toContain('#v2-capital-plan-editor:target')
            ->toContain('scroll-margin-top: 152px');
    }
);

test(
    'v2 capital main surface uses business language instead of chapter and implementation terminology',
    function () {
        $capital = file_get_contents(
            resource_path('views/workspaces/v2/capital.blade.php')
        );

        expect($capital)
            ->toContain('Capital Planning')
            ->toContain('Approved Plan')
            ->toContain('Business Tools')
            ->toContain('PLANNING FLOW')
            ->toContain('Capital Planning Flow')
            ->toContain('BUSINESS CAPITAL PLAN')
            ->toContain('New Capital Plan')
            ->toContain('Plan Currency')
            ->toContain('Create Draft Plan')
            ->not->toContain('Capital Plan Authority')
            ->not->toContain('Approved Result')
            ->not->toContain('Tool Library')
            ->not->toContain('CHAPTER 1 WORKFLOW')
            ->not->toContain('V2 CANONICAL PLAN')
            ->not->toContain('V2 Capital Plan အသစ်')
            ->not->toContain('Canonical Currency')
            ->not->toContain('Create V2 Draft');
    }
);

test(
    'v2 capital approved state uses business wording and readable workspace context',
    function () {
        $capital = file_get_contents(
            resource_path('views/workspaces/v2/capital.blade.php')
        );

        $bar = file_get_contents(
            resource_path(
                'views/workspaces/tools/partials/premium-tool-command-bar.blade.php'
            )
        );

        $css = file_get_contents(
            public_path('css/pbr-v2-capital-experience.css')
        );

        expect($capital)
            ->toContain(
                "'premiumToolActiveLabel' => 'Approved Plan'"
            )
            ->toContain('CAPITAL &amp; FUNDING')
            ->toContain('CURRENT APPROVED PLAN')
            ->toContain('formal approval workflow')
            ->not->toContain('APPROVED CURRENT')
            ->not->toContain('V2 canonical workflow')
            ->not->toContain(
                '<span class="pbr-os-chapter-pill">'."\n".
                '                        CHAPTER 1'
            );

        expect($bar)
            ->toContain('$premiumToolActiveLabel')
            ->toContain(
                "(\$premiumToolActiveLabel ?? 'Active Rule')"
            );

        expect($css)
            ->toContain(
                '.pbr-v2-capital-experience .pbr-os-business-context'
            )
            ->toContain(
                'background: linear-gradient(145deg, #f8fbf9, #eef7f2) !important;'
            )
            ->toContain(
                'color: #173d2b !important;'
            );
    }
);

test(
    'v2 capital final display polish hides implementation details without changing authority',
    function () {
        $capital = file_get_contents(
            resource_path('views/workspaces/v2/capital.blade.php')
        );

        expect($capital)
            ->toContain('Plan Protection')
            ->toContain('final business record')
            ->toContain('formatCapitalTimestamps')
            ->toContain("timeZone: 'UTC'")
            ->toContain("->replace('_', ' ')->title()")
            ->toContain('approved action workflow')
            ->not->toContain('capital / approved_capital_plan')
            ->not->toContain('Execution mutation ကို')
            ->not->toMatch('/·\s*ID\s*\{\{/');
    }
);
