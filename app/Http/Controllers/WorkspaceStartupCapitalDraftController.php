<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceStartupCapitalDraftController extends Controller
{
    public function store(
        Request $request,
        PartnershipWorkspace $workspace,
        StartupCapitalCalculator $calculator,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );
        abort_unless(
            $scenarios->canManage($request->user(), $workspace),
            403
        );
        abort_unless(
            $workspace->business_stage === 'new',
            404
        );

        $validated = $request->validate([
            'scenario_name' => ['required', 'string', 'max:120'],
            'tool_session_id' => ['nullable', 'integer'],
            'categories' => ['required', 'array', 'max:30'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.items' => ['nullable', 'array', 'max:100'],
            'categories.*.items.*.name' => ['nullable', 'string', 'max:150'],
            'categories.*.items.*.amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
        ]);

        $tool = ChapterTool::query()
            ->where('tool_key', 'startup_capital_planner')
            ->where('supports_new_business', true)
            ->whereHas('chapter', fn ($query) => $query->where('chapter_number', 1))
            ->firstOrFail();

        $inputData = ['categories' => $validated['categories']];
        $resultData = $calculator->calculate($inputData);

        $session = $scenarios->saveDraft(
            $request->user(),
            $workspace,
            $tool,
            $validated['scenario_name'],
            $inputData,
            $resultData,
            ! empty($validated['tool_session_id'])
                ? (int) $validated['tool_session_id']
                : null
        );

        return redirect()
            ->route('workspaces.tools.startup-capital.show', [
                'workspace' => $workspace,
                'session' => $session->id,
            ])
            ->with('status', 'Scenario ကို Draft အဖြစ်သိမ်းပြီးပါပြီ။');
    }
}
