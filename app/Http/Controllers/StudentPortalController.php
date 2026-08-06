<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user()->load([
            'classSession',
            'usedAccessCode',
            'studentEnrollments.classSession',
            'ownedWorkspaces',
            'workspaceMemberships.workspace',
        ]);

        return view('student.dashboard', compact('user'));
    }
}
