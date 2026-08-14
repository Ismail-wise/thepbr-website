<?php

namespace App\Services\PbrTools;

use Illuminate\Support\Collection;

class PbrBusinessJourneyService
{
    public function build(
        Collection $systems,
        Collection $actions,
        bool $canManage
    ): array {
        $steps = $systems
            ->map(function (array $system) use ($canManage): array {
                $modules = collect(
                    $system['modules'] ?? []
                );

                $ruleCount = (int) (
                    $system['rule_module_count']
                    ?? $modules
                        ->reject(
                            fn (array $module): bool =>
                                (bool) (
                                    $module['is_record']
                                    ?? false
                                )
                        )
                        ->count()
                );

                $approvedCount = (int) (
                    $system['active_count'] ?? 0
                );

                $missingCount = max(
                    0,
                    $ruleCount - $approvedCount
                );

                $workingCount = (int) (
                    $system['working_count'] ?? 0
                );

                $dependencyReviewCount = (int) (
                    $system[
                        'dependency_review_count'
                    ] ?? 0
                );

                $completion = $ruleCount > 0
                    ? (int) round(
                        $approvedCount
                        / $ruleCount
                        * 100
                    )
                    : 100;

                if ($workingCount > 0) {
                    $statusKey = 'review';
                    $statusLabel =
                        'Working Change Review';
                } elseif ($dependencyReviewCount > 0) {
                    $statusKey = 'dependency-review';
                    $statusLabel =
                        'Upstream Change Review';
                } elseif (
                    $ruleCount > 0
                    && $missingCount === 0
                ) {
                    $statusKey = 'established';
                    $statusLabel =
                        'Operating Rules Established';
                } elseif ($approvedCount > 0) {
                    $statusKey = 'in-progress';
                    $statusLabel =
                        'Partially Established';
                } else {
                    $statusKey = 'setup';
                    $statusLabel =
                        'Setup Required';
                }

                $nextModule = null;

                if ($canManage) {
                    $candidate = $modules
                        ->first(
                            fn (array $module): bool =>
                                (
                                    $module['state']['key']
                                    ?? null
                                ) === 'review'
                        );

                    if (! $candidate) {
                        $candidate = $modules
                            ->first(
                                fn (array $module): bool =>
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

                    if (! $candidate) {
                        $candidate = $modules
                            ->first(
                                fn (array $module): bool =>
                                    ! (
                                        $module[
                                            'is_record'
                                        ]
                                        ?? false
                                    )
                                    && empty(
                                        $module[
                                            'active_revision'
                                        ]
                                    )
                            );
                    }

                    if ($candidate) {
                        $nextModule = [
                            'key' =>
                                $candidate['key'],
                            'title_mm' =>
                                $candidate[
                                    'title_mm'
                                ],
                            'title_en' =>
                                $candidate[
                                    'title_en'
                                ],
                            'url' =>
                                $candidate['url'],
                        ];
                    }
                }

                return [
                    'step_number' =>
                        (int) (
                            $system[
                                'internal_number'
                            ] ?? 0
                        ),
                    'domain' =>
                        $system['domain'],
                    'name_mm' =>
                        $system['name_mm'],
                    'name_en' =>
                        $system['name_en'],
                    'slug' =>
                        $system['slug'],
                    'url' =>
                        $system['url'],
                    'status_key' =>
                        $statusKey,
                    'status_label' =>
                        $statusLabel,
                    'rule_count' =>
                        $ruleCount,
                    'approved_rule_count' =>
                        $approvedCount,
                    'missing_rule_count' =>
                        $missingCount,
                    'working_change_count' =>
                        $workingCount,
                    'dependency_review_count' =>
                        $dependencyReviewCount,
                    'completion_percent' =>
                        $completion,
                    'next_module' =>
                        $nextModule,
                ];
            })
            ->values();

        $totalRules = (int) $steps->sum(
            'rule_count'
        );

        $approvedRules = (int) $steps->sum(
            'approved_rule_count'
        );

        $completion = $totalRules > 0
            ? (int) round(
                $approvedRules
                / $totalRules
                * 100
            )
            : 100;

        $currentStep = $steps
            ->first(
                fn (array $step): bool =>
                    $step['status_key']
                    !== 'established'
            );

        return [
            'scope' =>
                'current-approved-rules-and-owner-working-state',
            'can_manage' =>
                $canManage,
            'completion_percent' =>
                $completion,
            'current_step' =>
                $currentStep,
            'next_action' =>
                $actions->first(),
            'metrics' => [
                'area_count' =>
                    $steps->count(),
                'established_area_count' =>
                    $steps
                        ->where(
                            'status_key',
                            'established'
                        )
                        ->count(),
                'in_progress_area_count' =>
                    $steps
                        ->whereIn(
                            'status_key',
                            [
                                'in-progress',
                                'setup',
                            ]
                        )
                        ->count(),
                'review_area_count' =>
                    $steps
                        ->whereIn(
                            'status_key',
                            [
                                'review',
                                'dependency-review',
                            ]
                        )
                        ->count(),
                'total_rule_count' =>
                    $totalRules,
                'approved_rule_count' =>
                    $approvedRules,
                'missing_rule_count' =>
                    max(
                        0,
                        $totalRules - $approvedRules
                    ),
                'dependency_review_count' =>
                    (int) $steps->sum(
                        'dependency_review_count'
                    ),
            ],
            'steps' => $steps,
        ];
    }
}
