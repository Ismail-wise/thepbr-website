<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AccountAuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StudentAccessRedemptionController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Middleware\EnsureStudentPortalAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/classes', [ClassController::class, 'index'])->name('classes');

Route::get('/about', [AboutController::class, 'index'])
    ->name('about');

Route::get('/workspace-invitations/{token}', [WorkspaceInvitationController::class, 'show'])
    ->name('workspace-invitations.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AccountAuthController::class, 'showLogin'])
        ->name('login');
    Route::post('/login', [AccountAuthController::class, 'login'])
        ->middleware('throttle:account-login')
        ->name('login.store');

    Route::get('/register', [AccountAuthController::class, 'showRegister'])
        ->name('register');
    Route::post('/register', [AccountAuthController::class, 'register'])
        ->middleware('throttle:public-registration')
        ->name('register.store');

    Route::get('/student/register', [StudentAuthController::class, 'showRegister'])
        ->name('student.register');
    Route::post('/student/register', [StudentAuthController::class, 'register'])
        ->middleware('throttle:student-registration')
        ->name('student.register.store');

    Route::get('/student/login', fn () => redirect()->route('login'))
        ->name('student.login');
    Route::post('/student/login', [AccountAuthController::class, 'login'])
        ->middleware('throttle:account-login')
        ->name('student.login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AccountAuthController::class, 'logout'])
        ->name('logout');
    Route::post('/student/logout', [AccountAuthController::class, 'logout'])
        ->name('student.logout');

    Route::get('/account', [AccountController::class, 'dashboard'])
        ->name('account.dashboard');
    Route::get('/account/redeem-access-code', [StudentAccessRedemptionController::class, 'show'])
        ->name('account.access-code.show');
    Route::post('/account/redeem-access-code', [StudentAccessRedemptionController::class, 'redeem'])
        ->middleware('throttle:access-code-redemption')
        ->name('account.access-code.redeem');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])
        ->name('workspaces.index');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])
        ->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])
        ->name('workspaces.store');
    Route::get('/workspaces/{workspace}/edit', [WorkspaceController::class, 'edit'])
        ->name('workspaces.edit');
    Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])
        ->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])
        ->name('workspaces.destroy');

    Route::get('/workspaces/{workspace}/feasibility', [\App\Http\Controllers\WorkspaceFeasibilityController::class, 'show'])
        ->name('workspaces.feasibility.show');
    Route::post('/workspaces/{workspace}/feasibility', [\App\Http\Controllers\WorkspaceFeasibilityController::class, 'calculate'])
        ->name('workspaces.feasibility.calculate');

    Route::get('/workspaces/{workspace}/valuation', [\App\Http\Controllers\WorkspaceValuationController::class, 'show'])
        ->name('workspaces.valuation.show');
    Route::post('/workspaces/{workspace}/valuation', [\App\Http\Controllers\WorkspaceValuationController::class, 'calculate'])
        ->name('workspaces.valuation.calculate');

    Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])
        ->middleware('throttle:workspace-invitations')
        ->name('workspace-invitations.store');

    Route::post('/workspaces/{workspace}/shareable-invitations', [WorkspaceInvitationController::class, 'storeShareable'])
        ->middleware('throttle:workspace-invitations')
        ->name('workspace-invitations.shareable.store');

    Route::post('/workspace-invitations/connect', [WorkspaceInvitationController::class, 'connect'])
        ->middleware('throttle:workspace-invitation-accept')
        ->name('workspace-invitations.connect');
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'revoke'])
        ->name('workspace-invitations.revoke');

    Route::get(
        '/workspaces/{workspace}/tools/startup-capital-planner',
        [\App\Http\Controllers\WorkspaceStartupCapitalController::class, 'show']
    )->name('workspaces.tools.startup-capital.show');

    Route::post(
        '/workspaces/{workspace}/tools/startup-capital-planner',
        [\App\Http\Controllers\WorkspaceStartupCapitalController::class, 'calculate']
    )->name('workspaces.tools.startup-capital.calculate');

    Route::post(
        '/workspaces/{workspace}/tools/startup-capital-planner/save-draft',
        [\App\Http\Controllers\WorkspaceStartupCapitalDraftController::class, 'store']
    )->name('workspaces.tools.startup-capital.draft.store');

    Route::get(
        '/workspaces/{workspace}/tools',
        [\App\Http\Controllers\WorkspaceToolsController::class, 'index']
    )->name('workspaces.tools.index');

    Route::put(
        '/workspaces/{workspace}/business-context',
        [\App\Http\Controllers\WorkspaceToolsController::class, 'updateContext']
    )->name('workspaces.business-context.update');

    Route::get(
        '/workspaces/{workspace}/partner-roster',
        [\App\Http\Controllers\WorkspacePartnerProfileController::class, 'index']
    )->name('workspaces.partner-roster.index');

    Route::post(
        '/workspaces/{workspace}/partner-roster',
        [\App\Http\Controllers\WorkspacePartnerProfileController::class, 'store']
    )->name('workspaces.partner-roster.store');

    Route::put(
        '/workspaces/{workspace}/partner-roster/{profile}',
        [\App\Http\Controllers\WorkspacePartnerProfileController::class, 'update']
    )->name('workspaces.partner-roster.update');

    Route::delete(
        '/workspaces/{workspace}/partner-roster/{profile}',
        [\App\Http\Controllers\WorkspacePartnerProfileController::class, 'destroy']
    )->name('workspaces.partner-roster.destroy');

    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])
        ->name('workspaces.show');

    Route::get(
        '/workspaces/{workspace}/partner-dynamics',
        [\App\Http\Controllers\WorkspacePartnerDynamicsController::class, 'show']
    )->name('workspaces.partner-dynamics.show');

    Route::get(
        '/workspaces/{workspace}/partner-dynamics/profile/{assessment}',
        [\App\Http\Controllers\WorkspacePartnerDynamicsController::class, 'profile']
    )->name('workspaces.partner-dynamics.profile');

    Route::post('/workspace-invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->middleware('throttle:workspace-invitation-accept')
        ->name('workspace-invitations.accept');
});

Route::middleware(['auth', EnsureStudentPortalAccess::class])
    ->group(function (): void {
        Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])
            ->name('student.dashboard');

        Route::get('/student', fn () => redirect()->route('student.dashboard'))
            ->name('student.legacy-dashboard');
    });

/*
|--------------------------------------------------------------------------
| PBR Partner Dynamics
|--------------------------------------------------------------------------
*/
Route::middleware('auth')
    ->prefix('partner-dynamics')
    ->name('partner-dynamics.')
    ->group(function (): void {
        Route::get(
            '/',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'index']
        )->name('index');

        Route::post(
            '/start',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'start']
        )->name('start');

        Route::post(
            '/retake',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'retake']
        )->name('retake');

        Route::get(
            '/assessment/{assessment}/step/{step}',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'step']
        )
            ->whereNumber('step')
            ->name('assessment.step');

        Route::put(
            '/assessment/{assessment}/step/{step}',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'saveStep']
        )
            ->whereNumber('step')
            ->name('assessment.save');

        Route::get(
            '/result/{assessment}',
            [\App\Http\Controllers\PartnerDynamicsController::class, 'result']
        )->name('result');
    });

/*
|--------------------------------------------------------------------------
| Shared PBR Tool Scenario Actions — Chapters 1–10
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}/scenarios/{session}/rename',
        [\App\Http\Controllers\WorkspaceToolScenarioController::class, 'rename']
    )->name('workspaces.tools.scenarios.rename');

    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}/scenarios/{session}/duplicate',
        [\App\Http\Controllers\WorkspaceToolScenarioController::class, 'duplicate']
    )->name('workspaces.tools.scenarios.duplicate');

    Route::delete(
        '/workspaces/{workspace}/tools/{toolSlug}/scenarios/{session}',
        [\App\Http\Controllers\WorkspaceToolScenarioController::class, 'destroy']
    )->name('workspaces.tools.scenarios.destroy');

    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}/scenarios/{session}/output',
        [\App\Http\Controllers\WorkspaceToolScenarioController::class, 'output']
    )->name('workspaces.tools.scenarios.output');

    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}/scenarios/{session}/approve',
        [\App\Http\Controllers\WorkspaceToolScenarioController::class, 'approve']
    )->name('workspaces.tools.scenarios.approve');
});

/*
|--------------------------------------------------------------------------
| Chapter 1 Shared Capital Tools
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    $chapterOneSlugs = implode('|', [
        'current-capital-position',
        'working-capital-calculator',
        'contingency-fund-calculator',
        'partner-contribution-matrix',
        'funding-gap-calculator',
        'capital-allocation-chart',
    ]);

    Route::get(
        '/workspaces/{workspace}/tools/{toolSlug}',
        [\App\Http\Controllers\WorkspaceChapterOneToolController::class, 'show']
    )
        ->where('toolSlug', $chapterOneSlugs)
        ->name('workspaces.tools.chapter-one.show');

    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}',
        [\App\Http\Controllers\WorkspaceChapterOneToolController::class, 'calculate']
    )
        ->where('toolSlug', $chapterOneSlugs)
        ->name('workspaces.tools.chapter-one.calculate');

    Route::post(
        '/workspaces/{workspace}/tools/{toolSlug}/save-draft',
        [\App\Http\Controllers\WorkspaceChapterOneToolController::class, 'save']
    )
        ->where('toolSlug', $chapterOneSlugs)
        ->name('workspaces.tools.chapter-one.save');
});

/*
|--------------------------------------------------------------------------
| Chapters 2–10 Connected Operating Tools
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::get(
        '/workspaces/{workspace}/tools/operating/{toolSlug}',
        [\App\Http\Controllers\WorkspaceOperatingToolController::class, 'show']
    )->name('workspaces.tools.operating.show');

    Route::post(
        '/workspaces/{workspace}/tools/operating/{toolSlug}',
        [\App\Http\Controllers\WorkspaceOperatingToolController::class, 'calculate']
    )->name('workspaces.tools.operating.calculate');

    Route::post(
        '/workspaces/{workspace}/tools/operating/{toolSlug}/save-draft',
        [\App\Http\Controllers\WorkspaceOperatingToolController::class, 'save']
    )
        ->where('toolSlug', '.*')
        ->name('workspaces.tools.operating.save');
});

require __DIR__.'/ai.php';
