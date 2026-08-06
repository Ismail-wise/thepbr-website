<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Middleware\EnsureStudentPortalAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/classes', [ClassController::class, 'index'])->name('classes');

Route::get('/login', fn () => redirect()->route('student.login'))->name('login');

Route::middleware('guest')->group(function (): void {
    Route::get('/student/register', [StudentAuthController::class, 'showRegister'])
        ->name('student.register');
    Route::post('/student/register', [StudentAuthController::class, 'register'])
        ->name('student.register.store');

    Route::get('/student/login', [StudentAuthController::class, 'showLogin'])
        ->name('student.login');
    Route::post('/student/login', [StudentAuthController::class, 'login'])
        ->name('student.login.store');
});

Route::post('/student/logout', [StudentAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('student.logout');

Route::middleware(['auth', EnsureStudentPortalAccess::class])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('/', [StudentPortalController::class, 'dashboard'])
            ->name('dashboard');
    });
