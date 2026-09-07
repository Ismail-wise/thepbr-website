<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalCallRuleApprovalService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadinessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WorkspaceV2CapitalCallRuleController extends Controller
{
    public function __construct(
        private readonly PbrV2CapitalCallRuleReadService $read,
        private readonly PbrV2ApplicationService $application,
        private readonly PbrV2CapitalCallRuleApprovalService $approval,
        private readonly PbrV2CapitalCallRuleReadinessService $readiness
    ) {
    }

    public function show(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        $actor = $request->user();

        $current = $this->read->current(
            $actor,
            $workspace
        );

        $currentAssessment =
            $current === null
                ? null
                : $this->readiness->assess(
                    'capital',
                    'capital_call_rule',
                    $current['payload']
                );

        $canManage = true;

        try {
            $draftPage = $this->read
                ->workArtifacts(
                    $actor,
                    $workspace
                );
        } catch (AuthorizationException) {
            $canManage = false;

            $draftPage = [
                'items' => [],
                'next_before_id' => null,
            ];
        }

        $activeArtifact = null;
        $payload = $this->emptyPayload();
        $assessment = null;

        $selected = $request->query(
            'artifact'
        );

        $artifactId = null;

        if (
            is_int($selected)
            && $selected > 0
        ) {
            $artifactId = $selected;
        }

        if (
            is_string($selected)
            && preg_match(
                '/\A[1-9][0-9]*\z/D',
                $selected
            ) === 1
        ) {
            $artifactId = (int) $selected;
        }

        if (
            $canManage
            && $artifactId !== null
        ) {
            $activeArtifact =
                $this->read->workArtifact(
                    $actor,
                    $workspace,
                    $artifactId
                );

            $payload =
                $activeArtifact['payload'];

            $assessment =
                $this->readiness->assess(
                    'capital',
                    'capital_call_rule',
                    $payload
                );
        }

        return view(
            'workspaces.v2.capital-call-rule',
            [
                'workspace' => $workspace,
                'canManage' => $canManage,
                'current' => $current,
                'currentAssessment' =>
                    $currentAssessment,
                'draftPage' => $draftPage,
                'activeArtifact' =>
                    $activeArtifact,
                'payload' => $payload,
                'assessment' => $assessment,
            ]
        );
    }

    public function store(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $actor = $request->user();

        $this->read->workArtifacts(
            $actor,
            $workspace
        );

        $payload =
            $this->payloadFromRequest(
                $request
            );

        $artifact =
            $this->application
                ->createWorkingDraft(
                    $actor,
                    $workspace,
                    'capital',
                    'capital_call_rule',
                    'capital',
                    'startup_capital_planner',
                    $payload,
                    []
                );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'Capital-Call Rule working draft ကို create လုပ်ပြီးပါပြီ။'
        );
    }

    public function update(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact =
            $this->managedArtifact(
                $actor,
                $workspace,
                $artifactId
            );

        $this->assertState(
            $artifact,
            'working_draft'
        );

        $payload =
            $this->payloadFromRequest(
                $request
            );

        $artifact =
            $this->application
                ->updateWorkingDraftPayload(
                    $actor,
                    $workspace,
                    $artifact,
                    $payload
                );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'Capital-Call Rule working draft ကို update လုပ်ပြီးပါပြီ။'
        );
    }

    public function submit(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact =
            $this->managedArtifact(
                $actor,
                $workspace,
                $artifactId
            );

        $this->assertState(
            $artifact,
            'working_draft'
        );

        $assessment =
            $this->readiness->assess(
                'capital',
                'capital_call_rule',
                $artifact->payload
            );

        $this->assertReady(
            $assessment
        );

        $artifact =
            $this->application
                ->submitProposal(
                    $actor,
                    $workspace,
                    $artifact
                );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'Capital-Call Rule ကို formal proposal အဖြစ်တင်ပြီးပါပြီ။'
        );
    }

    public function approve(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact =
            $this->managedArtifact(
                $actor,
                $workspace,
                $artifactId
            );

        $this->assertState(
            $artifact,
            'proposed'
        );

        $result =
            $this->approval
                ->approveAndPublish(
                    $actor,
                    $workspace,
                    $artifact
                );

        $revision = (int) (
            $result['workflow']
                ['canonical_revision']
                ->revision
        );

        return redirect()
            ->route(
                'workspaces.tools.v2.capital.call-rule.show',
                $workspace
            )
            ->with(
                'status',
                'Capital-Call Rule revision '
                .$revision
                .' ကို V2 Canonical Current အဖြစ် approve & publish လုပ်ပြီးပါပြီ။'
            );
    }

    public function reject(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact =
            $this->managedArtifact(
                $actor,
                $workspace,
                $artifactId
            );

        $this->assertState(
            $artifact,
            'proposed'
        );

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $this->application
            ->rejectAndReturnToWorkingDraft(
                $actor,
                $workspace,
                $artifact,
                $this->text(
                    $validated['reason']
                )
            );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'Capital-Call Rule proposal ကို Working Draft အဖြစ် ပြန်ပို့ပြီးပါပြီ။'
        );
    }

    private function managedArtifact(
        $actor,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): WorkspaceV2CanonicalWorkArtifact {
        $projection =
            $this->read->workArtifact(
                $actor,
                $workspace,
                $artifactId
            );

        return WorkspaceV2CanonicalWorkArtifact::query()
            ->whereKey(
                $projection['id']
            )
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'target_domain_key',
                'capital'
            )
            ->where(
                'target_component_key',
                'capital_call_rule'
            )
            ->firstOrFail();
    }

    private function payloadFromRequest(
        Request $request
    ): array {
        $validated = $request->validate([
            'rule_name' => [
                'required',
                'string',
                'max:120',
            ],
            'owner_reference' => [
                'required',
                'string',
                'max:120',
            ],
            'effective_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'review_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'rule_summary' => [
                'required',
                'string',
                'max:2000',
            ],
            'trigger_conditions' => [
                'required',
                'string',
                'max:50000',
            ],
            'procedure_steps' => [
                'required',
                'string',
                'max:50000',
            ],
            'evidence_references' => [
                'required',
                'string',
                'max:50000',
            ],
        ]);

        return [
            'schema_version' =>
                'capital-call-rule-v1',
            'rule_name' =>
                $this->text(
                    $validated['rule_name']
                ),
            'owner_reference' =>
                $this->text(
                    $validated['owner_reference']
                ),
            'effective_date' =>
                $this->text(
                    $validated['effective_date']
                ),
            'review_date' =>
                $this->text(
                    $validated['review_date']
                ),
            'rule_summary' =>
                $this->text(
                    $validated['rule_summary']
                ),
            'trigger_conditions' =>
                $this->lines(
                    $validated[
                        'trigger_conditions'
                    ]
                ),
            'procedure_steps' =>
                $this->lines(
                    $validated[
                        'procedure_steps'
                    ]
                ),
            'evidence_references' =>
                $this->lines(
                    $validated[
                        'evidence_references'
                    ]
                ),
        ];
    }

    private function emptyPayload(): array
    {
        return [
            'schema_version' =>
                'capital-call-rule-v1',
            'rule_name' => '',
            'owner_reference' => '',
            'effective_date' => '',
            'review_date' => '',
            'rule_summary' => '',
            'trigger_conditions' => [],
            'procedure_steps' => [],
            'evidence_references' => [],
        ];
    }

    private function assertReady(
        array $assessment
    ): void {
        if ($assessment['passed']) {
            return;
        }

        $messages = [];

        foreach (
            $assessment['errors']
            as $error
        ) {
            $path =
                $error['path'] === ''
                    ? 'payload'
                    : 'payload.'
                        .$error['path'];

            $messages[$path][] =
                $error['code']
                .': '
                .$error['message'];
        }

        throw ValidationException::withMessages(
            $messages
        );
    }

    private function assertState(
        WorkspaceV2CanonicalWorkArtifact $artifact,
        string $expected
    ): void {
        if ($artifact->state === $expected) {
            return;
        }

        throw ValidationException::withMessages([
            'state' =>
                'Capital-Call Rule artifact must be '
                .$expected
                .'.',
        ]);
    }

    private function artifactRedirect(
        PartnershipWorkspace $workspace,
        int $artifactId,
        string $message
    ): RedirectResponse {
        return redirect()
            ->route(
                'workspaces.tools.v2.capital.call-rule.show',
                [
                    'workspace' => $workspace,
                    'artifact' => $artifactId,
                ]
            )
            ->with(
                'status',
                $message
            );
    }

    private function text(
        mixed $value
    ): string {
        return trim(
            (string) ($value ?? '')
        );
    }

    private function lines(
        mixed $value
    ): array {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (
                        string $line
                    ): string => trim($line),
                    preg_split(
                        '/\R/u',
                        $value
                    ) ?: []
                ),
                static fn (
                    string $line
                ): bool => $line !== ''
            )
        );
    }
}
