<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Services\PbrTools\StartupCapitalCalculator;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $scenarioComparisons = $drafts
            ->map(function (ToolSession $draft): array {
                $data = is_array($draft->result_data) ? $draft->result_data : [];
                $total = (float) ($data['total_startup_capital'] ?? 0);
                $funded = (float) ($data['funded_total'] ?? 0);

                return [
                    'id' => $draft->id,
                    'name' => $draft->scenario_name ?: 'Draft Plan',
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

        return view($view, compact(
            'workspace',
            'tool',
            'categories',
            'result',
            'activeSession',
            'drafts',
            'scenarioComparisons',
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
