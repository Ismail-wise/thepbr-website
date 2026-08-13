<?php

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceOperatingSnapshot;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
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

test('active student portal is the real business operating system instead of a learning progress dashboard', function () {
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
        ->assertSee('PBR BUSINESS OPERATING SYSTEM')
        ->assertSee('Business တစ်ခုရွေးပြီး တိုက်ရိုက်စီမံပါ')
        ->assertSee('Portal Test Business')
        ->assertSee('Operating System ဖွင့်ရန် →')
        ->assertSee(route('workspaces.tools.index', $workspace), false)
        ->assertDontSee('10 Learning Chapters')
        ->assertDontSee('Learning Chapters')
        ->assertDontSee('Chapter Completion')
        ->assertDontSee('Open 64 Tools')
        ->assertDontSee('Coming Next');
});

test('workspace operating system is clarity first and keeps tool routes separate', function () {
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
        ->assertSee('PBR BUSINESS OPERATING SYSTEM')
        ->assertSee('လက်ရှိ Business မှာ အရင်ဆုံး စီမံရမယ့်အရာများ')
        ->assertSee('မတည်ငွေနှင့် ရင်းနှီးငွေ')
        ->assertSee('ပိုင်ဆိုင်မှုနှင့် အစုရှယ်ယာ')
        ->assertSee('အုပ်ချုပ်မှုနှင့် ဆုံးဖြတ်ချက် စနစ်')
        ->assertSee('မသတ်မှတ်ရသေး')
        ->assertSee('Current Business Rule Register')
        ->assertSee($chapterOneUrl, false)
        ->assertSee('/tools/operating/', false)
        ->assertDontSee('Operating System Completion')
        ->assertDontSee('Practical Tools')
        ->assertDontSee('Chapter 1')
        ->assertDontSee('CALCULATOR')
        ->assertDontSee('PLANNER')
        ->assertDontSee('MATRIX')
        ->assertDontSee('CHART');
});

test('capital business screen uses clear operational language instead of classroom language', function () {
    app(CourseCatalogSeeder::class)->run();

    $user = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $user->id,
        'name' => 'Capital UX Test Business',
        'business_name' => 'Capital UX Test Business',
        'business_stage' => 'existing',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspaces.tools.chapter-one.show', [
            $workspace,
            'current-capital-position',
        ]));

    $response
        ->assertOk()
        ->assertSee('Business Operating System သို့ ပြန်ရန်')
        ->assertSee('မတည်ငွေနှင့် ရင်းနှီးငွေ')
        ->assertSee('Draft အမည်')
        ->assertSee('Draft သိမ်းရန်')
        ->assertSee('Result စစ်ရန်')
        ->assertDontSee('Chapter 1')
        ->assertDontSee('Calculate / Review')
        ->assertDontSee('Save Draft');
});

test('startup capital screen is an operational planning workspace not a simple calculator', function () {
    app(CourseCatalogSeeder::class)->run();

    $user = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $user->id,
        'name' => 'Startup UX Test Business',
        'business_name' => 'Startup UX Test Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspaces.tools.startup-capital.show', $workspace));

    $response
        ->assertOk()
        ->assertSee('စတင်မတည်ငွေ အစီအစဉ်')
        ->assertSee('အသုံးများတဲ့ ကုန်ကျစရိတ်အုပ်စုကို တစ်ချက်နဲ့ထည့်ပါ')
        ->assertSee('Funding နဲ့ Due Date')
        ->assertSee('30 ရက်အတွင်းလို')
        ->assertSee('Plan အနှစ်ချုပ်')
        ->assertSee('Plan Result စစ်ရန်')
        ->assertSee('Draft သိမ်းရန်')
        ->assertSee('pbr-capital-workspace-grid', false)
        ->assertDontSee('Chapter 1')
        ->assertDontSee('Build Your Own Cost List')
        ->assertDontSee('Calculate Startup Capital');
});

test('partner sees only the active startup capital plan in a professional read only view', function () {
    app(CourseCatalogSeeder::class)->run();

    $owner = User::factory()->create([
        'role' => 'student',
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addDay(),
    ]);
    $partner = User::factory()->create([
        'role' => 'public',
        'account_status' => 'active',
    ]);

    $workspace = PartnershipWorkspace::create([
        'owner_user_id' => $owner->id,
        'name' => 'Partner Startup View Business',
        'business_name' => 'Partner Startup View Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'invited_email' => $partner->email,
        'invited_by_user_id' => $owner->id,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    $tool = ChapterTool::query()
        ->where('tool_key', 'startup_capital_planner')
        ->firstOrFail();
    $input = [
        'categories' => [[
            'name' => 'Premises',
            'items' => [[
                'name' => 'Shop Deposit',
                'amount' => 30000,
                'priority' => 'essential',
                'funded_amount' => 20000,
                'funding_source' => 'Partner A',
            ]],
        ]],
    ];
    $result = app(StartupCapitalCalculator::class)->calculate($input);
    $scenarios = app(ToolScenarioService::class);
    $session = $scenarios->saveDraft(
        $owner,
        $workspace,
        $tool,
        'Approved Launch Plan',
        $input,
        $result
    );
    $scenarios->publishAgreedOutput($owner, $workspace, $tool, $session);

    $snapshot = WorkspaceOperatingSnapshot::query()
        ->where('workspace_id', $workspace->id)
        ->where('domain_key', 'capital')
        ->where('status', 'agreed')
        ->latest('revision')
        ->firstOrFail();

    expect((float) ($snapshot->summary['capital_secured'] ?? 0))->toBe(20000.0)
        ->and((float) ($snapshot->summary['funding_gap'] ?? 0))->toBe(10000.0);

    $response = $this
        ->actingAs($partner)
        ->get(route('workspaces.tools.startup-capital.show', $workspace));

    $response
        ->assertOk()
        ->assertSee('လက်ရှိအတည်ပြုထားသော ကုန်ကျစရိတ် Plan')
        ->assertSee('Shop Deposit')
        ->assertSee('Funding ရပြီး')
        ->assertSee('လိုနေသေး')
        ->assertSee('Active Rule')
        ->assertDontSee('Draft သိမ်းရန်')
        ->assertDontSee('Chapter 1')
        ->assertDontSee('Partner Read-Only View');
});

test('dashboard polish keeps draft KPIs and partner roster semantics clear', function () {
    $javascript = file_get_contents(public_path('js/pbr-operating-system.js'));
    $styles = file_get_contents(public_path('css/pbr-operating-fixes.css'));

    expect($javascript)
        ->toContain('function polishBusinessDashboard()')
        ->toContain('Draft ခန့်မှန်းချက်')
        ->toContain('Partner Profile အရေအတွက်')
        ->toContain('အခု စစ်ဆေးရမည့်အချက်များ')
        ->toContain("value.textContent = '—'")
        ->and($styles)
        ->toContain('Executive dashboard final polish')
        ->toContain('.pbr-business-page-polished .pbr-business-hero h1')
        ->toContain('.pbr-business-metric.setup');
});
