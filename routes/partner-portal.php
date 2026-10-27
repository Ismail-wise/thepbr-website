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
        $plans = \App\Http\Controllers\PortalBusinessPlanController::class;
        Route::get('/{workspace}/business-plan', [$plans, 'edit'])->name('business-plan.edit');
        Route::post('/{workspace}/business-plan/draft', [$plans, 'save'])->middleware('throttle:20,1')->name('business-plan.save');
        Route::get('/{workspace}/business-plan/review', [$plans, 'review'])->name('business-plan.review');
        Route::post('/{workspace}/business-plan/confirm', [$plans, 'confirm'])->middleware('throttle:20,1')->name('business-plan.confirm');
        Route::get('/{workspace}/business-plan/history', [$plans, 'history'])->name('business-plan.history');
        Route::get('/{workspace}/business-plan/revisions/{revision}', [$plans, 'showRevision'])->whereNumber('revision')->name('business-plan.revision');
        Route::post('/{workspace}/business-plan/revisions/{revision}/draft', [$plans, 'copyRevision'])->whereNumber('revision')->middleware('throttle:20,1')->name('business-plan.copy');
        Route::post('/{workspace}/business-plan/revisions/{revision}/knowledge', [$plans, 'prepareKnowledge'])->whereNumber('revision')->middleware('throttle:20,1')->name('business-plan.knowledge');
        $knowledge = \App\Http\Controllers\PortalKnowledgeController::class;
        Route::get('/{workspace}/knowledge', [$knowledge, 'index'])->name('knowledge.index');
        Route::get('/{workspace}/knowledge/create', [$knowledge, 'create'])->name('knowledge.create');
        Route::post('/{workspace}/knowledge', [$knowledge, 'store'])->middleware('throttle:20,1')->name('knowledge.store');
        Route::get('/{workspace}/knowledge/{source}', [$knowledge, 'show'])->name('knowledge.show');
        Route::put('/{workspace}/knowledge/{source}', [$knowledge, 'update'])->middleware('throttle:20,1')->name('knowledge.update');
        Route::post('/{workspace}/knowledge/{source}/extract', [$knowledge, 'extract'])->middleware('throttle:10,1')->name('knowledge.extract');
        Route::get('/{workspace}/knowledge/{source}/original', [$knowledge, 'download'])->name('knowledge.download');
        Route::delete('/{workspace}/knowledge/{source}', [$knowledge, 'destroy'])->name('knowledge.destroy');
        $ai = \App\Http\Controllers\PortalWorkspaceAiController::class;
        Route::get('/{workspace}/ai', [$ai, 'index'])->name('ai');
        Route::post('/{workspace}/ai/chat', [$ai, 'chat'])->middleware('throttle:20,1')->name('ai.chat');
        Route::delete('/{workspace}/ai/{conversation}', [$ai, 'destroy'])->name('ai.destroy');
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
