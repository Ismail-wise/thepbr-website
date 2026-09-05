<?php

return [

    'schema_version' => '1.0',

    'table' => 'workspace_v2_canonical_revisions',

    'states' => [
        'approved_current',
        'superseded',
    ],

    'stable_identity' => [
        'workspace_id',
        'domain_key',
        'component_key',
    ],

    'revision_scope' => [
        'workspace_id',
        'domain_key',
        'component_key',
    ],

    'proposal_state' => 'proposed',

    'approved_target_state' => 'approved_current',

    'superseded_state' => 'superseded',

    'approval_authority' => 'approval_engine',

    'publication_owner' => 'canonical_output_foundation',

    'canonical_requirements' => [
        'types' => [
            'rule',
            'register',
            'plan',
        ],
        'approval_required' => true,
        'publish_authority' => 'approval_engine_only',
        'write_mode' => 'revisioned_current',
        'stale_source_guard' => true,
    ],

    'required_approval_decision' => [
        'decision' => 'approved',
        'decision_authority' => 'approval_engine',
        'publication_authorized' => true,
        'publication_owner' => 'canonical_output_foundation',
        'canonical_published' => false,
        'canonical_revision_created' => false,
        'current_state_changed' => false,
        'proposal_state' => 'proposed',
        'approved_target_state' => 'approved_current',
        'approval_required' => true,
        'publish_authority_contract' => 'approval_engine_only',
        'write_mode' => 'revisioned_current',
        'stale_source_guard' => true,
        'readiness_passed' => true,
        'sources_fresh' => true,
    ],

    'forbidden_service_methods' => [
        'approve',
        'reject',
        'activateActions',
        'appendOperatingRecord',
        'closeToolSession',
        'triggerReview',
        'applyAmendment',
        'publishAgreedOutput',
        'saveSnapshot',
        'saveRecord',
    ],

];
