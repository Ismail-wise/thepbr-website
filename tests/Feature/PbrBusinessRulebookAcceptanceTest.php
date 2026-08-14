<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\PbrBusinessRulebookService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolBusinessGuidanceService;
use App\Services\PbrTools\PbrToolRuntimeContractService;
use App\Services\PbrTools\ToolScenarioService;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rulebookFixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace =
        PartnershipWorkspace::create([
            'owner_user_id' =>
                $owner->id,
            'name' =>
                'Rulebook Business',
            'business_name' =>
                'Rulebook Business',
            'business_stage' =>
                'existing',
            'currency_code' =>
                'THB',
            'status' =>
                'active',
        ]);

    return compact(
        'owner',
        'workspace'
    );
}

function rulebookPartner(
    PartnershipWorkspace $workspace,
    User $owner
): User {
    $partner =
        User::factory()->create([
            'role' => 'public',
            'account_status' =>
                'active',
            'portal_access_expires_at' =>
                null,
            'is_admin' => false,
        ]);

    WorkspaceMember::create([
        'workspace_id' =>
            $workspace->id,
        'user_id' =>
            $partner->id,
        'member_role' =>
            'partner',
        'invitation_status' =>
            'accepted',
        'invited_email' =>
            strtolower(
                $partner->email
            ),
        'invitation_token_hash' =>
            null,
        'invited_by_user_id' =>
            $owner->id,
        'invited_at' =>
            now(),
        'accepted_at' =>
            now(),
        'permissions' =>
            null,
    ]);

    return $partner;
}

test('all fifty seven shared chapter two to ten tools receive real business guidance', function () {
    extract(rulebookFixture());

    $tools = ChapterTool::query()
        ->with('chapter')
        ->whereHas(
            'chapter',
            fn ($query) =>
                $query->whereBetween(
                    'chapter_number',
                    [2, 10]
                )
        )
        ->get();

    expect($tools)->toHaveCount(57);

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $runtime = app(
        PbrToolRuntimeContractService::class
    );

    $guidance = app(
        PbrToolBusinessGuidanceService::class
    );

    foreach ($tools as $tool) {
        $definition =
            $engine->definition(
                $tool->tool_key
            );

        $input =
            $engine->defaultInput(
                $tool->tool_key
            );

        $result =
            $engine->calculate(
                $tool->tool_key,
                $input,
                $workspace
            );

        $state =
            $guidance->build(
                $tool,
                $definition,
                $result,
                $runtime->forTool(
                    $tool
                ),
                null,
                false,
                false
            );

        expect($state)
            ->toHaveKeys([
                'domain',
                'status_key',
                'next_action_mm',
                'business_questions',
                'approval_effect_mm',
                'connection_effect_mm',
                'guardrails',
            ]);

        expect($state['domain'])
            ->not->toBe('');

        expect(
            $state[
                'business_questions'
            ]
        )->not->toBeEmpty();

        expect(
            $state['guardrails']
        )->not->toBeEmpty();
    }
});

test('empty business rulebook contains exactly ten approved-only operating areas', function () {
    extract(rulebookFixture());

    $response = $this
        ->actingAs($owner)
        ->get(
            route(
                'workspaces.rulebook.show',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'data-pbr-rulebook',
            false
        )
        ->assertSee(
            'Approved-only source of truth'
        )
        ->assertSee(
            '10 Operating Areas'
        );

    expect(
        substr_count(
            $response->getContent(),
            'data-rulebook-area='
        )
    )->toBe(10);

    $rulebook = app(
        PbrBusinessRulebookService::class
    )->build(
        $owner,
        $workspace
    );

    expect(
        $rulebook['metrics']
            ['current_rule_count']
    )->toBe(0);

    expect(
        $rulebook['metrics']
            ['operating_record_count']
    )->toBe(0);
});

test('rulebook uses approved current rule and excludes a newer private working draft', function () {
    extract(rulebookFixture());

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $approvedInput = [
        'partners' => [
            [
                'name' =>
                    'Approved Partner A',
                'units' => 60,
                'voting_units' => 40,
            ],
            [
                'name' =>
                    'Approved Partner B',
                'units' => 40,
                'voting_units' => 60,
            ],
        ],
        'reserved_units' => 0,
    ];

    $approvedResult =
        $engine->calculate(
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
            'Approved Cap Table',
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
        'partners' => [
            [
                'name' =>
                    'PRIVATE DRAFT PARTNER',
                'units' => 90,
                'voting_units' => 90,
            ],
            [
                'name' =>
                    'Private Other',
                'units' => 10,
                'voting_units' => 10,
            ],
        ],
        'reserved_units' => 0,
    ];

    $privateResult =
        $engine->calculate(
            $tool->tool_key,
            $privateInput,
            $workspace
        );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'PRIVATE OWNERSHIP DRAFT',
        $privateInput,
        $privateResult
    );

    $rulebook = app(
        PbrBusinessRulebookService::class
    )->build(
        $owner,
        $workspace
    );

    $json = json_encode(
        $rulebook,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    expect($json)
        ->toContain(
            'Approved Partner A'
        );

    expect($json)
        ->not->toContain(
            'PRIVATE DRAFT PARTNER'
        );

    expect($json)
        ->not->toContain(
            'PRIVATE OWNERSHIP DRAFT'
        );

    expect(
        $rulebook['metrics']
            ['current_rule_count']
    )->toBe(1);
});

test('approved operating record appears as history instead of current rule', function () {
    extract(rulebookFixture());

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
        'decision_date' =>
            '2026-08-14',
        'decision' =>
            'Open approved second sales channel',
        'owner' =>
            'Operations Partner',
        'rationale' =>
            'Approved after partner review',
        'follow_up' =>
            'Prepare implementation checklist',
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
        'Approved Sales Decision',
        $input,
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    $rulebook = app(
        PbrBusinessRulebookService::class
    )->build(
        $owner,
        $workspace
    );

    expect(
        $rulebook['metrics']
            ['current_rule_count']
    )->toBe(0);

    expect(
        $rulebook['metrics']
            ['operating_record_count']
    )->toBe(1);

    $json = json_encode(
        $rulebook,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    expect($json)
        ->toContain(
            'Open approved second sales channel'
        );
});

test('accepted partner receives approved rulebook without owner private draft data', function () {
    extract(rulebookFixture());

    $partner = rulebookPartner(
        $workspace,
        $owner
    );

    $tool = ChapterTool::query()
        ->where(
            'tool_key',
            'cap_table_builder'
        )
        ->firstOrFail();

    $engine = app(
        PbrOperatingToolEngine::class
    );

    $approvedInput = [
        'partners' => [
            [
                'name' =>
                    'Shared Partner A',
                'units' => 55,
                'voting_units' => 50,
            ],
            [
                'name' =>
                    'Shared Partner B',
                'units' => 45,
                'voting_units' => 50,
            ],
        ],
        'reserved_units' => 0,
    ];

    $approvedResult =
        $engine->calculate(
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
            'Partner Shared Rule',
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
        'partners' => [
            [
                'name' =>
                    'OWNER SECRET DRAFT',
                'units' => 70,
                'voting_units' => 70,
            ],
            [
                'name' =>
                    'Secret Other',
                'units' => 30,
                'voting_units' => 30,
            ],
        ],
        'reserved_units' => 0,
    ];

    $privateResult =
        $engine->calculate(
            $tool->tool_key,
            $privateInput,
            $workspace
        );

    $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'OWNER PRIVATE VERSION',
        $privateInput,
        $privateResult
    );

    $response = $this
        ->actingAs($partner)
        ->get(
            route(
                'workspaces.rulebook.show',
                $workspace
            )
        );

    $response
        ->assertOk()
        ->assertSee(
            'Partner Read-Only'
        )
        ->assertDontSee(
            'OWNER SECRET DRAFT'
        )
        ->assertDontSee(
            'OWNER PRIVATE VERSION'
        );

    $rulebook = app(
        PbrBusinessRulebookService::class
    )->build(
        $partner,
        $workspace
    );

    $json = json_encode(
        $rulebook,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    expect($json)
        ->toContain(
            'Shared Partner A'
        );

    expect($json)
        ->not->toContain(
            'OWNER SECRET DRAFT'
        );
});

test('business rulebook never leaks approved data from another workspace', function () {
    extract(rulebookFixture());

    $other =
        PartnershipWorkspace::create([
            'owner_user_id' =>
                $owner->id,
            'name' =>
                'Second Isolated Business',
            'business_name' =>
                'Second Isolated Business',
            'business_stage' =>
                'existing',
            'currency_code' =>
                'THB',
            'status' =>
                'active',
        ]);

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
                'name' =>
                    'ONLY FIRST WORKSPACE A',
                'units' => 50,
                'voting_units' => 50,
            ],
            [
                'name' =>
                    'ONLY FIRST WORKSPACE B',
                'units' => 50,
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
        'First Workspace Rule',
        $input,
        $result
    );

    $scenarios->publishAgreedOutput(
        $owner,
        $workspace,
        $tool,
        $draft
    );

    $otherRulebook = app(
        PbrBusinessRulebookService::class
    )->build(
        $owner,
        $other
    );

    $json = json_encode(
        $otherRulebook,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    expect($json)
        ->not->toContain(
            'ONLY FIRST WORKSPACE A'
        );

    expect(
        $otherRulebook['metrics']
            ['current_rule_count']
    )->toBe(0);

    expect(
        $otherRulebook['metrics']
            ['operating_record_count']
    )->toBe(0);
});
