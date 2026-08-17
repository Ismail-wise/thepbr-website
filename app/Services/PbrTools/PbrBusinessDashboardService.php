<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use Illuminate\Support\Collection;

class PbrBusinessDashboardService
{
    private const PHASES = [
        'build' => [
            'title_mm' => 'Partnership ကို တည်ဆောက်မယ်',
            'title_en' => 'Build the Partnership',
            'description_mm' =>
                'မတည်ငွေ၊ ပိုင်ဆိုင်မှုနဲ့ Partner တာဝန်တွေကို အရင်ရှင်းလင်းသတ်မှတ်ပါ။',
            'domains' => [
                'capital',
                'ownership',
                'contribution',
            ],
        ],

        'operate' => [
            'title_mm' => 'လုပ်ငန်းကို စနစ်တကျ လည်ပတ်မယ်',
            'title_en' => 'Operate the Business',
            'description_mm' =>
                'အမြတ်ခွဲဝေမှု၊ ငွေကြေးထိန်းချုပ်မှုနဲ့ ဆုံးဖြတ်ချက်စနစ်ကို တည်ဆောက်ပါ။',
            'domains' => [
                'distribution',
                'financial_controls',
                'governance',
            ],
        ],

        'protect' => [
            'title_mm' => 'Partnership ကို ကာကွယ်မယ်',
            'title_en' => 'Protect the Partnership',
            'description_mm' =>
                'ထွက်ခွာမှု၊ ဆက်ခံမှု၊ Share Transfer နဲ့ အငြင်းပွားမှုအတွက် ကြိုတင်စီစဉ်ပါ။',
            'domains' => [
                'exit',
                'continuity',
                'share_transfer',
                'dispute_resolution',
            ],
        ],
    ];

    public function build(
        array $businessState,
        PartnershipWorkspace $workspace
    ): array {
        $systems = collect(
            $businessState['systems'] ?? []
        );

        $metrics = $businessState['metrics'] ?? [];

        $actions = collect(
            $businessState['action_items'] ?? []
        );

        $canManage = (bool) (
            $businessState['can_manage'] ?? false
        );

        $areaCards = $systems
            ->map(
                fn (array $system): array =>
                    $this->areaCard(
                        $system,
                        $workspace,
                        $canManage
                    )
            )
            ->values();

        $phases = collect(self::PHASES)
            ->map(function (
                array $phase,
                string $phaseKey
            ) use ($areaCards): array {
                $domains = collect(
                    $phase['domains']
                );

                $areas = $areaCards
                    ->filter(
                        fn (array $area): bool =>
                            $domains->contains(
                                $area['domain']
                            )
                    )
                    ->values();

                return [
                    'key' => $phaseKey,
                    'title_mm' =>
                        $phase['title_mm'],
                    'title_en' =>
                        $phase['title_en'],
                    'description_mm' =>
                        $phase['description_mm'],
                    'area_count' =>
                        $areas->count(),
                    'started_area_count' =>
                        $areas
                            ->reject(
                                fn (array $area): bool =>
                                    $area['status_key']
                                    === 'not-started'
                            )
                            ->count(),
                    'approved_area_count' =>
                        $areas
                            ->where(
                                'status_key',
                                'approved'
                            )
                            ->count(),
                    'areas' => $areas,
                ];
            })
            ->values();

        $startedAreas = $areaCards
            ->reject(
                fn (array $area): bool =>
                    $area['status_key']
                    === 'not-started'
            )
            ->count();

        $approvedAreas = $areaCards
            ->where(
                'status_key',
                'approved'
            )
            ->count();

        $reviewAreas = $areaCards
            ->filter(
                fn (array $area): bool =>
                    in_array(
                        $area['status_key'],
                        [
                            'draft',
                            'needs-review',
                        ],
                        true
                    )
            )
            ->count();

        $capitalSource = (string) (
            $businessState['capital_source']
            ?? 'none'
        );

        $capital = $businessState['capital']
            ?? [];

        $capitalDataAvailable =
            $capitalSource !== 'none';

        $primaryAction =
            $canManage
                ? $actions->first()
                : null;

        /*
         * The primary action is already displayed prominently.
         * Do not repeat it inside the priority list.
         */
        $priorityActions =
            $canManage
                ? $actions
                    ->filter(
                        fn (array $action): bool =>
                            in_array(
                                $action['kind'] ?? null,
                                [
                                    'funding_gap',
                                    'working_change',
                                    'dependency_review',
                                ],
                                true
                            )
                    )
                    ->reject(
                        fn (array $action): bool =>
                            $primaryAction
                            && (
                                $action['url'] ?? null
                            ) === (
                                $primaryAction['url'] ?? null
                            )
                            && (
                                $action['title_mm'] ?? null
                            ) === (
                                $primaryAction['title_mm'] ?? null
                            )
                    )
                    ->take(3)
                    ->values()
                : collect();

        return [
            'version' => 'pbr-dashboard-v2',

            'can_manage' => $canManage,

            'primary_action' =>
                $primaryAction,

            'priority_actions' =>
                $priorityActions,

            'phases' => $phases,

            'areas' => $areaCards,

            /*
             * Overview progress is deliberately area-based rather than
             * "58 missing rules". Tool-level rule coverage remains available
             * to the Rulebook and existing operating-state contracts.
             */
            'health' => [
                'total_area_count' =>
                    $areaCards->count(),

                'started_area_count' =>
                    $startedAreas,

                'approved_area_count' =>
                    $approvedAreas,

                'review_area_count' =>
                    $reviewAreas,

                'working_change_count' =>
                    (int) (
                        $metrics[
                            'working_change_count'
                        ] ?? 0
                    ),

                'active_rule_count' =>
                    (int) (
                        $metrics[
                            'active_rule_count'
                        ] ?? 0
                    ),

                'operating_record_count' =>
                    (int) (
                        $metrics[
                            'operating_record_count'
                        ] ?? 0
                    ),

                'setup_progress_percent' =>
                    $areaCards->count() > 0
                        ? (int) round(
                            $startedAreas
                            / $areaCards->count()
                            * 100
                        )
                        : 100,

                'approved_area_percent' =>
                    $areaCards->count() > 0
                        ? (int) round(
                            $approvedAreas
                            / $areaCards->count()
                            * 100
                        )
                        : 100,
            ],

            /*
             * Zero and "not configured" are different business facts.
             * No approved/working capital source means values are null
             * for overview presentation, not THB 0.00.
             */
            'capital' => [
                'source' =>
                    $capitalSource,

                'data_available' =>
                    $capitalDataAvailable,

                'has_approved_data' =>
                    $capitalSource
                    === 'active',

                'source_label' =>
                    match ($capitalSource) {
                        'active' =>
                            'Approved Capital Data',

                        'working' =>
                            'Working Draft Estimate',

                        default =>
                            'Capital Not Set',
                    },

                'capital_required' =>
                    $capitalDataAvailable
                        ? (float) (
                            $capital[
                                'capital_required'
                            ] ?? 0
                        )
                        : null,

                'capital_secured' =>
                    $capitalDataAvailable
                        ? (float) (
                            $capital[
                                'capital_secured'
                            ] ?? 0
                        )
                        : null,

                'funding_gap' =>
                    $capitalDataAvailable
                        ? (float) (
                            $capital[
                                'funding_gap'
                            ] ?? 0
                        )
                        : null,
            ],

            'rulebook' => [
                'active_rule_count' =>
                    (int) (
                        $metrics[
                            'active_rule_count'
                        ] ?? 0
                    ),

                'working_change_count' =>
                    (int) (
                        $metrics[
                            'working_change_count'
                        ] ?? 0
                    ),

                'operating_record_count' =>
                    (int) (
                        $metrics[
                            'operating_record_count'
                        ] ?? 0
                    ),

                'url' =>
                    route(
                        'workspaces.rulebook.show',
                        $workspace
                    ),
            ],
        ];
    }

    private function areaCard(
        array $system,
        PartnershipWorkspace $workspace,
        bool $canManage
    ): array {
        $modules = collect(
            $system['modules'] ?? []
        );

        $ruleModules = $modules
            ->reject(
                fn (array $module): bool =>
                    (bool) (
                        $module['is_record']
                        ?? false
                    )
            )
            ->values();

        $ruleCount = (int) (
            $system['rule_module_count']
            ?? $ruleModules->count()
        );

        $approvedCount = (int) (
            $system['active_count']
            ?? 0
        );

        $workingCount = (int) (
            $system['working_count']
            ?? 0
        );

        $dependencyReviewCount =
            (int) (
                $system[
                    'dependency_review_count'
                ] ?? 0
            );

        $configuredCount = $ruleModules
            ->filter(
                fn (array $module): bool =>
                    ! empty(
                        $module[
                            'active_revision'
                        ]
                    )
                    || ! empty(
                        $module[
                            'draft_id'
                        ]
                    )
            )
            ->count();

        if ($workingCount > 0) {
            $statusKey = 'draft';
            $statusMm = 'Draft စစ်ဆေးရန်';
            $statusEn = 'Draft';
        } elseif (
            $dependencyReviewCount > 0
        ) {
            $statusKey = 'needs-review';
            $statusMm = 'ပြန်စစ်ရန်လိုသည်';
            $statusEn = 'Needs Review';
        } elseif (
            $ruleCount > 0
            && $approvedCount >= $ruleCount
        ) {
            $statusKey = 'approved';
            $statusMm = 'တည်ဆောက်ပြီး';
            $statusEn = 'Approved';
        } elseif (
            $approvedCount > 0
            || $configuredCount > 0
        ) {
            $statusKey = 'in-progress';
            $statusMm = 'လုပ်ဆောင်နေဆဲ';
            $statusEn = 'In Progress';
        } else {
            $statusKey = 'not-started';
            $statusMm = 'မစရသေး';
            $statusEn = 'Not Started';
        }

        $nextModule = null;

        if ($canManage) {
            $nextModule = $modules
                ->first(
                    fn (array $module): bool =>
                        (
                            $module[
                                'state'
                            ]['key']
                            ?? null
                        ) === 'review'
                );

            if (! $nextModule) {
                $nextModule = $modules
                    ->first(
                        fn (
                            array $module
                        ): bool =>
                            ! (
                                $module[
                                    'is_record'
                                ]
                                ?? false
                            )
                            && (
                                $module[
                                    'dependency_review_required'
                                ]
                                ?? false
                            )
                    );
            }

            if (! $nextModule) {
                $nextModule = $ruleModules
                    ->first(
                        fn (
                            array $module
                        ): bool =>
                            empty(
                                $module[
                                    'active_revision'
                                ]
                            )
                    );
            }
        }

        $url = $canManage
            ? (
                $nextModule['url']
                ?? $system['url']
                ?? route(
                    'workspaces.tools.index',
                    $workspace
                )
            )
            : route(
                'workspaces.rulebook.show',
                $workspace
            );

        return [
            'number' =>
                (int) (
                    $system[
                        'internal_number'
                    ] ?? 0
                ),

            'domain' =>
                (string) (
                    $system['domain']
                    ?? ''
                ),

            'slug' =>
                (string) (
                    $system['slug']
                    ?? ''
                ),

            'name_mm' =>
                (string) (
                    $system['name_mm']
                    ?? ''
                ),

            'name_en' =>
                (string) (
                    $system['name_en']
                    ?? ''
                ),

            'status_key' =>
                $statusKey,

            'status_mm' =>
                $statusMm,

            'status_en' =>
                $statusEn,

            'rule_count' =>
                $ruleCount,

            'approved_rule_count' =>
                $approvedCount,

            'configured_rule_count' =>
                $configuredCount,

            'working_change_count' =>
                $workingCount,

            'dependency_review_count' =>
                $dependencyReviewCount,

            'setup_percent' =>
                $ruleCount > 0
                    ? (int) round(
                        min(
                            $ruleCount,
                            $configuredCount
                        )
                        / $ruleCount
                        * 100
                    )
                    : 100,

            'approved_percent' =>
                $ruleCount > 0
                    ? (int) round(
                        min(
                            $ruleCount,
                            $approvedCount
                        )
                        / $ruleCount
                        * 100
                    )
                    : 100,

            'next_module' =>
                $nextModule
                    ? [
                        'key' =>
                            $nextModule['key'],

                        'title_mm' =>
                            $nextModule[
                                'title_mm'
                            ],

                        'title_en' =>
                            $nextModule[
                                'title_en'
                            ],

                        'url' =>
                            $nextModule['url'],
                    ]
                    : null,

            'url' => $url,
        ];
    }
}
