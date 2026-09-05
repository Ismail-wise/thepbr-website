<?php

return [

    'schema_version' => '1.0',

    'case_statuses' => [
        'open',
        'resolved',
    ],

    'resolutions' => [
        'no_change',
        'amendment_required',
        'dismissed',
    ],

    'direct_resolutions' => [
        'no_change',
        'dismissed',
    ],

    'trigger_types' => [
        'dependency_change',
        'scheduled_review',
        'manual_review',
        'producer_signal',
    ],

    'audit_event_types' => [
        'review_opened',
        'review_resolved',
        'amendment_requested',
    ],

    'target_state_required_on_open' => 'approved_current',

    'amendment_handoff' => [
        'working_draft_required' => true,
        'working_draft_created' => false,
        'proposal_created' => false,
        'approval_decision_created' => false,
        'canonical_revision_created' => false,
        'publication_performed' => false,
        'current_state_changed' => false,
        'required_next_artifact_state' => 'working_draft',
    ],

    'forbidden_service_methods' => [
        'approve',
        'reject',
        'publish',
        'publishCurrent',
        'createCanonicalRevision',
        'supersedeCurrent',
        'activateActions',
        'appendOperatingRecord',
        'saveCanonicalSnapshot',
        'applyAmendment',
    ],

];
