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

        $businesses = $workspaces
            ->map(function ($workspace) use (
                $user,
                $businessOperatingSystem
            ): array {
                $state = $businessOperatingSystem->workspaceState($user, $workspace);
                $status = $this->portfolioStatus($state);
                $actions = $state['action_items']->values();

                return [
                    'workspace' => $workspace,
                    'can_manage' => $state['can_manage'],
                    'metrics' => $state['metrics'],
                    'status' => $status,
                    'next_action' => $actions->first(),
                    'action_items' => $actions->take(3)->values(),
                    'systems' => $state['systems'],
                ];
            })
            ->sortBy(fn (array $business): int => (int) $business['status']['rank'])
            ->values();

        $portfolioMetrics = [
            'business_count' => $businesses->count(),
            'needs_action_count' => $businesses
                ->where('status.key', 'needs_action')
                ->count(),
            'needs_review_count' => $businesses
                ->where('status.key', 'needs_review')
                ->count(),
            'setup_required_count' => $businesses
                ->where('status.key', 'setup_required')
                ->count(),
            'stable_count' => $businesses
                ->where('status.key', 'stable')
                ->count(),

            // Kept for compatibility with existing consumers. Setup-only work
            // is intentionally not counted as urgent portfolio attention.
            'businesses_needing_attention' => $businesses
                ->filter(fn (array $business): bool => in_array(
                    $business['status']['key'],
                    ['needs_action', 'needs_review'],
                    true
                ))
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

    private function portfolioStatus(array $state): array
    {
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
                'detail_mm' => 'Funding Gap သို့မဟုတ် ငွေကြေးဆိုင်ရာ လုပ်ဆောင်ချက် ရှိနေသည်။',
            ];
        }

        if ($workingChanges > 0) {
            return [
                'key' => 'needs_review',
                'rank' => 20,
                'label_mm' => 'Review လိုသည်',
                'label_en' => 'Needs Review',
                'detail_mm' => 'Active Rule မပြောင်းခင် ပြန်စစ်ပြီး အတည်ပြုရမည့် Working Change ရှိနေသည်။',
            ];
        }

        if ($notConfigured > 0) {
            return [
                'key' => 'setup_required',
                'rank' => 30,
                'label_mm' => 'Setup လိုသည်',
                'label_en' => 'Setup Required',
                'detail_mm' => 'အရေးပေါ်ပြဿနာမဟုတ်ဘဲ Operating Area တချို့ကို စတင်သတ်မှတ်ရန် ကျန်နေသည်။',
            ];
        }

        return [
            'key' => 'stable',
            'rank' => 40,
            'label_mm' => 'ပုံမှန်',
            'label_en' => 'Stable',
            'detail_mm' => 'လက်ရှိမှတ်တမ်းအရ Review သို့မဟုတ် Setup လုပ်ရန်ကျန်သည့် အချက်မရှိပါ။',
        ];
    }
}
