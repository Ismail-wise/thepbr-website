<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('workspace owner can open the central operating action center', function () {
    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
        'is_admin' => false,
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Action Center Business',
        'business_name' => 'Action Center Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $this
        ->actingAs($owner)
        ->get(route('workspaces.tool-actions.index', $workspace))
        ->assertOk()
        ->assertSee('Operating Action Center')
        ->assertSee('Active Actions')
        ->assertSee('Tool 64');
});

test('business operating system exposes the central action center entry', function () {
    $controller = file_get_contents(
        app_path('Http/Controllers/WorkspaceToolsController.php')
    );

    $view = file_get_contents(
        resource_path('views/workspaces/tools/index.blade.php')
    );

    expect($controller)
        ->toContain('WorkspaceToolAction')
        ->toContain('$operatingActionSummary');

    expect($view)
        ->toContain('OPERATING ACTION CENTER')
        ->toContain("route('workspaces.tool-actions.index'");
});
