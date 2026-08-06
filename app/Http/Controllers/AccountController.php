<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user()->load([
            'studentEnrollments.classSession',
            'ownedWorkspaces',
            'workspaceMemberships.workspace',
        ]);

        return view('account.dashboard', compact('user'));
    }
}
