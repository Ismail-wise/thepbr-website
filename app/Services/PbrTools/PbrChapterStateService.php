<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceToolOutput;
use App\Services\PbrTools\Domains\CapitalDomainEngine;
use App\Services\PbrTools\Domains\ContributionDomainEngine;
use App\Services\PbrTools\Domains\ContinuityDomainEngine;
use App\Services\PbrTools\Domains\DisputeResolutionDomainEngine;
use App\Services\PbrTools\Domains\DistributionDomainEngine;
use App\Services\PbrTools\Domains\ExitDomainEngine;
use App\Services\PbrTools\Domains\FinancialControlsDomainEngine;
use App\Services\PbrTools\Domains\GovernanceDomainEngine;
use App\Services\PbrTools\Domains\OwnershipDomainEngine;
use App\Services\PbrTools\Domains\ShareTransferDomainEngine;

class PbrChapterStateService
{
    public function __construct(
        private readonly CapitalDomainEngine $capitalDomain,
        private readonly OwnershipDomainEngine $ownershipDomain,
        private readonly ContributionDomainEngine $contributionDomain,
        private readonly DistributionDomainEngine $distributionDomain,
        private readonly FinancialControlsDomainEngine $financialControlsDomain,
        private readonly GovernanceDomainEngine $governanceDomain,
        private readonly ExitDomainEngine $exitDomain,
        private readonly ContinuityDomainEngine $continuityDomain,
        private readonly ShareTransferDomainEngine $shareTransferDomain,
        private readonly DisputeResolutionDomainEngine $disputeResolutionDomain
    ) {
    }

    public function build(
        PartnershipWorkspace $workspace,
        int $chapterNumber,
        string $status = 'agreed'
    ): array {
        abort_unless(
            $chapterNumber >= 1
            && $chapterNumber <= 10,
            404
        );

        abort_unless(
            in_array(
                $status,
                ['draft', 'agreed'],
                true
            ),
            422
        );

        $tools = ChapterTool::query()
            ->whereHas(
                'chapter',
                fn ($query) =>
                    $query->where(
                        'chapter_number',
                        $chapterNumber
                    )
            )
            ->get([
                'id',
                'tool_key',
                'slug',
                'title_en',
                'title_mm',
            ]);

        $outputQuery = WorkspaceToolOutput::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->whereIn(
                'chapter_tool_id',
                $tools->pluck('id')
            );

        if ($status === 'agreed') {
            $outputQuery->where(
                'status',
                'agreed'
            );
        } else {
            /*
             * Working state may combine:
             * - a newer draft for one tool, and
             * - unchanged agreed rules for other tools.
             *
             * Official agreed state never reads draft outputs.
             */
            $outputQuery->whereIn(
                'status',
                ['draft', 'agreed']
            );
        }

        $latestOutputs = $outputQuery
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->keyBy('chapter_tool_id');

        $toolPayload = [];

        foreach ($tools as $tool) {
            $output = $latestOutputs->get(
                $tool->id
            );

            if (! $output) {
                continue;
            }

            $outputData = is_array(
                $output->output_data
            )
                ? $output->output_data
                : [];

            $toolPayload[$tool->tool_key] = [
                'workspace_tool_output_id' =>
                    $output->id,

                'revision' =>
                    $output->revision,

                'status' =>
                    $output->status,

                'result' =>
                    $outputData,

                'data' =>
                    is_array(
                        $outputData['data'] ?? null
                    )
                        ? $outputData['data']
                        : $outputData,

                'generated_at' =>
                    $output->generated_at
                        ?->toIso8601String(),

                'agreed_at' =>
                    $output->agreed_at
                        ?->toIso8601String(),
            ];
        }

        $summary = $this->summaryForChapter(
            $workspace,
            $chapterNumber,
            $toolPayload
        );

        return [
            'payload' => [
                'chapter' =>
                    $chapterNumber,

                'domain' =>
                    PbrOperatingSystemService::DOMAINS[
                        $chapterNumber
                    ],

                'business_stage' =>
                    $workspace->business_stage,

                'currency_code' =>
                    $workspace->currency_code,

                'source_status' =>
                    $status === 'draft'
                        ? 'working_latest_draft_or_agreed'
                        : 'agreed_only',

                'tools' =>
                    $toolPayload,

                'canonical' =>
                    $summary,
            ],

            'summary' =>
                $summary,
        ];
    }

    private function summaryForChapter(
        PartnershipWorkspace $workspace,
        int $chapter,
        array $tools
    ): array {
        return match ($chapter) {
            1 => $this->capitalDomain
                ->summarize(
                    $workspace,
                    $tools
                ),

            2 => $this->ownershipDomain
                ->summarize($tools),

            3 => $this->contributionDomain
                ->summarize($tools),

            4 => $this->distributionDomain
                ->summarize($tools),

            5 => $this->financialControlsDomain
                ->summarize($tools),

            6 => $this->governanceDomain
                ->summarize($tools),

            7 => $this->exitDomain
                ->summarize($tools),

            8 => $this->continuityDomain
                ->summarize($tools),

            9 => $this->shareTransferDomain
                ->summarize($tools),

            10 => $this->disputeResolutionDomain
                ->summarize($tools),

            default => [],
        };
    }
}
