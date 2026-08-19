<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\User;
use App\Models\WorkspaceToolAction;
use App\Models\WorkspaceToolOutput;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PbrToolActionService
{
    public const PRIORITIES = [
        'low',
        'normal',
        'high',
        'critical',
    ];

    public const EDITABLE_STATUSES = [
        'open',
        'in_progress',
        'blocked',
        'completed',
    ];

    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem
    ) {
    }

    public function activateFromApprovedSession(
        User $user,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session,
        WorkspaceToolOutput $output
    ): Collection {
        $input = is_array($session->input_data)
            ? $session->input_data
            : [];

        $context = is_array($input['operating_context'] ?? null)
            ? $input['operating_context']
            : [];

        $actions = collect(
            is_array($input['operating_actions'] ?? null)
                ? $input['operating_actions']
                : []
        )->filter(
            fn ($row): bool => is_array($row)
                && filled($row['title'] ?? null)
        )->values();

        $definition = config(
            'pbr_operating_tools.definitions.'.$tool->tool_key,
            []
        );

        $isRecordTool = is_array($definition)
            && filled($definition['record_type'] ?? null);

        if (! $isRecordTool) {
            WorkspaceToolAction::query()
                ->where('workspace_id', $workspace->id)
                ->where('chapter_tool_id', $tool->id)
                ->active()
                ->update([
                    'status' => 'superseded',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $actions->map(function (array $row) use (
            $user,
            $workspace,
            $tool,
            $session,
            $output,
            $context
        ): WorkspaceToolAction {
            $priority = in_array(
                $row['priority'] ?? null,
                self::PRIORITIES,
                true
            ) ? $row['priority'] : 'normal';

            $status = in_array(
                $row['status'] ?? null,
                self::EDITABLE_STATUSES,
                true
            ) ? $row['status'] : 'open';

            $description = filled($row['description'] ?? null)
                ? trim((string) $row['description'])
                : null;

            $ownerName = filled($row['owner_name'] ?? null)
                ? (string) $row['owner_name']
                : ($context['owner_name'] ?? null);

            return WorkspaceToolAction::create([
                'workspace_id' => $workspace->id,
                'chapter_tool_id' => $tool->id,
                'source_tool_session_id' => $session->id,
                'workspace_tool_output_id' => $output->id,
                'created_by_user_id' => $user->id,
                'title' => Str::limit(
                    trim((string) $row['title']),
                    180,
                    ''
                ),
                'description' => $description,
                'owner_name' => filled($ownerName)
                    ? Str::limit(trim((string) $ownerName), 160, '')
                    : null,
                'priority' => $priority,
                'status' => $status,
                'due_date' => filled($row['due_date'] ?? null)
                    ? (string) $row['due_date']
                    : null,
                'completed_at' => $status === 'completed'
                    ? now()
                    : null,
                'operating_context' => array_filter([
                    'scenario_name' => $session->scenario_name,
                    'revision' => $output->revision,
                    'decision_summary' =>
                        $context['decision_summary'] ?? null,
                    'effective_date' =>
                        $context['effective_date'] ?? null,
                    'review_date' =>
                        $context['review_date'] ?? null,
                    'evidence' =>
                        $context['evidence'] ?? null,
                    'operating_status' =>
                        $context['status'] ?? null,
                ], fn ($value): bool => $value !== null && $value !== ''),
            ]);
        });
    }

    public function changeStatus(
        User $user,
        PartnershipWorkspace $workspace,
        WorkspaceToolAction $action,
        string $status
    ): WorkspaceToolAction {
        abort_unless(
            $this->operatingSystem->canManage($user, $workspace),
            403
        );

        abort_unless(
            (int) $action->workspace_id === (int) $workspace->id,
            404
        );

        if (! in_array($status, self::EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Action Status မမှန်ပါ။',
            ]);
        }

        $action->status = $status;
        $action->completed_at = $status === 'completed'
            ? now()
            : null;
        $action->save();

        return $action->fresh();
    }
}
