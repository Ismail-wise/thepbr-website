<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceStartupCapitalController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $workspace->business_stage === 'new',
            404
        );

        $tool = ChapterTool::query()
            ->where(
                'tool_key',
                'startup_capital_planner'
            )
            ->firstOrFail();

        return view(
            'workspaces.tools.startup-capital',
            compact(
                'workspace',
                'tool'
            )
        );
    }
}
