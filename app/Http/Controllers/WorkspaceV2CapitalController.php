<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalApprovalService;
use App\Services\PbrTools\PbrV2CapitalPlanReadinessService;
use App\Services\PbrTools\PbrV2CapitalReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WorkspaceV2CapitalController extends Controller
{
    public function __construct(
        private readonly PbrV2CapitalReadService $read,
        private readonly PbrV2ApplicationService $application,
        private readonly PbrV2CapitalApprovalService $approval,
        private readonly PbrV2CapitalPlanReadinessService $readiness
    ) {
    }

    public function show(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        $actor = $request->user();

        // current() is the actor-safe read authorization gate.
        $current = $this->read->current(
            $actor,
            $workspace
        );

        $canManage = true;

        try {
            $draftPage = $this->read->workArtifacts(
                $actor,
                $workspace
            );
        } catch (AuthorizationException) {
            // Accepted partners are intentionally approved-current only.
            $canManage = false;
            $draftPage = [
                'items' => [],
                'next_before_id' => null,
            ];
        }

        $activeArtifact = null;

        if (
            $canManage
            && $request->query('artifact') !== null
        ) {
            $artifactId = (string) $request->query(
                'artifact'
            );

            abort_unless(
                ctype_digit($artifactId)
                && (int) $artifactId > 0,
                404
            );

            $activeArtifact = $this->read->workArtifact(
                $actor,
                $workspace,
                (int) $artifactId
            );
        }

        $payload = $activeArtifact !== null
            ? $activeArtifact['payload']
            : $this->emptyPayload($workspace);

        $assessment = $activeArtifact !== null
            ? $this->readiness->assess(
                'capital',
                'approved_capital_plan',
                $payload
            )
            : null;

        $currentAssessment = $current !== null
            ? $this->readiness->assess(
                'capital',
                'approved_capital_plan',
                $current['payload']
            )
            : null;

        return view(
            'workspaces.v2.capital',
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

        // Authorize management before request validation so an
        // accepted partner cannot probe draft input rules.
        $this->read->workArtifacts(
            $actor,
            $workspace
        );

        $workspace = PartnershipWorkspace::query()
            ->findOrFail($workspace->getKey());

        $payload = $this->payloadFromRequest(
            $request,
            $workspace
        );

        $artifact = $this->application
            ->createWorkingDraft(
                $actor,
                $workspace,
                'capital',
                'approved_capital_plan',
                'capital',
                'capital_funding_workflow',
                $payload,
                []
            );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'V2 Capital Plan Draft ကို သိမ်းပြီးပါပြီ။'
        );
    }

    public function update(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact = $this->managedArtifact(
            $actor,
            $workspace,
            $artifactId
        );

        $workspace = PartnershipWorkspace::query()
            ->findOrFail($workspace->getKey());

        $payload = $this->payloadFromRequest(
            $request,
            $workspace
        );

        $artifact = $this->application
            ->updateWorkingDraftPayload(
                $actor,
                $workspace,
                $artifact,
                $payload
            );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'V2 Capital Plan Draft ကို update လုပ်ပြီးပါပြီ။'
        );
    }

    public function submit(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact = $this->managedArtifact(
            $actor,
            $workspace,
            $artifactId
        );

        $assessment = $this->readiness->assess(
            'capital',
            'approved_capital_plan',
            $artifact->payload
        );

        $this->assertReady($assessment);

        $artifact = $this->application
            ->submitProposal(
                $actor,
                $workspace,
                $artifact
            );

        return $this->artifactRedirect(
            $workspace,
            (int) $artifact->id,
            'Capital Plan ကို formal proposal အဖြစ်တင်ပြီးပါပြီ။'
        );
    }

    public function approve(
        Request $request,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): RedirectResponse {
        $actor = $request->user();

        $artifact = $this->managedArtifact(
            $actor,
            $workspace,
            $artifactId
        );

        $result = $this->approval
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
                'workspaces.tools.v2.capital.show',
                $workspace
            )
            ->with(
                'status',
                'Capital Plan revision '
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

        $artifact = $this->managedArtifact(
            $actor,
            $workspace,
            $artifactId
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
                $validated['reason']
            );

        return $this->artifactRedirect(
            $workspace,
            $artifactId,
            'Proposal ကို Working Draft အဖြစ် ပြန်ပို့ပြီးပါပြီ။'
        );
    }

    private function managedArtifact(
        $actor,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): WorkspaceV2CanonicalWorkArtifact {
        $projection = $this->read->workArtifact(
            $actor,
            $workspace,
            $artifactId
        );

        return WorkspaceV2CanonicalWorkArtifact::query()
            ->whereKey($projection['id'])
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->firstOrFail();
    }

    private function payloadFromRequest(
        Request $request,
        PartnershipWorkspace $workspace
    ): array {
        $moneyRule = [
            'nullable',
            'string',
            'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',
        ];

        $identifierRule = [
            'nullable',
            'string',
            'max:64',
            'regex:/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D',
        ];

        $validated = $request->validate([
            'plan_name' => [
                'nullable',
                'string',
                'max:120',
            ],
            'scenario_type' => [
                'nullable',
                Rule::in([
                    'lean',
                    'base',
                    'growth',
                    'custom',
                ]),
            ],
            'assessment_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'opening_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'working_capital_months' => [
                'nullable',
                'integer',
                'min:1',
                'max:24',
            ],
            'contingency_percentage' =>
                $moneyRule,
            'monthly_burn' => $moneyRule,

            'cost_items' => [
                'nullable',
                'array',
                'max:300',
            ],
            'cost_items.*.id' =>
                $identifierRule,
            'cost_items.*.category' => [
                'nullable',
                Rule::in([
                    'pre_opening',
                    'initial_assets_inventory',
                    'working_capital',
                ]),
            ],
            'cost_items.*.description' => [
                'nullable',
                'string',
                'max:150',
            ],
            'cost_items.*.amount' =>
                $moneyRule,
            'cost_items.*.basis' => [
                'nullable',
                Rule::in([
                    'one_time',
                    'monthly',
                ]),
            ],
            'cost_items.*.evidence_reference' => [
                'nullable',
                'string',
                'max:500',
            ],

            'funding_sources' => [
                'nullable',
                'array',
                'max:300',
            ],
            'funding_sources.*.id' =>
                $identifierRule,
            'funding_sources.*.source_type' => [
                'nullable',
                Rule::in([
                    'partner_cash',
                    'business_cash',
                    'loan',
                    'grant',
                    'other',
                ]),
            ],
            'funding_sources.*.description' => [
                'nullable',
                'string',
                'max:150',
            ],
            'funding_sources.*.confirmed_amount' =>
                $moneyRule,
            'funding_sources.*.confirmation_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'funding_sources.*.evidence_reference' => [
                'nullable',
                'string',
                'max:500',
            ],

            'shortfall_enabled' => [
                'nullable',
                'boolean',
            ],
            'shortfall_strategy' => [
                'nullable',
                Rule::in([
                    'reduce_scope',
                    'delay',
                    'borrow',
                    'capital_call',
                    'mixed',
                ]),
            ],
            'shortfall_owner_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'shortfall_target_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'shortfall_summary' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'decision_owner_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'decision_effective_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'decision_review_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'decision_summary' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'decision_evidence_references' => [
                'nullable',
                'string',
                'max:50000',
            ],
        ]);

        $costItems = [];

        foreach (
            $validated['cost_items'] ?? []
            as $index => $row
        ) {
            $description = $this->text(
                $row['description'] ?? null
            );

            $amount = $this->money(
                $row['amount'] ?? null
            );

            $evidence = $this->text(
                $row['evidence_reference'] ?? null
            );

            if (
                $description === ''
                && $amount === ''
                && $evidence === ''
            ) {
                continue;
            }

            $id = $this->text(
                $row['id'] ?? null
            );

            if ($id === '') {
                $id = 'cost-'.($index + 1);
            }

            $costItems[] = [
                'id' => $id,
                'category' =>
                    $row['category']
                    ?? 'pre_opening',
                'description' => $description,
                'amount' => $amount,
                'basis' =>
                    $row['basis']
                    ?? 'one_time',
                'evidence_reference' =>
                    $evidence,
            ];
        }

        $fundingSources = [];

        foreach (
            $validated['funding_sources'] ?? []
            as $index => $row
        ) {
            $description = $this->text(
                $row['description'] ?? null
            );

            $amount = $this->money(
                $row['confirmed_amount'] ?? null
            );

            $confirmationDate = $this->text(
                $row['confirmation_date'] ?? null
            );

            $evidence = $this->text(
                $row['evidence_reference'] ?? null
            );

            if (
                $description === ''
                && $amount === ''
                && $confirmationDate === ''
                && $evidence === ''
            ) {
                continue;
            }

            $id = $this->text(
                $row['id'] ?? null
            );

            if ($id === '') {
                $id = 'funding-'.($index + 1);
            }

            $fundingSources[] = [
                'id' => $id,
                'source_type' =>
                    $row['source_type']
                    ?? 'partner_cash',
                'description' => $description,
                'confirmed_amount' => $amount,
                'confirmation_date' =>
                    $confirmationDate,
                'evidence_reference' =>
                    $evidence,
            ];
        }

        $shortfall = null;

        if (
            (bool) (
                $validated[
                    'shortfall_enabled'
                ] ?? false
            )
        ) {
            $shortfall = [
                'strategy' =>
                    $validated[
                        'shortfall_strategy'
                    ] ?? '',
                'owner_reference' =>
                    $this->text(
                        $validated[
                            'shortfall_owner_reference'
                        ] ?? null
                    ),
                'target_date' =>
                    $this->text(
                        $validated[
                            'shortfall_target_date'
                        ] ?? null
                    ),
                'summary' =>
                    $this->text(
                        $validated[
                            'shortfall_summary'
                        ] ?? null
                    ),
            ];
        }

        return [
            'schema_version' =>
                'capital-plan-v1',
            'business_stage' =>
                (string) $workspace
                    ->business_stage,
            'currency_code' =>
                (string) $workspace
                    ->currency_code,
            'plan_name' =>
                $this->text(
                    $validated[
                        'plan_name'
                    ] ?? null
                ),
            'scenario_type' =>
                (string) (
                    $validated[
                        'scenario_type'
                    ] ?? ''
                ),
            'assessment_date' =>
                $this->text(
                    $validated[
                        'assessment_date'
                    ] ?? null
                ),
            'assumptions' => [
                'opening_date' =>
                    filled(
                        $validated[
                            'opening_date'
                        ] ?? null
                    )
                        ? $validated[
                            'opening_date'
                        ]
                        : null,
                'working_capital_months' =>
                    isset(
                        $validated[
                            'working_capital_months'
                        ]
                    )
                        ? (int) $validated[
                            'working_capital_months'
                        ]
                        : 0,
                'contingency_percentage' =>
                    $this->money(
                        $validated[
                            'contingency_percentage'
                        ] ?? null
                    ),
                'monthly_burn' =>
                    $this->money(
                        $validated[
                            'monthly_burn'
                        ] ?? null
                    ),
            ],
            'cost_items' => $costItems,
            'funding_sources' =>
                $fundingSources,
            'shortfall_response' =>
                $shortfall,
            'decision_record' => [
                'owner_reference' =>
                    $this->text(
                        $validated[
                            'decision_owner_reference'
                        ] ?? null
                    ),
                'effective_date' =>
                    $this->text(
                        $validated[
                            'decision_effective_date'
                        ] ?? null
                    ),
                'review_date' =>
                    $this->text(
                        $validated[
                            'decision_review_date'
                        ] ?? null
                    ),
                'decision_summary' =>
                    $this->text(
                        $validated[
                            'decision_summary'
                        ] ?? null
                    ),
                'evidence_references' =>
                    $this->lines(
                        $validated[
                            'decision_evidence_references'
                        ] ?? null
                    ),
            ],
        ];
    }

    private function emptyPayload(
        PartnershipWorkspace $workspace
    ): array {
        return [
            'schema_version' =>
                'capital-plan-v1',
            'business_stage' =>
                (string) $workspace
                    ->business_stage,
            'currency_code' =>
                (string) $workspace
                    ->currency_code,
            'plan_name' => '',
            'scenario_type' => '',
            'assessment_date' => '',
            'assumptions' => [
                'opening_date' => null,
                'working_capital_months' => 0,
                'contingency_percentage' => '',
                'monthly_burn' => '',
            ],
            'cost_items' => [],
            'funding_sources' => [],
            'shortfall_response' => null,
            'decision_record' => [
                'owner_reference' => '',
                'effective_date' => '',
                'review_date' => '',
                'decision_summary' => '',
                'evidence_references' => [],
            ],
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
            $path = $error['path'] === ''
                ? 'payload'
                : 'payload.'.$error['path'];

            $messages[$path][] =
                $error['code']
                .': '
                .$error['message'];
        }

        throw ValidationException::withMessages(
            $messages
        );
    }

    private function artifactRedirect(
        PartnershipWorkspace $workspace,
        int $artifactId,
        string $message
    ): RedirectResponse {
        return redirect()
            ->route(
                'workspaces.tools.v2.capital.show',
                [
                    'workspace' => $workspace,
                    'artifact' => $artifactId,
                ]
            )
            ->with('status', $message);
    }

    private function money(
        mixed $value
    ): string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        if (! str_contains($value, '.')) {
            return $value.'.00';
        }

        [$whole, $fraction] = explode(
            '.',
            $value,
            2
        );

        return $whole.'.'.str_pad(
            $fraction,
            2,
            '0'
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
