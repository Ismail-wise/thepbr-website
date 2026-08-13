<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Services\PbrTools\PbrBusinessOperatingService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('historical draft snapshot does not keep a business area in review after its working plan is deleted', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Snapshot Lifecycle Business',
        'business_name' => 'Snapshot Lifecycle Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);
    $businessOs = app(PbrBusinessOperatingService::class);

    $approvedInput = [
        'partners' => [
            ['name' => 'Owner', 'units' => 70, 'voting_units' => 70],
            ['name' => 'Partner', 'units' => 30, 'voting_units' => 30],
        ],
        'reserved_units' => 0,
    ];

    $approved = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Current Ownership',
        $approvedInput,
        $engine->calculate($tool->tool_key, $approvedInput, $workspace)
    );
    $scenarios->publishAgreedOutput($owner, $workspace, $tool, $approved);

    expect($scenarios->latestAgreedInput($workspace, $tool))
        ->toMatchArray($approvedInput);

    $workingInput = [
        'partners' => [
            ['name' => 'Owner', 'units' => 50, 'voting_units' => 50],
            ['name' => 'Partner', 'units' => 50, 'voting_units' => 50],
        ],
        'reserved_units' => 0,
    ];

    $working = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Proposed Change',
        $workingInput,
        $engine->calculate($tool->tool_key, $workingInput, $workspace)
    );

    // This deliberately creates a historical draft output/snapshot before the
    // user decides to abandon the proposed change.
    $scenarios->createWorkspaceOutput($owner, $workspace, $tool, $working);

    $duringReview = $businessOs->workspaceState($owner, $workspace);
    expect($duringReview['system_map']->get('ownership')['state']['key'])
        ->toBe('review');

    $scenarios->deleteDraft($owner, $workspace, $tool, $working->id);

    $afterDelete = $businessOs->workspaceState($owner, $workspace);

    expect($afterDelete['metrics']['working_change_count'])->toBe(0)
        ->and($afterDelete['system_map']->get('ownership')['state']['key'])->toBe('active')
        ->and($afterDelete['system_map']->get('ownership')['working_count'])->toBe(0)
        ->and($afterDelete['active_rules'])->toHaveCount(1);
});
