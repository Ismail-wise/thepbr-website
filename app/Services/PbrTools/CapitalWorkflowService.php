<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Models\WorkspaceToolOutput;

class CapitalWorkflowService
{
    private const NEW_SEQUENCE = [
        'startup_capital_planner',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ];

    private const EXISTING_SEQUENCE = [
        'current_capital_position',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ];

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

        $canManage = $this->operatingSystem
            ->canManage(
                $actor,
                $workspace
            );

        $sequence = $this->sequenceFor(
            $workspace
        );

        $tools = ChapterTool::query()
            ->whereIn('tool_key', $sequence)
            ->whereHas(
                'chapter',
                fn ($query) =>
                    $query->where(
                        'chapter_number',
                        1
                    )
            )
            ->get()
            ->keyBy('tool_key');

        $toolIds = $tools
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $approved = WorkspaceToolOutput::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->whereIn(
                'chapter_tool_id',
                $toolIds
            )
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->keyBy(
                fn ($output) =>
                    (int) $output->chapter_tool_id
            );

        $drafts = $canManage
            ? ToolSession::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->whereIn(
                    'chapter_tool_id',
                    $toolIds
                )
                ->where('status', 'draft')
                ->whereDoesntHave(
                    'workspaceOutputs',
                    fn ($query) =>
                        $query->where(
                            'status',
                            'agreed'
                        )
                )
                ->orderByDesc('last_saved_at')
                ->orderByDesc('id')
                ->get()
                ->unique('chapter_tool_id')
                ->keyBy(
                    fn ($session) =>
                        (int) $session
                            ->chapter_tool_id
                )
            : collect();

        $steps = [];

        foreach (
            $sequence
            as $index => $toolKey
        ) {
            $tool = $tools->get($toolKey);

            if (! $tool) {
                continue;
            }

            $toolId = (int) $tool->id;
            $approvedOutput =
                $approved->get($toolId);

            $draftSession =
                $drafts->get($toolId);

            $state = $draftSession
                ? 'working'
                : (
                    $approvedOutput
                        ? 'approved'
                        : 'setup'
                );

            $steps[] = [
                'position' => $index + 1,
                'tool_key' => $toolKey,
                'slug' => $tool->slug,
                'title_en' =>
                    $tool->title_en,
                'title_mm' =>
                    $tool->title_mm
                    ?: $tool->title_en,
                'state' => $state,
                'is_approved' =>
                    $approvedOutput !== null,
                'has_working_change' =>
                    $draftSession !== null,
                'approved_revision' =>
                    $approvedOutput?->revision,
                'approved_at' =>
                    $approvedOutput
                        ?->agreed_at
                        ?->toIso8601String(),
                'draft_id' =>
                    $draftSession?->id,
                'draft_name' =>
                    $draftSession
                        ?->scenario_name,
                'draft_updated_at' =>
                    $draftSession
                        ?->last_saved_at
                        ?->toIso8601String(),
                'url' =>
                    $this->toolUrl(
                        $workspace,
                        $toolKey,
                        $tool->slug
                    ),
            ];
        }

        $stepCollection = collect($steps);

        /*
         * Review an existing Working Change before suggesting a fresh setup.
         * Partners never receive management-oriented next-step metadata.
         */
        $nextStep = $canManage
            ? (
                $stepCollection->firstWhere(
                    'state',
                    'working'
                )
                ?? $stepCollection->firstWhere(
                    'state',
                    'setup'
                )
            )
            : null;

        /*
         * A Working Change never erases the currently approved rule.
         * Therefore approved progress is based on is_approved rather than
         * presentation state.
         */
        $approvedCount = $stepCollection
            ->filter(
                fn (array $step): bool =>
                    (bool) $step['is_approved']
            )
            ->count();

        $workingCount = $stepCollection
            ->where(
                'state',
                'working'
            )
            ->count();

        $currentRuleComplete =
            count($steps) > 0
            && $stepCollection->every(
                fn (array $step): bool =>
                    (bool) $step['is_approved']
            );

        $approvedSnapshot =
            $this->operatingSystem
                ->latestSnapshot(
                    $workspace,
                    'capital',
                    'agreed'
                );

        $summary =
            is_array(
                $approvedSnapshot?->summary
            )
                ? $approvedSnapshot->summary
                : [];

        return [
            'business_stage' =>
                $workspace->business_stage,
            'can_manage' => $canManage,
            'steps' => $steps,
            'step_count' => count($steps),
            'approved_count' =>
                $approvedCount,
            'working_count' =>
                $workingCount,
            'next_step' => $nextStep,
            'current_rule_complete' =>
                $currentRuleComplete,
            'has_pending_changes' =>
                $workingCount > 0,
            'is_complete' =>
                $currentRuleComplete
                && $workingCount === 0,
            'current_rule' => [
                'revision' =>
                    $approvedSnapshot?->revision,
                'agreed_at' =>
                    $approvedSnapshot
                        ?->agreed_at
                        ?->toIso8601String(),
                'summary' => $summary,
            ],
        ];
    }

    public function sequenceFor(
        PartnershipWorkspace $workspace
    ): array {
        return $workspace->business_stage
            === 'new'
                ? self::NEW_SEQUENCE
                : self::EXISTING_SEQUENCE;
    }

    private function toolUrl(
        PartnershipWorkspace $workspace,
        string $toolKey,
        string $slug
    ): string {
        if (
            $toolKey
            === 'startup_capital_planner'
        ) {
            return route(
                'workspaces.tools.startup-capital.show',
                $workspace
            );
        }

        return route(
            'workspaces.tools.chapter-one.show',
            [
                $workspace,
                $slug,
            ]
        );
    }
}
