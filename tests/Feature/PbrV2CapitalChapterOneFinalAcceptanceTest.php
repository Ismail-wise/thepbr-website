<?php

test(
    'chapter one preserves the source live capital position terminology',
    function () {
        $view = file_get_contents(
            resource_path(
                'views/workspaces/v2/capital.blade.php'
            )
        );

        expect($view)
            ->toContain(
                'LIVE CAPITAL POSITION'
            )
            ->not->toContain(
                'CURRENT CAPITAL POSITION'
            )
            ->toContain(
                'Capital Rule &amp; Allocation'
            )
            ->toContain(
                'Calculate → Compare → Approve → Record → Act'
            )
            ->toContain(
                'workspaces.tools.v2.capital.call-rule.show'
            );
    }
);
