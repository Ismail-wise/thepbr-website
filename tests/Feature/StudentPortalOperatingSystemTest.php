<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('active student portal exposes the connected PBR operating system', function () {
    app(CourseCatalogSeeder::class)->run();

    $user = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $user->id,
        'name' => 'Portal Test Business',
        'business_name' => 'Portal Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('student.dashboard'));

    $response
        ->assertOk()
        ->assertSee('10 Chapters · 64 Tools')
        ->assertSee('Portal Test Business')
        ->assertSee('Open 64 Tools')
        ->assertSee(route('workspaces.tools.index', $workspace), false)
        ->assertDontSee('Coming Next');
});
