<?php

return [
    'states' => [
        'working_draft',
        'proposed',
    ],

    'initial_state' =>
        'working_draft',

    'initial_source_generation' =>
        1,

    'required_target_source_policy' =>
        'approved_upstream_only',

    'required_source_state_on_pin' =>
        'approved_current',

    'required_source_state_on_submit' =>
        'approved_current',

    'amendment_required_review_status' =>
        'resolved',

    'amendment_required_resolution' =>
        'amendment_required',

    'consumption_requires_revision_state' =>
        'approved_current',
];
