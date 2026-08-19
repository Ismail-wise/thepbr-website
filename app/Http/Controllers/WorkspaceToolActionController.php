<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\PbrToolActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceToolActionController extends Controller
{
    public function update(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspaceToolAction $action,
        PbrToolActionService $actions
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:open,in_progress,blocked,completed',
            ],
        ]);

        $actions->changeStatus(
            $request->user(),
            $workspace,
            $action,
            $validated['status']
        );

        return back()->with(
            'status',
            'Operating Action status ပြောင်းပြီးပါပြီ။'
        );
    }
}
