<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\PbrV2CapitalActionPlanService;
use App\Services\PbrTools\PbrV2CapitalDecisionRecordService;
use App\Services\PbrTools\PbrV2LifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class WorkspaceV2CapitalActionPlanController extends Controller
{
    public function __construct(
        private readonly PbrV2CapitalActionPlanService $actionPlan,
        private readonly PbrV2CapitalDecisionRecordService $decisionRecord,
        private readonly PbrV2LifecycleService $lifecycle
    ) {
    }

    public function show(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        $actor = $request->user();

        $actions = $this->actionPlan->actions(
            $actor,
            $workspace
        );

        $decisionRecords = $this->decisionRecord->records(
            $actor,
            $workspace
        );

        $rules = $this->lifecycle->transitionRules();

        $actionTransitions = is_array(
            $rules['actions'] ?? null
        )
            ? $rules['actions']
            : [];

        return view(
            'workspaces.v2.capital-action-plan',
            [
                'workspace' => $workspace,
                'actions' => $actions,
                'decisionRecords' => $decisionRecords,
                'priorities' =>
                    $this->actionPlan->priorities(),
                'actionTransitions' =>
                    $actionTransitions,
            ]
        );
    }

    public function store(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $actor = $request->user();

        // Authorization before validation prevents partner probing.
        $this->actionPlan->actions(
            $actor,
            $workspace
        );

        $validated = $request->validate([
            'decision_record_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:4000',
            ],
            'owner_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'priority' => [
                'required',
                'string',
                Rule::in(
                    $this->actionPlan->priorities()
                ),
            ],
            'due_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $this->actionPlan->createFromDecisionRecord(
            $actor,
            $workspace,
            (int) $validated['decision_record_id'],
            [
                'title' =>
                    $validated['title'],
                'description' =>
                    $validated['description']
                    ?? null,
                'owner_name' =>
                    $validated['owner_name']
                    ?? null,
                'priority' =>
                    $validated['priority'],
                'due_date' =>
                    $validated['due_date']
                    ?? null,
            ]
        );

        return redirect()
            ->route(
                'workspaces.tools.v2.capital.action-plan.show',
                $workspace
            )
            ->with(
                'status',
                'Capital Action item ကို ဖန်တီးပြီးပါပြီ။'
            );
    }

    public function transition(
        Request $request,
        PartnershipWorkspace $workspace,
        int $actionId
    ): RedirectResponse {
        $actor = $request->user();

        $this->actionPlan->actions(
            $actor,
            $workspace
        );

        $validated = $request->validate([
            'to_state' => [
                'required',
                'string',
                Rule::in(
                    $this->actionPlan->actionStates()
                ),
            ],
        ]);

        $this->actionPlan->transition(
            $actor,
            $workspace,
            $actionId,
            $validated['to_state']
        );

        return redirect()
            ->route(
                'workspaces.tools.v2.capital.action-plan.show',
                $workspace
            )
            ->with(
                'status',
                'Capital Action status ကို '
                .str_replace(
                    '_',
                    ' ',
                    strtoupper(
                        $validated['to_state']
                    )
                )
                .' အဖြစ် update လုပ်ပြီးပါပြီ။'
            );
    }
}
