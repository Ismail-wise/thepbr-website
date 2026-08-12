<?php

namespace App\Http\Controllers;

use App\Models\BusinessValuation;
use App\Models\PartnershipWorkspace;
use App\Services\BusinessDecision\BusinessValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceValuationController extends Controller
{
    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);
        abort_unless($workspace->isExistingPartnership(), 422);

        $latest = BusinessValuation::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->first();

        $canManageBusiness = $request->user()->isAdmin()
            || $workspace->owner_user_id === $request->user()->id;

        return view(
            'workspaces.valuation',
            compact('workspace', 'latest', 'canManageBusiness')
        );
    }

    public function calculate(
        Request $request,
        PartnershipWorkspace $workspace,
        BusinessValuationService $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->isAdmin()
                || $workspace->owner_user_id === $request->user()->id,
            403
        );
        abort_unless($workspace->isExistingPartnership(), 422);

        $validated = $request->validate([
            'annual_revenue' => ['required', 'numeric', 'min:0'],
            'ebitda' => ['required', 'numeric', 'min:0'],
            'owner_earnings' => ['required', 'numeric', 'min:0'],
            'free_cash_flow' => ['required', 'numeric', 'min:0'],
            'total_assets' => ['required', 'numeric', 'min:0'],
            'total_liabilities' => ['required', 'numeric', 'min:0'],
            'cash' => ['required', 'numeric', 'min:0'],
            'debt' => ['required', 'numeric', 'min:0'],
            'ebitda_multiple' => ['required', 'numeric', 'min:0', 'max:50'],
            'sde_multiple' => ['required', 'numeric', 'min:0', 'max:50'],
            'growth_rate' => ['required', 'numeric', 'min:-50', 'max:100'],
            'discount_rate' => ['required', 'numeric', 'min:1', 'max:60'],
            'terminal_growth' => ['required', 'numeric', 'min:0', 'max:15'],
            'recurring_revenue_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'customer_concentration_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'owner_dependency' => ['required', 'integer', 'between:1,5'],
        ]);

        if (max(
            (float) $validated['ebitda'],
            (float) $validated['owner_earnings'],
            (float) $validated['free_cash_flow'],
            (float) $validated['total_assets']
        ) <= 0) {
            throw ValidationException::withMessages([
                'annual_revenue' => 'Valuation တွက်ဖို့ EBITDA, Owner Earnings, Free Cash Flow သို့မဟုတ် Assets တစ်ခုခုမှာ Real Data လိုအပ်ပါတယ်။',
            ]);
        }

        if ((float) $validated['discount_rate'] <= (float) $validated['terminal_growth']) {
            throw ValidationException::withMessages([
                'discount_rate' => 'Discount Rate က Terminal Growth Rate ထက် မြင့်ရပါမယ်။',
            ]);
        }

        BusinessValuation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'inputs' => $validated,
            'result' => $service->calculate($validated),
        ]);

        return redirect()
            ->route('workspaces.valuation.show', $workspace)
            ->with('success', 'Business Valuation Estimate တွက်ချက်ပြီးပါပြီ။');
    }
}
