<?php

namespace App\Http\Controllers;

use App\Models\BusinessFeasibilityAssessment;
use App\Models\PartnershipWorkspace;
use App\Services\BusinessDecision\BusinessFeasibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceFeasibilityController extends Controller
{
    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $latest = BusinessFeasibilityAssessment::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->first();

        $canManageBusiness = $request->user()->isAdmin()
            || $workspace->owner_user_id === $request->user()->id;

        return view(
            'workspaces.feasibility',
            compact('workspace', 'latest', 'canManageBusiness')
        );
    }

    public function calculate(
        Request $request,
        PartnershipWorkspace $workspace,
        BusinessFeasibilityService $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->isAdmin()
                || $workspace->owner_user_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'project_name' => ['nullable', 'string', 'max:160'],
            'startup_cost' => ['required', 'numeric', 'min:0'],
            'available_capital' => ['required', 'numeric', 'min:0'],
            'monthly_fixed_cost' => ['required', 'numeric', 'min:0'],
            'monthly_expected_revenue' => ['required', 'numeric', 'min:0'],
            'reserve_months' => ['required', 'numeric', 'min:0', 'max:60'],
            'market_demand' => ['required', 'integer', 'between:1,5'],
            'customer_validation' => ['required', 'integer', 'between:1,5'],
            'competitive_advantage' => ['required', 'integer', 'between:1,5'],
            'team_experience' => ['required', 'integer', 'between:1,5'],
            'operational_readiness' => ['required', 'integer', 'between:1,5'],
            'partner_alignment' => ['required', 'integer', 'between:1,5'],
            'legal_readiness' => ['required', 'integer', 'between:1,5'],
            'sales_readiness' => ['required', 'integer', 'between:1,5'],
        ]);

        BusinessFeasibilityAssessment::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'project_name' => $validated['project_name'] ?? null,
            'inputs' => $validated,
            'result' => $service->calculate($validated),
        ]);

        return redirect()
            ->route('workspaces.feasibility.show', $workspace)
            ->with('success', 'Feasibility Assessment တွက်ချက်ပြီးပါပြီ။');
    }
}
