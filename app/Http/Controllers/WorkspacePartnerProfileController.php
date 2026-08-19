<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspacePartnerProfile;
use App\Services\PbrTools\PbrOperatingSystemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspacePartnerProfileController extends Controller
{
    public function index(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrOperatingSystemService $operatingSystem
    ): View {
        $this->authorizeManagement($request, $workspace, $operatingSystem);
        $operatingSystem->syncWorkspacePartners($workspace);
        $workspace->load(['owner', 'acceptedMemberships.user']);

        $ownerHasLoginAccess = (bool) $workspace->owner?->canAccessWorkspace($workspace);

        $acceptedPartnerUserIds = $workspace->acceptedMemberships
            ->filter(fn ($membership): bool =>
                $membership->member_role === 'partner'
                && $membership->user_id !== null
                && (bool) $membership->user?->hasActiveAccount())
            ->map(fn ($membership): int => (int) $membership->user_id)
            ->values();

        $profiles = WorkspacePartnerProfile::query()
            ->where('workspace_id', $workspace->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        return view('workspaces.tools.partner-roster', compact(
            'workspace',
            'profiles',
            'acceptedPartnerUserIds',
            'ownerHasLoginAccess'
        ));
    }

    public function store(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrOperatingSystemService $operatingSystem
    ): RedirectResponse {
        $this->authorizeManagement($request, $workspace, $operatingSystem);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'role_title' => ['nullable', 'string', 'max:160'],
            'contribution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $operatingSystem->addPlannedPartner(
            $request->user(),
            $workspace,
            $validated['display_name'],
            [
                'workspace_role' => 'planned_partner',
                'role_title' => $validated['role_title'] ?? null,
                'contribution_note' => $validated['contribution_note'] ?? null,
            ]
        );

        return redirect()
            ->route('workspaces.partner-roster.index', $workspace)
            ->with('success', 'Planned Partner ကို Partner Roster ထဲထည့်ပြီးပါပြီ။');
    }

    public function update(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspacePartnerProfile $profile,
        PbrOperatingSystemService $operatingSystem
    ): RedirectResponse {
        $this->authorizeManagement($request, $workspace, $operatingSystem);
        $this->assertBelongsToWorkspace($workspace, $profile);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'role_title' => ['nullable', 'string', 'max:160'],
            'contribution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $profileData = array_merge($profile->profile_data ?? [], [
            'role_title' => $validated['role_title'] ?? null,
            'contribution_note' => $validated['contribution_note'] ?? null,
        ]);

        $updates = ['profile_data' => $profileData];

        // Linked account names come from the actual PBR user account. Planned
        // partners may be freely renamed before they are invited/connected.
        if ($profile->user_id === null) {
            $updates['display_name'] = trim($validated['display_name']);
        }

        $profile->update($updates);

        return redirect()
            ->route('workspaces.partner-roster.index', $workspace)
            ->with('success', 'Partner Roster information ကို update လုပ်ပြီးပါပြီ။');
    }

    public function destroy(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspacePartnerProfile $profile,
        PbrOperatingSystemService $operatingSystem
    ): RedirectResponse {
        $this->authorizeManagement($request, $workspace, $operatingSystem);
        $this->assertBelongsToWorkspace($workspace, $profile);

        abort_if(
            $profile->user_id !== null,
            422,
            'Connected PBR account ကို Partner Roster ကနေတိုက်ရိုက်ဖျက်လို့မရပါ။ Workspace invitation/membership flow ကိုအသုံးပြုပါ။'
        );

        $profile->delete();

        return redirect()
            ->route('workspaces.partner-roster.index', $workspace)
            ->with('success', 'Planned Partner ကို Partner Roster ကနေဖယ်ရှားပြီးပါပြီ။');
    }

    private function authorizeManagement(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrOperatingSystemService $operatingSystem
    ): void {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace)
            && $operatingSystem->canManage($request->user(), $workspace),
            403
        );
    }

    private function assertBelongsToWorkspace(
        PartnershipWorkspace $workspace,
        WorkspacePartnerProfile $profile
    ): void {
        abort_unless(
            (int) $profile->workspace_id === (int) $workspace->id,
            404
        );
    }
}
