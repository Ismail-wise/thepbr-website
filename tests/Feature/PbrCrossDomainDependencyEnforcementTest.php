<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use App\Services\PbrTools\PbrCanonicalDataService;
use App\Services\PbrTools\PbrToolPrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dependencyWorkspace(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Dependency Test Business',
        'business_name' => 'Dependency Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

function dependencySnapshot(
    PartnershipWorkspace $workspace,
    User $owner,
    string $domain,
    int $revision,
    string $status,
    array $summary
): WorkspaceOperatingSnapshot {
    return WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => $domain,
        'revision' => $revision,
        'status' => $status,
        'schema_version' => 'v1',
        'payload' => [],
        'summary' => $summary,
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => $status === 'agreed'
            ? now()
            : null,
    ]);
}

test('every tool prefill source is permitted by its consumer domain contract', function () {
    $service = app(
        PbrCanonicalDataService::class
    );

    $domains = config(
        'pbr_canonical_data.domains'
    );

    $contracts = config(
        'pbr_canonical_data.prefill_contracts'
    );

    expect($contracts)
        ->toBeArray()
        ->not->toBeEmpty();

    foreach ($contracts as $toolKey => $contract) {
        $consumer = $contract['consumer']
            ?? null;

        expect(
            array_key_exists(
                $consumer,
                $domains
            )
        )->toBeTrue(
            $toolKey
            .' has invalid consumer domain.'
        );

        foreach (
            $contract['sources'] ?? []
            as $source
        ) {
            expect(
                $service->canDomainRead(
                    $consumer,
                    $source
                )
            )->toBeTrue(
                $toolKey
                .' illegally reads '
                .$source
                .' from '
                .$consumer
            );
        }

        foreach (
            $contract['advisory'] ?? []
            as $source
        ) {
            expect(
                $service->allowsAdvisorySource(
                    $toolKey,
                    $source
                )
            )->toBeTrue(
                $toolKey
                .' has invalid advisory source '
                .$source
            );
        }
    }
});

test('reserve fund planner may read approved capital but cannot see unrelated canonical domains', function () {
    extract(dependencyWorkspace());

    dependencySnapshot(
        $workspace,
        $owner,
        'capital',
        1,
        'agreed',
        [
            'monthly_operating_cost' => 50000,
        ]
    );

    dependencySnapshot(
        $workspace,
        $owner,
        'ownership',
        1,
        'agreed',
        [
            'total_units' => 100,
        ]
    );

    dependencySnapshot(
        $workspace,
        $owner,
        'governance',
        1,
        'agreed',
        [
            'secret_test_value' => 999,
        ]
    );

    $sources = app(
        PbrCanonicalDataService::class
    )->approvedPrefillSources(
        $workspace,
        'reserve_fund_planner'
    );

    expect(array_keys($sources))
        ->toBe(['capital']);

    expect(
        $sources['capital']['monthly_operating_cost']
    )->toBe(50000);
});

test('distribution prefill uses approved ownership roster, ignores newer draft, and never infers profit share', function () {
    extract(dependencyWorkspace());

    dependencySnapshot(
        $workspace,
        $owner,
        'ownership',
        1,
        'agreed',
        [
            'holders' => [
                [
                    'holder' => 'Owner',
                    'ownership_percentage' => 60,
                ],
                [
                    'holder' => 'Partner',
                    'ownership_percentage' => 40,
                ],
            ],
        ]
    );

    dependencySnapshot(
        $workspace,
        $owner,
        'ownership',
        2,
        'draft',
        [
            'holders' => [
                [
                    'holder' => 'Draft Owner',
                    'ownership_percentage' => 5,
                ],
                [
                    'holder' => 'Draft Partner',
                    'ownership_percentage' => 95,
                ],
            ],
        ]
    );

    $tool = new ChapterTool();
    $tool->tool_key =
        'profit_distribution_calculator';

    $input = app(
        PbrToolPrefillService::class
    )->prefill(
        $workspace,
        $tool,
        []
    );

    expect(
        $input['partners'][0]['name']
    )->toBe('Owner');

    expect(
        $input['partners'][1]['name']
    )->toBe('Partner');

    expect(
        (float) $input['partners'][0]['percentage']
    )->toBe(0.0);

    expect(
        (float) $input['partners'][1]['percentage']
    )->toBe(0.0);
});

test('capital contribution data can inform equity planning but never becomes ownership automatically', function () {
    extract(dependencyWorkspace());

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'partner_key' => 'owner-key',
        'display_name' => 'Owner',
        'status' => 'active',
        'profile_data' => [],
    ]);

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'partner_key' => 'partner-key',
        'display_name' => 'Partner',
        'status' => 'planned',
        'profile_data' => [],
    ]);

    dependencySnapshot(
        $workspace,
        $owner,
        'capital',
        1,
        'agreed',
        [
            'partner_contributions' => [
                [
                    'name' => 'Owner',
                    'total' => 100000,
                ],
                [
                    'name' => 'Partner',
                    'total' => 50000,
                ],
            ],
        ]
    );

    $tool = new ChapterTool();
    $tool->tool_key =
        'equity_split_simulator';

    $input = app(
        PbrToolPrefillService::class
    )->prefill(
        $workspace,
        $tool,
        []
    );

    expect(
        (float) $input['partners'][0]['capital']
    )->toBe(100000.0);

    expect(
        (float) $input['partners'][1]['capital']
    )->toBe(50000.0);

    foreach ($input['partners'] as $partner) {
        expect(
            array_key_exists(
                'ownership_percentage',
                $partner
            )
        )->toBeFalse();

        expect(
            array_key_exists(
                'percentage',
                $partner
            )
        )->toBeFalse();
    }
});

test('approved share transfer state never silently replaces current ownership state', function () {
    extract(dependencyWorkspace());

    dependencySnapshot(
        $workspace,
        $owner,
        'ownership',
        1,
        'agreed',
        [
            'total_units' => 100,
            'holders' => [
                [
                    'holder' => 'Owner',
                    'units' => 60,
                ],
                [
                    'holder' => 'Partner',
                    'units' => 40,
                ],
            ],
        ]
    );

    dependencySnapshot(
        $workspace,
        $owner,
        'share_transfer',
        1,
        'agreed',
        [
            'ownership_before_after' => [
                'holders' => [
                    [
                        'holder' => 'Owner',
                        'after_units' => 10,
                    ],
                    [
                        'holder' => 'Partner',
                        'after_units' => 90,
                    ],
                ],
            ],
        ]
    );

    $sources = app(
        PbrCanonicalDataService::class
    )->approvedPrefillSources(
        $workspace,
        'cap_table_builder'
    );

    expect(array_keys($sources))
        ->toBe(['ownership']);

    expect(
        $sources['ownership']['holders'][0]['units']
    )->toBe(60);

    expect(
        $sources['ownership']['holders'][1]['units']
    )->toBe(40);
});

test('advisory valuation is available only to tools that explicitly declare it', function () {
    $service = app(
        PbrCanonicalDataService::class
    );

    expect(
        $service->allowsAdvisorySource(
            'share_value_calculator',
            'business_valuation'
        )
    )->toBeTrue();

    expect(
        $service->allowsAdvisorySource(
            'partner_buyout_calculator',
            'business_valuation'
        )
    )->toBeTrue();

    expect(
        $service->allowsAdvisorySource(
            'profit_distribution_calculator',
            'business_valuation'
        )
    )->toBeFalse();

    expect(
        $service->allowsAdvisorySource(
            'voting_simulator',
            'business_valuation'
        )
    )->toBeFalse();
});
