<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPortalAccess
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->isStudent() && $user->hasActivePortalAccess()) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('student.login')
            ->withErrors([
                'email' => 'Your Student Portal access is not active. Please contact the PBR team.',
            ]);
    }
}
