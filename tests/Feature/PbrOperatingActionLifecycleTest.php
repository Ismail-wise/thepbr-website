<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrOperatingActionFixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Action Lifecycle Business',
        'business_name' => 'Action Lifecycle Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    return compact('owner', 'workspace', 'tool');
}

function pbrOperatingActionInput(string $actionTitle): array
{
    return [
        'partners' => [
            [
                'name' => 'Founder A',
                'units' => 60,
                'voting_units' => 60,
            ],
            [
                'name' => 'Founder B',
                'units' => 40,
                'voting_units' => 40,
            ],
        ],
        'reserved_units' => 0,
        'operating_context' => [
            'owner_name' => 'Si Thu Aung',
            'decision_summary' =>
                'Partners approved the operating ownership structure.',
            'effective_date' => '2026-08-20',
            'review_date' => '2026-11-20',
            'evidence' => 'Signed partner meeting minutes.',
        ],
        'operating_actions' => [
            [
                'title' => $actionTitle,
                'description' =>
                    'Update the company records and partner register.',
                'owner_name' => 'Si Thu Aung',
                'priority' => 'high',
                'status' => 'open',
                'due_date' => '2026-08-25',
            ],
        ],
    ];
}

test('approved operating output activates accountable action items', function () {
    extract(pbrOperatingActionFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);
    $input = pbrOperatingActionInput('Update ownership register');

    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Approved Ownership Plan',
        $input,
        $engine->calculate($tool->tool_key, $input, $workspace)
    );

    $output = $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $session
    );

    $action = WorkspaceToolAction::query()->firstOrFail();

    expect($output->actions()->count())->toBe(1)
        ->and($workspace->toolActions()->count())->toBe(1)
        ->and($action->title)->toBe('Update ownership register')
        ->and($action->owner_name)->toBe('Si Thu Aung')
        ->and($action->priority)->toBe('high')
        ->and($action->status)->toBe('open')
        ->and($action->due_date?->format('Y-m-d'))->toBe('2026-08-25')
        ->and($action->operating_context['decision_summary'])
        ->toBe('Partners approved the operating ownership structure.');
});

test('new approved current rule supersedes unfinished actions from the old rule', function () {
    extract(pbrOperatingActionFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);

    $firstInput = pbrOperatingActionInput('Old ownership action');
    $firstSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'First Ownership Rule',
        $firstInput,
        $engine->calculate($tool->tool_key, $firstInput, $workspace)
    );
    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $firstSession
    );

    $oldAction = WorkspaceToolAction::query()->firstOrFail();

    $secondInput = pbrOperatingActionInput('Current ownership action');
    $secondSession = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Revised Ownership Rule',
        $secondInput,
        $engine->calculate($tool->tool_key, $secondInput, $workspace)
    );
    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $secondSession
    );

    expect($oldAction->fresh()->status)->toBe('superseded')
        ->and(WorkspaceToolAction::query()
            ->where('title', 'Current ownership action')
            ->value('status'))->toBe('open')
        ->and(WorkspaceToolAction::query()->count())->toBe(2);
});


test('workspace manager can complete an approved operating action', function () {
    extract(pbrOperatingActionFixture());

    $engine = app(PbrOperatingToolEngine::class);
    $scenarios = app(ToolScenarioService::class);
    $input = pbrOperatingActionInput('Complete this action');

    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Action Status Rule',
        $input,
        $engine->calculate($tool->tool_key, $input, $workspace)
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $session
    );

    $action = WorkspaceToolAction::query()->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('workspaces.tool-actions.update', [
            $workspace,
            $action,
        ]), [
            'status' => 'completed',
        ])
        ->assertRedirect();

    expect($action->fresh()->status)->toBe('completed')
        ->and($action->fresh()->completed_at)->not->toBeNull();
});
