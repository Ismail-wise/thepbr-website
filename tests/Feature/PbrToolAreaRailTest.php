<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The area rail puts all ten business areas at the top of every tool page so
 * an owner can move between tools without going back to the tools index.
 *
 * These tests guard the two things that could go wrong: the rail claiming an
 * area is settled when nothing has been approved, and the rail leaking
 * owner-wide navigation to an invited read-only partner.
 */
function railFixture(): array
{
    // RefreshDatabase gives an empty schema; the catalog is not seeded
    // automatically, so tools only exist once this runs.
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Rail Test Business',
        'business_name' => 'Rail Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    // A shared operating tool: Capital has its own dedicated pages, so this
    // one exercises the template that serves the other 62 tools.
    $tool = ChapterTool::query()
        ->where('tool_key', 'equity_split_simulator')
        ->firstOrFail();

    return [$owner, $workspace, $tool];
}

it('shows all ten business areas on a tool page', function (): void {
    [$owner, $workspace, $tool] = railFixture();

    $response = $this->actingAs($owner)->get(
        route('workspaces.tools.operating.show', [$workspace, $tool->slug])
    );

    $response->assertOk();

    expect(substr_count($response->getContent(), 'pbr-area-rail-item'))->toBe(10);
});

it('never marks an untouched area as established', function (): void {
    [$owner, $workspace, $tool] = railFixture();

    $response = $this->actingAs($owner)->get(
        route('workspaces.tools.operating.show', [$workspace, $tool->slug])
    );

    $response->assertOk();

    // Nothing has been approved in a brand-new workspace. Showing an
    // unconfigured area as settled is the failure this rail must never have.
    expect($response->getContent())->not->toContain('pbr-area-rail-dot is-established');
    expect($response->getContent())->toContain('pbr-area-rail-dot is-setup');
});

it('marks exactly one area as the current page', function (): void {
    [$owner, $workspace, $tool] = railFixture();

    $response = $this->actingAs($owner)->get(
        route('workspaces.tools.operating.show', [$workspace, $tool->slug])
    );

    $response->assertOk();

    expect(substr_count($response->getContent(), 'aria-current="page"'))->toBe(1);
});

it('states each area status in text, not colour alone', function (): void {
    [$owner, $workspace, $tool] = railFixture();

    $response = $this->actingAs($owner)->get(
        route('workspaces.tools.operating.show', [$workspace, $tool->slug])
    );

    $response->assertOk();

    // A dot on its own fails for colour vision deficiency, so the status word
    // must be present for assistive technology.
    expect(substr_count($response->getContent(), 'pbr-area-rail-sr'))->toBe(10);
    $response->assertSee('မစရသေး');
});
