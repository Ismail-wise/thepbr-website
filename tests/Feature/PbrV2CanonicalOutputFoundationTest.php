<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2ComponentRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function f7CanonicalWorkspace(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'F7 Canonical Workspace',
        'business_name' => 'F7 Canonical Workspace',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return [$owner, $workspace];
}

function f7ApprovedDecision(
    string $domainKey = 'share_transfer',
    string $componentKey = 'share_transfer_rule'
): array {
    return app(
        PbrV2ApprovalEngineService::class
    )->approve(
        $domainKey,
        $componentKey,
        'proposed',
        true,
        true
    );
}

test('f7 canonical revision storage exposes the exact required columns', function () {
    expect(
        Schema::hasTable(
            'workspace_v2_canonical_revisions'
        )
    )->toBeTrue();

    foreach ([
        'workspace_id',
        'domain_key',
        'component_key',
        'component_type',
        'revision',
        'state',
        'payload',
        'approval_decision',
        'published_by_user_id',
        'published_at',
        'superseded_at',
    ] as $column) {
        expect(
            Schema::hasColumn(
                'workspace_v2_canonical_revisions',
                $column
            )
        )->toBeTrue();
    }
});

test('f7 exposes only approved current and superseded canonical states', function () {
    expect(
        app(PbrV2CanonicalOutputService::class)
            ->states()
    )->toBe([
        'approved_current',
        'superseded',
    ]);
});

test('all thirty two canonical components satisfy the f7 publication contract', function () {
    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $canonical = collect(
        $registry->all()
    )->filter(
        fn (array $component): bool =>
            ($component['canonical_capability'] ?? 'none')
            !== 'none'
    );

    expect($canonical)->toHaveCount(32);

    foreach ($canonical as $key => $component) {
        $contract = $service->publicationContract(
            $component['domain_key'],
            $key
        );

        expect($contract['domain_key'])
            ->toBe($component['domain_key'])
            ->and($contract['component_key'])
            ->toBe($key)
            ->and($contract['component_type'])
            ->toBe($component['component_type'])
            ->and($contract['publication_owner'])
            ->toBe('canonical_output_foundation')
            ->and($contract['write_mode'])
            ->toBe('revisioned_current');
    }
});

test('first valid f6 approval publishes canonical revision one as current', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $revision = $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'first'],
        f7ApprovedDecision(),
        $owner
    );

    expect($revision->revision)->toBe(1)
        ->and($revision->state)
        ->toBe('approved_current')
        ->and($revision->isCurrent())
        ->toBeTrue()
        ->and($revision->superseded_at)
        ->toBeNull()
        ->and($revision->payload)
        ->toBe(['rule' => 'first'])
        ->and($revision->published_by_user_id)
        ->toBe($owner->id);
});

test('new canonical publication supersedes the prior current revision', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $first = $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'first'],
        f7ApprovedDecision(),
        $owner
    );

    $second = $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'second'],
        f7ApprovedDecision(),
        $owner
    );

    expect($second->revision)->toBe(2)
        ->and($second->state)
        ->toBe('approved_current')
        ->and($first->fresh()->state)
        ->toBe('superseded')
        ->and($first->fresh()->superseded_at)
        ->not->toBeNull();
});

test('canonical history remains revisioned and keeps superseded revisions', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'one'],
        f7ApprovedDecision(),
        $owner
    );

    $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['rule' => 'two'],
        f7ApprovedDecision(),
        $owner
    );

    $history = $service->history(
        $workspace,
        'share_transfer',
        'share_transfer_rule'
    );

    expect($history)->toHaveCount(2)
        ->and($history[0]->revision)->toBe(2)
        ->and($history[0]->state)
        ->toBe('approved_current')
        ->and($history[1]->revision)->toBe(1)
        ->and($history[1]->state)
        ->toBe('superseded');
});

test('current resolves only the approved current revision', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['version' => 1],
        f7ApprovedDecision(),
        $owner
    );

    $second = $service->publish(
        $workspace,
        'share_transfer',
        'share_transfer_rule',
        ['version' => 2],
        f7ApprovedDecision(),
        $owner
    );

    expect(
        $service->current(
            $workspace,
            'share_transfer',
            'share_transfer_rule'
        )?->id
    )->toBe($second->id);
});

test('canonical revision counters are isolated by stable component identity', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    $canonical = collect($registry->all())
        ->filter(
            fn (array $component): bool =>
                ($component['canonical_capability'] ?? 'none')
                !== 'none'
        )
        ->values();

    $first = $canonical[0];
    $second = $canonical[1];

    $one = $service->publish(
        $workspace,
        $first['domain_key'],
        $first['component_key'],
        ['component' => 'one'],
        f7ApprovedDecision(
            $first['domain_key'],
            $first['component_key']
        ),
        $owner
    );

    $two = $service->publish(
        $workspace,
        $second['domain_key'],
        $second['component_key'],
        ['component' => 'two'],
        f7ApprovedDecision(
            $second['domain_key'],
            $second['component_key']
        ),
        $owner
    );

    expect($one->revision)->toBe(1)
        ->and($two->revision)->toBe(1);
});

test('rejected f6 decisions cannot publish canonical state', function () {
    [, $workspace] = f7CanonicalWorkspace();

    $decision = app(
        PbrV2ApprovalEngineService::class
    )->reject(
        'share_transfer',
        'share_transfer_rule',
        'proposed',
        'Requires revision.'
    );

    expect(
        fn () =>
            app(PbrV2CanonicalOutputService::class)
                ->publish(
                    $workspace,
                    'share_transfer',
                    'share_transfer_rule',
                    ['invalid' => true],
                    $decision
                )
    )->toThrow(DomainException::class);

    expect(
        WorkspaceV2CanonicalRevision::query()->count()
    )->toBe(0);
});

test('incomplete or fabricated approval handoff fails closed', function () {
    [, $workspace] = f7CanonicalWorkspace();

    $decision = f7ApprovedDecision();
    $decision['publication_authorized'] = false;

    expect(
        fn () =>
            app(PbrV2CanonicalOutputService::class)
                ->publish(
                    $workspace,
                    'share_transfer',
                    'share_transfer_rule',
                    ['invalid' => true],
                    $decision
                )
    )->toThrow(DomainException::class);

    expect(
        WorkspaceV2CanonicalRevision::query()->count()
    )->toBe(0);
});

test('f6 and f7 stable domain component identity must match', function () {
    [, $workspace] = f7CanonicalWorkspace();

    expect(
        fn () =>
            app(PbrV2CanonicalOutputService::class)
                ->publish(
                    $workspace,
                    'ownership',
                    'share_transfer_rule',
                    ['invalid' => true],
                    f7ApprovedDecision()
                )
    )->toThrow(DomainException::class);
});

test('non canonical components cannot enter f7 publication authority', function () {
    [, $workspace] = f7CanonicalWorkspace();

    $registry = app(
        PbrV2ComponentRegistryService::class
    );

    $nonCanonicalKey = null;
    $nonCanonical = null;

    foreach ($registry->all() as $key => $component) {
        if (
            ($component['canonical_capability'] ?? 'none')
            === 'none'
        ) {
            $nonCanonicalKey = $key;
            $nonCanonical = $component;
            break;
        }
    }

    expect($nonCanonicalKey)->not->toBeNull();

    expect(
        fn () =>
            app(PbrV2CanonicalOutputService::class)
                ->publicationContract(
                    $nonCanonical['domain_key'],
                    $nonCanonicalKey
                )
    )->toThrow(DomainException::class);
});

test('f7 publication leaves exactly one current row per stable identity', function () {
    [$owner, $workspace] = f7CanonicalWorkspace();

    $service = app(
        PbrV2CanonicalOutputService::class
    );

    foreach ([1, 2, 3] as $version) {
        $service->publish(
            $workspace,
            'share_transfer',
            'share_transfer_rule',
            ['version' => $version],
            f7ApprovedDecision(),
            $owner
        );
    }

    expect(
        WorkspaceV2CanonicalRevision::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', 'share_transfer')
            ->where(
                'component_key',
                'share_transfer_rule'
            )
            ->where('state', 'approved_current')
            ->count()
    )->toBe(1)
        ->and(
            WorkspaceV2CanonicalRevision::query()
                ->where('workspace_id', $workspace->id)
                ->where('domain_key', 'share_transfer')
                ->where(
                    'component_key',
                    'share_transfer_rule'
                )
                ->where('state', 'superseded')
                ->count()
        )->toBe(2);
});

test('f7 service exposes none of the reserved f6 or f8 authority methods', function () {
    $reflection = new ReflectionClass(
        PbrV2CanonicalOutputService::class
    );

    foreach (
        config(
            'pbr_v2_canonical_output.forbidden_service_methods',
            []
        )
        as $method
    ) {
        expect(
            $reflection->hasMethod($method)
        )->toBeFalse();
    }
});

test('f7 foundation has no legacy bundled publication side effects or chapter identity', function () {
    $source = file_get_contents(
        base_path(
            'app/Services/PbrTools/'
            .'PbrV2CanonicalOutputService.php'
        )
    );

    expect($source)
        ->not->toContain('publishAgreedOutput')
        ->not->toContain('WorkspaceToolAction')
        ->not->toContain('WorkspaceOperatingRecord')
        ->not->toContain('WorkspaceOperatingSnapshot')
        ->not->toContain('ToolSession')
        ->not->toContain('chapter_number')
        ->not->toContain('chapterNumber')
        ->not->toContain('saveSnapshot')
        ->not->toContain('saveRecord');
});
