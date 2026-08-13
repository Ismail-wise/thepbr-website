<?php

namespace App\Http\Controllers;

use App\Services\PbrTools\PbrBusinessOperatingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function dashboard(
        Request $request,
        PbrBusinessOperatingService $businessOperatingSystem
    ): View {
        $user = $request->user()->load([
            'ownedWorkspaces',
            'workspaceMemberships.workspace',
        ]);

        $workspaces = $user->ownedWorkspaces
            ->concat(
                $user->workspaceMemberships
                    ->where('invitation_status', 'accepted')
                    ->pluck('workspace')
                    ->filter()
            )
            ->unique('id')
            ->values();

        $businesses = $workspaces->map(function ($workspace) use (
            $user,
            $businessOperatingSystem
        ): array {
            $state = $businessOperatingSystem->workspaceState($user, $workspace);

            return [
                'workspace' => $workspace,
                'can_manage' => $state['can_manage'],
                'metrics' => $state['metrics'],
                'action_items' => $state['action_items']->take(3)->values(),
                'systems' => $state['systems'],
            ];
        });

        $portfolioMetrics = [
            'business_count' => $businesses->count(),
            'businesses_needing_attention' => $businesses
                ->filter(fn (array $business) => $business['action_items']->isNotEmpty())
                ->count(),
            'active_rule_count' => $businesses
                ->sum(fn (array $business) => (int) $business['metrics']['active_rule_count']),
            'working_change_count' => $businesses
                ->sum(fn (array $business) => (int) $business['metrics']['working_change_count']),
        ];

        return view('student.dashboard', compact(
            'user',
            'businesses',
            'portfolioMetrics'
        ));
    }
}
