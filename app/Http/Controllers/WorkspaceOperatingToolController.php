<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Services\PbrTools\PbrOperatingSystemService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolPrefillService;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceOperatingToolController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        PbrOperatingToolEngine $engine,
        PbrToolPrefillService $prefill,
        ToolScenarioService $scenarios,
        PbrOperatingSystemService $operatingSystem
    ): View {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $definition = $engine->definition($tool->tool_key);
        $canManage = $scenarios->canManage($request->user(), $workspace);

        if ($canManage) {
            $operatingSystem->syncWorkspacePartners($workspace);
        }

        $activeSession = null;
        $input = $engine->defaultInput($tool->tool_key);
        $result = null;

        if ($canManage && $request->query('session') !== null) {
            $sessionId = (string) $request->query('session');
            abort_unless(ctype_digit($sessionId), 404);

            $activeSession = $scenarios->ownedDraft(
                $request->user(),
                $workspace,
                $tool,
                (int) $sessionId
            );

            $input = is_array($activeSession->input_data)
                ? $activeSession->input_data
                : $input;

            $result = is_array($activeSession->result_data)
                ? $activeSession->result_data
                : null;
        } elseif ($canManage) {
            $input = $prefill->prefill(
                $workspace,
                $tool,
                $input
            );
        } else {
            $agreed = $scenarios->latestAgreedOutput($workspace, $tool);
            $result = is_array($agreed?->output_data)
                ? $agreed->output_data
                : null;
        }

        return $this->render(
            $request,
            $workspace,
            $tool,
            $definition,
            $input,
            $result,
            $activeSession,
            $canManage,
            $scenarios
        );
    }

    public function calculate(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        PbrOperatingToolEngine $engine,
        ToolScenarioService $scenarios
    ): View {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $this->authorizeManagement($request, $workspace, $scenarios);
        $definition = $engine->definition($tool->tool_key);

        $rules = array_merge(
            ['tool_session_id' => ['nullable', 'integer']],
            $engine->rules($tool->tool_key)
        );
        $validated = $request->validate($rules);

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
        $input = $validated;
        $result = $engine->calculate($tool->tool_key, $input, $workspace);

        return $this->render(
            $request,
            $workspace,
            $tool,
            $definition,
            $input,
            $result,
            $activeSession,
            true,
            $scenarios
        );
    }

    public function save(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        PbrOperatingToolEngine $engine,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $this->authorizeManagement($request, $workspace, $scenarios);

        $rules = array_merge([
            'scenario_name' => ['required', 'string', 'max:120'],
            'tool_session_id' => ['nullable', 'integer'],
        ], $engine->rules($tool->tool_key));

        $validated = $request->validate($rules);
        $scenarioName = (string) $validated['scenario_name'];
        $sessionId = ! empty($validated['tool_session_id'])
            ? (int) $validated['tool_session_id']
            : null;

        unset(
            $validated['scenario_name'],
            $validated['tool_session_id']
        );

        $input = $validated;
        $result = $engine->calculate($tool->tool_key, $input, $workspace);

        $session = $scenarios->saveDraft(
            $request->user(),
            $workspace,
            $tool,
            $scenarioName,
            $input,
            $result,
            $sessionId
        );

        return redirect()
            ->route('workspaces.tools.operating.show', [
                'workspace' => $workspace,
                'toolSlug' => $tool->slug,
                'session' => $session->id,
            ])
            ->with('status', 'Scenario ကို Draft အဖြစ်သိမ်းပြီးပါပြီ။');
    }

    private function resolveTool(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug
    ): ChapterTool {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $tool = ChapterTool::query()
            ->where('slug', $toolSlug)
            ->whereHas('chapter', fn ($query) => $query
                ->whereBetween('chapter_number', [2, 10]))
            ->with('chapter:id,chapter_number,title_en,title_mm')
            ->firstOrFail();

        $supported = $workspace->business_stage === 'new'
            ? $tool->supports_new_business
            : $tool->supports_existing_business;

        abort_unless($supported, 404);

        return $tool;
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

    private function render(
        Request $request,
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        array $definition,
        array $input,
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

        return view('workspaces.tools.operating-tool', compact(
            'workspace',
            'tool',
            'definition',
            'input',
            'result',
            'activeSession',
            'drafts',
            'canManage',
            'latestAgreedOutput',
            'outputHistory'
        ));
    }
}
