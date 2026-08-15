<?php

test('business os javascript never rewrites server rendered hero navigation', function () {
    $javascript = file_get_contents(
        public_path(
            'js/pbr-operating-system.js'
        )
    );

    expect($javascript)
        ->not->toContain(
            "links[0].textContent = 'Partner များ'"
        )
        ->not->toContain(
            "links[1].textContent = 'PBR AI ✦'"
        )
        ->not->toContain(
            'pbr-dashboard-settings-link'
        )
        ->not->toContain(
            "settingsLink.textContent = 'Settings'"
        );
});

test('business os javascript never converts the active rules metric into partner roster by position', function () {
    $javascript = file_get_contents(
        public_path(
            'js/pbr-operating-system.js'
        )
    );

    expect($javascript)
        ->not->toContain(
            'const partnerMetric = metrics[2]'
        )
        ->not->toContain(
            "mmLabel.textContent = 'Partner Profile အရေအတွက်'"
        )
        ->not->toContain(
            "enLabel.textContent = 'Partner Roster'"
        )
        ->not->toContain(
            'Roster ထဲက လက်ရှိ/စီစဉ်ထားသော Partner Profiles'
        );
});
