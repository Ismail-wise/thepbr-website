<?php

namespace App\Services\PbrTools;

use LogicException;
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
        private readonly DisputeResolutionDomainEngine $disputeResolutionDomain,
        private readonly PbrDomainRegistryService $domainRegistry
    ) {
    }

    /**
     * Legacy V1 catalog compatibility.
     *
     * Chapter number remains catalog metadata only. Domain identity and
     * engine routing are resolved through buildForDomain().
     */
    public function build(
        PartnershipWorkspace $workspace,
        int $chapterNumber,
        string $status = 'agreed'
    ): array {
        $domainKey = $this->domainRegistry
            ->domainForLegacyChapter(
                $chapterNumber
            );

        return $this->buildForDomain(
            $workspace,
            $domainKey,
            $status
        );
    }

    /**
     * Build operating state by stable business-domain identity.
     */
    public function buildForDomain(
        PartnershipWorkspace $workspace,
        string $domainKey,
        string $status = 'agreed'
    ): array {
        $area = $this->domainRegistry
            ->domain($domainKey);

        $chapterNumber = (int) (
            $area['legacy_chapter_number']
            ?? 0
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
            ->published()
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

        $summary = $this->summaryForDomain(
            $workspace,
            $domainKey,
            $toolPayload
        );

        return [
            'payload' => [
                'chapter' =>
                    $chapterNumber,

                'domain' =>
                    $domainKey,

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

    private function summaryForDomain(
        PartnershipWorkspace $workspace,
        string $domainKey,
        array $tools
    ): array {
        return match ($domainKey) {
            'capital' => $this->capitalDomain
                ->summarize(
                    $workspace,
                    $tools
                ),

            'ownership' => $this->ownershipDomain
                ->summarize($tools),

            'contribution' => $this->contributionDomain
                ->summarize($tools),

            'distribution' => $this->distributionDomain
                ->summarize($tools),

            'financial_controls' =>
                $this->financialControlsDomain
                    ->summarize($tools),

            'governance' => $this->governanceDomain
                ->summarize($tools),

            'exit' => $this->exitDomain
                ->summarize($tools),

            'continuity' => $this->continuityDomain
                ->summarize($tools),

            'share_transfer' =>
                $this->shareTransferDomain
                    ->summarize($tools),

            'dispute_resolution' =>
                $this->disputeResolutionDomain
                    ->summarize($tools),

            default => throw new LogicException(
                'No PBR domain state engine is registered for '
                .$domainKey
                .'.'
            ),
        };
    }
}
