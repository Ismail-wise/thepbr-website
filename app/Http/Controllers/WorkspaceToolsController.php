<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\PbrBusinessOperatingService;
use App\Services\PbrTools\PbrOperatingSystemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceToolsController extends Controller
{
    public function index(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrOperatingSystemService $operatingSystem,
        PbrBusinessOperatingService $businessOperatingSystem
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $workspace->load([
            'owner',
            'acceptedMemberships.user',
        ]);

        $canManageContext = $operatingSystem->canManage(
            $request->user(),
            $workspace
        );

        $businessState = $businessOperatingSystem->workspaceState(
            $request->user(),
            $workspace
        );

        $businessStages = PartnershipWorkspace::BUSINESS_STAGES;
        $currencies = PartnershipWorkspace::CURRENCIES;

        return view('workspaces.tools.index', compact(
            'workspace',
            'canManageContext',
            'businessStages',
            'currencies',
            'businessState'
        ));
    }

    public function updateContext(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $request->user()->isAdmin()
            || (int) $workspace->owner_user_id === (int) $request->user()->id,
            403
        );

        $validated = $request->validate([
            'business_stage' => [
                'required',
                Rule::in(array_keys(PartnershipWorkspace::BUSINESS_STAGES)),
            ],
            'currency_code' => [
                'required',
                Rule::in(array_keys(PartnershipWorkspace::CURRENCIES)),
            ],
        ]);

        $workspace->update($validated);

        return redirect()
            ->route('workspaces.tools.index', $workspace)
            ->with('success', 'Business settings ကို သိမ်းပြီးပါပြီ။');
    }
}
