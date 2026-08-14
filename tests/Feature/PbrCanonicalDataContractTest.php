<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use App\Services\PbrTools\PbrCanonicalDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function canonicalFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Canonical Test Business',
        'business_name' => 'Canonical Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => $partner->email,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'partner_key' => 'owner-key',
        'display_name' => 'Owner',
        'status' => 'active',
        'profile_data' => [
            'workspace_role' => 'owner',
        ],
    ]);

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'partner_key' => 'partner-key',
        'display_name' => 'Partner',
        'status' => 'active',
        'profile_data' => [
            'workspace_role' => 'partner',
        ],
    ]);

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'partner_key' => 'planned-key',
        'display_name' => 'Future Partner',
        'status' => 'planned',
        'profile_data' => [],
    ]);

    return compact(
        'owner',
        'partner',
        'workspace'
    );
}

test('canonical contract defines exactly ten operating domains', function () {
    $domains = config(
        'pbr_canonical_data.domains'
    );

    expect($domains)
        ->toBeArray()
        ->toHaveCount(10);

    expect(array_keys($domains))->toBe([
        'capital',
        'ownership',
        'contribution',
        'distribution',
        'financial_controls',
        'governance',
        'exit',
        'continuity',
        'share_transfer',
        'dispute_resolution',
    ]);
});

test('every canonical dependency references another valid domain', function () {
    $domains = config(
        'pbr_canonical_data.domains'
    );

    $domainKeys = array_keys($domains);

    foreach ($domains as $domain => $contract) {
        expect($contract['chapter'])
            ->toBeInt()
            ->toBeGreaterThanOrEqual(1)
            ->toBeLessThanOrEqual(10);

        foreach ($contract['reads_from'] ?? [] as $dependency) {
            expect(
                in_array(
                    $dependency,
                    $domainKeys,
                    true
                )
            )->toBeTrue(
                $domain.' references invalid dependency '.$dependency
            );
        }
    }
});

test('approved canonical state ignores newer draft snapshots', function () {
    extract(canonicalFixture());

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => [
            'source_status' => 'agreed_only',
        ],
        'summary' => [
            'capital_required' => 100000,
            'capital_secured' => 80000,
            'funding_gap' => 20000,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now()->subMinute(),
        'agreed_at' => now()->subMinute(),
    ]);

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'revision' => 2,
        'status' => 'draft',
        'schema_version' => 'v1',
        'payload' => [
            'source_status' => 'working_latest_draft_or_agreed',
        ],
        'summary' => [
            'capital_required' => 999999,
            'capital_secured' => 1,
            'funding_gap' => 999998,
        ],
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => null,
    ]);

    $state = app(
        PbrCanonicalDataService::class
    )->approvedState(
        $owner,
        $workspace
    );

    expect(
        $state['domains']['capital']['status']
    )->toBe('agreed');

    expect(
        $state['domains']['capital']['revision']
    )->toBe(1);

    expect(
        (float) $state['domains']['capital']['summary']['funding_gap']
    )->toBe(20000.0);
});

test('partner canonical view excludes planned partners', function () {
    extract(canonicalFixture());

    $service = app(
        PbrCanonicalDataService::class
    );

    $ownerState = $service->approvedState(
        $owner,
        $workspace
    );

    $partnerState = $service->approvedState(
        $partner,
        $workspace
    );

    expect(
        $ownerState['partners']['planned']
    )->toHaveCount(1);

    expect(
        $partnerState['partners']['planned']
    )->toBe([]);

    expect(
        $partnerState['partners']['active']
    )->toHaveCount(2);
});

test('canonical data never leaks another workspace snapshot', function () {
    extract(canonicalFixture());

    $otherOwner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $otherWorkspace = PartnershipWorkspace::create([
        'owner_user_id' => $otherOwner->id,
        'name' => 'Other Business',
        'business_name' => 'Other Business',
        'business_stage' => 'existing',
        'currency_code' => 'USD',
        'status' => 'active',
    ]);

    WorkspaceOperatingSnapshot::create([
        'workspace_id' => $otherWorkspace->id,
        'domain_key' => 'capital',
        'revision' => 1,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => [],
        'summary' => [
            'funding_gap' => 777777,
        ],
        'generated_by_user_id' => $otherOwner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);

    $state = app(
        PbrCanonicalDataService::class
    )->approvedState(
        $owner,
        $workspace
    );

    expect(
        $state['domains']['capital']['summary']
    )->toBe([]);
});

test('business valuation is explicitly advisory rather than canonical current state', function () {
    $advisory = config(
        'pbr_canonical_data.advisory_sources.business_valuation'
    );

    expect($advisory['policy'])
        ->toBe(
            'advisory_until_adopted_into_approved_rule'
        );
});
