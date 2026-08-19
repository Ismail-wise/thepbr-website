<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\WorkspaceOperatingRecord;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\PbrToolApprovalReadinessService;
use App\Services\PbrTools\PbrToolBusinessGuidanceService;
use App\Services\PbrTools\PbrToolOperatingContextService;
use App\Services\PbrTools\PbrOperatingSystemService;
use App\Services\PbrTools\PbrOperatingToolEngine;
use App\Services\PbrTools\PbrToolPrefillService;
use App\Services\PbrTools\PbrToolRuntimeContractService;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceOperatingToolController extends Controller
{
    public function __construct(
        private readonly PbrToolRuntimeContractService $runtimeContracts,
        private readonly PbrToolApprovalReadinessService $approvalReadiness,
        private readonly PbrToolBusinessGuidanceService $businessGuidance,
        private readonly PbrToolOperatingContextService $operatingContext
    ) {
    }

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
            // Start from the current approved rule when one exists, then let
            // cross-domain prefills fill only blanks. Saving creates a new
            // working version; it never edits the active rule in place.
            $approvedInput = $scenarios->latestAgreedInput($workspace, $tool);
            if (! empty($approvedInput)) {
                // Keep the approved business inputs and decision context,
                // but do not recreate actions from the previous revision.
                unset($approvedInput['operating_actions']);

                $input = array_replace_recursive($input, $approvedInput);
            }

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

        if ($canManage) {
            $input = $this->operatingContext->withDefaults(
                $input,
                $request->user()->name
            );
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
            $this->operatingContext->rules(),
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

        $input = $this->operatingContext->normalize(
            $validated,
            $request->user()->name
        );

        $result = $engine->calculate(
            $tool->tool_key,
            $this->operatingContext->toolInput($input),
            $workspace
        );

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
        ], $this->operatingContext->rules(), $engine->rules($tool->tool_key));

        $validated = $request->validate($rules);
        $scenarioName = (string) $validated['scenario_name'];
        $sessionId = ! empty($validated['tool_session_id'])
            ? (int) $validated['tool_session_id']
            : null;

        unset(
            $validated['scenario_name'],
            $validated['tool_session_id']
        );

        $input = $this->operatingContext->normalize(
            $validated,
            $request->user()->name
        );

        $result = $engine->calculate(
            $tool->tool_key,
            $this->operatingContext->toolInput($input),
            $workspace
        );

        $session = $scenarios->saveDraft(
            $request->user(),
            $workspace,
            $tool,
            $scenarioName,
            $input,
            $result,
            $sessionId
        );

        $runtimeContract =
            $this->runtimeContracts->forTool($tool);

        $statusMessage =
            $runtimeContract['is_record']
                ? 'Working Record သိမ်းပြီးပါပြီ။ Approve မလုပ်မချင်း Operating History ထဲကို မထည့်သေးပါ။'
                : 'Working Draft သိမ်းပြီးပါပြီ။ လက်ရှိ Active Business Rule ကို မပြောင်းသေးပါ။';

        return redirect()
            ->route('workspaces.tools.operating.show', [
                'workspace' => $workspace,
                'toolSlug' => $tool->slug,
                'session' => $session->id,
            ])
            ->with('status', $statusMessage);
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
            ->published()
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

        if ($result === null && is_array($latestAgreedOutput?->output_data)) {
            $result = $latestAgreedOutput->output_data;
        }

        $outputHistory = $canManage
            ? $scenarios->outputHistory($workspace, $tool)
            : collect([$latestAgreedOutput])->filter();

        $toolContract =
            $this->runtimeContracts->forTool($tool);

        $operatingRecords =
            $toolContract['is_record']
                ? WorkspaceOperatingRecord::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('chapter_tool_id', $tool->id)
                    ->where('status', 'active')
                    ->orderByDesc('effective_at')
                    ->orderByDesc('id')
                    ->limit(25)
                    ->get()
                : collect();

        $operatingActions = $canManage
            ? WorkspaceToolAction::query()
                ->where('workspace_id', $workspace->id)
                ->where('chapter_tool_id', $tool->id)
                ->where('status', '!=', 'superseded')
                ->with('workspaceOutput:id,revision')
                ->orderByRaw(
                    "CASE
                        WHEN status = 'blocked' THEN 0
                        WHEN status = 'in_progress' THEN 1
                        WHEN status = 'open' THEN 2
                        ELSE 3
                    END"
                )
                ->orderByRaw(
                    'CASE WHEN due_date IS NULL THEN 1 ELSE 0 END'
                )
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
            : collect();

        $approvalState =
            $canManage && $activeSession
                ? $this->approvalReadiness->assess(
                    $workspace,
                    $tool,
                    $activeSession
                )
                : null;

        $businessGuidance =
            $this->businessGuidance->build(
                $tool,
                $definition,
                $result,
                $toolContract,
                $approvalState,
                $latestAgreedOutput !== null,
                $activeSession !== null
            );

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
            'outputHistory',
            'toolContract',
            'operatingRecords',
            'operatingActions',
            'approvalState',
            'businessGuidance'
        ));
    }
}
