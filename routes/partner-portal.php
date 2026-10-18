<?php

use App\Http\Controllers\PartnerPortalController;
use App\Http\Controllers\PortalAiController;
use App\Http\Controllers\PortalToolsController;
use App\Http\Middleware\EnsurePartnerPortalAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('portal')->name('portal.')->group(function (): void {
    Route::get('/access', [PartnerPortalController::class, 'access'])->name('access');
    Route::prefix('workspaces')->name('workspaces.')->group(function (): void {
        $controller = \App\Http\Controllers\PortalWorkspaceController::class;
        Route::get('/', [$controller, 'index'])->name('index');
        Route::get('/create', [$controller, 'create'])->name('create');
        Route::post('/', [$controller, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{workspace}', [$controller, 'show'])->name('show');
        Route::get('/{workspace}/edit', [$controller, 'edit'])->name('edit');
        Route::put('/{workspace}', [$controller, 'update'])->name('update');
        Route::get('/{workspace}/partners', [$controller, 'partners'])->name('partners');
    });
    Route::middleware(EnsurePartnerPortalAccess::class)->group(function (): void {
        Route::get('/', [PartnerPortalController::class, 'home'])->name('home');
        Route::get('/ai', [PortalAiController::class, 'index'])->name('ai');
        Route::post('/ai/chat', [PortalAiController::class, 'chat'])->middleware('throttle:20,1')->name('ai.chat');
        Route::delete('/ai/{conversation}', [PortalAiController::class, 'destroy'])->name('ai.destroy');
        Route::get('/tools/{tool}', [PortalToolsController::class, 'show'])->name('tool');
        Route::post('/tools/{tool}', [PortalToolsController::class, 'save'])->name('tool.save');
        Route::get('/library', [PartnerPortalController::class, 'library'])->name('library');
        Route::get('/saved/{item}', [PartnerPortalController::class, 'saved'])->name('saved');
        Route::delete('/saved/{item}', [PartnerPortalController::class, 'deleteSaved'])->name('saved.destroy');
        Route::get('/admin/accounts', [PartnerPortalController::class, 'accounts'])->name('accounts');
        Route::post('/admin/accounts/{account}/access', [PartnerPortalController::class, 'grant'])->name('grant');
        Route::post('/admin/accounts/{account}/suspend', [PartnerPortalController::class, 'suspend'])->name('suspend');
    });
});
