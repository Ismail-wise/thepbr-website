<?php

return [
    'schema_version' => '1.0',

    'action_states' => [
        'open',
        'in_progress',
        'blocked',
        'completed',
        'superseded',
    ],

    'record_state' => 'recorded',

    'priorities' => [
        'low',
        'normal',
        'high',
        'critical',
    ],

    'producer_types' => [
        'canonical',
        'record',
        'monitor',
    ],

    'source_state_required_on_pin' => 'approved_current',

    'rules' => [
        'non_empty_source_set' => true,
        'same_workspace_required' => true,
        'cross_domain_action_write' => false,
        'source_domain_limited_to_own_or_reads_from' => true,
        'canonical_producer_source_membership_required' => true,
    ],

    'forbidden_service_methods' => [
        'approve',
        'reject',
        'publish',
        'publishCurrent',
        'createCanonicalRevision',
        'supersedeCurrent',
        'resolveReview',
        'requestAmendment',
        'publishAgreedOutput',
        'activateFromApprovedSession',
        'saveRecord',
        'updateAction',
        'updateRecord',
        'deleteAction',
        'deleteRecord',
        'replaceSources',
    ],
];
