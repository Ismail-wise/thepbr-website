<?php

use App\Http\Controllers\WorkspaceAiAdvisorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/workspaces/{workspace}/ai-advisor',
        [WorkspaceAiAdvisorController::class, 'index']
    )->name('workspaces.ai-advisor.index');

    Route::post(
        '/workspaces/{workspace}/ai-advisor/chat',
        [WorkspaceAiAdvisorController::class, 'chat']
    )
        ->middleware('throttle:30,1')
        ->name('workspaces.ai-advisor.chat');

    Route::delete(
        '/workspaces/{workspace}/ai-advisor/conversations/{conversation}',
        [WorkspaceAiAdvisorController::class, 'destroy']
    )->name('workspaces.ai-advisor.conversations.destroy');
});
