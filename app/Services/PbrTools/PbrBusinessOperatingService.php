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
        private readonly ChapterOneIntegrationService $capitalIntegration,
        private readonly PbrToolRuntimeContractService $runtimeContracts,
        private readonly PbrBusinessJourneyService $journey
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
                ->whereDoesntHave(
                    'workspaceOutputs',
                    fn ($query) => $query->where('status', 'agreed')
                )
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

        $agreedSnapshots = WorkspaceOperatingSnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('domain_key')
            ->keyBy('domain_key');

        $toolDefinitions = config('pbr_operating_tools.definitions', []);
        $capitalOverrides = config('pbr_business_operating_system.capital_module_overrides', []);
        $states = config('pbr_business_operating_system.states', []);
        $systems = [];
        $activeRuleTotal = 0;

        foreach ($chapters as $chapter) {
            $number = (int) $chapter->chapter_number;
            $area = $this->area($number);
            $domainSnapshot = $snapshots->get($area['domain']);
            $agreedDomainSnapshot = $agreedSnapshots->get($area['domain']);
            $modules = [];
            $activeCount = 0;
            $workingCount = 0;
            $ruleModuleCount = 0;
            $recordModuleCount = 0;
            $dependencyReviewCount = 0;

            foreach ($chapter->tools as $tool) {
                $toolId = (int) $tool->id;
                $agreedOutput = $latestAgreedOutputs->get($toolId);
                $draftSession = $latestDraftSessions->get($toolId);
                $definition = $toolDefinitions[$tool->tool_key] ?? [];
                $runtimeContract =
                    $this->runtimeContracts->forTool($tool);

                if ($runtimeContract['is_record']) {
                    $recordModuleCount++;
                } else {
                    $ruleModuleCount++;
                }

                /*
                 * Approved downstream rules remain current until explicitly
                 * replaced. If an approved upstream source changed later,
                 * flag the downstream rule for human review rather than
                 * silently recalculating or replacing it.
                 */
                $staleSourceDomains = [];

                if (
                    $agreedOutput
                    && ! $runtimeContract['is_record']
                    && $agreedOutput->agreed_at
                ) {
                    foreach (
                        $runtimeContract[
                            'prefill_sources'
                        ] ?? []
                        as $sourceDomain
                    ) {
                        if (
                            $sourceDomain
                            === $area['domain']
                        ) {
                            continue;
                        }

                        $sourceSnapshot =
                            $agreedSnapshots->get(
                                $sourceDomain
                            );

                        if (
                            ! $sourceSnapshot
                            || ! $sourceSnapshot
                                ->agreed_at
                        ) {
                            continue;
                        }

                        if (
                            $sourceSnapshot
                                ->agreed_at
                                ->gt(
                                    $agreedOutput
                                        ->agreed_at
                                )
                        ) {
                            $staleSourceDomains[] =
                                $sourceDomain;
                        }
                    }
                }

                $staleSourceDomains =
                    array_values(
                        array_unique(
                            $staleSourceDomains
                        )
                    );

                if ($staleSourceDomains !== []) {
                    $dependencyReviewCount++;
                }

                if (
                    $agreedOutput
                    && ! $runtimeContract['is_record']
                ) {
                    $activeCount++;
                    $activeRuleTotal++;
                }

                if ($draftSession) {
                    $workingCount++;
                }

                $moduleStateKey = $draftSession
                    ? 'review'
                    : ($agreedOutput ? 'active' : 'setup');

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
                    'active_result' => is_array($agreedOutput?->output_data)
                        ? $agreedOutput->output_data
                        : [],
                    'active_generated_at' => $agreedOutput?->generated_at,
                    'agreed_at' => $agreedOutput?->agreed_at,
                    'draft_id' => $draftSession?->id,
                    'draft_updated_at' => $draftSession?->last_saved_at,
                    'runtime_mode' =>
                        $runtimeContract['mode'],
                    'is_record' =>
                        $runtimeContract['is_record'],
                    'record_type' =>
                        $runtimeContract['record_type'],
                    'connected_sources' =>
                        $runtimeContract['prefill_sources'],
                    'dependency_review_required' =>
                        $staleSourceDomains !== [],
                    'stale_source_domains' =>
                        $staleSourceDomains,
                    'stale_source_labels' =>
                        collect(
                            $staleSourceDomains
                        )
                            ->map(
                                fn (
                                    string $source
                                ): string =>
                                    (string) (
                                        config(
                                            'pbr_canonical_data.domains.'
                                            .$source
                                            .'.name'
                                        )
                                        ?? str($source)
                                            ->replace(
                                                '_',
                                                ' '
                                            )
                                            ->title()
                                    )
                            )
                            ->values()
                            ->all(),
                    'url' => $this->toolUrl($workspace, $number, $tool->tool_key, $tool->slug),
                ];
            }

            $missingRuleCount = max(
                0,
                $ruleModuleCount - $activeCount
            );

            $ruleCompletionPercent =
                $ruleModuleCount > 0
                    ? (int) round(
                        $activeCount
                        / $ruleModuleCount
                        * 100
                    )
                    : 100;

            // Historical draft snapshots are audit history. Only a currently
            // open ToolSession represents a live working change that needs
            // action, so deleting or approving a draft clears Review state.
            $hasAgreed = $activeCount > 0 || $agreedDomainSnapshot !== null;
            $hasWorkingChange = $canManage && $workingCount > 0;

            $stateKey = $hasWorkingChange
                ? 'review'
                : ($hasAgreed ? 'active' : 'setup');

            $systems[] = array_merge($area, [
                'state' => $this->state($stateKey, $states),
                'active_count' => $activeCount,
                'working_count' => $workingCount,
                'module_count' => count($modules),
                'rule_module_count' =>
                    $ruleModuleCount,
                'record_module_count' =>
                    $recordModuleCount,
                'missing_rule_count' =>
                    $missingRuleCount,
                'rule_completion_percent' =>
                    $ruleCompletionPercent,
                'dependency_review_count' =>
                    $dependencyReviewCount,
                'modules' => $modules,
                'snapshot_revision' => $domainSnapshot?->revision,
                'snapshot_status' => $domainSnapshot?->status,
                'snapshot_summary' => is_array($domainSnapshot?->summary)
                    ? $domainSnapshot->summary
                    : [],
                'active_snapshot_revision' => $agreedDomainSnapshot?->revision,
                'active_snapshot_summary' => is_array($agreedDomainSnapshot?->summary)
                    ? $agreedDomainSnapshot->summary
                    : [],
                'last_agreed_at' => $agreedDomainSnapshot?->agreed_at,
                'url' => route('workspaces.tools.index', $workspace).'#system-'.$area['slug'],
            ]);
        }

        $systemsCollection = collect($systems);
        $capitalState = $this->capitalState($workspace, $canManage);
        $capitalSummary = $capitalState['summary'];
        $fundingGap = (float) ($capitalSummary['funding_gap'] ?? 0);
        $capitalRequired = (float) ($capitalSummary['capital_required'] ?? 0);
        $capitalSecured = (float) ($capitalSummary['capital_secured'] ?? 0);

        $actionItems = $this->actionItems(
            $systemsCollection,
            $workspace,
            $fundingGap,
            $canManage
        );

        $journey = $this->journey->build(
            $systemsCollection,
            $actionItems,
            $canManage
        );

        $totalRuleCount =
            (int) $systemsCollection->sum(
                'rule_module_count'
            );

        $dependencyReviewTotal =
            (int) $systemsCollection->sum(
                'dependency_review_count'
            );

        return [
            'can_manage' => $canManage,
            'systems' => $systemsCollection,
            'system_map' => $systemsCollection->keyBy('domain'),
            'action_items' => $actionItems,
            'journey' => $journey,
            'capital' => $capitalSummary,
            'capital_source' => $capitalState['source'],
            'active_rules' => $systemsCollection
                ->flatMap(fn (array $system) => collect($system['modules'])
                    ->filter(
                        fn (array $module): bool =>
                            ! empty(
                                $module[
                                    'active_revision'
                                ]
                            )
                            && ! (
                                $module[
                                    'is_record'
                                ]
                                ?? false
                            )
                    )
                    ->map(fn (array $module) => array_merge($module, [
                        'domain' => $system['domain'],
                        'area_name_mm' => $system['name_mm'],
                        'area_name_en' => $system['name_en'],
                    ])))
                ->values(),
            'metrics' => [
                'capital_required' => $capitalRequired,
                'capital_secured' => $capitalSecured,
                'funding_gap' => $fundingGap,
                'capital_data_available' =>
                    $capitalState['source'] !== 'none',
                'capital_data_source' =>
                    $capitalState['source'],
                'capital_has_approved_data' =>
                    $capitalState['source'] === 'active',
                'partner_count' => $this->partnerCount($workspace, $canManage),
                'active_rule_count' => $activeRuleTotal,
                'total_rule_count' =>
                    $totalRuleCount,
                'rule_completion_percent' =>
                    $totalRuleCount > 0
                        ? (int) round(
                            $activeRuleTotal
                            / $totalRuleCount
                            * 100
                        )
                        : 100,
                'dependency_review_count' =>
                    $dependencyReviewTotal,
                'incomplete_area_count' =>
                    $systemsCollection
                        ->filter(
                            fn (array $system): bool =>
                                (
                                    $system[
                                        'missing_rule_count'
                                    ] ?? 0
                                ) > 0
                        )
                        ->count(),
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

    private function capitalState(
        PartnershipWorkspace $workspace,
        bool $canManage
    ): array {
        // Current dashboards must never silently switch to a proposed draft.
        // Once a capital rule has been approved, the latest agreed snapshot is
        // the source of truth until another version is explicitly approved.
        $agreedSnapshot = $this->operatingSystem->latestSnapshot(
            $workspace,
            'capital',
            'agreed'
        );

        if ($agreedSnapshot && is_array($agreedSnapshot->summary)) {
            return [
                'source' => 'active',
                'summary' => $agreedSnapshot->summary,
            ];
        }

        if ($canManage) {
            $working = $this->capitalIntegration->summary($workspace);
            $hasWorkingData = collect($working)
                ->except(['outputs', 'allocations'])
                ->contains(fn ($value) => is_numeric($value) && (float) $value !== 0.0);

            return [
                'source' => $hasWorkingData ? 'working' : 'none',
                'summary' => $working,
            ];
        }

        return [
            'source' => 'none',
            'summary' => [],
        ];
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
                'kind' => 'funding_gap',
                'domain' => 'capital',
                'title_mm' => 'လိုအပ်နေသေးသော ရင်းနှီးငွေ ရှိသည်',
                'detail_mm' => $currency.' '.number_format($fundingGap, 2).' Funding Gap ကို ဖြည့်မယ့် အရင်းအမြစ်နဲ့ အချိန်ကို သတ်မှတ်ပါ။',
                'action_mm' => $canManage ? 'Capital & Funding ကို စီမံရန် →' : 'Capital အခြေအနေ ကြည့်ရန် →',
                'url' => route('workspaces.tools.index', $workspace).'#system-capital',
            ]);
        }

        foreach ($systems as $system) {
            $modules = collect(
                $system['modules'] ?? []
            );

            /*
             * First priority inside an area is an actual live Working Change.
             * Partners never receive draft metadata because their state does
             * not contain manager ToolSessions.
             */
            $reviewModule = $canManage
                ? $modules->first(
                    fn (array $module): bool =>
                        (
                            $module['state']['key']
                            ?? null
                        ) === 'review'
                )
                : null;

            if ($reviewModule) {
                $actions->push([
                    'priority' =>
                        10
                        + (int) (
                            $system['priority']
                            ?? 50
                        ),
                    'level' => 'high',
                    'kind' => 'working_change',
                    'domain' =>
                        $system['domain'],
                    'module_key' =>
                        $reviewModule['key'],
                    'title_mm' =>
                        $reviewModule['title_mm']
                        .' ပြန်လည်စစ်ဆေးရန်ရှိသည်',
                    'detail_mm' =>
                        'Working Change အသစ် ရှိနေပါတယ်။ Current Rule ကို မပြောင်းခင် result နဲ့ approval readiness ကို review လုပ်ပါ။',
                    'action_mm' =>
                        'Working Change ကို Review လုပ်ရန် →',
                    'url' =>
                        $reviewModule['url'],
                ]);

                continue;
            }

            /*
             * A newer approved upstream rule does not invalidate or replace a
             * downstream Current Rule. It creates a review signal only.
             */
            $dependencyModule = $modules
                ->first(
                    fn (array $module): bool =>
                        ! (
                            $module['is_record']
                            ?? false
                        )
                        && (
                            $module[
                                'dependency_review_required'
                            ]
                            ?? false
                        )
                );

            if ($dependencyModule) {
                $sourceNames = implode(
                    ', ',
                    $dependencyModule[
                        'stale_source_labels'
                    ] ?? []
                );

                $actions->push([
                    'priority' =>
                        60
                        + (int) (
                            $system['priority']
                            ?? 50
                        ),
                    'level' => 'medium',
                    'kind' => 'dependency_review',
                    'domain' =>
                        $system['domain'],
                    'module_key' =>
                        $dependencyModule['key'],
                    'title_mm' =>
                        $dependencyModule['title_mm']
                        .' ကို ပြန်စစ်သင့်သည်',
                    'detail_mm' =>
                        ($sourceNames !== ''
                            ? $sourceNames
                            : 'Upstream approved data')
                        .' က ဒီ Current Rule approve လုပ်ပြီးနောက် ပြောင်းထားပါတယ်။ Rule ကို အလိုအလျောက်မပြောင်းဘဲ business relevance ကို review လုပ်ပါ။',
                    'action_mm' =>
                        $canManage
                            ? 'Current Rule ကို Review လုပ်ရန် →'
                            : 'Review လိုနိုင်သော Rule ကြည့်ရန် →',
                    'url' =>
                        $dependencyModule['url'],
                ]);

                continue;
            }

            if (! $canManage) {
                continue;
            }

            /*
             * An area with one approved rule is not necessarily fully built.
             * Continue guiding the owner to the next missing Current Rule.
             * Operating-record tools never block rule coverage.
             */
            $missingRuleModule = $modules
                ->first(
                    fn (array $module): bool =>
                        ! (
                            $module['is_record']
                            ?? false
                        )
                        && empty(
                            $module[
                                'active_revision'
                            ]
                        )
                );

            if (! $missingRuleModule) {
                continue;
            }

            $actions->push([
                'priority' =>
                    120
                    + (int) (
                        $system['priority']
                        ?? 50
                    ),
                'level' =>
                    (
                        (int) (
                            $system['priority']
                            ?? 50
                        )
                        <= 6
                    )
                        ? 'medium'
                        : 'normal',
                'kind' => 'setup',
                'domain' =>
                    $system['domain'],
                'module_key' =>
                    $missingRuleModule['key'],
                'title_mm' =>
                    $missingRuleModule['title_mm']
                    .' မသတ်မှတ်ရသေး',
                'detail_mm' =>
                    $system['name_mm']
                    .' အတွက် လိုအပ်တဲ့ business data ကို ဖြည့်ပြီး '
                    .$missingRuleModule['title_mm']
                    .' ကို သင့်လုပ်ငန်းအခြေအနေနဲ့ ကိုက်ညီအောင် သတ်မှတ်ပါ။',
                'action_mm' =>
                    'ဆက်လက်သတ်မှတ်ရန် →',
                'url' =>
                    $missingRuleModule['url'],
            ]);
        }

        return $actions
            ->sortBy('priority')
            ->values();
    }
}
