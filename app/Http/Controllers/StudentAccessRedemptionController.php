<?php

namespace App\Http\Controllers;

use App\Services\StudentAccessUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentAccessRedemptionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->isStudent()) {
            return redirect()
                ->route('student.dashboard')
                ->with('success', 'This account already has active Student Portal access.');
        }

        return view('account.redeem-access-code', [
            'user' => $request->user(),
        ]);
    }

    public function redeem(
        Request $request,
        StudentAccessUpgradeService $upgradeService,
    ): RedirectResponse {
        $validated = $request->validate([
            'access_code' => ['required', 'string', 'max:40'],
        ]);

        $upgradeService->upgrade(
            $request->user(),
            $validated['access_code'],
        );

        return redirect()
            ->route('student.dashboard')
            ->with('success', 'Your account has been upgraded to Student access successfully.');
    }
}
