<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPortalAccess
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasActivePortalAccess()) {
            return $next($request);
        }

        if (! $user) {
            return redirect()
                ->route('student.login')
                ->withErrors([
                    'email' => 'Please log in to access the PBR Business Operating System.',
                ]);
        }

        return redirect()
            ->route('home')
            ->withErrors([
                'email' => 'This account does not currently have PBR Business OS access.',
            ]);
    }
}
