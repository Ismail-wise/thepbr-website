<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingRecord;
use App\Services\Ai\PbrAiContextBuilder;
use App\Services\PbrTools\PbrBusinessOperatingService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolRuntimeContractService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fullConnectedRuntimeFixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Full Connected Runtime Business',
        'business_name' =>
            'Full Connected Runtime Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    return compact('owner', 'workspace');
}

function addFullConnectedPartner(
    PartnershipWorkspace $workspace,
    User $owner
): User {
    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
        'portal_access_expires_at' => null,
        'is_admin' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' =>
            strtolower($partner->email),
        'invitation_token_hash' => null,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
        'permissions' => null,
    ]);

    return $partner;
}

test('all ten chapters and all catalog tools resolve to a production runtime contract', function () {
    app(CourseCatalogSeeder::class)->run();

    $chapters = collect(
        config('pbr_course.chapters', [])
    );

    expect($chapters)->toHaveCount(10);

    $tools = ChapterTool::query()
        ->with('chapter')
        ->get();

    expect($tools)->toHaveCount(64);

    $contracts = app(
        PbrToolRuntimeContractService::class
    );

    $recordToolCount = 0;

    foreach ($tools as $tool) {
        $contract =
            $contracts->forTool($tool);

        expect($contract['domain'])
            ->not->toBe('');

        expect($contract['mode'])
            ->toBeIn([
                'current_rule',
                'operating_record',
            ]);

        expect(
            $contract['approved_data_only']
        )->toBeTrue();

        expect(
            $contract['draft_is_current_rule']
        )->toBeFalse();

        $chapterNumber =
            (int) $tool->chapter->chapter_number;

        if ($chapterNumber >= 2) {
            $definition = config(
                'pbr_operating_tools.definitions.'
                .$tool->tool_key
            );

            expect($definition)
                ->toBeArray();

            expect(
                $definition['handler'] ?? null
            )->not->toBeEmpty();
        }

        if ($contract['is_record']) {
            $recordToolCount++;
            expect(
                $contract['record_type']
            )->not->toBeEmpty();
        }
    }

    expect($recordToolCount)
        ->toBeGreaterThan(0);
});

test('approved operating record appends history instead of inflating active business rule count', function () {
    extract(fullConnectedRuntimeFixture());

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'meeting_decision_log'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $input = [
        'decision_date' => '2026-08-14',
        'decision' => 'Open second sales channel',
        'owner' => 'Operations Partner',
        'rationale' =>
            'Approved after partner review',
        'follow_up' =>
            'Prepare launch checklist',
    ];

    $result = $engine->calculate(
        $tool->tool_key,
        $input,
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $draft = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Approved Sales Channel Decision',
        $input,
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    expect(
        WorkspaceOperatingRecord::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'record_type',
                'meeting_decision'
            )
            ->count()
    )->toBe(1);

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    expect(
        $state['metrics']['active_rule_count']
    )->toBe(0);

    expect(
        $state['metrics']['operating_record_count']
    )->toBe(1);
});

test('approved non record tool remains a current business rule', function () {
    extract(fullConnectedRuntimeFixture());

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $input = [
        'partners' => [
            [
                'name' => 'Partner A',
                'units' => 60,
                'voting_units' => 50,
            ],
            [
                'name' => 'Partner B',
                'units' => 40,
                'voting_units' => 50,
            ],
        ],
        'reserved_units' => 0,
    ];

    $result = $engine->calculate(
        $tool->tool_key,
        $input,
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $draft = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Approved Cap Table',
        $input,
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    $state = app(
        PbrBusinessOperatingService::class
    )->workspaceState(
        $owner,
        $workspace
    );

    expect(
        $state['metrics']['active_rule_count']
    )->toBe(1);

    expect(
        $state['metrics']['operating_record_count']
    )->toBe(0);
});

test('accepted partner sees approved operating history but never owner private draft', function () {
    extract(fullConnectedRuntimeFixture());

    $partner = addFullConnectedPartner(
        $workspace,
        $owner
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'meeting_decision_log'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $approvedInput = [
        'decision_date' => '2026-08-14',
        'decision' => 'Approved supplier policy',
        'owner' => 'Operations',
        'rationale' => 'Partner agreement',
        'follow_up' => 'Implement policy',
    ];

    $approvedResult = $engine->calculate(
        $tool->tool_key,
        $approvedInput,
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $approvedDraft =
        $scenarios->saveDraft(
            $owner,
            $workspace,
            $tool,
            'Approved Supplier Decision',
            $approvedInput,
            $approvedResult
        );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $approvedDraft
    );

    $privateInput = [
        'decision_date' => '2026-08-15',
        'decision' => 'Owner Private Draft Decision',
        'owner' => 'Owner',
        'rationale' => 'Still under review',
        'follow_up' => 'Do not publish',
    ];

    $privateResult = $engine->calculate(
        $tool->tool_key,
        $privateInput,
        $workspace
    );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Owner Private Draft',
        $privateInput,
        $privateResult
    );

    $response = $this
        ->actingAs($partner)
        ->get(
            route(
                'workspaces.tools.operating.show',
                [
                    $workspace,
                    $tool->slug,
                ]
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-pbr-runtime-contract="operating_record"',
            false
        )
        ->assertSee(
            'data-pbr-operating-record-history',
            false
        )
        ->assertSee(
            'Approved Supplier Decision'
        )
        ->assertDontSee(
            'Owner Private Draft'
        )
        ->assertDontSee(
            'Owner Private Draft Decision'
        );
});

test('pbr ai receives approved operating history and excludes unapproved record drafts', function () {
    extract(fullConnectedRuntimeFixture());

    $partner = addFullConnectedPartner(
        $workspace,
        $owner
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'meeting_decision_log'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $approvedInput = [
        'decision_date' => '2026-08-14',
        'decision' => 'Approved AI-visible decision',
        'owner' => 'Partnership',
        'rationale' => 'Approved business record',
        'follow_up' => 'Proceed',
    ];

    $approvedResult = $engine->calculate(
        $tool->tool_key,
        $approvedInput,
        $workspace
    );

    $scenarios = app(
        ToolScenarioService::class
    );

    $approvedDraft =
        $scenarios->saveDraft(
            $owner,
            $workspace,
            $tool,
            'Approved AI Record',
            $approvedInput,
            $approvedResult
        );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $approvedDraft
    );

    $privateInput = [
        'decision_date' => '2026-08-15',
        'decision' => 'PRIVATE UNAPPROVED DECISION',
        'owner' => 'Owner',
        'rationale' => 'Draft only',
        'follow_up' => 'None',
    ];

    $privateResult = $engine->calculate(
        $tool->tool_key,
        $privateInput,
        $workspace
    );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Private AI Draft',
        $privateInput,
        $privateResult
    );

    $context = app(
        PbrAiContextBuilder::class
    )->build(
        $partner,
        $workspace
    );

    $json = json_encode(
        $context,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    expect($context['context_version'])
        ->toBe(
            'pbr-ai-v4-approved-rules-and-records'
        );

    expect(
        $context['access_scope']
            ['operating_record_scope']
    )->toBe(
        'approved_active_records_only'
    );

    expect($json)
        ->toContain(
            'Approved AI-visible decision'
        );

    expect($json)
        ->not->toContain(
            'PRIVATE UNAPPROVED DECISION'
        );

    expect($json)
        ->not->toContain(
            'Private AI Draft'
        );
});
