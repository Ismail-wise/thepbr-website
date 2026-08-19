<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\CapitalWorkflowService;
use App\Services\PbrTools\PbrToolOperatingContextService;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceStartupCapitalController extends Controller
{
    public function __construct(
        private readonly CapitalWorkflowService $capitalWorkflow,
        private readonly PbrToolOperatingContextService $operatingContext
    ) {
    }

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
        $input = ['categories' => []];
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
                $input,
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

            $input = is_array($activeSession->input_data)
                ? $activeSession->input_data
                : $input;
            $categories = is_array($input['categories'] ?? null)
                ? $input['categories']
                : [];
            $result = is_array($activeSession->result_data)
                ? $activeSession->result_data
                : null;
        } else {
            $approvedInput = $scenarios->latestAgreedInput($workspace, $tool);

            if (is_array($approvedInput)) {
                unset($approvedInput['operating_actions']);
                $input = $approvedInput;
            }

            $categories = is_array($input['categories'] ?? null)
                ? $input['categories']
                : [];
        }

        $input = $this->operatingContext->withDefaults(
            $input,
            $request->user()->name
        );

        return $this->render(
            $request,
            $workspace,
            $tool,
            $categories,
            $input,
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
            $this->operatingContext->rules(),
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

        $input = $this->operatingContext->normalize(
            $validated,
            $request->user()->name
        );
        $toolInput = $this->operatingContext->toolInput($input);
        $categories = $toolInput['categories'] ?? [];
        $result = $calculator->calculate($toolInput);

        return $this->render(
            $request,
            $workspace,
            $tool,
            $categories,
            $input,
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
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);
        abort_unless($workspace->business_stage === 'new', 404);
    }

    private function authorizeManagement(
        Request $request,
        PartnershipWorkspace $workspace,
        ToolScenarioService $scenarios
    ): void {
        abort_unless($scenarios->canManage($request->user(), $workspace), 403);
    }

    private function resolveTool(): ChapterTool
    {
        return ChapterTool::query()
            ->published()
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

        $scenarioComparisons = $drafts
            ->map(function (ToolSession $draft) use ($activeSession): array {
                $data = is_array($draft->result_data) ? $draft->result_data : [];
                $total = (float) ($data['total_startup_capital'] ?? 0);
                $funded = (float) ($data['funded_total'] ?? 0);

                return [
                    'id' => $draft->id,
                    'name' => $draft->scenario_name ?: 'Working Plan',
                    'total' => $total,
                    'essential' => (float) ($data['essential_total'] ?? $total),
                    'funded' => $funded,
                    'gap' => (float) ($data['funding_gap'] ?? max(0, $total - $funded)),
                    'due_30_days' => (float) ($data['due_30_days_outstanding'] ?? 0),
                    'updated_at' => $draft->last_saved_at,
                    'is_active' => (int) $activeSession?->id === (int) $draft->id,
                ];
            })
            ->values();

        $view = $canManage
            ? 'workspaces.tools.startup-capital'
            : 'workspaces.tools.startup-capital-readonly';

        $capitalWorkflow = $this->capitalWorkflow->build(
            $request->user(),
            $workspace
        );

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
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view($view, compact(
            'workspace',
            'tool',
            'categories',
            'input',
            'result',
            'activeSession',
            'drafts',
            'scenarioComparisons',
            'canManage',
            'latestAgreedOutput',
            'outputHistory',
            'capitalWorkflow',
            'operatingActions'
        ));
    }

    private function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'max:30'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.items' => ['nullable', 'array', 'max:100'],
            'categories.*.items.*.name' => ['nullable', 'string', 'max:150'],
            'categories.*.items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'categories.*.items.*.priority' => ['nullable', Rule::in(['essential', 'optional'])],
            'categories.*.items.*.frequency' => ['nullable', Rule::in(['one_time', 'monthly'])],
            'categories.*.items.*.reserve_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'categories.*.items.*.funded_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'categories.*.items.*.funding_source' => ['nullable', 'string', 'max:150'],
            'categories.*.items.*.due_date' => ['nullable', 'date_format:Y-m-d'],
            'categories.*.items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
