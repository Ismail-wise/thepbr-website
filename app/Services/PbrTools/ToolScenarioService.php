<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Models\WorkspaceToolOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ToolScenarioService
{
    public function saveDraft(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        string $scenarioName,
        array $inputData,
        array $resultData,
        ?int $sessionId = null
    ): ToolSession {

        if (! array_key_exists(
            (string) $workspace->business_stage,
            PartnershipWorkspace::BUSINESS_STAGES
        )) {
            throw ValidationException::withMessages([
                'business_stage' =>
                    'Please set Partnership Stage in PBR Business Tools before saving a scenario.',
            ]);
        }

        $scenarioName = trim($scenarioName);

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
            $session->business_stage =
                $workspace->business_stage;
            $session->status = 'draft';
            $session->started_at = now();
        }

        $session->scenario_name = $scenarioName;
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
    ) {
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
        $session = $this->ownedDraft(
            $user,
            $workspace,
            $tool,
            $sessionId
        );

        $session->scenario_name = trim($scenarioName);
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
        $copy->business_stage =
            $source->business_stage;

        $copy->scenario_name =
            $this->copyName(
                $source->scenario_name ?: 'Scenario'
            );

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
        abort_unless(
            $session->workspace_id === $workspace->id
            && $session->chapter_tool_id === $tool->id,
            403
        );

        return DB::transaction(
            function () use (
                $user,
                $workspace,
                $tool,
                $session
            ) {
                $latest = WorkspaceToolOutput::query()
                    ->where(
                        'workspace_id',
                        $workspace->id
                    )
                    ->where(
                        'chapter_tool_id',
                        $tool->id
                    )
                    ->orderByDesc('revision')
                    ->lockForUpdate()
                    ->first();

                $revision =
                    ($latest?->revision ?? 0) + 1;

                return WorkspaceToolOutput::create([
                    'workspace_id' =>
                        $workspace->id,

                    'chapter_tool_id' =>
                        $tool->id,

                    'source_tool_session_id' =>
                        $session->id,

                    'revision' => $revision,

                    'status' => 'draft',

                    'output_data' =>
                        $session->result_data ?? [],

                    'generated_by_user_id' =>
                        $user->id,

                    'generated_at' => now(),
                ]);
            }
        );
    }

    private function copyName(string $name): string
    {
        $base = 'Copy of '.$name;

        return Str::limit(
            $base,
            120,
            ''
        );
    }
}
