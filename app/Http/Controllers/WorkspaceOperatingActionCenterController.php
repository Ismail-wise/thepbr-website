<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\PbrOperatingSystemService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceOperatingActionCenterController extends Controller
{
    public function index(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrOperatingSystemService $operatingSystem
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $operatingSystem->canManage(
                $request->user(),
                $workspace
            ),
            403
        );

        $status = in_array(
            $request->query('status', 'active'),
            [
                'active',
                'open',
                'in_progress',
                'blocked',
                'completed',
                'all',
            ],
            true
        ) ? $request->query('status', 'active') : 'active';

        $priority = in_array(
            $request->query('priority'),
            [
                'low',
                'normal',
                'high',
                'critical',
            ],
            true
        ) ? $request->query('priority') : null;

        $owner = filled($request->query('owner'))
            ? trim((string) $request->query('owner'))
            : null;

        $due = in_array(
            $request->query('due'),
            [
                'overdue',
                'today',
                'seven_days',
                'no_date',
            ],
            true
        ) ? $request->query('due') : null;

        $base = WorkspaceToolAction::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', 'superseded');

        $summary = [
            'active' => (clone $base)
                ->whereIn('status', [
                    'open',
                    'in_progress',
                    'blocked',
                ])
                ->count(),
            'open' => (clone $base)
                ->where('status', 'open')
                ->count(),
            'in_progress' => (clone $base)
                ->where('status', 'in_progress')
                ->count(),
            'blocked' => (clone $base)
                ->where('status', 'blocked')
                ->count(),
            'overdue' => (clone $base)
                ->whereIn('status', [
                    'open',
                    'in_progress',
                    'blocked',
                ])
                ->whereNotNull('due_date')
                ->whereDate(
                    'due_date',
                    '<',
                    now()->toDateString()
                )
                ->count(),
        ];

        $owners = (clone $base)
            ->whereNotNull('owner_name')
            ->where('owner_name', '!=', '')
            ->distinct()
            ->orderBy('owner_name')
            ->pluck('owner_name');

        $query = clone $base;

        if ($status === 'active') {
            $query->whereIn('status', [
                'open',
                'in_progress',
                'blocked',
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        if ($owner !== null) {
            $query->where('owner_name', $owner);
        }

        if ($due === 'overdue') {
            $query
                ->whereNotNull('due_date')
                ->whereDate(
                    'due_date',
                    '<',
                    now()->toDateString()
                );
        } elseif ($due === 'today') {
            $query->whereDate(
                'due_date',
                now()->toDateString()
            );
        } elseif ($due === 'seven_days') {
            $query
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [
                    now()->toDateString(),
                    now()->addDays(7)->toDateString(),
                ]);
        } elseif ($due === 'no_date') {
            $query->whereNull('due_date');
        }

        $actions = $query
            ->orderByRaw(
                "CASE
                    WHEN status = 'blocked' THEN 0
                    WHEN status = 'in_progress' THEN 1
                    WHEN status = 'open' THEN 2
                    ELSE 3
                END"
            )
            ->orderByRaw(
                "CASE
                    WHEN priority = 'critical' THEN 0
                    WHEN priority = 'high' THEN 1
                    WHEN priority = 'normal' THEN 2
                    ELSE 3
                END"
            )
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        $tools = ChapterTool::query()
            ->published()
            ->with('chapter:id,chapter_number,title_en,title_mm')
            ->whereIn(
                'id',
                $actions->pluck('chapter_tool_id')->unique()
            )
            ->get()
            ->keyBy('id');

        $filters = compact(
            'status',
            'priority',
            'owner',
            'due'
        );

        return view(
            'workspaces.tools.action-center',
            compact(
                'workspace',
                'actions',
                'tools',
                'summary',
                'owners',
                'filters'
            )
        );
    }
}
