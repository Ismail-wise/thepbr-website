<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('PBR course catalog contains ten connected chapters and sixty four unique tools', function () {
    $chapters = config('pbr_course.chapters', []);
    $toolKeys = collect($chapters)
        ->flatMap(fn (array $chapter) => collect($chapter['tools'] ?? [])->pluck('key'))
        ->values();

    expect($chapters)->toHaveCount(10)
        ->and($toolKeys)->toHaveCount(64)
        ->and($toolKeys->unique())->toHaveCount(64)
        ->and(collect($chapters)->pluck('number')->all())->toBe(range(1, 10));

    $domains = \App\Services\PbrTools\PbrOperatingSystemService::DOMAINS;

    expect(array_keys($domains))->toBe(range(1, 10))
        ->and(array_values($domains))->toBe([
            'capital',
            'ownership',
            'contribution',
            'distribution',
            'financial_controls',
            'governance',
            'exit',
            'continuity',
            'share_transfer',
            'dispute_resolution',
        ]);
});

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

test('workspace tools dashboard keeps chapter one and operating routes registered separately', function () {
    app(CourseCatalogSeeder::class)->run();

    expect(Route::has('workspaces.tools.chapter-one.show'))->toBeTrue()
        ->and(Route::has('workspaces.tools.chapter-one.calculate'))->toBeTrue()
        ->and(Route::has('workspaces.tools.chapter-one.save'))->toBeTrue()
        ->and(Route::has('workspaces.tools.operating.show'))->toBeTrue()
        ->and(Route::has('workspaces.tools.operating.calculate'))->toBeTrue()
        ->and(Route::has('workspaces.tools.operating.save'))->toBeTrue();

    $user = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $user->id,
        'name' => 'Tools Dashboard Test Business',
        'business_name' => 'Tools Dashboard Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $chapterOneUrl = route('workspaces.tools.chapter-one.show', [
        $workspace,
        'current-capital-position',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspaces.tools.index', $workspace));

    $response
        ->assertOk()
        ->assertSee('Partnership Business Operating System')
        ->assertSee($chapterOneUrl, false)
        ->assertSee('/tools/operating/', false);
});
