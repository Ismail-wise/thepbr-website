<?php

use App\Http\Controllers\WorkspaceV2CapitalCallRuleController;
use App\Http\Controllers\WorkspaceV2CapitalController;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2CapitalCallRuleApprovalService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function c2dRuntimeOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2dRuntimeWorkspace(
    User $owner,
    string $name = 'C2D Runtime Workspace'
): PartnershipWorkspace {
    return PartnershipWorkspace::query()
        ->create([
            'owner_user_id' =>
                $owner->id,
            'name' => $name,
            'business_name' =>
                $name.' Business',
            'business_stage' => 'new',
            'currency_code' => 'THB',
            'status' => 'active',
        ]);
}

function c2dRuntimePayload(
    string $name = 'C2D Runtime Rule',
    array $overrides = []
): array {
    return array_replace(
        [
            'schema_version' =>
                'capital-call-rule-v1',
            'rule_name' => $name,
            'owner_reference' =>
                'Managing Partner',
            'effective_date' =>
                '2026-09-07',
            'review_date' =>
                '2027-09-07',
            'rule_summary' =>
                'Use the approved procedure before additional capital is called.',
            'trigger_conditions' => [
                'Additional approved capital is required.',
                'The requirement is documented for partner review.',
            ],
            'procedure_steps' => [
                'Document the capital requirement.',
                'Follow the agreed approval and communication process.',
            ],
            'evidence_references' => [
                'Capital policy meeting note',
                'Partnership agreement reference',
            ],
        ],
        $overrides
    );
}

function c2dRuntimeForm(
    array $overrides = []
): array {
    return array_replace(
        [
            'rule_name' =>
                'C2D Runtime Rule',
            'owner_reference' =>
                'Managing Partner',
            'effective_date' =>
                '2026-09-07',
            'review_date' =>
                '2027-09-07',
            'rule_summary' =>
                'Use the approved procedure before additional capital is called.',
            'trigger_conditions' =>
                "Additional approved capital is required.\n"
                ."The requirement is documented for partner review.",
            'procedure_steps' =>
                "Document the capital requirement.\n"
                ."Follow the agreed approval and communication process.",
            'evidence_references' =>
                "Capital policy meeting note\n"
                ."Partnership agreement reference",
        ],
        $overrides
    );
}

function c2dRuntimeDraft(
    User $actor,
    PartnershipWorkspace $workspace,
    ?array $payload = null,
    string $target = 'capital_call_rule'
): WorkspaceV2CanonicalWorkArtifact {
    return app(
        PbrV2ApplicationService::class
    )->createWorkingDraft(
        $actor,
        $workspace,
        'capital',
        $target,
        'capital',
        'startup_capital_planner',
        $payload
            ?? c2dRuntimePayload(),
        []
    );
}

function c2dRuntimeProposal(
    User $actor,
    PartnershipWorkspace $workspace,
    ?array $payload = null
): WorkspaceV2CanonicalWorkArtifact {
    $draft = c2dRuntimeDraft(
        $actor,
        $workspace,
        $payload
    );

    return app(
        PbrV2ApplicationService::class
    )->submitProposal(
        $actor,
        $workspace,
        $draft
    );
}

function c2dRuntimePublish(
    User $actor,
    PartnershipWorkspace $workspace,
    ?array $payload = null
): array {
    $proposal =
        c2dRuntimeProposal(
            $actor,
            $workspace,
            $payload
        );

    return app(
        PbrV2CapitalCallRuleApprovalService::class
    )->approveAndPublish(
        $actor,
        $workspace,
        $proposal
    );
}

function c2dRuntimePartner(
    PartnershipWorkspace $workspace
): User {
    $partner =
        User::factory()->create([
            'role' => 'public',
            'is_admin' => false,
            'account_status' =>
                'active',
        ]);

    WorkspaceMember::query()
        ->forceCreate([
            'workspace_id' =>
                $workspace->id,
            'user_id' =>
                $partner->id,
            'member_role' =>
                'partner',
            'invitation_status' =>
                'accepted',
            'permissions' => [
                'approved_workspace_read_only',
            ],
        ]);

    return $partner;
}

test(
    'capital call rule runtime exposes exact authenticated routes and controller contract',
    function () {
        $expectedRoutes = [
            'workspaces.tools.v2.capital.call-rule.show' => [
                'GET',
                'workspaces/{workspace}/v2/capital/call-rule',
                'show',
            ],
            'workspaces.tools.v2.capital.call-rule.store' => [
                'POST',
                'workspaces/{workspace}/v2/capital/call-rule/drafts',
                'store',
            ],
            'workspaces.tools.v2.capital.call-rule.update' => [
                'PUT',
                'workspaces/{workspace}/v2/capital/call-rule/drafts/{artifactId}',
                'update',
            ],
            'workspaces.tools.v2.capital.call-rule.submit' => [
                'POST',
                'workspaces/{workspace}/v2/capital/call-rule/drafts/{artifactId}/submit',
                'submit',
            ],
            'workspaces.tools.v2.capital.call-rule.approve' => [
                'POST',
                'workspaces/{workspace}/v2/capital/call-rule/drafts/{artifactId}/approve',
                'approve',
            ],
            'workspaces.tools.v2.capital.call-rule.reject' => [
                'POST',
                'workspaces/{workspace}/v2/capital/call-rule/drafts/{artifactId}/reject',
                'reject',
            ],
        ];

        foreach (
            $expectedRoutes
            as $name => [
                $method,
                $uri,
                $action,
            ]
        ) {
            $route =
                Route::getRoutes()
                    ->getByName($name);

            expect($route)
                ->not->toBeNull()
                ->and($route->uri())
                ->toBe($uri)
                ->and($route->methods())
                ->toContain($method)
                ->and($route->gatherMiddleware())
                ->toContain('auth')
                ->and($route->getActionName())
                ->toBe(
                    WorkspaceV2CapitalCallRuleController::class
                    .'@'
                    .$action
                );
        }

        $reflection = new ReflectionClass(
            WorkspaceV2CapitalCallRuleController::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

        $constructor =
            $reflection->getConstructor();

        expect(
            array_map(
                fn (ReflectionParameter $parameter) =>
                    [
                        $parameter->getName(),
                        (string) $parameter->getType(),
                    ],
                $constructor->getParameters()
            )
        )->toBe([
            [
                'read',
                PbrV2CapitalCallRuleReadService::class,
            ],
            [
                'application',
                PbrV2ApplicationService::class,
            ],
            [
                'approval',
                PbrV2CapitalCallRuleApprovalService::class,
            ],
            [
                'readiness',
                PbrV2CapitalCallRuleReadinessService::class,
            ],
        ]);

        $public = array_values(
            array_map(
                fn (ReflectionMethod $method) =>
                    $method->getName(),
                array_filter(
                    $reflection->getMethods(
                        ReflectionMethod::IS_PUBLIC
                    ),
                    fn (ReflectionMethod $method) =>
                        $method->getDeclaringClass()
                            ->getName()
                        === WorkspaceV2CapitalCallRuleController::class
                )
            )
        );

        sort($public);

        expect($public)->toBe([
            '__construct',
            'approve',
            'reject',
            'show',
            'store',
            'submit',
            'update',
        ]);

        expect(
            hash_file(
                'sha256',
                app_path(
                    'Http/Controllers/WorkspaceV2CapitalController.php'
                )
            )
        )->toBe(
            '247d3b3aa8c4652efc7e14d1aae58be51ba0d66f223154ad1b3891ecbe8189db'
        );
    }
);

test(
    'manager opens dedicated capital call rule page with explicit not approved state',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee('Capital-Call Rule')
            ->assertSee('V2 CANONICAL RULE')
            ->assertSee('Working Rules')
            ->assertSee('Create Rule')
            ->assertSee('Not approved yet');
    }
);

test(
    'manager creates exact normalized root capital call rule working draft with empty source pins',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $response =
            $this->actingAs($owner)
                ->post(
                    route(
                        'workspaces.tools.v2.capital.call-rule.store',
                        $workspace
                    ),
                    c2dRuntimeForm([
                        'rule_name' =>
                            '  Normalized Runtime Rule  ',
                        'owner_reference' =>
                            '  Managing Partner  ',
                        'rule_summary' =>
                            '  Approved neutral procedure.  ',
                        'trigger_conditions' =>
                            " First trigger \n\n Second trigger ",
                        'procedure_steps' =>
                            " First step \n\n Second step ",
                        'evidence_references' =>
                            " Evidence A \n\n Evidence B ",
                    ])
                );

        $artifact =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->latest('id')
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'workspaces.tools.v2.capital.call-rule.show',
                [
                    'workspace' => $workspace,
                    'artifact' => $artifact->id,
                ]
            )
        );

        expect($artifact->state)
            ->toBe('working_draft')
            ->and($artifact->target_domain_key)
            ->toBe('capital')
            ->and($artifact->target_component_key)
            ->toBe('capital_call_rule')
            ->and($artifact->producer_domain_key)
            ->toBe('capital')
            ->and($artifact->producer_component_key)
            ->toBe('startup_capital_planner')
            ->and($artifact->source_generation)
            ->toBe(1)
            ->and($artifact->payload)
            ->toBe([
                'schema_version' =>
                    'capital-call-rule-v1',
                'rule_name' =>
                    'Normalized Runtime Rule',
                'owner_reference' =>
                    'Managing Partner',
                'effective_date' =>
                    '2026-09-07',
                'review_date' =>
                    '2027-09-07',
                'rule_summary' =>
                    'Approved neutral procedure.',
                'trigger_conditions' => [
                    'First trigger',
                    'Second trigger',
                ],
                'procedure_steps' => [
                    'First step',
                    'Second step',
                ],
                'evidence_references' => [
                    'Evidence A',
                    'Evidence B',
                ],
            ]);

        expect(
            app(
                PbrV2CanonicalWorkService::class
            )->sources(
                $workspace,
                $artifact
            )
        )->toHaveCount(0);
    }
);

test(
    'manager updates exact working rule and persisted payload remains canonical rule only',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $artifact =
            c2dRuntimeDraft(
                $owner,
                $workspace
            );

        $response =
            $this->actingAs($owner)
                ->put(
                    route(
                        'workspaces.tools.v2.capital.call-rule.update',
                        [
                            'workspace' => $workspace,
                            'artifactId' => $artifact->id,
                        ]
                    ),
                    c2dRuntimeForm([
                        'rule_name' =>
                            'Updated C2D Runtime Rule',
                        'trigger_conditions' =>
                            "Updated trigger one\nUpdated trigger two",
                    ])
                );

        $response->assertRedirect();

        $fresh = $artifact->fresh();

        expect($fresh->state)
            ->toBe('working_draft')
            ->and($fresh->payload['rule_name'])
            ->toBe(
                'Updated C2D Runtime Rule'
            )
            ->and(
                $fresh->payload[
                    'trigger_conditions'
                ]
            )
            ->toBe([
                'Updated trigger one',
                'Updated trigger two',
            ])
            ->and(
                array_keys(
                    $fresh->payload
                )
            )
            ->toBe([
                'schema_version',
                'rule_name',
                'owner_reference',
                'effective_date',
                'review_date',
                'rule_summary',
                'trigger_conditions',
                'procedure_steps',
                'evidence_references',
            ]);
    }
);

test(
    'invalid persisted rule cannot submit and remains working draft',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $artifact =
            c2dRuntimeDraft(
                $owner,
                $workspace,
                c2dRuntimePayload(
                    'Invalid Date Rule',
                    [
                        'effective_date' =>
                            '2026-09-07',
                        'review_date' =>
                            '2026-09-06',
                    ]
                )
            );

        $from = route(
            'workspaces.tools.v2.capital.call-rule.show',
            [
                'workspace' => $workspace,
                'artifact' => $artifact->id,
            ]
        );

        $this->actingAs($owner)
            ->from($from)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.submit',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $artifact->id,
                    ]
                )
            )
            ->assertRedirect($from)
            ->assertSessionHasErrors(
                'payload.review_date'
            );

        expect($artifact->fresh()->state)
            ->toBe('working_draft')
            ->and(
                $artifact->fresh()
                    ->submitted_at
            )
            ->toBeNull();
    }
);

test(
    'valid working rule submits to proposed and proposed rule cannot be edited as draft',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $artifact =
            c2dRuntimeDraft(
                $owner,
                $workspace
            );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.submit',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $artifact->id,
                    ]
                )
            )
            ->assertRedirect();

        expect($artifact->fresh()->state)
            ->toBe('proposed')
            ->and(
                $artifact->fresh()
                    ->submitted_at
            )
            ->not->toBeNull();

        $from = route(
            'workspaces.tools.v2.capital.call-rule.show',
            [
                'workspace' => $workspace,
                'artifact' => $artifact->id,
            ]
        );

        $this->actingAs($owner)
            ->from($from)
            ->put(
                route(
                    'workspaces.tools.v2.capital.call-rule.update',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $artifact->id,
                    ]
                ),
                c2dRuntimeForm([
                    'rule_name' =>
                        'Must Not Replace Proposal',
                ])
            )
            ->assertRedirect($from)
            ->assertSessionHasErrors('state');

        expect(
            $artifact->fresh()
                ->payload['rule_name']
        )->toBe('C2D Runtime Rule');
    }
);

test(
    'manager approves proposed rule through dedicated authority and approved canonical payload renders',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $payload =
            c2dRuntimePayload(
                'Approved Runtime Capital Call Rule'
            );

        $artifact =
            c2dRuntimeDraft(
                $owner,
                $workspace,
                $payload
            );

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.submit',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $artifact->id,
                    ]
                )
            )
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.approve',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $artifact->id,
                    ]
                )
            )
            ->assertRedirect(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                )
            );

        $revision =
            WorkspaceV2CanonicalRevision::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'domain_key',
                    'capital'
                )
                ->where(
                    'component_key',
                    'capital_call_rule'
                )
                ->where(
                    'state',
                    'approved_current'
                )
                ->firstOrFail();

        expect($revision->payload)
            ->toBe($payload)
            ->and(
                $artifact->fresh()
                    ->consumed_at
            )
            ->not->toBeNull()
            ->and(
                (int) $artifact->fresh()
                    ->published_canonical_revision_id
            )
            ->toBe((int) $revision->id);

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee(
                'Approved Runtime Capital Call Rule'
            )
            ->assertSee(
                'Additional approved capital is required.'
            )
            ->assertSee(
                'Document the capital requirement.'
            )
            ->assertSee(
                'Capital policy meeting note'
            )
            ->assertSee('Revision 1');
    }
);

test(
    'manager rejects proposed rule back to the same working draft',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $proposal =
            c2dRuntimeProposal(
                $owner,
                $workspace
            );

        $response =
            $this->actingAs($owner)
                ->post(
                    route(
                        'workspaces.tools.v2.capital.call-rule.reject',
                        [
                            'workspace' => $workspace,
                            'artifactId' => $proposal->id,
                        ]
                    ),
                    [
                        'reason' =>
                            'Clarify the procedure before approval.',
                    ]
                );

        $response->assertRedirect(
            route(
                'workspaces.tools.v2.capital.call-rule.show',
                [
                    'workspace' => $workspace,
                    'artifact' => $proposal->id,
                ]
            )
        );

        expect($proposal->fresh()->state)
            ->toBe('working_draft')
            ->and(
                $proposal->fresh()
                    ->consumed_at
            )
            ->toBeNull()
            ->and(
                $proposal->fresh()
                    ->published_canonical_revision_id
            )
            ->toBeNull();
    }
);

test(
    'accepted active partner sees approved current only and mutation routes deny before request validation',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        c2dRuntimePublish(
            $owner,
            $workspace,
            c2dRuntimePayload(
                'PARTNER APPROVED C2D RULE'
            )
        );

        $private =
            c2dRuntimeDraft(
                $owner,
                $workspace,
                c2dRuntimePayload(
                    'PRIVATE C2D RUNTIME RULE'
                )
            );

        $partner =
            c2dRuntimePartner(
                $workspace
            );

        $this->actingAs($partner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee(
                'PARTNER APPROVED C2D RULE'
            )
            ->assertSee(
                'APPROVED READ-ONLY'
            )
            ->assertDontSee(
                'PRIVATE C2D RUNTIME RULE'
            )
            ->assertDontSee('Working Rules')
            ->assertDontSee('Create Rule')
            ->assertDontSee('Submit Proposal')
            ->assertDontSee(
                'Approve &amp; Publish',
                false
            )
            ->assertDontSee('Return to Draft');

        $this->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.store',
                    $workspace
                ),
                []
            )
            ->assertForbidden();

        $this->actingAs($partner)
            ->put(
                route(
                    'workspaces.tools.v2.capital.call-rule.update',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $private->id,
                    ]
                ),
                []
            )
            ->assertForbidden();

        $this->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.submit',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $private->id,
                    ]
                )
            )
            ->assertForbidden();

        $this->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.approve',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $private->id,
                    ]
                )
            )
            ->assertForbidden();

        $this->actingAs($partner)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.reject',
                    [
                        'workspace' => $workspace,
                        'artifactId' => $private->id,
                    ]
                ),
                []
            )
            ->assertForbidden();
    }
);

test(
    'unrelated active user cannot read or create capital call rule runtime data',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        c2dRuntimePublish(
            $owner,
            $workspace
        );

        $intruder =
            c2dRuntimeOwner();

        $this->actingAs($intruder)
            ->get(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                )
            )
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(
                route(
                    'workspaces.tools.v2.capital.call-rule.store',
                    $workspace
                ),
                []
            )
            ->assertForbidden();
    }
);

test(
    'wrong workspace and wrong target artifact mutation are isolated before payload replacement',
    function () {
        $owner = c2dRuntimeOwner();

        $workspace =
            c2dRuntimeWorkspace(
                $owner,
                'Primary C2D Runtime'
            );

        $other =
            c2dRuntimeWorkspace(
                $owner,
                'Other C2D Runtime'
            );

        $foreign =
            c2dRuntimeDraft(
                $owner,
                $other
            );

        $wrongTarget =
            c2dRuntimeDraft(
                $owner,
                $workspace,
                [],
                'approved_capital_plan'
            );

        $from = route(
            'workspaces.tools.v2.capital.call-rule.show',
            $workspace
        );

        foreach ([
            $foreign->id,
            $wrongTarget->id,
        ] as $artifactId) {
            $this->actingAs($owner)
                ->from($from)
                ->put(
                    route(
                        'workspaces.tools.v2.capital.call-rule.update',
                        [
                            'workspace' => $workspace,
                            'artifactId' => $artifactId,
                        ]
                    ),
                    c2dRuntimeForm()
                )
                ->assertRedirect($from)
                ->assertSessionHasErrors(
                    'work_artifact'
                );
        }

        expect($foreign->fresh()->payload)
            ->toBe(
                c2dRuntimePayload()
            )
            ->and(
                $wrongTarget->fresh()
                    ->target_component_key
            )
            ->toBe(
                'approved_capital_plan'
            );
    }
);

test(
    'runtime controller contains no direct publication writes capital plan fallback or invented consequence semantics',
    function () {
        $controller =
            file_get_contents(
                app_path(
                    'Http/Controllers/WorkspaceV2CapitalCallRuleController.php'
                )
            );

        $view =
            file_get_contents(
                resource_path(
                    'views/workspaces/v2/capital-call-rule.blade.php'
                )
            );

        foreach ([
            'DB::',
            'PbrV2ApprovalEngineService',
            'PbrV2CanonicalOutputService',
            'PbrV2CapitalPlanReadinessService',
            "'approved_capital_plan'",
            '->create(',
            '->save(',
            '->update(',
            '->delete(',
            'partner_percentage',
            'share_percentage',
            'equity_percentage',
            'capital_call_amount',
            'contribution_amount',
            'dilution',
            'interest_rate',
            'default_penalty',
            'voting_threshold',
            'notice_days',
            'payment_deadline',
        ] as $forbidden) {
            expect($controller)
                ->not->toContain(
                    $forbidden
                );
        }

        foreach ([
            'partner_percentage',
            'share_percentage',
            'equity_percentage',
            'capital_call_amount',
            'contribution_amount',
            'dilution',
            'interest_rate',
            'default_penalty',
            'voting_threshold',
            'notice_days',
            'payment_deadline',
        ] as $forbiddenField) {
            expect($view)
                ->not->toContain(
                    'name="'.$forbiddenField.'"'
                )
                ->not->toContain(
                    "name='".$forbiddenField."'"
                );
        }

        expect($view)
            ->toContain('Rule Boundary')
            ->toContain('dilution')
            ->toContain('voting threshold')
            ->toContain(
                'အလိုအလျောက် မဖန်တီးပါ။'
            );

        expect($controller)
            ->toContain(
                "'capital_call_rule'"
            )
            ->toContain(
                "'startup_capital_planner'"
            )
            ->toContain(
                "                    []"
            );
    }
);

test(
    'main chapter one exposes only the dedicated capital call rule entry link and keeps main controller frozen',
    function () {
        $owner = c2dRuntimeOwner();
        $workspace =
            c2dRuntimeWorkspace($owner);

        $mainView =
            file_get_contents(
                resource_path(
                    'views/workspaces/v2/capital.blade.php'
                )
            );

        expect($mainView)
            ->toContain(
                "workspaces.tools.v2.capital.call-rule.show"
            )
            ->toContain(
                'Capital Rule &amp; Allocation'
            )
            ->toContain(
                'Calculate → Compare → Approve → Record → Act'
            );

        expect(
            hash_file(
                'sha256',
                app_path(
                    'Http/Controllers/WorkspaceV2CapitalController.php'
                )
            )
        )->toBe(
            '247d3b3aa8c4652efc7e14d1aae58be51ba0d66f223154ad1b3891ecbe8189db'
        );

        $this->actingAs($owner)
            ->get(
                route(
                    'workspaces.tools.v2.capital.show',
                    $workspace
                )
            )
            ->assertOk()
            ->assertSee('Capital-Call Rule')
            ->assertSee(
                route(
                    'workspaces.tools.v2.capital.call-rule.show',
                    $workspace
                ),
                false
            );
    }
);
