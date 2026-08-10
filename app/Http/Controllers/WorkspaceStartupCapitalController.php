<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\StartupCapitalCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceStartupCapitalController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        $this->authorizeAccess($request, $workspace);

        return $this->render(
            $workspace,
            $this->ensureOthersCategory([]),
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
        ?array $result
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

        return view(
            'workspaces.tools.startup-capital',
            compact(
                'workspace',
                'tool',
                'categories',
                'result'
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
