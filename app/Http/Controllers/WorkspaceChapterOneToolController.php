<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\WorkspaceToolAction;
use App\Services\PbrTools\CapitalWorkflowService;
use App\Services\PbrTools\ChapterOneCapitalService;
use App\Services\PbrTools\ChapterOneIntegrationService;
use App\Services\PbrTools\PbrToolOperatingContextService;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceChapterOneToolController extends Controller
{
    public function __construct(
        private readonly CapitalWorkflowService $capitalWorkflow,
        private readonly PbrToolOperatingContextService $operatingContext
    ) {
    }

    private const SUPPORTED_TOOLS = [
        'current_capital_position',
        'working_capital_calculator',
        'contingency_fund_calculator',
        'partner_contribution_matrix',
        'funding_gap_calculator',
        'capital_allocation_chart',
    ];

    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        ToolScenarioService $scenarios,
        ChapterOneIntegrationService $integration
    ): View {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $canManage = $scenarios->canManage($request->user(), $workspace);
        $activeSession = null;
        $input = $this->defaultInput($tool->tool_key);
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
                $input,
                $result,
                null,
                false,
                $scenarios
            );
        }

        if ($request->query('session') === null) {
            $approvedInput = $scenarios->latestAgreedInput($workspace, $tool);

            if (! empty($approvedInput)) {
                unset($approvedInput['operating_actions']);
                $input = array_replace_recursive($input, $approvedInput);
            }

            $input = $integration->prefill(
                $workspace,
                $tool->tool_key,
                $input
            );
        } else {
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
        }

        $input = $this->operatingContext->withDefaults(
            $input,
            $request->user()->name
        );

        return $this->render(
            $request,
            $workspace,
            $tool,
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
        string $toolSlug,
        ChapterOneCapitalService $capital,
        ToolScenarioService $scenarios
    ): View {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $this->authorizeManagement($request, $workspace, $scenarios);

        $validated = $request->validate(array_merge(
            $this->operatingContext->rules(),
            $this->rules($tool->tool_key)
        ));

        $input = $this->operatingContext->normalize(
            $this->toolInput($validated),
            $request->user()->name
        );

        $result = $this->calculateTool(
            $tool->tool_key,
            $this->operatingContext->toolInput($input),
            $capital
        );
        $activeSession = null;

        if (! empty($validated['tool_session_id'])) {
            $activeSession = $scenarios->ownedDraft(
                $request->user(),
                $workspace,
                $tool,
                (int) $validated['tool_session_id']
            );
        }

        return $this->render(
            $request,
            $workspace,
            $tool,
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
        ChapterOneCapitalService $capital,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $this->authorizeManagement($request, $workspace, $scenarios);

        $rules = array_merge([
            'scenario_name' => ['required', 'string', 'max:120'],
            'tool_session_id' => ['nullable', 'integer'],
        ], $this->operatingContext->rules(), $this->rules(
            $tool->tool_key,
            false
        ));

        $validated = $request->validate($rules);

        $input = $this->operatingContext->normalize(
            $this->toolInput($validated),
            $request->user()->name
        );

        $result = $this->calculateTool(
            $tool->tool_key,
            $this->operatingContext->toolInput($input),
            $capital
        );

        $session = $scenarios->saveDraft(
            $request->user(),
            $workspace,
            $tool,
            $validated['scenario_name'],
            $input,
            $result,
            ! empty($validated['tool_session_id'])
                ? (int) $validated['tool_session_id']
                : null
        );

        return redirect(
            url('/workspaces/'.$workspace->id.'/tools/'.$tool->slug)
            .'?session='.$session->id
        )->with('status', 'Working Draft သိမ်းပြီးပါပြီ။ လက်ရှိ Active Business Rule ကို မပြောင်းသေးပါ။');
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
            ->whereIn('tool_key', self::SUPPORTED_TOOLS)
            ->whereHas(
                'chapter',
                fn ($query) => $query->where('chapter_number', 1)
            )
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

        return view('workspaces.tools.chapter-one', compact(
            'workspace',
            'tool',
            'input',
            'result',
            'activeSession',
            'drafts',
            'canManage',
            'latestAgreedOutput',
            'outputHistory',
            'capitalWorkflow',
            'operatingActions'
        ));
    }

    private function calculateTool(
        string $toolKey,
        array $input,
        ChapterOneCapitalService $capital
    ): array {
        return match ($toolKey) {
            'current_capital_position' => $capital->currentCapitalPosition($input),
            'working_capital_calculator' => $capital->workingCapital($input),
            'contingency_fund_calculator' => $capital->contingencyFund($input),
            'partner_contribution_matrix' => $capital->partnerContributions($input),
            'funding_gap_calculator' => $capital->fundingGap($input),
            'capital_allocation_chart' => $capital->capitalAllocation($input),
            default => abort(404),
        };
    }

    private function rules(string $toolKey, bool $includeSession = true): array
    {
        $base = $includeSession
            ? ['tool_session_id' => ['nullable', 'integer']]
            : [];

        return array_merge($base, match ($toolKey) {
            'current_capital_position' => array_merge(
                $this->categoryRules('resources'),
                $this->categoryRules('liabilities')
            ),
            'working_capital_calculator' => array_merge(
                $this->categoryRules('monthly_costs'),
                [
                    'reserve_months' => ['nullable', 'numeric', 'min:0', 'max:24'],
                    'inventory_requirement' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'short_term_payables' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'expected_receivables' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                ]
            ),
            'contingency_fund_calculator' => [
                'method' => ['required', 'in:percentage,months'],
                'base_capital' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'monthly_operating_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                'months' => ['nullable', 'numeric', 'min:0', 'max:24'],
            ],
            'partner_contribution_matrix' => [
                'partners' => ['nullable', 'array', 'max:30'],
                'partners.*.name' => ['nullable', 'string', 'max:120'],
                'partners.*.contributions' => ['nullable', 'array', 'max:100'],
                'partners.*.contributions.*.name' => ['nullable', 'string', 'max:150'],
                'partners.*.contributions.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            ],
            'funding_gap_calculator' => [
                'capital_required' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                'partner_capital' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                'other_funding' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            ],
            'capital_allocation_chart' => [
                'allocations' => ['nullable', 'array', 'max:100'],
                'allocations.*.name' => ['nullable', 'string', 'max:150'],
                'allocations.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            ],
            default => [],
        });
    }

    private function categoryRules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array', 'max:30'],
            $prefix.'.*.name' => ['nullable', 'string', 'max:120'],
            $prefix.'.*.items' => ['nullable', 'array', 'max:100'],
            $prefix.'.*.items.*.name' => ['nullable', 'string', 'max:150'],
            $prefix.'.*.items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    private function toolInput(array $validated): array
    {
        unset($validated['scenario_name'], $validated['tool_session_id']);
        return $validated;
    }

    private function defaultInput(string $toolKey): array
    {
        return match ($toolKey) {
            'current_capital_position' => [
                'resources' => [],
                'liabilities' => [],
            ],
            'working_capital_calculator' => [
                'monthly_costs' => [],
                'reserve_months' => '',
                'inventory_requirement' => '',
                'short_term_payables' => '',
                'expected_receivables' => '',
            ],
            'contingency_fund_calculator' => [
                'method' => 'percentage',
                'base_capital' => '',
                'percentage' => '',
                'monthly_operating_cost' => '',
                'months' => '',
            ],
            'partner_contribution_matrix' => ['partners' => []],
            'funding_gap_calculator' => [
                'capital_required' => '',
                'partner_capital' => '',
                'other_funding' => '',
            ],
            'capital_allocation_chart' => ['allocations' => []],
            default => [],
        };
    }
}
