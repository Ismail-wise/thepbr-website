<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Services\PbrTools\PbrCanonicalDataService;
use App\Services\PbrTools\PbrOperatingSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Guards the batched snapshot read path (latestSnapshots) introduced to remove
 * the per-domain N+1 in PbrCanonicalDataService::approvedDomainSummaries().
 *
 * The batched query MUST resolve exactly the same snapshot per domain as the
 * single-domain latestSnapshot() call it replaced. In particular a newer DRAFT
 * revision must never displace the latest AGREED revision, because partner
 * visibility and PBR AI context both read this path.
 */
function batchedFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Batched Read Business',
        'business_name' => 'Batched Read Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return [$owner, $workspace];
}

function makeSnapshot(
    PartnershipWorkspace $workspace,
    string $domainKey,
    int $revision,
    string $status,
    array $summary
): WorkspaceOperatingSnapshot {
    return WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => $domainKey,
        'revision' => $revision,
        'status' => $status,
        'schema_version' => 'v1',
        'payload' => ['revision' => $revision],
        'summary' => $summary,
    ]);
}

it('resolves the same snapshot per domain as the single-domain read path', function (): void {
    [, $workspace] = batchedFixture();
    $os = app(PbrOperatingSystemService::class);

    $domains = array_keys(config('pbr_canonical_data.domains', []));
    expect($domains)->not->toBeEmpty();

    // Build a messy but realistic history across several domains.
    foreach (array_slice($domains, 0, 4) as $i => $domainKey) {
        makeSnapshot($workspace, $domainKey, 1, 'agreed', ['v' => 1]);
        makeSnapshot($workspace, $domainKey, 2, 'agreed', ['v' => 2]);
        // A newer DRAFT that must never win over revision 2.
        makeSnapshot($workspace, $domainKey, 3, 'draft', ['v' => 3]);
    }

    $batched = $os->latestSnapshots($workspace, $domains, 'agreed');

    foreach ($domains as $domainKey) {
        $single = $os->latestSnapshot($workspace, $domainKey, 'agreed');
        $fromBatch = $batched->get($domainKey);

        expect($fromBatch?->id)->toBe($single?->id);
    }
});

it('never lets a newer draft displace the latest agreed snapshot', function (): void {
    [, $workspace] = batchedFixture();
    $os = app(PbrOperatingSystemService::class);

    $domains = array_keys(config('pbr_canonical_data.domains', []));
    $domainKey = $domains[0];

    // Several agreed revisions, then a newer draft. The HIGHEST agreed
    // revision must win — not the lowest, and not the draft.
    makeSnapshot($workspace, $domainKey, 3, 'agreed', ['state' => 'older']);
    makeSnapshot($workspace, $domainKey, 5, 'agreed', ['state' => 'approved']);
    makeSnapshot($workspace, $domainKey, 6, 'draft', ['state' => 'working']);

    $resolved = $os->latestSnapshots($workspace, [$domainKey], 'agreed')->get($domainKey);

    expect($resolved)->not->toBeNull();
    expect($resolved->revision)->toBe(5);
    expect($resolved->status)->toBe('agreed');
    expect($resolved->summary['state'])->toBe('approved');
});

it('keeps the highest revision when many agreed revisions exist', function (): void {
    // Regression guard for a keyBy() last-wins bug: collapsing a DESC-ordered
    // result with keyBy() alone silently returns the OLDEST revision.
    [, $workspace] = batchedFixture();
    $os = app(PbrOperatingSystemService::class);

    $domains = array_keys(config('pbr_canonical_data.domains', []));
    $domainKey = $domains[0];

    foreach ([1, 2, 3, 4, 7] as $revision) {
        makeSnapshot($workspace, $domainKey, $revision, 'agreed', ['v' => $revision]);
    }

    $resolved = $os->latestSnapshots($workspace, [$domainKey], 'agreed')->get($domainKey);

    expect($resolved->revision)->toBe(7);
    expect($resolved->summary['v'])->toBe(7);

    // And it must agree with the single-domain path it replaced.
    expect($resolved->id)->toBe($os->latestSnapshot($workspace, $domainKey, 'agreed')->id);
});

it('reads every contracted domain in a single query', function (): void {
    [, $workspace] = batchedFixture();

    $domains = array_keys(config('pbr_canonical_data.domains', []));

    foreach ($domains as $domainKey) {
        makeSnapshot($workspace, $domainKey, 1, 'agreed', ['ok' => true]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PbrCanonicalDataService::class)->approvedDomainSummaries($workspace);

    // One batched SELECT. Previously this was one query per domain.
    expect($queries)->toBe(1);
});

it('does not leak snapshots across workspaces', function (): void {
    [$owner, $workspaceA] = batchedFixture();

    $workspaceB = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Other Business',
        'business_name' => 'Other Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $domains = array_keys(config('pbr_canonical_data.domains', []));
    $domainKey = $domains[0];

    makeSnapshot($workspaceA, $domainKey, 1, 'agreed', ['owner' => 'A']);
    makeSnapshot($workspaceB, $domainKey, 9, 'agreed', ['owner' => 'B']);

    $os = app(PbrOperatingSystemService::class);

    $resolvedA = $os->latestSnapshots($workspaceA, [$domainKey], 'agreed')->get($domainKey);
    $resolvedB = $os->latestSnapshots($workspaceB, [$domainKey], 'agreed')->get($domainKey);

    expect($resolvedA->summary['owner'])->toBe('A');
    expect($resolvedB->summary['owner'])->toBe('B');
});

it('returns an empty collection when no domains are requested', function (): void {
    [, $workspace] = batchedFixture();

    $resolved = app(PbrOperatingSystemService::class)
        ->latestSnapshots($workspace, [], 'agreed');

    expect($resolved)->toHaveCount(0);
});
