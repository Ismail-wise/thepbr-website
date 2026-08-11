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
        PartnershipWorkspace $workspace
    ): View {
        $this->authorizeAccess($request, $workspace);

        $tool = ChapterTool::query()
            ->where(
                'tool_key',
                'startup_capital_planner'
            )
            ->where(
                'supports_new_business',
                true
            )
            ->firstOrFail();

        $activeSession = null;

        if ($request->query('session') !== null) {
            $sessionId = (string) $request->query('session');

            abort_unless(
                ctype_digit($sessionId),
                404
            );

            $activeSession = ToolSession::query()
                ->whereKey((int) $sessionId)
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'chapter_tool_id',
                    $tool->id
                )
                ->where(
                    'status',
                    'draft'
                )
                ->firstOrFail();

            return $this->render(
                $workspace,
                $this->ensureOthersCategory(
                    $activeSession->input_data[
                        'categories'
                    ] ?? []
                ),
                is_array($activeSession->result_data)
                    ? $activeSession->result_data
                    : null,
                $activeSession
            );
        }

        return $this->render(
            $workspace,
            $this->ensureOthersCategory([]),
            null,
            null
        );
    }

    public function calculate(
        Request $request,
        PartnershipWorkspace $workspace,
        StartupCapitalCalculator $calculator
    ): View {
        $this->authorizeAccess($request, $workspace);

        $validated = $request->validate([
            'categories' => [
                'required',
                'array',
                'max:30',
            ],

            'categories.*.name' => [
                'required',
                'string',
                'max:120',
            ],

            'categories.*.items' => [
                'nullable',
                'array',
                'max:100',
            ],

            'categories.*.items.*.name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'categories.*.items.*.amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
        ]);

        $validated['categories'] =
            $this->ensureOthersCategory(
                $validated['categories'] ?? []
            );

        $result = $calculator->calculate(
            $validated
        );

        return $this->render(
            $workspace,
            $validated['categories'],
            $result
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

    private function render(
        PartnershipWorkspace $workspace,
        array $categories,
        ?array $result,
        ?ToolSession $activeSession = null
    ): View {
        $tool = ChapterTool::query()
            ->where(
                'tool_key',
                'startup_capital_planner'
            )
            ->where(
                'supports_new_business',
                true
            )
            ->firstOrFail();

        $drafts = app(ToolScenarioService::class)
            ->drafts(
                auth()->user(),
                $workspace,
                $tool
            );

        return view(
            'workspaces.tools.startup-capital',
            compact(
                'workspace',
                'tool',
                'categories',
                'result',
                'activeSession',
                'drafts'
            )
        );
    }

    private function ensureOthersCategory(
        array $categories
    ): array {
        $hasOthers = false;

        foreach ($categories as &$category) {
            if (
                strtolower(
                    trim(
                        (string) ($category['name'] ?? '')
                    )
                ) === 'others'
            ) {
                $category['name'] = 'Others';
                $hasOthers = true;
                break;
            }
        }

        unset($category);

        if (! $hasOthers) {
            $categories[] = [
                'name' => 'Others',
                'items' => [
                    [
                        'name' => '',
                        'amount' => '',
                    ],
                ],
            ];
        }

        return array_values($categories);
    }
}
