<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalCallRuleApprovalService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadinessService;
use App\Services\PbrTools\PbrV2CanonicalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function c2dApprovalOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2dApprovalWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()
        ->create([
            'owner_user_id' =>
                $owner->id,
            'name' =>
                'C2D Approval Workspace',
            'business_name' =>
                'C2D Approval Business',
            'business_stage' => 'new',
            'currency_code' => 'THB',
            'status' => 'active',
        ]);
}

function c2dApprovalPayload(
    array $overrides = []
): array {
    return array_replace(
        [
            'schema_version' =>
                'capital-call-rule-v1',
            'rule_name' =>
                'C2D Capital Call Rule',
            'owner_reference' =>
                'C2D Managing Partner',
            'effective_date' =>
                '2026-09-07',
            'review_date' =>
                '2027-09-07',
            'rule_summary' =>
                'Use the approved procedure before additional capital is called.',
            'trigger_conditions' => [
                'Additional approved capital is required.',
            ],
            'procedure_steps' => [
                'Document the requirement and follow the agreed approval process.',
            ],
            'evidence_references' => [
                'C2D rule evidence',
            ],
        ],
        $overrides
    );
}

function c2dApprovalProposal(
    User $actor,
    PartnershipWorkspace $workspace,
    ?array $payload = null
): WorkspaceV2CanonicalWorkArtifact {
    $application = app(
        PbrV2ApplicationService::class
    );

    $draft =
        $application->createWorkingDraft(
            $actor,
            $workspace,
            'capital',
            'capital_call_rule',
            'capital',
            'startup_capital_planner',
            $payload
                ?? c2dApprovalPayload(),
            []
        );

    return $application->submitProposal(
        $actor,
        $workspace,
        $draft
    );
}

test(
    'capital call rule approval exposes the exact final constructor and public method contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalCallRuleApprovalService::class
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
                'readiness',
                PbrV2CapitalCallRuleReadinessService::class,
            ],
            [
                'workflow',
                PbrV2CanonicalWorkflowService::class,
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
                        === PbrV2CapitalCallRuleApprovalService::class
                )
            )
        );

        expect($public)->toBe([
            '__construct',
            'approveAndPublish',
        ]);

        $method =
            $reflection->getMethod(
                'approveAndPublish'
            );

        expect(
            array_map(
                fn (ReflectionParameter $parameter) =>
                    [
                        $parameter->getName(),
                        (string) $parameter->getType(),
                        $parameter->isOptional(),
                    ],
                $method->getParameters()
            )
        )->toBe([
            [
                'actor',
                User::class,
                false,
            ],
            [
                'workspace',
                PartnershipWorkspace::class,
                false,
            ],
            [
                'artifact',
                WorkspaceV2CanonicalWorkArtifact::class,
                false,
            ],
        ])
            ->and(
                (string) $method->getReturnType()
            )
            ->toBe('array');
    }
);

test(
    'capital call rule approval assesses persisted proposal then publishes and consumes exactly once',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $proposal =
            c2dApprovalProposal(
                $owner,
                $workspace
            );

        $payload = $proposal->payload;

        $result = app(
            PbrV2CapitalCallRuleApprovalService::class
        )->approveAndPublish(
            $owner,
            $workspace,
            $proposal
        );

        expect(array_keys($result))
            ->toBe([
                'readiness',
                'workflow',
            ])
            ->and(
                $result['readiness']['passed']
            )
            ->toBeTrue()
            ->and(
                $result['readiness']
                    ['calculations']
            )
            ->toBeNull()
            ->and(
                array_keys(
                    $result['workflow']
                )
            )
            ->toBe([
                'decision',
                'canonical_revision',
                'work_artifact',
            ]);

        $revision =
            $result['workflow']
                ['canonical_revision'];

        expect($revision->domain_key)
            ->toBe('capital')
            ->and($revision->component_key)
            ->toBe('capital_call_rule')
            ->and($revision->state)
            ->toBe('approved_current')
            ->and($revision->payload)
            ->toBe($payload)
            ->and(
                $revision
                    ->approval_decision
            )
            ->toBe(
                $result['workflow']
                    ['decision']
            )
            ->and(
                (int) $revision
                    ->published_by_user_id
            )
            ->toBe((int) $owner->id)
            ->and(
                $proposal->fresh()
                    ->consumed_at
            )
            ->not->toBeNull()
            ->and(
                (int) $proposal->fresh()
                    ->published_canonical_revision_id
            )
            ->toBe(
                (int) $revision->id
            );

        expect(
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
                ->count()
        )->toBe(1);

        expect(
            fn () => app(
                PbrV2CapitalCallRuleApprovalService::class
            )->approveAndPublish(
                $owner,
                $workspace,
                $proposal
            )
        )->toThrow(
            ValidationException::class
        );

        expect(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(1);
    }
);

test(
    'capital call rule approval reloads persisted proposal evidence instead of trusting caller mutation',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $proposal =
            c2dApprovalProposal(
                $owner,
                $workspace
            );

        $persistedPayload =
            $proposal->payload;

        $proposal->payload = [
            'forged' => true,
        ];

        $proposal->target_component_key =
            'approved_capital_plan';

        $proposal->workspace_id = -1;

        $result = app(
            PbrV2CapitalCallRuleApprovalService::class
        )->approveAndPublish(
            $owner,
            $workspace,
            $proposal
        );

        expect(
            $result['workflow']
                ['canonical_revision']
                ->payload
        )->toBe($persistedPayload)
            ->and(
                $result['workflow']
                    ['canonical_revision']
                    ->component_key
            )
            ->toBe(
                'capital_call_rule'
            );
    }
);

test(
    'capital call rule approval permits active admin for another owners workspace',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $proposal =
            c2dApprovalProposal(
                $owner,
                $workspace
            );

        $admin =
            User::factory()->create([
                'role' => 'admin',
                'is_admin' => true,
                'account_status' =>
                    'active',
            ]);

        $result = app(
            PbrV2CapitalCallRuleApprovalService::class
        )->approveAndPublish(
            $admin,
            $workspace,
            $proposal
        );

        expect(
            (int) $result['workflow']
                ['canonical_revision']
                ->published_by_user_id
        )->toBe((int) $admin->id);
    }
);

test(
    'capital call rule approval denies unauthorized manager before publication',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $proposal =
            c2dApprovalProposal(
                $owner,
                $workspace
            );

        $intruder =
            c2dApprovalOwner();

        expect(
            fn () => app(
                PbrV2CapitalCallRuleApprovalService::class
            )->approveAndPublish(
                $intruder,
                $workspace,
                $proposal
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0)
            ->and(
                $proposal->fresh()
                    ->consumed_at
            )
            ->toBeNull();
    }
);

test(
    'capital call rule approval rejects wrong target and non proposed state',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $application = app(
            PbrV2ApplicationService::class
        );

        $wrongTarget =
            $application->createWorkingDraft(
                $owner,
                $workspace,
                'capital',
                'approved_capital_plan',
                'capital',
                'startup_capital_planner',
                [],
                []
            );

        $wrongTarget =
            $application->submitProposal(
                $owner,
                $workspace,
                $wrongTarget
            );

        try {
            app(
                PbrV2CapitalCallRuleApprovalService::class
            )->approveAndPublish(
                $owner,
                $workspace,
                $wrongTarget
            );

            throw new RuntimeException(
                'Expected wrong-target rejection.'
            );
        } catch (
            ValidationException $exception
        ) {
            expect(
                $exception->errors()
            )->toHaveKey('target');
        }

        $draft =
            $application->createWorkingDraft(
                $owner,
                $workspace,
                'capital',
                'capital_call_rule',
                'capital',
                'startup_capital_planner',
                c2dApprovalPayload(),
                []
            );

        try {
            app(
                PbrV2CapitalCallRuleApprovalService::class
            )->approveAndPublish(
                $owner,
                $workspace,
                $draft
            );

            throw new RuntimeException(
                'Expected state rejection.'
            );
        } catch (
            ValidationException $exception
        ) {
            expect(
                $exception->errors()
            )->toHaveKey('state');
        }

        expect(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'capital call rule approval maps readiness failures to payload validation and never publishes',
    function () {
        $owner =
            c2dApprovalOwner();

        $workspace =
            c2dApprovalWorkspace(
                $owner
            );

        $proposal =
            c2dApprovalProposal(
                $owner,
                $workspace,
                c2dApprovalPayload([
                    'rule_name' => '   ',
                ])
            );

        try {
            app(
                PbrV2CapitalCallRuleApprovalService::class
            )->approveAndPublish(
                $owner,
                $workspace,
                $proposal
            );

            throw new RuntimeException(
                'Expected readiness rejection.'
            );
        } catch (
            ValidationException $exception
        ) {
            expect(
                $exception->errors()
            )->toHaveKey(
                'payload.rule_name'
            );
        }

        expect(
            WorkspaceV2CanonicalRevision::query()
                ->count()
        )->toBe(0)
            ->and(
                $proposal->fresh()
                    ->consumed_at
            )
            ->toBeNull();
    }
);

test(
    'capital call rule approval service never expands capital plan target authority',
    function () {
        $source = file_get_contents(
            app_path(
                'Services/PbrTools/PbrV2CapitalCallRuleApprovalService.php'
            )
        );

        expect($source)
            ->toContain(
                "'capital_call_rule'"
            )
            ->not->toContain(
                "'approved_capital_plan'"
            )
            ->not->toContain(
                'PbrV2CapitalPlanReadinessService'
            )
            ->not->toContain(
                'PbrV2ApprovalEngineService'
            )
            ->not->toContain(
                'PbrV2CanonicalOutputService'
            );
    }
);
