<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use App\Services\PbrTools\PbrOperatingSystemService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolPrefillService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrIntegrityFixture(): array
{
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Integrity Business',
        'business_name' => 'Integrity Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact(
        'owner',
        'workspace'
    );
}

function pbrIntegritySnapshot(
    PartnershipWorkspace $workspace,
    User $owner,
    string $domain,
    array $summary,
    int $revision = 1
): WorkspaceOperatingSnapshot {
    return WorkspaceOperatingSnapshot::create([
        'workspace_id' => $workspace->id,
        'domain_key' => $domain,
        'revision' => $revision,
        'status' => 'agreed',
        'schema_version' => 'v1',
        'payload' => [
            'source_status' => 'agreed_only',
        ],
        'summary' => $summary,
        'generated_by_user_id' => $owner->id,
        'generated_at' => now(),
        'agreed_at' => now(),
    ]);
}

test('cap table preserves voting units independently from ownership units', function () {
    $workspace = new PartnershipWorkspace([
        'business_stage' => 'existing',
        'currency_code' => 'THB',
    ]);

    $result = app(
        PbrOperatingToolEngine::class
    )->calculate(
        'cap_table_builder',
        [
            'partners' => [
                [
                    'name' => 'Owner',
                    'units' => 60,
                    'voting_units' => 25,
                ],
                [
                    'name' => 'Partner',
                    'units' => 40,
                    'voting_units' => 75,
                ],
            ],
            'reserved_units' => 0,
        ],
        $workspace
    );

    expect(
        $result['data']['holders'][0]['units']
    )->toBe(60.0);

    expect(
        $result['data']['holders'][0]['voting_units']
    )->toBe(25.0);

    expect(
        $result['data']['holders'][0]['ownership_percentage']
    )->toBe(60.0);

    expect(
        $result['data']['holders'][0]['voting_percentage']
    )->toBe(25.0);
});

test('ownership prefill never substitutes ownership units for missing voting units', function () {
    extract(pbrIntegrityFixture());

    pbrIntegritySnapshot(
        $workspace,
        $owner,
        'ownership',
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

    $service = app(
        PbrToolPrefillService::class
    );

    $capTable = new ChapterTool();
    $capTable->tool_key = 'cap_table_builder';

    $capInput = $service->prefill(
        $workspace,
        $capTable,
        []
    );

    expect(
        (float) $capInput['partners'][0]['voting_units']
    )->toBe(0.0);

    expect(
        (float) $capInput['partners'][1]['voting_units']
    )->toBe(0.0);

    $votingPower = new ChapterTool();
    $votingPower->tool_key =
        'voting_power_calculator';

    $votingInput = $service->prefill(
        $workspace,
        $votingPower,
        []
    );

    expect(
        (float) $votingInput['partners'][0]['voting_units']
    )->toBe(0.0);

    expect(
        (float) $votingInput['partners'][1]['voting_units']
    )->toBe(0.0);
});

test('governance voting never substitutes ownership percentage for voting power', function () {
    extract(pbrIntegrityFixture());

    pbrIntegritySnapshot(
        $workspace,
        $owner,
        'ownership',
        [
            'holders' => [
                [
                    'holder' => 'Owner',
                    'ownership_percentage' => 80,
                ],
                [
                    'holder' => 'Partner',
                    'ownership_percentage' => 20,
                ],
            ],
        ]
    );

    $tool = new ChapterTool();
    $tool->tool_key = 'voting_simulator';

    $input = app(
        PbrToolPrefillService::class
    )->prefill(
        $workspace,
        $tool,
        []
    );

    expect(
        (float) $input['votes'][0]['weight']
    )->toBe(0.0);

    expect(
        (float) $input['votes'][1]['weight']
    )->toBe(0.0);
});

test('profit distribution never converts ownership percentage into profit share automatically', function () {
    extract(pbrIntegrityFixture());

    pbrIntegritySnapshot(
        $workspace,
        $owner,
        'ownership',
        [
            'holders' => [
                [
                    'holder' => 'Owner',
                    'ownership_percentage' => 70,
                ],
                [
                    'holder' => 'Partner',
                    'ownership_percentage' => 30,
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
        $input['partners']
    )->toHaveCount(2);

    expect(
        (float) $input['partners'][0]['percentage']
    )->toBe(0.0);

    expect(
        (float) $input['partners'][1]['percentage']
    )->toBe(0.0);
});

test('salary planner uses approved profit share but never ownership as a fallback', function () {
    extract(pbrIntegrityFixture());

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'partner_key' => 'owner',
        'display_name' => 'Owner',
        'status' => 'active',
        'profile_data' => [],
    ]);

    WorkspacePartnerProfile::create([
        'workspace_id' => $workspace->id,
        'user_id' => null,
        'partner_key' => 'partner',
        'display_name' => 'Partner',
        'status' => 'active',
        'profile_data' => [],
    ]);

    pbrIntegritySnapshot(
        $workspace,
        $owner,
        'ownership',
        [
            'holders' => [
                [
                    'holder' => 'Owner',
                    'ownership_percentage' => 70,
                ],
                [
                    'holder' => 'Partner',
                    'ownership_percentage' => 30,
                ],
            ],
        ]
    );

    $tool = new ChapterTool();
    $tool->tool_key =
        'salary_profit_share_planner';

    $service = app(
        PbrToolPrefillService::class
    );

    $withoutDistribution = $service->prefill(
        $workspace,
        $tool,
        []
    );

    expect(
        (float) $withoutDistribution['partners'][0]['profit_share']
    )->toBe(0.0);

    expect(
        (float) $withoutDistribution['partners'][1]['profit_share']
    )->toBe(0.0);

    pbrIntegritySnapshot(
        $workspace,
        $owner,
        'distribution',
        [
            'profit_distribution' => [
                'partners' => [
                    [
                        'partner' => 'Owner',
                        'profit_share' => 55,
                    ],
                    [
                        'partner' => 'Partner',
                        'profit_share' => 45,
                    ],
                ],
            ],
        ]
    );

    $withApprovedDistribution = $service->prefill(
        $workspace,
        $tool,
        []
    );

    expect(
        (float) $withApprovedDistribution['partners'][0]['profit_share']
    )->toBe(55.0);

    expect(
        (float) $withApprovedDistribution['partners'][1]['profit_share']
    )->toBe(45.0);

    expect(
        (float) $withApprovedDistribution['partners'][0]['monthly_salary']
    )->toBe(0.0);
});

test('approved share transfer scenario never replaces current ownership rule', function () {
    extract(pbrIntegrityFixture());

    app(CourseCatalogSeeder::class)->run();

    $ownership = pbrIntegritySnapshot(
        $workspace,
        $owner,
        'ownership',
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

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'share_transfer_simulator'
        )
        ->firstOrFail();

    $result = app(
        PbrOperatingToolEngine::class
    )->calculate(
        'share_transfer_simulator',
        [
            'total_units' => 100,
            'seller_name' => 'Owner',
            'seller_units' => 60,
            'buyer_name' => 'Partner',
            'buyer_units' => 40,
            'transfer_units' => 20,
        ],
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $draft = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Transfer Scenario',
        [
            'total_units' => 100,
            'seller_name' => 'Owner',
            'seller_units' => 60,
            'buyer_name' => 'Partner',
            'buyer_units' => 40,
            'transfer_units' => 20,
        ],
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    $operating = app(
        PbrOperatingSystemService::class
    );

    $currentOwnership = $operating
        ->latestSnapshot(
            $workspace,
            'ownership',
            'agreed'
        );

    $transfer = $operating
        ->latestSnapshot(
            $workspace,
            'share_transfer',
            'agreed'
        );

    expect($currentOwnership)
        ->not->toBeNull();

    expect(
        $currentOwnership->id
    )->toBe($ownership->id);

    expect(
        $currentOwnership->summary['holders'][0]['units']
    )->toBe(60);

    expect(
        $currentOwnership->summary['holders'][1]['units']
    )->toBe(40);

    expect($transfer)
        ->not->toBeNull();

    expect(
        (float) $transfer->summary[
            'latest_transfer_scenario'
        ]['transfer_units']
    )->toBe(20.0);
});
