<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBR OS V2 Approval Engine Foundation
    |--------------------------------------------------------------------------
    |
    | F6 owns approval and rejection decisions only.
    |
    | F6 does NOT publish canonical current state, allocate canonical
    | revisions, supersede current state, activate operating actions,
    | append publication-side-effect records, trigger review or apply
    | amendments.
    |
    | F7 owns canonical publication / revision / supersession.
    | F8 owns review / trigger / amendment / audit orchestration.
    |
    */

    'schema_version' => '1.0',

    'proposal_state' => 'proposed',

    'decisions' => [
        'approved',
        'rejected',
    ],

    'approval_authority' =>
        'approval_engine',

    'publication_owner' =>
        'canonical_output_foundation',

    'approved_target_state' =>
        'approved_current',

    'canonical_requirements' => [
        'approval_required' => true,
        'publish_authority' =>
            'approval_engine_only',
        'write_mode' =>
            'revisioned_current',
        'stale_source_guard' => true,
    ],

    'approved_decision' => [
        'publication_authorized' => true,
        'canonical_published' => false,
        'canonical_revision_created' => false,
        'current_state_changed' => false,
    ],

    'rejected_decision' => [
        'publication_authorized' => false,
        'canonical_published' => false,
        'canonical_revision_created' => false,
        'current_state_changed' => false,
        'proposal_state_after_decision' =>
            'proposed',
        'revision_path' =>
            'working_draft',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit F6 Ownership Boundary
    |--------------------------------------------------------------------------
    */

    'forbidden_service_methods' => [
        'publish',
        'publishCurrent',
        'createCanonicalRevision',
        'allocateCanonicalRevision',
        'supersedeCurrent',
        'saveCanonicalSnapshot',
        'activateActions',
        'appendOperatingRecord',
        'triggerReview',
        'applyAmendment',
    ],
];
