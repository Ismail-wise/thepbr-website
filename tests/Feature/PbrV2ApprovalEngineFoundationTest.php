<?php

use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2ComponentRegistryService;
use App\Services\PbrTools\PbrV2LifecycleService;

test('v2 approval engine exposes exact decision vocabulary', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect($approval->decisions())
        ->toBe([
            'approved',
            'rejected',
        ])
        ->and(
            config('pbr_v2_approval.approval_authority')
        )
        ->toBe('approval_engine')
        ->and(
            config('pbr_v2_approval.publication_owner')
        )
        ->toBe('canonical_output_foundation');
});

test('all thirty two canonical components satisfy the approval contract', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    $registry =
        app(PbrV2ComponentRegistryService::class);

    $canonical = collect(
        $registry->all()
    )->filter(
        fn (array $component): bool =>
            ($component['canonical_capability'] ?? 'none')
            !== 'none'
    );

    expect($canonical)->toHaveCount(32);

    foreach ($canonical as $key => $component) {
        $contract =
            $approval->approvalContract(
                $component['domain_key'],
                $key,
                'proposed'
            );

        expect($contract['domain_key'])
            ->toBe($component['domain_key'])
            ->and($contract['component_key'])
            ->toBe($key)
            ->and($contract['approval_required'])
            ->toBeTrue()
            ->and($contract['approval_authority'])
            ->toBe('approval_engine')
            ->and($contract['publication_owner'])
            ->toBe('canonical_output_foundation')
            ->and($contract['write_mode'])
            ->toBe('revisioned_current')
            ->and($contract['stale_source_guard'])
            ->toBeTrue();
    }
});

test('approval creates an authorization decision but never canonical current state', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    $decision = $approval->approve(
        'share_transfer',
        'share_transfer_rule',
        'proposed',
        true,
        true
    );

    expect($decision['decision'])
        ->toBe('approved')
        ->and($decision['decision_authority'])
        ->toBe('approval_engine')
        ->and($decision['publication_authorized'])
        ->toBeTrue()
        ->and($decision['publication_owner'])
        ->toBe('canonical_output_foundation')
        ->and($decision['approved_target_state'])
        ->toBe('approved_current')
        ->and($decision['canonical_published'])
        ->toBeFalse()
        ->and($decision['canonical_revision_created'])
        ->toBeFalse()
        ->and($decision['current_state_changed'])
        ->toBeFalse();
});

test('rejection remains a proposal decision and grants no publication authority', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    $decision = $approval->reject(
        'share_transfer',
        'share_transfer_rule',
        'proposed',
        'Proposal requires revision.'
    );

    expect($decision['decision'])
        ->toBe('rejected')
        ->and($decision['publication_authorized'])
        ->toBeFalse()
        ->and($decision['canonical_published'])
        ->toBeFalse()
        ->and($decision['canonical_revision_created'])
        ->toBeFalse()
        ->and($decision['current_state_changed'])
        ->toBeFalse()
        ->and($decision['proposal_state_after_decision'])
        ->toBe('proposed')
        ->and($decision['revision_path'])
        ->toBe('working_draft');
});

test('approval fails closed when readiness has not passed', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->approve(
            'share_transfer',
            'share_transfer_rule',
            'proposed',
            false,
            true
        )
    )->toThrow(DomainException::class);
});

test('approval fails closed when proposal sources are stale', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->approve(
            'share_transfer',
            'share_transfer_rule',
            'proposed',
            true,
            false
        )
    )->toThrow(DomainException::class);
});

test('approval engine decides only proposed artifacts', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->approve(
            'share_transfer',
            'share_transfer_rule',
            'working_draft',
            true,
            true
        )
    )->toThrow(DomainException::class);

    expect(
        fn () => $approval->reject(
            'share_transfer',
            'share_transfer_rule',
            'approved_current',
            'Invalid state.'
        )
    )->toThrow(DomainException::class);
});

test('stable domain and component identity must match', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->approvalContract(
            'capital',
            'share_transfer_rule',
            'proposed'
        )
    )->toThrow(DomainException::class);
});

test('non canonical components cannot enter approval authority', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    $registry =
        app(PbrV2ComponentRegistryService::class);

    $entry = collect(
        $registry->all()
    )->first(
        fn (array $component): bool =>
            ($component['canonical_capability'] ?? 'none')
            === 'none'
    );

    expect($entry)->toBeArray();

    expect(
        fn () => $approval->approvalContract(
            $entry['domain_key'],
            $entry['component_key'],
            'proposed'
        )
    )->toThrow(DomainException::class);
});

test('unknown components cannot enter approval authority', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->approvalContract(
            'capital',
            'not_a_real_component',
            'proposed'
        )
    )->toThrow(InvalidArgumentException::class);
});

test('rejection requires an explicit reason', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    expect(
        fn () => $approval->reject(
            'share_transfer',
            'share_transfer_rule',
            'proposed',
            '   '
        )
    )->toThrow(InvalidArgumentException::class);
});

test('f6 consumes the frozen reserved approval boundary without taking f7 ownership', function () {
    $lifecycle =
        app(PbrV2LifecycleService::class);

    $decision =
        $lifecycle->transitionDecision(
            'share_transfer_rule',
            'proposed',
            'approved_current'
        );

    expect($decision['decision'])
        ->toBe('reserved')
        ->and($decision['authority'])
        ->toBe('approval_engine')
        ->and($decision['publication_owner'])
        ->toBe('canonical_output_foundation');
});

test('f6 service exposes none of the reserved f7 or f8 authority methods', function () {
    $approval =
        app(PbrV2ApprovalEngineService::class);

    foreach (
        config(
            'pbr_v2_approval.forbidden_service_methods'
        )
        as $method
    ) {
        expect(
            method_exists(
                $approval,
                $method
            )
        )->toBeFalse();
    }
});

test('f6 foundation has no persistence or legacy bundled publication dependency', function () {
    $source = file_get_contents(
        app_path(
            'Services/PbrTools/PbrV2ApprovalEngineService.php'
        )
    );

    expect($source)
        ->not->toContain('DB::')
        ->not->toContain('Schema::')
        ->not->toContain('publishAgreedOutput')
        ->not->toContain('ToolScenarioService')
        ->not->toContain('WorkspaceToolOutput')
        ->not->toContain('WorkspaceOperatingSnapshot')
        ->not->toContain('WorkspaceToolAction')
        ->not->toContain('saveSnapshot(')
        ->not->toContain('saveRecord(');
});
