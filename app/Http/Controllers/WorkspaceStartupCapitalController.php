<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceStartupCapitalController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        ToolScenarioService $scenarios
    ): View {
        $this->authorizeAccess($request, $workspace);
        $tool = $this->resolveTool();
        $canManage = $scenarios->canManage($request->user(), $workspace);
        $activeSession = null;
        $categories = [];
        $result = null;

        if (! $canManage) {
            $agreed = $scenarios->latestAgreedOutput($workspace, $tool);
            $result = is_array($agreed?->output_data)
                ? $agreed->output_data
                : null;

            return $this->render(
                $request,
                $workspace,
                $tool,
                $categories,
                $result,
                null,
                false,
                $scenarios
            );
        }

        if ($request->query('session') !== null) {
            $sessionId = (string) $request->query('session');
            abort_unless(ctype_digit($sessionId), 404);

            $activeSession = $scenarios->ownedDraft(
                $request->user(),
                $workspace,
                $tool,
                (int) $sessionId
            );

            $categories = is_array($activeSession->input_data['categories'] ?? null)
                ? $activeSession->input_data['categories']
                : [];
            $result = is_array($activeSession->result_data)
                ? $activeSession->result_data
                : null;
        }

        return $this->render(
            $request,
            $workspace,
            $tool,
            $categories,
            $result,
            $activeSession,
            true,
            $scenarios
        );
    }

    public function calculate(
        Request $request,
        PartnershipWorkspace $workspace,
        StartupCapitalCalculator $calculator,
        ToolScenarioService $scenarios
    ): View {
        $this->authorizeAccess($request, $workspace);
        $this->authorizeManagement($request, $workspace, $scenarios);
        $tool = $this->resolveTool();

        $validated = $request->validate(array_merge(
            ['tool_session_id' => ['nullable', 'integer']],
            $this->rules()
        ));

        $activeSession = null;
        if (! empty($validated['tool_session_id'])) {
            $activeSession = $scenarios->ownedDraft(
                $request->user(),
                $workspace,
                $tool,
                (int) $validated['tool_session_id']
            );
        }

        unset($validated['tool_session_id']);
        $categories = $validated['categories'] ?? [];
        $result = $calculator->calculate(['categories' => $categories]);

        return $this->render(
            $request,
            $workspace,
            $tool,
            $categories,
            $result,
            $activeSession,
            true,
            $scenarios
        );
    }

    private function authorizeAccess(
        Request $request,
        PartnershipWorkspace $workspace
    ): void {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $workspace->business_stage === 'new',
            404
        );
    }

    private function authorizeManagement(
        Request $request,
        PartnershipWorkspace $workspace,
        ToolScenarioService $scenarios
    ): void {
        abort_unless(
            $scenarios->canManage($request->user(), $workspace),
            403
        );
    }

    private function resolveTool(): ChapterTool
    {
        return ChapterTool::query()
            ->where('tool_key', 'startup_capital_planner')
            ->where('supports_new_business', true)
            ->whereHas('chapter', fn ($query) => $query->where('chapter_number', 1))
            ->with('chapter:id,chapter_number,title_en,title_mm')
            ->firstOrFail();
    }

    private function render(
        Request $request,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        array $categories,
        ?array $result,
        ?ToolSession $activeSession,
        bool $canManage,
        ToolScenarioService $scenarios
    ): View {
        $drafts = $canManage
            ? $scenarios->drafts($request->user(), $workspace, $tool)
            : collect();
        $latestAgreedOutput = $scenarios->latestAgreedOutput($workspace, $tool);
        $outputHistory = $canManage
            ? $scenarios->outputHistory($workspace, $tool)
            : collect([$latestAgreedOutput])->filter();

        return view('workspaces.tools.startup-capital', compact(
            'workspace',
            'tool',
            'categories',
            'result',
            'activeSession',
            'drafts',
            'canManage',
            'latestAgreedOutput',
            'outputHistory'
        ));
    }

    private function rules(): array
    {
        return [
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
        ];
    }
}
