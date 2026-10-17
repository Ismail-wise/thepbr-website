<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('partner_portal.enabled'), 404);
        if (! $request->user()?->hasActivePortalAccess()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Portal ဝင်ခွင့်ကို Admin နှင့် ပြန်စစ်ပါ။', 'access_url' => route('portal.access')], 403);
            }

            return redirect()->route('portal.access');
        }

        return $next($request);
    }
}
