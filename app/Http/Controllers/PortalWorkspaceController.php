<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $this->student($request);

        return view('portal.workspaces.index', [
            'workspaces' => $request->user()->ownedWorkspaces()->latest('id')->paginate(12),
        ]);
    }

    public function create(Request $request): View
    {
        $this->student($request);

        return view('portal.workspaces.form', ['workspace' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->student($request);
        $values = $this->values($request);
        $workspace = DB::transaction(function () use ($request, $values): PartnershipWorkspace {
            $workspace = $request->user()->ownedWorkspaces()->create([
                'name' => $values['name'],
                'business_name' => $values['name'],
                'description' => $values['description'],
                'status' => 'active',
            ]);
            $workspace->memberships()->create([
                'user_id' => $request->user()->id,
                'member_role' => 'owner',
                'invitation_status' => 'accepted',
                'invited_email' => strtolower($request->user()->email),
                'invited_by_user_id' => $request->user()->id,
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);

            return $workspace;
        });

        return redirect()->route('portal.workspaces.show', $workspace)
            ->with('success', 'Workspace အသစ် ဖန်တီးပြီးပါပြီ။');
    }

    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->owner($request, $workspace);

        return view('portal.workspaces.show', compact('workspace'));
    }

    public function edit(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->owner($request, $workspace);

        return view('portal.workspaces.form', compact('workspace'));
    }

    public function update(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->owner($request, $workspace);
        $values = $this->values($request);
        $workspace->update($values + ['business_name' => $values['name']]);

        return redirect()->route('portal.workspaces.show', $workspace)
            ->with('success', 'Workspace အချက်အလက် သိမ်းပြီးပါပြီ။');
    }

    public function partners(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->owner($request, $workspace);
        $memberships = $workspace->memberships()->where('member_role', 'partner')
            ->with('user')->latest('id')->paginate(20);

        return view('portal.workspaces.partners', compact('workspace', 'memberships'));
    }

    private function values(Request $request): array
    {
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'description' => is_string($request->input('description')) ? trim($request->input('description')) : $request->input('description'),
        ]);
        $values = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        return ['name' => $values['name'], 'description' => filled($values['description'] ?? null) ? $values['description'] : null];
    }

    private function student(Request $request): void
    {
        abort_unless(config('partner_portal.enabled'), 404);
        abort_unless($request->user()?->isStudent(), 403);
    }

    private function owner(Request $request, PartnershipWorkspace $workspace): void
    {
        $this->student($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 404);
    }
}
