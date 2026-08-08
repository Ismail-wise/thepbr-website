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
    Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])
        ->middleware('throttle:workspace-invitations')
        ->name('workspace-invitations.store');
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'revoke'])
        ->name('workspace-invitations.revoke');
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
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('/', [StudentPortalController::class, 'dashboard'])
            ->name('dashboard');
    });


/*
|--------------------------------------------------------------------------
| PBR Partner Dynamics
|--------------------------------------------------------------------------
|
| Available to Admins, Students and accepted Student Partners.
| Public-only accounts cannot access the assessment.
|
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
