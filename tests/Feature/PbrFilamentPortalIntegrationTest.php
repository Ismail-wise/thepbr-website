<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\PortalAccessEvents\Pages\ListPortalAccessEvents;
use App\Filament\Resources\PortalAccessEvents\PortalAccessEventResource;
use App\Filament\Resources\StudentEnrollments\Pages\ListStudentEnrollments;
use App\Filament\Resources\UserAccess\Pages\ListUserAccess;
use App\Filament\Resources\UserAccess\UserAccessResource;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\VideoResource;
use App\Filament\Widgets\PortalAccessWidget;
use App\Models\Article;
use App\Models\PortalAccessEvent;
use App\Models\PortalConversation;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\Video;
use App\Services\AccessAdministrationService;
use App\Services\Portal\StudentPortalAccess;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function filamentPortalAccount(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'public', 'is_admin' => false, 'account_status' => 'active',
        'portal_access_expires_at' => null,
    ], $attributes));
}

beforeEach(function (): void {
    config(['partner_portal.enabled' => true, 'app.display_timezone' => 'Asia/Bangkok']);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-17 07:00:00', 'UTC'));
    $this->actingAs(filamentPortalAccount(['role' => 'admin', 'is_admin' => true]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

test('admin public login returns to original Filament while students retain the new launcher', function (): void {
    $admin = auth()->user();
    Auth::logout();
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('filament.admin.pages.dashboard'));
    $this->get(route('account.dashboard'))->assertRedirect('/admin');
    $this->get('/admin')->assertOk()->assertSee('Articles')->assertSee('Videos')->assertSee('Accounts &amp; Access', false)
        ->assertSee('Open User Portal');
    $this->get(route('portal.home'))->assertOk()->assertSee('Admin Portal')->assertSee('PBR AI');

    Auth::logout();
    $student = filamentPortalAccount(['role' => 'student', 'portal_access_expires_at' => now()->addDays(30)]);
    $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
        ->assertRedirect(route('portal.home'));
    $this->get(route('account.dashboard'))->assertRedirect(route('portal.home'));
    $this->get(route('portal.home'))->assertOk()->assertSee('PBR AI')->assertDontSee('Admin Portal');
});

test('old custom admin bookmark redirects into the existing Filament access resource', function (): void {
    $this->get(route('portal.accounts'))->assertRedirect(UserAccessResource::getUrl('index', ['tableSearch' => ''], panel: 'admin'));
    $this->get(route('portal.accounts', ['search' => 'someone@example.com']))
        ->assertRedirect(UserAccessResource::getUrl('index', ['tableSearch' => 'someone@example.com'], panel: 'admin'));
});

test('Filament custom day action activates extends and audits the real user entitlement', function (): void {
    $admin = auth()->user();
    $account = filamentPortalAccount(['created_at' => now()->subMonths(2)]);
    $component = Livewire::test(ListUserAccess::class)
        ->callAction(TestAction::make('grantDays')->table($account), data: ['days' => 30])
        ->assertHasNoFormErrors();
    expect($account->fresh()->isStudent())->toBeTrue()
        ->and($account->studentEnrollments()->first()->started_at->equalTo(now()))->toBeTrue();
    $component->callAction(TestAction::make('grantDays')->table($account), data: ['days' => 14])->assertHasNoFormErrors();
    expect($account->studentEnrollments()->first()->access_expires_at->equalTo(now()->addDays(44)))->toBeTrue();
    $this->assertDatabaseHas('portal_access_events', ['user_id' => $account->id, 'actor_id' => $admin->id, 'action' => 'activated', 'days' => 30]);
    $this->assertDatabaseHas('portal_access_events', ['user_id' => $account->id, 'action' => 'extended', 'days' => 14]);
    $this->actingAs($account->fresh())->get(route('portal.home'))->assertOk();
});

test('Filament validates duration and protects existing lifetime users', function (): void {
    $pending = filamentPortalAccount();
    Livewire::test(ListUserAccess::class)
        ->callAction(TestAction::make('grantDays')->table($pending), data: ['days' => 0])
        ->assertHasFormErrors(['days']);
    expect($pending->studentEnrollments()->count())->toBe(0)->and(PortalAccessEvent::count())->toBe(0);
    $lifetime = filamentPortalAccount(['role' => 'student']);
    Livewire::test(ListUserAccess::class)
        ->assertActionHidden(TestAction::make('grantDays')->table($lifetime))
        ->assertActionHidden(TestAction::make('grantYear')->table($lifetime));
    expect(fn () => app(AccessAdministrationService::class)->grantOneYearStudentAccess(auth()->user(), $lifetime))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($lifetime->studentEnrollments()->count())->toBe(0);
});

test('original one year action preserves unused legacy time when creating an authoritative enrollment', function (): void {
    $expiry = now()->addDays(500);
    $account = filamentPortalAccount(['role' => 'student', 'portal_access_expires_at' => $expiry]);
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('grantYear')->table($account))->assertHasNoFormErrors();
    expect($account->studentEnrollments()->first()->access_expires_at->equalTo($expiry->copy()->addYear()))->toBeTrue();
    $this->assertDatabaseHas('portal_access_events', ['user_id' => $account->id, 'action' => 'granted_year']);
});

test('original year renewal restarts a suspended portal term now instead of reviving its old future expiry', function (): void {
    $account = filamentPortalAccount();
    $enrollment = StudentEnrollment::create([
        'user_id' => $account->id, 'status' => 'active', 'started_at' => now()->subMonth(),
        'access_expires_at' => now()->addDays(100),
    ]);
    app(StudentPortalAccess::class)->suspend(auth()->user(), $account);
    Livewire::test(ListStudentEnrollments::class)->callAction(TestAction::make('renewYear')->table($enrollment))->assertHasNoFormErrors();
    expect($enrollment->fresh()->started_at->equalTo(now()))->toBeTrue()
        ->and($enrollment->fresh()->access_expires_at->equalTo(now()->addYear()))->toBeTrue();
});

test('Filament suspension closes every entitlement and reactivation retains private conversations', function (): void {
    $account = filamentPortalAccount();
    foreach ([30, 60] as $days) {
        StudentEnrollment::create(['user_id' => $account->id, 'status' => 'active', 'started_at' => now(), 'access_expires_at' => now()->addDays($days)]);
    }
    $chat = PortalConversation::create(['user_id' => $account->id, 'title' => 'Private existing conversation']);
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('suspendPortal')->table($account))->assertHasNoFormErrors();
    expect($account->fresh()->isStudent())->toBeFalse()
        ->and($account->studentEnrollments()->where('status', 'active')->count())->toBe(0);
    $this->actingAs($account->fresh())->get(route('portal.home'))->assertRedirect(route('portal.access'));
    $this->actingAs(User::where('role', 'admin')->first());
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('grantDays')->table($account), data: ['days' => 30]);
    expect($account->fresh()->isStudent())->toBeTrue()->and($chat->fresh()->title)->toBe('Private existing conversation');
    expect($account->studentEnrollments()->latest('id')->first()->access_expires_at->equalTo(now()->addDays(30)))->toBeTrue();
});

test('Filament status filters use current expiry and authoritative enrollments rather than legacy labels', function (): void {
    $pending = filamentPortalAccount();
    $legacy = filamentPortalAccount(['role' => 'student', 'portal_access_expires_at' => now()->addDays(3)]);
    $expired = filamentPortalAccount(['role' => 'student', 'portal_access_expires_at' => now()->subSecond()]);
    $suspended = filamentPortalAccount(['role' => 'student']);
    StudentEnrollment::create(['user_id' => $suspended->id, 'status' => 'revoked']);
    $inactive = filamentPortalAccount(['account_status' => 'inactive']);
    $mixed = filamentPortalAccount();
    StudentEnrollment::create(['user_id' => $mixed->id, 'status' => 'revoked']);
    StudentEnrollment::create(['user_id' => $mixed->id, 'status' => 'active', 'access_expires_at' => now()->addDays(60)]);
    $lifetime = filamentPortalAccount();
    StudentEnrollment::create(['user_id' => $lifetime->id, 'status' => 'active']);
    $adminFlag = filamentPortalAccount(['is_admin' => true]);

    foreach (['pending', 'active', 'expired', 'suspended', 'expiring'] as $status) {
        $expected = User::all()->filter(fn (User $user): bool => $status === 'expiring'
            ? $user->id === $legacy->id
            : app(StudentPortalAccess::class)->summary($user)['status'] === $status);
        Livewire::test(ListUserAccess::class)->filterTable('portal_status', $status)
            ->assertCanSeeTableRecords($expected)->assertCountTableRecords($expected->count());
    }
    Livewire::test(ListUserAccess::class)->filterTable('portal_status', 'untrusted')->assertCountTableRecords(0);
    $stats = (new ReflectionMethod(PortalAccessWidget::class, 'getStats'))->invoke(new PortalAccessWidget);
    expect(array_map(fn ($stat) => $stat->getValue(), $stats))->toBe(['1', '3', '1']);
});

test('expiring filter excludes an active lifetime or a longer second entitlement', function (): void {
    foreach ([null, now()->addDays(60)] as $laterExpiry) {
        $account = filamentPortalAccount();
        foreach ([now()->addDay(), $laterExpiry] as $expiry) {
            StudentEnrollment::create(['user_id' => $account->id, 'status' => 'active', 'access_expires_at' => $expiry]);
        }
    }
    Livewire::test(ListUserAccess::class)->filterTable('portal_status', 'expiring')->assertCountTableRecords(0);
});

test('original year lifetime and account actions share the access audit history', function (): void {
    $account = filamentPortalAccount();
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('grantYear')->table($account))->assertHasNoFormErrors();
    $enrollment = $account->studentEnrollments()->firstOrFail();
    Livewire::test(ListStudentEnrollments::class)->callAction(TestAction::make('renewYear')->table($enrollment))->assertHasNoFormErrors();
    Livewire::test(ListStudentEnrollments::class)->callAction(TestAction::make('grantLifetime')->table($enrollment))->assertHasNoFormErrors();
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('deactivate')->table($account))->assertHasNoFormErrors();
    Livewire::test(ListUserAccess::class)->callAction(TestAction::make('activate')->table($account))->assertHasNoFormErrors();
    expect(PortalAccessEvent::where('user_id', $account->id)->pluck('action')->all())
        ->toBe(['granted_year', 'renewed_year', 'granted_lifetime', 'account_deactivated', 'account_activated']);
    expect($account->studentEnrollments()->first()->access_expires_at)->toBeNull();
    Livewire::test(ListPortalAccessEvents::class)->filterTable('user', $account->id)->assertCountTableRecords(5);
    expect(PortalAccessEventResource::canCreate())->toBeFalse()
        ->and(PortalAccessEventResource::canEdit(PortalAccessEvent::first()))->toBeFalse()
        ->and(PortalAccessEventResource::canDelete(PortalAccessEvent::first()))->toBeFalse();
});

test('existing Filament article form saves drafts edits and publishes to the public website', function (): void {
    $admin = auth()->user();
    Livewire::test(CreateArticle::class)->fillForm([
        'title' => 'Filament Partnership Article', 'slug' => 'filament-partnership-article',
        'category' => 'Agreement', 'excerpt' => 'Public partnership article excerpt.',
        'body' => 'Partnership business article body.', 'published_at' => null,
    ])->call('create')->assertHasNoFormErrors();
    $article = Article::where('slug', 'filament-partnership-article')->firstOrFail();
    Auth::logout();
    $this->get('/articles/'.$article->slug)->assertNotFound();
    $this->actingAs($admin);
    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->fillForm([
        'title' => 'Published Partnership Article', 'published_at' => '2026-10-17 13:00:00',
    ])->call('save')->assertHasNoFormErrors();
    expect($article->fresh()->published_at->format('Y-m-d H:i:s'))->toBe('2026-10-17 06:00:00');
    Auth::logout();
    $this->get('/articles')->assertOk()->assertSee('Published Partnership Article');
    $this->get('/articles/'.$article->slug)->assertOk()->assertSee('Partnership business article body.');
});

test('existing Filament video form accepts a YouTube link and publishes the edited video publicly', function (): void {
    $admin = auth()->user();
    Livewire::test(CreateVideo::class)->fillForm([
        'title' => 'Filament Partnership Video', 'slug' => 'filament-partnership-video',
        'youtube_id' => 'https://youtu.be/dQw4w9WgXcQ?si=fixture', 'category' => 'Capital',
        'excerpt' => 'Public partnership video excerpt.', 'duration_minutes' => 8,
        'published_at' => '2026-10-18 13:00:00',
    ])->call('create')->assertHasNoFormErrors();
    $video = Video::where('slug', 'filament-partnership-video')->firstOrFail();
    expect($video->youtube_id)->toBe('dQw4w9WgXcQ');
    Auth::logout();
    $this->get('/videos/'.$video->slug)->assertNotFound();
    $this->get('/videos')->assertDontSee('Filament Partnership Video');
    $this->actingAs($admin);
    Livewire::test(EditVideo::class, ['record' => $video->getRouteKey()])->fillForm([
        'title' => 'Published Partnership Video', 'published_at' => '2026-10-17 13:00:00',
    ])->call('save')->assertHasNoFormErrors();
    Auth::logout();
    $this->get('/videos')->assertOk()->assertSee('Published Partnership Video');
    $this->get('/videos/'.$video->slug)->assertOk()->assertSee('dQw4w9WgXcQ');
});

test('ordinary users cannot open admin publishing accounts or access history pages', function (): void {
    $this->actingAs(filamentPortalAccount(['role' => 'student']));
    foreach (['/admin', ArticleResource::getUrl('create'), VideoResource::getUrl('create'), UserAccessResource::getUrl('index'), PortalAccessEventResource::getUrl('index')] as $url) {
        $this->get($url)->assertForbidden();
    }
});

test('disabling the new portal preserves the original Filament content and access screens', function (): void {
    config(['partner_portal.enabled' => false]);
    $account = filamentPortalAccount();
    $this->get(ArticleResource::getUrl('create'))->assertOk();
    $this->get(VideoResource::getUrl('create'))->assertOk();
    Livewire::test(ListUserAccess::class)->assertActionHidden(TestAction::make('grantDays')->table($account))
        ->callAction(TestAction::make('grantYear')->table($account))->assertHasNoFormErrors();
    expect($account->fresh()->isStudent())->toBeTrue()->and(PortalAccessEvent::count())->toBe(0)
        ->and(PortalAccessEventResource::canViewAny())->toBeFalse()->and(PortalAccessWidget::canView())->toBeFalse();
});
