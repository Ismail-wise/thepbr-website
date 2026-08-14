<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;

class PbrToolRuntimeContractService
{
    public function forTool(ChapterTool $tool): array
    {
        $tool->loadMissing(
            'chapter:id,chapter_number,title_en,title_mm'
        );

        $chapterNumber = (int) (
            $tool->chapter?->chapter_number ?? 0
        );

        $area = config(
            'pbr_business_operating_system.areas.'.$chapterNumber,
            []
        );

        $domain = (string) (
            $area['domain']
            ?? PbrOperatingSystemService::DOMAINS[$chapterNumber]
            ?? ''
        );

        $definition = config(
            'pbr_operating_tools.definitions.'.$tool->tool_key,
            []
        );

        if (! is_array($definition)) {
            $definition = [];
        }

        $prefillContract = config(
            'pbr_canonical_data.prefill_contracts.'.$tool->tool_key,
            []
        );

        if (! is_array($prefillContract)) {
            $prefillContract = [];
        }

        $domainContract = config(
            'pbr_canonical_data.domains.'.$domain,
            []
        );

        if (! is_array($domainContract)) {
            $domainContract = [];
        }

        $recordType = trim(
            (string) ($definition['record_type'] ?? '')
        );

        $isRecord = $recordType !== '';

        $prefillSources = array_values(array_unique(
            array_filter(
                $prefillContract['sources'] ?? [],
                'is_string'
            )
        ));

        $upstreamDomains = array_values(array_unique(
            array_filter(
                $domainContract['reads_from'] ?? [],
                'is_string'
            )
        ));

        $advisorySources = array_values(array_unique(
            array_filter(
                $prefillContract['advisory'] ?? [],
                'is_string'
            )
        ));

        return [
            'tool_key' => $tool->tool_key,
            'tool_type' => $tool->tool_type,
            'chapter_number' => $chapterNumber,
            'domain' => $domain,
            'area_name_mm' =>
                $area['name_mm']
                ?? $tool->chapter?->title_mm
                ?? $tool->chapter?->title_en,
            'area_name_en' =>
                $area['name_en']
                ?? $tool->chapter?->title_en,
            'mode' => $isRecord
                ? 'operating_record'
                : 'current_rule',
            'is_record' => $isRecord,
            'record_type' =>
                $isRecord ? $recordType : null,
            'prefill_sources' => $prefillSources,
            'prefill_source_labels' =>
                $this->domainLabels($prefillSources),
            'upstream_domains' => $upstreamDomains,
            'upstream_domain_labels' =>
                $this->domainLabels($upstreamDomains),
            'advisory_sources' => $advisorySources,
            'advisory_source_labels' =>
                $this->advisoryLabels($advisorySources),
            'approved_data_only' => true,
            'draft_is_current_rule' => false,
        ];
    }

    private function domainLabels(array $domains): array
    {
        return collect($domains)
            ->map(function (string $domain): array {
                $definition = config(
                    'pbr_canonical_data.domains.'.$domain,
                    []
                );

                return [
                    'key' => $domain,
                    'name' =>
                        $definition['name']
                        ?? str($domain)
                            ->replace('_', ' ')
                            ->title()
                            ->toString(),
                ];
            })
            ->values()
            ->all();
    }

    private function advisoryLabels(array $sources): array
    {
        return collect($sources)
            ->map(fn (string $source): array => [
                'key' => $source,
                'name' => match ($source) {
                    'business_valuation' =>
                        'Business Valuation',
                    'business_feasibility' =>
                        'Business Feasibility',
                    'partner_dynamics' =>
                        'Partner Dynamics',
                    default =>
                        str($source)
                            ->replace('_', ' ')
                            ->title()
                            ->toString(),
                },
            ])
            ->values()
            ->all();
    }
}
