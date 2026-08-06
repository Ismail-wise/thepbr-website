<?php

use App\Http\Controllers\AccountAuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StudentAccessRedemptionController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureStudentPortalAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/classes', [ClassController::class, 'index'])->name('classes');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AccountAuthController::class, 'showLogin'])
        ->name('login');
    Route::post('/login', [AccountAuthController::class, 'login'])
        ->name('login.store');

    Route::get('/register', [AccountAuthController::class, 'showRegister'])
        ->name('register');
    Route::post('/register', [AccountAuthController::class, 'register'])
        ->name('register.store');

    Route::get('/student/register', [StudentAuthController::class, 'showRegister'])
        ->name('student.register');
    Route::post('/student/register', [StudentAuthController::class, 'register'])
        ->name('student.register.store');

    Route::get('/student/login', fn () => redirect()->route('login'))
        ->name('student.login');
    Route::post('/student/login', [AccountAuthController::class, 'login'])
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
        ->name('account.access-code.redeem');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])
        ->name('workspaces.index');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])
        ->name('workspaces.show');
});

Route::middleware(['auth', EnsureStudentPortalAccess::class])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('/', [StudentPortalController::class, 'dashboard'])
            ->name('dashboard');
    });
