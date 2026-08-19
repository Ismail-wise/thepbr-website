<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the shared premium system covers all sixty four operating tools', function () {
    app(CourseCatalogSeeder::class)->run();

    $chapterOneKeys = [
        'startup_capital_planner',
        'current_capital_position',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ];

    $publishedTools = ChapterTool::query()->published()->get();

    expect($publishedTools)->toHaveCount(64)
        ->and($publishedTools->whereIn('tool_key', $chapterOneKeys))->toHaveCount(7)
        ->and($publishedTools->whereNotIn('tool_key', $chapterOneKeys))->toHaveCount(57);

    $layout = file_get_contents(
        resource_path('views/layouts/student-portal.blade.php')
    );

    $operatingTool = file_get_contents(
        resource_path('views/workspaces/tools/operating-tool.blade.php')
    );

    $chapterOne = file_get_contents(
        resource_path('views/workspaces/tools/chapter-one.blade.php')
    );

    $startupCapital = file_get_contents(
        resource_path('views/workspaces/tools/startup-capital.blade.php')
    );

    $startupCapitalReadonly = file_get_contents(
        resource_path('views/workspaces/tools/startup-capital-readonly.blade.php')
    );

    expect($layout)
        ->toContain('pbr-premium-tools.css')
        ->toContain('pbr-premium-tools.js')
        ->toContain('pbr-tool-route')
        ->and($operatingTool)
        ->toContain('data-pbr-premium-tool')
        ->toContain('premium-tool-command-bar')
        ->toContain('pbr-premium-tool-intelligence')
        ->toContain('id="tool-workspace"')
        ->and($chapterOne)
        ->toContain('data-pbr-premium-tool')
        ->toContain('premium-tool-command-bar')
        ->toContain('id="capital-calculator-workspace"')
        ->toContain('id="capital-tool-result"')
        ->and($startupCapital)
        ->toContain('data-pbr-premium-tool')
        ->toContain('premium-tool-command-bar')
        ->toContain('id="startup-capital-workspace"')
        ->toContain('id="result"')
        ->and($startupCapitalReadonly)
        ->toContain('data-pbr-premium-tool')
        ->toContain('premium-tool-command-bar')
        ->toContain('id="readonly-capital-result"');
});

test('every tool renderer exposes the premium saas command surface', function () {
    app(CourseCatalogSeeder::class)->run();

    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);

    $existingBusiness = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'Premium Existing Business',
        'business_name' => 'Premium Existing Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $newBusiness = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'Premium New Partnership',
        'business_name' => 'Premium New Partnership',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $this->actingAs($student)
        ->get(route('workspaces.tools.operating.show', [
            $existingBusiness,
            'equity-split-simulator',
        ]))
        ->assertOk()
        ->assertSee('data-pbr-premium-tool', false)
        ->assertSee('data-pbr-premium-toolbar', false)
        ->assertSee('Workspace')
        ->assertSee('All Tools');

    $this->get(route('workspaces.tools.chapter-one.show', [
        $existingBusiness,
        'current-capital-position',
    ]))
        ->assertOk()
        ->assertSee('data-pbr-premium-tool', false)
        ->assertSee('data-pbr-premium-toolbar', false)
        ->assertSee('Capital &amp; Funding', false)
        ->assertSee('Result');

    $this->get(route('workspaces.tools.startup-capital.show', $newBusiness))
        ->assertOk()
        ->assertSee('data-pbr-premium-tool', false)
        ->assertSee('data-pbr-premium-toolbar', false)
        ->assertSee('Startup Capital Planner')
        ->assertSee('Setup Required');
});

test('premium tool css keeps desktop density mobile actions and reduced motion safe', function () {
    $css = file_get_contents(
        public_path('css/pbr-premium-tools.css')
    );

    $javascript = file_get_contents(
        public_path('js/pbr-premium-tools.js')
    );

    expect($css)
        ->toContain('Shared SaaS workspace for all 64 Business OS tools')
        ->toContain('Desktop inspection refinements')
        ->toContain('.pbr-premium-toolbar')
        ->toContain('.pbr-premium-tool-intelligence')
        ->toContain('.pbr-premium-capital-tool-page .pbr-calculator-layout')
        ->toContain('.pbr-premium-capital-tool-page .pbr-capital-workspace-grid')
        ->toContain('@media (max-width: 620px)')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($javascript)
        ->toContain('Unsaved Input')
        ->toContain('IntersectionObserver')
        ->toContain('prefers-reduced-motion: reduce');

    $chapterOne = file_get_contents(
        resource_path('views/workspaces/tools/chapter-one.blade.php')
    );

    expect($chapterOne)
        ->toContain('@if(filled($toolPurpose))')
        ->and($css)
        ->toContain(".pbr-premium-capital-tool-page .pbr-calculator-actions {\n    position: static;");
});
