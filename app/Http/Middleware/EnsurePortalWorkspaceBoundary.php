<?php

namespace App\Http\Middleware;

use App\Models\PartnershipWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalWorkspaceBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('partner_portal.enabled')) {
            return $next($request);
        }
        $name = $request->route()?->getName() ?? '';
        if ($request->user() && (str_starts_with($name, 'workspaces.')
            || in_array($name, ['workspace-invitations.store', 'workspace-invitations.revoke', 'workspace-members.destroy'], true))) {
            // Legacy entry points must enforce the same boundary as the new UI.
            // In particular, neither an invited Partner nor an application Admin
            // receives access to another person's Workspace through an old URL.
            $user = $request->user();
            abort_unless($user->isStudent(), 403);
            $workspace = $request->route('workspace');
            if ($workspace !== null && ! $workspace instanceof PartnershipWorkspace) {
                $workspace = PartnershipWorkspace::query()->findOrFail($workspace);
            }
            if ($workspace instanceof PartnershipWorkspace) {
                abort_unless((int) $workspace->owner_user_id === (int) $user->id, 403);
            }
            // Team reports previously inferred consent from membership alone.
            // This release grants only personal Dynamics; sharing is separate.
            abort_if(str_starts_with($name, 'workspaces.partner-dynamics.'), 404);
            if ($request->isMethod('get')) {
                if (in_array($name, ['workspaces.index', 'workspaces.create', 'workspaces.show', 'workspaces.edit'], true)) {
                    $route = str_replace('workspaces.', 'portal.workspaces.', $name);

                    return redirect()->route($route, $workspace ? [$workspace] : []);
                }
            }
        }
        $lock = null;
        if (isset($workspace) && $workspace instanceof PartnershipWorkspace && in_array($name, ['workspaces.update', 'workspaces.destroy'], true)) {
            $lock = app(\App\Services\Portal\PrivateWorkspaceAccess::class)->lock($workspace);
        }
        try {
            if ($name === 'workspaces.destroy' && isset($workspace)) {
                // The old deletion screen has no private-file review step.
                // The Owner must remove originals through Knowledge first.
                abort_if(\App\Models\WorkspaceKnowledgeSource::where('workspace_id', $workspace->id)->exists(), 409,
                    'Workspace မဖျက်ခင် Knowledge မှာ မူရင်းဖိုင်များကို အရင်ဖယ်ပေးပါ။');
            }
            $response = $next($request);
        } finally { $lock?->release(); }
        if ($name === 'workspace-invitations.show' || str_starts_with($name, 'portal.workspaces.')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
