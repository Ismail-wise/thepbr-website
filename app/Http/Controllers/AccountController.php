<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        if (config('partner_portal.enabled')) {
            return redirect()->route($request->user()->isAdmin()
                ? 'filament.admin.pages.dashboard'
                : 'portal.home');
        }
        $user = $request->user()->load([
            'studentEnrollments.classSession',
            'ownedWorkspaces',
            'workspaceMemberships.workspace',
        ]);

        return view('account.dashboard', compact('user'));
    }
}
