<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\CourseChapter;
use App\Models\WorkspaceToolOutput;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user()->load([
            'classSession',
            'usedAccessCode',
            'studentEnrollments.classSession',
            'ownedWorkspaces',
            'workspaceMemberships.workspace',
        ]);

        $workspaces = $user->ownedWorkspaces
            ->concat(
                $user->workspaceMemberships
                    ->where('invitation_status', 'accepted')
                    ->pluck('workspace')
                    ->filter()
            )
            ->unique('id')
            ->values();

        $chapterCount = CourseChapter::query()->count();
        $toolCount = ChapterTool::query()
            ->distinct()
            ->count('tool_key');

        $agreedToolCounts = $workspaces->isEmpty()
            ? collect()
            : WorkspaceToolOutput::query()
                ->whereIn('workspace_id', $workspaces->pluck('id'))
                ->where('status', 'agreed')
                ->selectRaw('workspace_id, COUNT(DISTINCT chapter_tool_id) as agreed_count')
                ->groupBy('workspace_id')
                ->pluck('agreed_count', 'workspace_id');

        return view('student.dashboard', compact(
            'user',
            'workspaces',
            'chapterCount',
            'toolCount',
            'agreedToolCounts'
        ));
    }
}
