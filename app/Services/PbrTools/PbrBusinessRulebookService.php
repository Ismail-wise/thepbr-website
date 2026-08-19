<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingRecord;

class PbrBusinessRulebookService
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem
    ) {
    }

    public function build(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        abort_unless(
            $actor->canAccessWorkspace($workspace),
            403
        );

        $approvedDomains =
            $this->operatingSystem
                ->agreedDomainMap(
                    $actor,
                    $workspace
                );

        $toolMap = ChapterTool::query()
            ->published()
            ->with(
                'chapter:id,chapter_number,title_en,title_mm'
            )
            ->get()
            ->keyBy('tool_key');

        $records =
            WorkspaceOperatingRecord::query()
                ->with([
                    'tool:id,course_chapter_id,tool_key,title_en,title_mm',
                    'tool.chapter:id,chapter_number,title_en,title_mm',
                ])
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->orderByDesc(
                    'effective_at'
                )
                ->orderByDesc('id')
                ->get();

        $sections = collect(
            PbrOperatingSystemService::DOMAINS
        )
            ->map(function (
                string $domain,
                int $chapterNumber
            ) use (
                $approvedDomains,
                $toolMap,
                $records
            ): array {
                $area = config(
                    'pbr_business_operating_system.areas.'
                    .$chapterNumber,
                    []
                );

                $snapshot =
                    $approvedDomains[
                        $domain
                    ] ?? null;

                $payloadTools =
                    is_array(
                        data_get(
                            $snapshot,
                            'payload.tools'
                        )
                    )
                        ? data_get(
                            $snapshot,
                            'payload.tools'
                        )
                        : [];

                $currentRules = [];

                foreach (
                    $payloadTools
                    as $toolKey => $state
                ) {
                    if (! is_array($state)) {
                        continue;
                    }

                    $definition = config(
                        'pbr_operating_tools.definitions.'
                        .$toolKey,
                        []
                    );

                    /*
                     * Record-type tools belong to Operating History,
                     * not Current Business Rules.
                     */
                    if (
                        is_array($definition)
                        && filled(
                            $definition[
                                'record_type'
                            ] ?? null
                        )
                    ) {
                        continue;
                    }

                    $tool =
                        $toolMap->get(
                            $toolKey
                        );

                    $result = is_array(
                        $state['result'] ?? null
                    )
                        ? $state['result']
                        : [];

                    $currentRules[] = [
                        'tool_key' =>
                            (string) $toolKey,
                        'title_mm' =>
                            $tool?->title_mm,
                        'title_en' =>
                            $tool?->title_en
                            ?? str(
                                (string) $toolKey
                            )
                                ->replace(
                                    '_',
                                    ' '
                                )
                                ->title()
                                ->toString(),
                        'revision' =>
                            $state[
                                'revision'
                            ] ?? null,
                        'approved_at' =>
                            $state[
                                'agreed_at'
                            ] ?? null,
                        'headline' =>
                            is_array(
                                $result[
                                    'headline'
                                ] ?? null
                            )
                                ? $result[
                                    'headline'
                                ]
                                : null,
                        'warnings' =>
                            is_array(
                                $result[
                                    'warnings'
                                ] ?? null
                            )
                                ? array_values(
                                    $result[
                                        'warnings'
                                    ]
                                )
                                : [],
                        'result' => $result,
                    ];
                }

                $sectionRecords =
                    $records
                        ->filter(
                            fn (
                                WorkspaceOperatingRecord $record
                            ): bool =>
                                (int) (
                                    $record
                                        ->tool
                                        ?->chapter
                                        ?->chapter_number
                                    ?? 0
                                )
                                ===
                                $chapterNumber
                        )
                        ->map(
                            function (
                                WorkspaceOperatingRecord $record
                            ): array {
                                return [
                                    'record_type' =>
                                        $record
                                            ->record_type,
                                    'title' =>
                                        $record
                                            ->title,
                                    'tool_title' =>
                                        $record
                                            ->tool
                                            ?->title_en,
                                    'record_date' =>
                                        $record
                                            ->record_date
                                            ?->toDateString(),
                                    'effective_at' =>
                                        $record
                                            ->effective_at
                                            ?->toIso8601String(),
                                    'data' =>
                                        is_array(
                                            $record
                                                ->data
                                        )
                                            ? $record
                                                ->data
                                            : [],
                                ];
                            }
                        )
                        ->values()
                        ->all();

                return [
                    'chapter_number' =>
                        $chapterNumber,
                    'domain' => $domain,
                    'name_mm' =>
                        $area['name_mm']
                        ?? 'Business Area '
                            .$chapterNumber,
                    'name_en' =>
                        $area['name_en']
                        ?? str($domain)
                            ->replace('_', ' ')
                            ->title()
                            ->toString(),
                    'slug' =>
                        $area['slug']
                        ?? $domain,
                    'configured' =>
                        $snapshot !== null
                        || $sectionRecords !== [],
                    'revision' =>
                        $snapshot[
                            'revision'
                        ] ?? null,
                    'approved_at' =>
                        $snapshot[
                            'agreed_at'
                        ] ?? null,
                    'summary_items' =>
                        $this->summaryItems(
                            is_array(
                                $snapshot[
                                    'summary'
                                ] ?? null
                            )
                                ? $snapshot[
                                    'summary'
                                ]
                                : []
                        ),
                    'current_rules' =>
                        array_values(
                            $currentRules
                        ),
                    'records' =>
                        $sectionRecords,
                ];
            })
            ->values();

        return [
            'workspace_id' =>
                $workspace->id,
            'business_name' =>
                $workspace->business_name
                ?: $workspace->name,
            'business_stage' =>
                $workspace->business_stage,
            'currency_code' =>
                $workspace->currency_code
                ?: 'THB',
            'generated_at' =>
                now()->toIso8601String(),
            'scope' =>
                'approved_current_rules_and_active_operating_records_only',
            'can_manage' =>
                $this->operatingSystem
                    ->canManage(
                        $actor,
                        $workspace
                    ),
            'metrics' => [
                'area_count' =>
                    $sections->count(),
                'configured_area_count' =>
                    $sections
                        ->where(
                            'configured',
                            true
                        )
                        ->count(),
                'current_rule_count' =>
                    $sections
                        ->sum(
                            fn (
                                array $section
                            ): int =>
                                count(
                                    $section[
                                        'current_rules'
                                    ]
                                )
                        ),
                'operating_record_count' =>
                    $records->count(),
            ],
            'sections' => $sections,
        ];
    }

    private function summaryItems(
        array $summary
    ): array {
        $items = [];

        $this->flattenSummary(
            $summary,
            '',
            $items,
            0
        );

        return array_slice(
            $items,
            0,
            16
        );
    }

    private function flattenSummary(
        array $values,
        string $prefix,
        array &$items,
        int $depth
    ): void {
        foreach ($values as $key => $value) {
            if (count($items) >= 16) {
                return;
            }

            $label =
                trim(
                    $prefix.' '
                    .str((string) $key)
                        ->replace('_', ' ')
                        ->title()
                        ->toString()
                );

            if (
                is_scalar($value)
                || $value === null
            ) {
                $items[] = [
                    'label' => $label,
                    'value' => $value,
                ];

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $items[] = [
                    'label' => $label,
                    'value' =>
                        count($value)
                        .' items',
                ];

                continue;
            }

            if ($depth >= 1) {
                $items[] = [
                    'label' => $label,
                    'value' =>
                        count($value)
                        .' fields',
                ];

                continue;
            }

            $this->flattenSummary(
                $value,
                $label,
                $items,
                $depth + 1
            );
        }
    }
}
