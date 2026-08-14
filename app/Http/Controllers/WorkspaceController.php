<?php

namespace App\Http\Controllers;

use App\Models\BusinessFeasibilityAssessment;
use App\Models\BusinessValuation;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceMember;
use App\Services\PbrTools\PbrBusinessOperatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(
        Request $request,
        PbrBusinessOperatingService $businessOperatingSystem
    ): View {
        $user = $request->user();

        abort_unless(
            $user->canAccessBusinessOperatingSystem(),
            403
        );

        $workspaces = PartnershipWorkspace::query()
            ->when(! $user->isAdmin(), function ($query) use ($user): void {
                $query->where(function ($workspaceQuery) use ($user): void {
                    if ($user->isStudent()) {
                        $workspaceQuery
                            ->where('owner_user_id', $user->id)
                            ->orWhereHas('memberships', function ($membershipQuery) use ($user): void {
                                $membershipQuery
                                    ->where('user_id', $user->id)
                                    ->where('member_role', 'partner')
                                    ->where('invitation_status', 'accepted');
                            });

                        return;
                    }

                    $workspaceQuery->whereHas(
                        'memberships',
                        function ($membershipQuery) use ($user): void {
                            $membershipQuery
                                ->where('user_id', $user->id)
                                ->where('member_role', 'partner')
                                ->where('invitation_status', 'accepted');
                        }
                    );
                });
            })
            ->with(['owner', 'acceptedMemberships'])
            ->latest()
            ->get();

        $businesses = $workspaces
            ->map(function (PartnershipWorkspace $workspace) use (
                $user,
                $businessOperatingSystem
            ): array {
                $state = $businessOperatingSystem->workspaceState($user, $workspace);
                $status = $this->portfolioStatus($state, (bool) $state['can_manage']);

                return [
                    'workspace' => $workspace,
                    'can_manage' => $state['can_manage'],
                    'metrics' => $state['metrics'],
                    'status' => $status,
                    'next_action' => $state['action_items']->first(),
                    'systems' => $state['systems'],
                ];
            })
            ->sortBy(fn (array $business): int => (int) $business['status']['rank'])
            ->values();

        $portfolioSummary = [
            'business_count' => $businesses->count(),
            'owned_count' => $businesses
                ->filter(fn (array $business): bool => (bool) $business['can_manage'])
                ->count(),
            'partner_access_count' => $businesses
                ->reject(fn (array $business): bool => (bool) $business['can_manage'])
                ->count(),
            'needs_attention_count' => $businesses
                ->filter(fn (array $business): bool => in_array(
                    $business['status']['key'],
                    ['needs_action', 'needs_review'],
                    true
                ))
                ->count(),
            'setup_required_count' => $businesses
                ->where('status.key', 'setup_required')
                ->count(),
        ];

        $canCreateBusiness = $user->isAdmin() || $user->isStudent();

        return view('workspaces.index', compact(
            'user',
            'businesses',
            'portfolioSummary',
            'canCreateBusiness'
        ));
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

        $validated = $this->validateBusiness($request);

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
            ->with('success', 'Business အသစ်ကို အောင်မြင်စွာ ဖန်တီးပြီးပါပြီ။');
    }

    public function edit(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->authorizeManagement($request, $workspace);

        return view('workspaces.edit', [
            'workspace' => $workspace,
            'businessStages' => PartnershipWorkspace::BUSINESS_STAGES,
            'currencies' => PartnershipWorkspace::CURRENCIES,
        ]);
    }

    public function update(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->authorizeManagement($request, $workspace);

        $validated = $this->validateBusiness($request);

        $workspace->update([
            'name' => $validated['business_name'],
            'business_name' => $validated['business_name'],
            'business_stage' => $validated['business_stage'],
            'currency_code' => $validated['currency_code'],
        ]);

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with('success', 'Business အချက်အလက်တွေကို Update လုပ်ပြီးပါပြီ။');
    }

    public function destroy(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->authorizeManagement($request, $workspace);

        $validated = $request->validate([
            'confirmation_name' => ['required', 'string', 'max:160'],
        ]);

        $expected = trim((string) ($workspace->business_name ?: $workspace->name));
        $provided = trim($validated['confirmation_name']);

        if (! hash_equals($expected, $provided)) {
            throw ValidationException::withMessages([
                'confirmation_name' => 'Business Name ကို အတိအကျ ရိုက်ထည့်မှ ဖျက်နိုင်ပါတယ်။',
            ]);
        }

        DB::transaction(function () use ($workspace): void {
            BusinessFeasibilityAssessment::query()
                ->where('workspace_id', $workspace->id)
                ->delete();

            BusinessValuation::query()
                ->where('workspace_id', $workspace->id)
                ->delete();

            $workspace->toolOutputs()->delete();
            $workspace->toolSessions()->delete();
            $workspace->memberships()->delete();
            $workspace->delete();
        });

        return redirect()
            ->route('workspaces.index')
            ->with('success', 'Business နဲ့ ဆက်စပ် Workspace Data တွေကို အပြီးဖျက်ပြီးပါပြီ။');
    }

    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrBusinessOperatingService $businessOperatingSystem
    ): View {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $workspace->load([
            'owner',
            'memberships' => fn ($query) => $query
                ->with(['user', 'invitedBy'])
                ->latest('id'),
            'acceptedMemberships.user',
        ]);

        $canManageBusiness = $request->user()->isAdmin()
            || (
                $request->user()->isStudent()
                && (int) $workspace->owner_user_id === (int) $request->user()->id
            );

        $canManageInvitations = $canManageBusiness;
        $canUsePbrAiAdvisor = $request->user()->canUsePbrAiAdvisor();

        $businessState = $businessOperatingSystem->workspaceState(
            $request->user(),
            $workspace
        );

        return view('workspaces.show', compact(
            'workspace',
            'canManageBusiness',
            'canManageInvitations',
            'canUsePbrAiAdvisor',
            'businessState'
        ));
    }

    private function portfolioStatus(array $state, bool $canManage): array
    {
        if (! $canManage) {
            return [
                'key' => 'partner_access',
                'rank' => 50,
                'label_mm' => 'ကြည့်ရှုရန်သာ',
                'label_en' => 'Partner View',
            ];
        }

        $metrics = $state['metrics'] ?? [];
        $fundingGap = (float) ($metrics['funding_gap'] ?? 0);
        $workingChanges = (int) ($metrics['working_change_count'] ?? 0);
        $notConfigured = (int) ($metrics['not_configured_area_count'] ?? 0);

        if ($fundingGap > 0) {
            return [
                'key' => 'needs_action',
                'rank' => 10,
                'label_mm' => 'Action လိုသည်',
                'label_en' => 'Needs Action',
            ];
        }

        if ($workingChanges > 0) {
            return [
                'key' => 'needs_review',
                'rank' => 20,
                'label_mm' => 'Review လိုသည်',
                'label_en' => 'Needs Review',
            ];
        }

        if ($notConfigured > 0) {
            return [
                'key' => 'setup_required',
                'rank' => 30,
                'label_mm' => 'Setup လိုသည်',
                'label_en' => 'Setup Required',
            ];
        }

        return [
            'key' => 'stable',
            'rank' => 40,
            'label_mm' => 'ပုံမှန်',
            'label_en' => 'Stable',
        ];
    }

    private function authorizeManagement(Request $request, PartnershipWorkspace $workspace): void
    {
        abort_unless(
            $request->user()->isAdmin()
                || (
                    $request->user()->isStudent()
                    && (int) $workspace->owner_user_id
                        === (int) $request->user()->id
                ),
            403
        );
    }

    private function validateBusiness(Request $request): array
    {
        return $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'business_stage' => ['required', 'in:new,existing'],
            'currency_code' => ['required', 'in:THB,MMK,USD,SGD,MYR'],
        ]);
    }
}
