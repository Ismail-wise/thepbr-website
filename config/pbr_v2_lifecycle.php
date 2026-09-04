<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBR OS V2 Lifecycle Foundation
    |--------------------------------------------------------------------------
    |
    | This file defines lifecycle vocabulary and transition policy only.
    |
    | It does NOT approve decisions, publish canonical revisions, supersede
    | current canonical state, trigger reviews or apply amendments.
    |
    | Authority ownership:
    |
    | F5 = lifecycle vocabulary / transition policy
    | F6 = approval authority
    | F7 = canonical publication / revision ownership
    | F8 = review / trigger / amendment / audit
    |
    */

    'schema_version' => '1.0',

    /*
    |--------------------------------------------------------------------------
    | Canonical Lifecycle States
    |--------------------------------------------------------------------------
    */

    'canonical_states' => [
        'working_draft',
        'proposed',
        'approved_current',
        'superseded',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supporting Artifact States
    |--------------------------------------------------------------------------
    |
    | Scenario and record artifacts do not enter the canonical lifecycle.
    |
    */

    'artifact_states' => [
        'scenario' => 'scenario',
        'record' => 'recorded',
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Lifecycle States
    |--------------------------------------------------------------------------
    */

    'action_states' => [
        'open',
        'in_progress',
        'blocked',
        'completed',
        'superseded',
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy Decisions
    |--------------------------------------------------------------------------
    */

    'decisions' => [
        'allowed',
        'reserved',
        'forbidden',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved Authorities
    |--------------------------------------------------------------------------
    */

    'authorities' => [
        'none',
        'approval_engine',
        'canonical_output_foundation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical Transition Matrix
    |--------------------------------------------------------------------------
    |
    | F5 may evaluate these transitions but performs no authoritative state
    | mutation. Reserved transitions belong to later foundation phases.
    |
    */

    'canonical_transitions' => [

        'working_draft' => [

            'working_draft' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'same_state_transition',
            ],

            'proposed' => [
                'decision' => 'allowed',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'proposal_submission_allowed',
            ],

            'approved_current' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'approval_bypass_forbidden',
            ],

            'superseded' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'draft_cannot_become_historical_current',
            ],
        ],

        'proposed' => [

            'working_draft' => [
                'decision' => 'allowed',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'proposal_revision_allowed',
            ],

            'proposed' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'same_state_transition',
            ],

            'approved_current' => [
                'decision' => 'reserved',
                'authority' => 'approval_engine',
                'publication_owner' => 'canonical_output_foundation',
                'reason' => 'approval_and_canonical_publication_required',
            ],

            'superseded' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'proposal_has_never_been_current',
            ],
        ],

        'approved_current' => [

            'working_draft' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'new_working_change_must_be_separate_artifact',
            ],

            'proposed' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'amendment_proposal_must_be_separate_artifact',
            ],

            'approved_current' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'same_state_transition',
            ],

            'superseded' => [
                'decision' => 'reserved',
                'authority' => 'canonical_output_foundation',
                'publication_owner' => 'canonical_output_foundation',
                'reason' => 'new_approved_revision_required',
            ],
        ],

        'superseded' => [

            'working_draft' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'historical_revision_is_immutable',
            ],

            'proposed' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'historical_revision_is_immutable',
            ],

            'approved_current' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'historical_revision_is_immutable',
            ],

            'superseded' => [
                'decision' => 'forbidden',
                'authority' => 'none',
                'publication_owner' => null,
                'reason' => 'same_state_transition',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supporting Artifact Rules
    |--------------------------------------------------------------------------
    */

    'artifact_rules' => [

        'scenario' => [
            'state' => 'scenario',
            'canonical' => false,
            'may_inform_proposal' => true,
            'direct_current_publication' => false,
            'cross_domain_write' => false,
        ],

        'record' => [
            'state' => 'recorded',
            'canonical' => false,
            'append_only' => true,
            'terminal' => true,
            'cross_domain_write' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Transition Matrix
    |--------------------------------------------------------------------------
    */

    'action_transitions' => [

        'open' => [
            'in_progress',
            'blocked',
            'completed',
            'superseded',
        ],

        'in_progress' => [
            'blocked',
            'completed',
            'superseded',
        ],

        'blocked' => [
            'in_progress',
            'completed',
            'superseded',
        ],

        'completed' => [],

        'superseded' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit F5 Ownership Boundary
    |--------------------------------------------------------------------------
    */

    'forbidden_service_methods' => [
        'approve',
        'reject',
        'publish',
        'publishCurrent',
        'createCanonicalRevision',
        'supersedeCurrent',
        'triggerReview',
        'applyAmendment',
    ],
];
