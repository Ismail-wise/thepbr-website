<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $workspaces = PartnershipWorkspace::query()
            ->when(! $user->isAdmin(), function ($query) use ($user): void {
                $query->where(function ($workspaceQuery) use ($user): void {
                    $workspaceQuery
                        ->where('owner_user_id', $user->id)
                        ->orWhereHas('memberships', function ($membershipQuery) use ($user): void {
                            $membershipQuery
                                ->where('user_id', $user->id)
                                ->where('invitation_status', 'accepted');
                        });
                });
            })
            ->with(['owner', 'acceptedMemberships'])
            ->latest()
            ->get();

        return view('workspaces.index', compact('user', 'workspaces'));
    }

    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $workspace->load([
            'owner',
            'acceptedMemberships.user',
        ]);

        return view('workspaces.show', compact('workspace'));
    }
}
