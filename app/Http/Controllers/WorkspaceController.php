<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $canCreateBusiness = $user->isAdmin() || $user->isStudent();

        return view('workspaces.index', compact('user', 'workspaces', 'canCreateBusiness'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isStudent(), 403);

        return view('workspaces.create', [
            'businessStages' => PartnershipWorkspace::BUSINESS_STAGES,
            'currencies' => PartnershipWorkspace::CURRENCIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isStudent(), 403);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'business_stage' => ['required', 'in:new,existing'],
            'currency_code' => ['required', 'in:THB,MMK,USD,SGD,MYR'],
        ]);

        $workspace = DB::transaction(function () use ($validated, $user): PartnershipWorkspace {
            $workspace = PartnershipWorkspace::create([
                'owner_user_id' => $user->id,
                'name' => $validated['business_name'],
                'business_name' => $validated['business_name'],
                'business_stage' => $validated['business_stage'],
                'currency_code' => $validated['currency_code'],
                'status' => 'active',
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'member_role' => 'owner',
                'invitation_status' => 'accepted',
                'invited_email' => strtolower($user->email),
                'invitation_token_hash' => null,
                'invited_by_user_id' => $user->id,
                'invited_at' => now(),
                'accepted_at' => now(),
                'permissions' => null,
            ]);

            return $workspace;
        });

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with('success', 'Business Workspace အသစ် ဖန်တီးပြီးပါပြီ။');
    }

    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $workspace->load([
            'owner',
            'memberships' => fn ($query) => $query
                ->with(['user', 'invitedBy'])
                ->latest('id'),
            'acceptedMemberships.user',
        ]);

        $canManageInvitations = $request->user()->isAdmin()
            || $workspace->owner_user_id === $request->user()->id;

        return view('workspaces.show', compact('workspace', 'canManageInvitations'));
    }
}
