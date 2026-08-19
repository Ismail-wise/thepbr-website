<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('working capital v11 assets are isolated from startup capital and operating tools', function () {
    app(CourseCatalogSeeder::class)->run();

    $student = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);

    $existingBusiness = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'V11 Existing Business',
        'business_name' => 'V11 Existing Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $newBusiness = PartnershipWorkspace::query()->create([
        'owner_user_id' => $student->id,
        'name' => 'V11 New Business',
        'business_name' => 'V11 New Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $this->actingAs($student)
        ->get(route('workspaces.tools.chapter-one.show', [
            $existingBusiness,
            'working-capital-calculator',
        ]))
        ->assertOk()
        ->assertSee('pbr-premium-tool-design-system-v11.css')
        ->assertSee('pbr-premium-tool-design-system-v11.js');

    $this->get(route('workspaces.tools.operating.show', [
        $existingBusiness,
        'decision-rights-matrix',
    ]))
        ->assertOk()
        ->assertDontSee('pbr-premium-tool-design-system-v11.css')
        ->assertDontSee('pbr-premium-tool-design-system-v11.js');

    $this->get(route('workspaces.tools.startup-capital.show', $newBusiness))
        ->assertOk()
        ->assertDontSee('pbr-premium-tool-design-system-v11.css')
        ->assertDontSee('pbr-premium-tool-design-system-v11.js');
});

test('working capital v11 implements calculator first governance later and simplified plan actions', function () {
    $css = file_get_contents(
        public_path('css/pbr-premium-tool-design-system-v11.css')
    );

    $javascript = file_get_contents(
        public_path('js/pbr-premium-tool-design-system-v11.js')
    );

    expect($css)
        ->toContain('Working Capital information-architecture refinement only')
        ->toContain('[data-pbr-pilot-tool="working-capital-calculator"]')
        ->toContain('.pbr-capital-tool-step-track')
        ->toContain('.pbr-ds-v11-governance')
        ->toContain('.pbr-ds-working-plan-bar')
        ->toContain('.pbr-ds-plan-primary')
        ->toContain('.pbr-ds-plan-more')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($javascript)
        ->toContain("includes('/working-capital-calculator')")
        ->toContain('moveWorkingPlanNameIntoCalculator')
        ->toContain('moveGovernanceAfterCalculator')
        ->toContain("layout.insertAdjacentElement('afterend', details)")
        ->toContain('simplifyApproval')
        ->toContain('simplifyWorkingPlans')
        ->toContain("open.textContent = 'Continue Editing'")
        ->toContain("approveButton.textContent = 'Review & Approve'")
        ->toContain("form.hidden = true");
});
