<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Models\WorkspaceToolOutput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ToolScenarioService
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem,
        private readonly PbrChapterStateService $chapterState
    ) {
    }

    public function canManage(User $user, PartnershipWorkspace $workspace): bool
    {
        return $this->operatingSystem->canManage($user, $workspace);
    }

    public function saveDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        string $scenarioName,
        array $inputData,
        array $resultData,
        ?int $sessionId = null
    ): ToolSession {
        $this->authorizeManagement($user, $workspace);
        $this->assertBusinessContext($workspace);

        $scenarioName = trim($scenarioName);

        if ($scenarioName === '') {
            throw ValidationException::withMessages([
                'scenario_name' => 'Working Plan / Version အမည်ထည့်ပါ။',
            ]);
        }

        if ($sessionId !== null) {
            $session = $this->ownedDraft(
                $user,
                $workspace,
                $tool,
                $sessionId
            );
        } else {
            $session = new ToolSession();
            $session->user_id = $user->id;
            $session->workspace_id = $workspace->id;
            $session->chapter_tool_id = $tool->id;
            $session->business_stage = $workspace->business_stage;
            $session->status = 'draft';
            $session->started_at = now();
        }

        $session->scenario_name = Str::limit($scenarioName, 120, '');
        $session->input_data = $inputData;
        $session->result_data = $resultData;
        $session->last_saved_at = now();
        $session->save();

        return $session->fresh();
    }

    public function ownedDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $sessionId
    ): ToolSession {
        return ToolSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->where('status', 'draft')
            ->firstOrFail();
    }

    public function drafts(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool
    ): Collection {
        if (! $this->canManage($user, $workspace)) {
            return collect();
        }

        return ToolSession::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->where('status', 'draft')
            ->orderByDesc('last_saved_at')
            ->orderByDesc('id')
            ->get();
    }

    public function renameDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $sessionId,
        string $scenarioName
    ): ToolSession {
        $this->authorizeManagement($user, $workspace);

        $session = $this->ownedDraft(
            $user,
            $workspace,
            $tool,
            $sessionId
        );

        $session->scenario_name = Str::limit(trim($scenarioName), 120, '');
        $session->last_saved_at = now();
        $session->save();

        return $session->fresh();
    }

    public function duplicateDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $sessionId
    ): ToolSession {
        $this->authorizeManagement($user, $workspace);

        $source = $this->ownedDraft(
            $user,
            $workspace,
            $tool,
            $sessionId
        );

        $copy = new ToolSession();
        $copy->user_id = $user->id;
        $copy->workspace_id = $workspace->id;
        $copy->chapter_tool_id = $tool->id;
        $copy->business_stage = $source->business_stage;
        $copy->scenario_name = $this->copyName($source->scenario_name ?: 'Working Plan');
        $copy->status = 'draft';
        $copy->input_data = $source->input_data;
        $copy->result_data = $source->result_data;
        $copy->started_at = now();
        $copy->last_saved_at = now();
        $copy->save();

        return $copy->fresh();
    }

    public function deleteDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $sessionId
    ): void {
        $this->authorizeManagement($user, $workspace);

        $session = $this->ownedDraft(
            $user,
            $workspace,
            $tool,
            $sessionId
        );

        $session->delete();
    }

    public function createWorkspaceOutput(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session
    ): WorkspaceToolOutput {
        $this->authorizeManagement($user, $workspace);
        $this->assertSessionMatches($workspace, $tool, $session);

        $tool->loadMissing('chapter:id,chapter_number');
        $chapterNumber = (int) $tool->chapter?->chapter_number;

        return DB::transaction(function () use (
            $user,
            $workspace,
            $tool,
            $session,
            $chapterNumber
        ): WorkspaceToolOutput {
            $output = $this->createOutputRow(
                $user,
                $workspace,
                $tool,
                $session,
                'draft'
            );

            $state = $this->chapterState->build(
                $workspace,
                $chapterNumber,
                'draft'
            );

            $this->operatingSystem->saveSnapshot(
                $user,
                $workspace,
                $this->operatingSystem->domainForChapter($chapterNumber),
                $state['payload'],
                $state['summary'],
                'draft'
            );

            return $output;
        });
    }

    public function publishAgreedOutput(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session
    ): WorkspaceToolOutput {
        $this->authorizeManagement($user, $workspace);
        $this->assertSessionMatches($workspace, $tool, $session);

        $tool->loadMissing('chapter:id,chapter_number');
        $chapterNumber = (int) $tool->chapter?->chapter_number;

        return DB::transaction(function () use (
            $user,
            $workspace,
            $tool,
            $session,
            $chapterNumber
        ): WorkspaceToolOutput {
            $output = $this->createOutputRow(
                $user,
                $workspace,
                $tool,
                $session,
                'agreed'
            );

            $definition = config(
                'pbr_operating_tools.definitions.'.$tool->tool_key,
                []
            );

            if (is_array($definition) && filled($definition['record_type'] ?? null)) {
                $recordData = is_array($session->result_data['data'] ?? null)
                    ? $session->result_data['data']
                    : ($session->result_data ?? []);

                $this->operatingSystem->saveRecord(
                    $user,
                    $workspace,
                    $tool,
                    (string) $definition['record_type'],
                    $recordData,
                    $session->scenario_name,
                    $this->recordDateFrom($recordData),
                    [
                        'workspace_tool_output_id' => $output->id,
                        'source_tool_session_id' => $session->id,
                        'chapter_number' => $chapterNumber,
                    ]
                );
            }

            $state = $this->chapterState->build(
                $workspace,
                $chapterNumber,
                'agreed'
            );

            $this->operatingSystem->saveSnapshot(
                $user,
                $workspace,
                $this->operatingSystem->domainForChapter($chapterNumber),
                $state['payload'],
                $state['summary'],
                'agreed'
            );

            // Approval closes this working version. A later change must be a
            // new draft so active policy and proposed changes stay separate.
            $session->status = 'completed';
            $session->completed_at = now();
            $session->last_saved_at = now();
            $session->save();

            return $output;
        });
    }

    public function latestAgreedOutput(
        PartnershipWorkspace $workspace,
        ChapterTool $tool
    ): ?WorkspaceToolOutput {
        return WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();
    }

    public function latestAgreedInput(
        PartnershipWorkspace $workspace,
        ChapterTool $tool
    ): array {
        $output = $this->latestAgreedOutput($workspace, $tool);

        if (! $output?->source_tool_session_id) {
            return [];
        }

        $source = ToolSession::query()
            ->whereKey($output->source_tool_session_id)
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->first();

        return is_array($source?->input_data)
            ? $source->input_data
            : [];
    }

    public function outputHistory(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $limit = 12
    ): Collection {
        return WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->limit(max(1, min(50, $limit)))
            ->get();
    }

    private function createOutputRow(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session,
        string $status
    ): WorkspaceToolOutput {
        $latest = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('chapter_tool_id', $tool->id)
            ->orderByDesc('revision')
            ->lockForUpdate()
            ->first();

        $revision = ($latest?->revision ?? 0) + 1;
        $now = now();

        return WorkspaceToolOutput::create([
            'workspace_id' => $workspace->id,
            'chapter_tool_id' => $tool->id,
            'source_tool_session_id' => $session->id,
            'revision' => $revision,
            'status' => $status,
            'output_data' => $session->result_data ?? [],
            'generated_by_user_id' => $user->id,
            'generated_at' => $now,
            'agreed_at' => $status === 'agreed' ? $now : null,
        ]);
    }

    private function assertSessionMatches(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session
    ): void {
        abort_unless(
            (int) $session->workspace_id === (int) $workspace->id
            && (int) $session->chapter_tool_id === (int) $tool->id
            && $session->status === 'draft',
            403
        );
    }

    private function assertBusinessContext(PartnershipWorkspace $workspace): void
    {
        if (! array_key_exists(
            (string) $workspace->business_stage,
            PartnershipWorkspace::BUSINESS_STAGES
        )) {
            throw ValidationException::withMessages([
                'business_stage' => 'PBR Business Operating System မှာ Partnership Stage ကို အရင်သတ်မှတ်ပါ။',
            ]);
        }
    }

    private function authorizeManagement(User $user, PartnershipWorkspace $workspace): void
    {
        abort_unless($this->canManage($user, $workspace), 403);
    }

    private function recordDateFrom(array $data): ?string
    {
        foreach ([
            'decision_date',
            'transfer_date',
            'issue_date',
            'target_date',
            'offer_date',
            'notice_date',
            'start_date',
        ] as $key) {
            if (filled($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    private function copyName(string $name): string
    {
        return Str::limit('Copy of '.$name, 120, '');
    }
}
