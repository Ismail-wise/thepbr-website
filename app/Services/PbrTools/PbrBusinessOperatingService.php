<?php

namespace App\Services\PbrTools;

use App\Models\CourseChapter;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspaceToolOutput;
use Illuminate\Support\Collection;

class PbrBusinessOperatingService
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem,
        private readonly ChapterOneIntegrationService $capitalIntegration
    ) {
    }

    public function area(int $internalNumber): array
    {
        $area = config('pbr_business_operating_system.areas.'.$internalNumber);

        abort_unless(is_array($area), 404);

        return array_merge(['internal_number' => $internalNumber], $area);
    }

    public function areaForDomain(string $domain): array
    {
        foreach (config('pbr_business_operating_system.areas', []) as $number => $area) {
            if (($area['domain'] ?? null) === $domain) {
                return array_merge(['internal_number' => (int) $number], $area);
            }
        }

        abort(404);
    }

    public function workspaceState(User $actor, PartnershipWorkspace $workspace): array
    {
        $canManage = $this->operatingSystem->canManage($actor, $workspace);

        if ($canManage) {
            $this->operatingSystem->syncWorkspacePartners($workspace);
        }

        $workspace->loadMissing([
            'owner:id,name',
            'acceptedMemberships.user:id,name',
            'partnerProfiles',
        ]);

        $businessStage = $workspace->business_stage;
        $chapters = CourseChapter::query()
            ->with([
                'tools' => function ($query) use ($businessStage): void {
                    $query->orderBy('sort_order');

                    if ($businessStage === 'new') {
                        $query->where('supports_new_business', true);
                    } elseif ($businessStage === 'existing') {
                        $query->where('supports_existing_business', true);
                    }
                },
            ])
            ->orderBy('chapter_number')
            ->get();

        $latestAgreedOutputs = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->keyBy(fn ($output) => (int) $output->chapter_tool_id);

        $latestDraftSessions = $canManage
            ? ToolSession::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'draft')
                ->orderByDesc('last_saved_at')
                ->orderByDesc('id')
                ->get()
                ->unique('chapter_tool_id')
                ->keyBy(fn ($session) => (int) $session->chapter_tool_id)
            : collect();

        $snapshots = WorkspaceOperatingSnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->when(! $canManage, fn ($query) => $query->where('status', 'agreed'))
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('domain_key')
            ->keyBy('domain_key');

        $toolDefinitions = config('pbr_operating_tools.definitions', []);
        $capitalOverrides = config('pbr_business_operating_system.capital_module_overrides', []);
        $states = config('pbr_business_operating_system.states', []);
        $systems = [];

        foreach ($chapters as $chapter) {
            $number = (int) $chapter->chapter_number;
            $area = $this->area($number);
            $domainSnapshot = $snapshots->get($area['domain']);
            $modules = [];
            $activeCount = 0;
            $workingCount = 0;

            foreach ($chapter->tools as $tool) {
                $toolId = (int) $tool->id;
                $agreedOutput = $latestAgreedOutputs->get($toolId);
                $draftSession = $latestDraftSessions->get($toolId);

                if ($agreedOutput) {
                    $activeCount++;
                }

                if ($draftSession) {
                    $workingCount++;
                }

                $moduleStateKey = $draftSession
                    ? 'review'
                    : ($agreedOutput ? 'active' : 'setup');
                $definition = $toolDefinitions[$tool->tool_key] ?? [];
                $override = $number === 1
                    ? ($capitalOverrides[$tool->tool_key] ?? [])
                    : [];

                $modules[] = [
                    'id' => $toolId,
                    'key' => $tool->tool_key,
                    'slug' => $tool->slug,
                    'title_mm' => $override['title_mm']
                        ?? $definition['title_mm']
                        ?? $tool->title_mm
                        ?? $tool->title_en,
                    'title_en' => $override['title_en'] ?? $tool->title_en,
                    'purpose_mm' => $override['purpose_mm']
                        ?? $definition['purpose_mm']
                        ?? $tool->description,
                    'action_mm' => $canManage
                        ? ($override['action_mm'] ?? 'စီမံရန် →')
                        : 'အတည်ပြုထားသော အချက်အလက် ကြည့်ရန် →',
                    'state' => $this->state($moduleStateKey, $states),
                    'active_revision' => $agreedOutput?->revision,
                    'agreed_at' => $agreedOutput?->agreed_at,
                    'draft_id' => $draftSession?->id,
                    'draft_updated_at' => $draftSession?->last_saved_at,
                    'url' => $this->toolUrl($workspace, $number, $tool->tool_key, $tool->slug),
                ];
            }

            $hasAgreed = $activeCount > 0 || $domainSnapshot?->status === 'agreed';
            $hasWorkingChange = $canManage
                && ($workingCount > 0 || $domainSnapshot?->status === 'draft');

            $stateKey = $hasWorkingChange
                ? 'review'
                : ($hasAgreed ? 'active' : 'setup');

            $systems[] = array_merge($area, [
                'state' => $this->state($stateKey, $states),
                'active_count' => $activeCount,
                'working_count' => $workingCount,
                'module_count' => count($modules),
                'modules' => $modules,
                'snapshot_revision' => $domainSnapshot?->revision,
                'snapshot_status' => $domainSnapshot?->status,
                'snapshot_summary' => is_array($domainSnapshot?->summary)
                    ? $domainSnapshot->summary
                    : [],
                'last_agreed_at' => $domainSnapshot?->agreed_at,
                'url' => route('workspaces.tools.index', $workspace).'#system-'.$area['slug'],
            ]);
        }

        $systemsCollection = collect($systems);
        $capitalSummary = $this->capitalSummary($actor, $workspace, $canManage, $snapshots);
        $fundingGap = (float) ($capitalSummary['funding_gap'] ?? 0);
        $capitalRequired = (float) ($capitalSummary['capital_required'] ?? 0);
        $capitalSecured = (float) ($capitalSummary['capital_secured'] ?? 0);

        $actionItems = $this->actionItems(
            $systemsCollection,
            $workspace,
            $fundingGap,
            $canManage
        );

        return [
            'can_manage' => $canManage,
            'systems' => $systemsCollection,
            'system_map' => $systemsCollection->keyBy('domain'),
            'action_items' => $actionItems,
            'capital' => $capitalSummary,
            'metrics' => [
                'capital_required' => $capitalRequired,
                'capital_secured' => $capitalSecured,
                'funding_gap' => $fundingGap,
                'partner_count' => $this->partnerCount($workspace, $canManage),
                'active_rule_count' => $latestAgreedOutputs->count(),
                'working_change_count' => $latestDraftSessions->count(),
                'active_area_count' => $systemsCollection
                    ->where('state.key', 'active')
                    ->count(),
                'review_area_count' => $systemsCollection
                    ->where('state.key', 'review')
                    ->count(),
                'not_configured_area_count' => $systemsCollection
                    ->where('state.key', 'setup')
                    ->count(),
                'operating_record_count' => $workspace->operatingRecords()->count(),
            ],
        ];
    }

    public function areaForToolChapter(int $chapterNumber): array
    {
        return $this->area($chapterNumber);
    }

    private function capitalSummary(
        User $actor,
        PartnershipWorkspace $workspace,
        bool $canManage,
        Collection $snapshots
    ): array {
        if ($canManage) {
            return $this->capitalIntegration->summary($workspace);
        }

        $summary = $snapshots->get('capital')?->summary;

        return is_array($summary) ? $summary : [];
    }

    private function partnerCount(PartnershipWorkspace $workspace, bool $canManage): int
    {
        $acceptedUserIds = collect([$workspace->owner_user_id])
            ->merge($workspace->acceptedMemberships->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if (! $canManage) {
            return $acceptedUserIds->count();
        }

        $profiles = $workspace->partnerProfiles
            ->whereIn('status', ['active', 'planned']);
        $profileUserIds = $profiles
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $profiles->count()
            + $acceptedUserIds->diff($profileUserIds)->count();
    }

    private function state(string $key, array $states): array
    {
        return array_merge(['key' => $key], $states[$key] ?? []);
    }

    private function toolUrl(
        PartnershipWorkspace $workspace,
        int $internalNumber,
        string $toolKey,
        string $slug
    ): string {
        if ($toolKey === 'startup_capital_planner' && $workspace->business_stage === 'new') {
            return route('workspaces.tools.startup-capital.show', $workspace);
        }

        if ($internalNumber === 1) {
            return route('workspaces.tools.chapter-one.show', [$workspace, $slug]);
        }

        return route('workspaces.tools.operating.show', [$workspace, $slug]);
    }

    private function actionItems(
        Collection $systems,
        PartnershipWorkspace $workspace,
        float $fundingGap,
        bool $canManage
    ): Collection {
        $actions = collect();
        $currency = $workspace->currency_code ?? 'THB';

        if ($fundingGap > 0) {
            $actions->push([
                'priority' => 0,
                'level' => 'high',
                'domain' => 'capital',
                'title_mm' => 'လိုအပ်နေသေးသော ရင်းနှီးငွေ ရှိသည်',
                'detail_mm' => $currency.' '.number_format($fundingGap, 2).' Funding Gap ကို ဖြည့်မယ့် အရင်းအမြစ်နဲ့ အချိန်ကို သတ်မှတ်ပါ။',
                'action_mm' => $canManage ? 'Capital & Funding ကို စီမံရန် →' : 'Capital အခြေအနေ ကြည့်ရန် →',
                'url' => route('workspaces.tools.index', $workspace).'#system-capital',
            ]);
        }

        foreach ($systems as $system) {
            $stateKey = $system['state']['key'] ?? 'setup';

            if ($stateKey === 'active') {
                continue;
            }

            if ($stateKey === 'review') {
                $actions->push([
                    'priority' => (int) ($system['priority'] ?? 50),
                    'level' => 'high',
                    'domain' => $system['domain'],
                    'title_mm' => $system['name_mm'].' ပြန်လည်စစ်ဆေးရန်ရှိသည်',
                    'detail_mm' => 'Working Draft / ပြောင်းလဲမှုအသစ် ရှိနေပါတယ်။ လက်ရှိ Active Rule ကို မပြောင်းခင် Review လုပ်ပြီးမှ အတည်ပြုပါ။',
                    'action_mm' => $canManage ? 'Review လုပ်ရန် →' : 'လက်ရှိ Rule ကြည့်ရန် →',
                    'url' => $system['url'],
                ]);
                continue;
            }

            $actions->push([
                'priority' => 100 + (int) ($system['priority'] ?? 50),
                'level' => (int) ($system['priority'] ?? 50) <= 6 ? 'medium' : 'normal',
                'domain' => $system['domain'],
                'title_mm' => $system['name_mm'].' မသတ်မှတ်ရသေး',
                'detail_mm' => $system['short_mm'],
                'action_mm' => $canManage ? 'စတင်စီမံရန် →' : 'အခြေအနေ ကြည့်ရန် →',
                'url' => $system['url'],
            ]);
        }

        return $actions
            ->sortBy('priority')
            ->values();
    }
}
