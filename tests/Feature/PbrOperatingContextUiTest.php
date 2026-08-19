<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrOperatingContextUiFixture(): array
{
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Operating Context UI Business',
        'business_name' => 'Operating Context UI Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'cap_table_builder')
        ->firstOrFail();

    return compact('owner', 'workspace', 'tool');
}

test('operating tools expose real decision and action context', function () {
    extract(pbrOperatingContextUiFixture());

    $this->actingAs($owner)
        ->get(route('workspaces.tools.operating.show', [
            $workspace,
            $tool->slug,
        ]))
        ->assertOk()
        ->assertSee('Operating Owner')
        ->assertSee('Decision / Rule Summary')
        ->assertSee('Effective Date')
        ->assertSee('Review Date')
        ->assertSee('Operating Action Plan');
});

test('operating context and action plan are saved with the working draft', function () {
    extract(pbrOperatingContextUiFixture());

    $this->actingAs($owner)
        ->post(route('workspaces.tools.operating.save', [
            $workspace,
            $tool->slug,
        ]), [
            'scenario_name' => 'Real Ownership Decision',
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
                'status' => 'in_progress',
                'effective_date' => '2026-08-20',
                'review_date' => '2026-11-20',
                'decision_summary' =>
                    'Use the approved 60/40 ownership structure.',
                'evidence' => 'Partner meeting minutes.',
            ],
            'operating_actions' => [
                [
                    'title' => 'Update company ownership register',
                    'description' =>
                        'Submit the approved ownership data.',
                    'owner_name' => 'Si Thu Aung',
                    'priority' => 'high',
                    'status' => 'open',
                    'due_date' => '2026-08-25',
                ],
            ],
        ])
        ->assertRedirect();

    $session = ToolSession::query()->firstOrFail();

    expect($session->scenario_name)
        ->toBe('Real Ownership Decision')
        ->and($session->input_data['operating_context']['status'])
        ->toBe('in_progress')
        ->and(
            $session->input_data['operating_context']['decision_summary']
        )
        ->toBe('Use the approved 60/40 ownership structure.')
        ->and($session->input_data['operating_actions'])
        ->toHaveCount(1)
        ->and($session->input_data['operating_actions'][0]['priority'])
        ->toBe('high');
});
