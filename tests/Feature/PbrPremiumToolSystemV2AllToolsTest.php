<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pbrV2ExcludedSlugs(): array
{
    return [
        'startup-capital-planner',
        'working-capital-calculator',
    ];
}

test('premium tool system v2 covers exactly the remaining sixty two tools', function () {
    app(CourseCatalogSeeder::class)->run();

    $tools = ChapterTool::query()->published()->get();
    $covered = $tools->reject(
        fn (ChapterTool $tool) => in_array($tool->slug, pbrV2ExcludedSlugs(), true)
    );

    expect($tools)->toHaveCount(64)
        ->and($covered)->toHaveCount(62);

    $javascript = file_get_contents(
        public_path('js/pbr-premium-tool-system-v2.js')
    );

    foreach ($covered as $tool) {
        expect($javascript)->toContain("'{$tool->slug}':");
    }

    expect($javascript)
        ->not->toContain("'startup-capital-planner':")
        ->not->toContain("'working-capital-calculator':");
});

test('v2 provides all six business tool families and preserves accessibility safeguards', function () {
    $css = file_get_contents(
        public_path('css/pbr-premium-tool-system-v2.css')
    );

    $javascript = file_get_contents(
        public_path('js/pbr-premium-tool-system-v2.js')
    );

    expect($css)
        ->toContain('remaining 62 Business OS tools')
        ->toContain('.pbr-ds-family-calculator')
        ->toContain('.pbr-ds-family-matrix')
        ->toContain('.pbr-ds-family-visual')
        ->toContain('.pbr-ds-family-planner')
        ->toContain('.pbr-ds-family-checklist')
        ->toContain('.pbr-ds-family-record')
        ->toContain('.pbr-ds-v2-governance')
        ->toContain('.pbr-ds-v2-primary-grid')
        ->toContain('@media (max-width: 620px)')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($javascript)
        ->toContain('Operating Context & Action Plan')
        ->toContain('Working Plans & History')
        ->toContain('Connected Business Context')
        ->toContain('Continue Editing')
        ->toContain('Review & Approve')
        ->toContain('Approve as Current Rule')
        ->toContain('Approve & Add to History');
});

test('v2 assets load only on shared tool routes and startup capital remains isolated', function () {
    $layout = file_get_contents(
        resource_path('views/layouts/student-portal.blade.php')
    );

    expect($layout)
        ->toContain('pbr-premium-tool-system-v2.css')
        ->toContain('pbr-premium-tool-system-v2.js')
        ->toContain("request()->routeIs('workspaces.tools.operating.*', 'workspaces.tools.chapter-one.*')")
        ->toContain('pbr-startup-capital-expense-ux.css')
        ->toContain('pbr-premium-tool-design-system-v11.css');
});

test('all sixty two covered tools render through the shared premium surfaces', function () {
    app(CourseCatalogSeeder::class)->run();

    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);

    $workspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'V2 Premium Tool Test Business',
        'business_name' => 'V2 Premium Tool Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $this->actingAs($student);

    $chapterOneSlugs = [
        'current-capital-position',
        'contingency-fund-calculator',
        'partner-contribution-matrix',
        'funding-gap-calculator',
        'capital-allocation-chart',
    ];

    foreach ($chapterOneSlugs as $slug) {
        $this->get(route('workspaces.tools.chapter-one.show', [$workspace, $slug]))
            ->assertOk()
            ->assertSee('data-pbr-premium-tool', false)
            ->assertSee('pbr-premium-tool-system-v2.css', false)
            ->assertSee('pbr-premium-tool-system-v2.js', false);
    }

    $operatingTools = ChapterTool::query()
        ->published()
        ->whereNotIn('slug', array_merge(pbrV2ExcludedSlugs(), $chapterOneSlugs))
        ->get();

    expect($operatingTools)->toHaveCount(57);

    foreach ($operatingTools as $tool) {
        $this->get(route('workspaces.tools.operating.show', [$workspace, $tool->slug]))
            ->assertOk()
            ->assertSee('data-pbr-premium-tool', false)
            ->assertSee('pbr-premium-tool-system-v2.css', false)
            ->assertSee('pbr-premium-tool-system-v2.js', false);
    }
});

test('startup capital route does not load v2 and working capital remains controlled by v11', function () {
    app(CourseCatalogSeeder::class)->run();

    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);

    $newWorkspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'Frozen Startup Reference',
        'business_name' => 'Frozen Startup Reference',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $existingWorkspace = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'Frozen Working Capital Reference',
        'business_name' => 'Frozen Working Capital Reference',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $this->actingAs($student)
        ->get(route('workspaces.tools.startup-capital.show', $newWorkspace))
        ->assertOk()
        ->assertDontSee('pbr-premium-tool-system-v2.css', false)
        ->assertDontSee('pbr-premium-tool-system-v2.js', false);

    $this->get(route('workspaces.tools.chapter-one.show', [
        $existingWorkspace,
        'working-capital-calculator',
    ]))
        ->assertOk()
        ->assertSee('pbr-premium-tool-design-system-v11.css', false)
        ->assertSee('pbr-premium-tool-design-system-v11.js', false)
        ->assertSee('pbr-premium-tool-system-v2.css', false)
        ->assertSee('pbr-premium-tool-system-v2.js', false);
});
