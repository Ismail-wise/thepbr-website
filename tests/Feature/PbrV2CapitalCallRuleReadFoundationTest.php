<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CanonicalWorkService;
use App\Services\PbrTools\PbrV2CapitalCallRuleReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function c2dReadOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function c2dReadWorkspace(
    User $owner,
    string $name = 'C2D Read Workspace'
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

function c2dReadPayload(
    string $name = 'C2D Read Rule'
): array {
    return [
        'schema_version' =>
            'capital-call-rule-v1',
        'rule_name' => $name,
        'owner_reference' =>
            'C2D Rule Owner',
        'effective_date' =>
            '2026-09-07',
        'review_date' =>
            '2027-09-07',
        'rule_summary' =>
            'C2D approved capital-call procedure.',
        'trigger_conditions' => [
            'Additional approved capital is required.',
        ],
        'procedure_steps' => [
            'Document and approve the proposed call before execution.',
        ],
        'evidence_references' => [
            'C2D evidence',
        ],
    ];
}

function c2dReadPublish(
    User $actor,
    PartnershipWorkspace $workspace,
    array $payload
) {
    $decision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        'capital',
        'capital_call_rule',
        'proposed',
        true,
        true
    );

    return app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        'capital',
        'capital_call_rule',
        $payload,
        $decision,
        $actor
    );
}

function c2dReadArtifact(
    User $actor,
    PartnershipWorkspace $workspace,
    string $state = 'working_draft',
    string $target =
        'capital_call_rule',
    ?array $payload = null
): WorkspaceV2CanonicalWorkArtifact {
    $artifact = app(
        PbrV2CanonicalWorkService::class
    )->createWorkingDraft(
        $workspace,
        'capital',
        $target,
        'capital',
        'startup_capital_planner',
        $payload
            ?? c2dReadPayload(
                'C2D Draft Rule'
            ),
        [],
        $actor
    );

    if ($state === 'proposed') {
        $artifact = app(
            PbrV2CanonicalWorkService::class
        )->submitProposal(
            $workspace,
            $artifact,
            $actor
        );
    }

    return $artifact;
}

function c2dReadPartner(
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
    'capital call rule read exposes the exact final public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalCallRuleReadService::class
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
                'canonical',
                PbrV2CanonicalOutputService::class,
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
                        === PbrV2CapitalCallRuleReadService::class
                )
            )
        );

        expect($public)->toBe([
            '__construct',
            'current',
            'workArtifacts',
            'workArtifact',
        ]);
    }
);

test(
    'manager reads exact approved current capital call rule projection',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        $payload =
            c2dReadPayload(
                'Approved C2D Rule'
            );

        $revision =
            c2dReadPublish(
                $owner,
                $workspace,
                $payload
            );

        $current = app(
            PbrV2CapitalCallRuleReadService::class
        )->current(
            $owner,
            $workspace
        );

        expect(array_keys($current))
            ->toBe([
                'id',
                'workspace_id',
                'domain_key',
                'component_key',
                'revision',
                'state',
                'payload',
                'published_by_user_id',
                'published_at',
            ])
            ->and($current['id'])
            ->toBe((int) $revision->id)
            ->and($current['workspace_id'])
            ->toBe((int) $workspace->id)
            ->and($current['domain_key'])
            ->toBe('capital')
            ->and($current['component_key'])
            ->toBe('capital_call_rule')
            ->and($current['revision'])
            ->toBe(1)
            ->and($current['state'])
            ->toBe('approved_current')
            ->and($current['payload'])
            ->toBe($payload)
            ->and(
                $current[
                    'published_by_user_id'
                ]
            )
            ->toBe((int) $owner->id)
            ->and($current['published_at'])
            ->not->toBeNull();
    }
);

test(
    'capital call rule current returns null when no approved current exists',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        expect(
            app(
                PbrV2CapitalCallRuleReadService::class
            )->current(
                $owner,
                $workspace
            )
        )->toBeNull();
    }
);

test(
    'accepted active partner may read approved current only',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        $payload =
            c2dReadPayload(
                'Partner Visible Rule'
            );

        c2dReadPublish(
            $owner,
            $workspace,
            $payload
        );

        c2dReadArtifact(
            $owner,
            $workspace
        );

        $partner =
            c2dReadPartner(
                $workspace
            );

        $service = app(
            PbrV2CapitalCallRuleReadService::class
        );

        $current =
            $service->current(
                $partner,
                $workspace
            );

        expect($current['payload'])
            ->toBe($payload);

        expect(
            fn () =>
                $service->workArtifacts(
                    $partner,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            fn () =>
                $service->workArtifact(
                    $partner,
                    $workspace,
                    1
                )
        )->toThrow(
            AuthorizationException::class
        );
    }
);

test(
    'unauthorized user cannot read approved current or private work',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        c2dReadPublish(
            $owner,
            $workspace,
            c2dReadPayload()
        );

        $intruder =
            c2dReadOwner();

        $service = app(
            PbrV2CapitalCallRuleReadService::class
        );

        expect(
            fn () =>
                $service->current(
                    $intruder,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            fn () =>
                $service->workArtifacts(
                    $intruder,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );
    }
);

test(
    'manager work list is newest first paginated and isolates capital call rule target',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        $valid = [];

        for (
            $index = 1;
            $index <= 27;
            $index++
        ) {
            $valid[] =
                c2dReadArtifact(
                    $owner,
                    $workspace,
                    $index % 2 === 0
                        ? 'proposed'
                        : 'working_draft',
                    'capital_call_rule',
                    c2dReadPayload(
                        'Rule '.$index
                    )
                );
        }

        c2dReadArtifact(
            $owner,
            $workspace,
            'working_draft',
            'approved_capital_plan',
            [
                'wrong' => 'target',
            ]
        );

        $expected = array_map(
            fn (
                WorkspaceV2CanonicalWorkArtifact $artifact
            ) =>
                (int) $artifact->id,
            $valid
        );

        rsort($expected);

        $service = app(
            PbrV2CapitalCallRuleReadService::class
        );

        $first =
            $service->workArtifacts(
                $owner,
                $workspace
            );

        expect(array_keys($first))
            ->toBe([
                'items',
                'next_before_id',
            ])
            ->and($first['items'])
            ->toHaveCount(25)
            ->and(
                array_column(
                    $first['items'],
                    'id'
                )
            )
            ->toBe(
                array_slice(
                    $expected,
                    0,
                    25
                )
            )
            ->and(
                $first[
                    'next_before_id'
                ]
            )
            ->not->toBeNull();

        foreach (
            $first['items']
            as $item
        ) {
            expect(
                array_keys($item)
            )->toBe([
                'id',
                'workspace_id',
                'target_domain_key',
                'target_component_key',
                'state',
                'payload',
                'source_generation',
                'submitted_at',
            ])
                ->and(
                    $item[
                        'target_domain_key'
                    ]
                )
                ->toBe('capital')
                ->and(
                    $item[
                        'target_component_key'
                    ]
                )
                ->toBe(
                    'capital_call_rule'
                );
        }

        $second =
            $service->workArtifacts(
                $owner,
                $workspace,
                $first[
                    'next_before_id'
                ]
            );

        expect(
            array_column(
                $second['items'],
                'id'
            )
        )->toBe(
            array_slice(
                $expected,
                25
            )
        )
            ->and(
                $second[
                    'next_before_id'
                ]
            )
            ->toBeNull();
    }
);

test(
    'manager reads one exact unconsumed capital call rule artifact only',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        $artifact =
            c2dReadArtifact(
                $owner,
                $workspace,
                'proposed',
                'capital_call_rule',
                c2dReadPayload(
                    'Single Rule'
                )
            );

        $result = app(
            PbrV2CapitalCallRuleReadService::class
        )->workArtifact(
            $owner,
            $workspace,
            $artifact->id
        );

        expect($result['id'])
            ->toBe((int) $artifact->id)
            ->and(
                $result[
                    'target_component_key'
                ]
            )
            ->toBe(
                'capital_call_rule'
            )
            ->and($result['state'])
            ->toBe('proposed')
            ->and(
                $result['payload']
                    ['rule_name']
            )
            ->toBe('Single Rule');
    }
);

test(
    'capital call rule work artifact rejects invalid foreign and wrong target identities',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        $other =
            c2dReadWorkspace(
                $owner,
                'Other C2D Workspace'
            );

        $foreign =
            c2dReadArtifact(
                $owner,
                $other
            );

        $wrongTarget =
            c2dReadArtifact(
                $owner,
                $workspace,
                'working_draft',
                'approved_capital_plan',
                [
                    'wrong' => true,
                ]
            );

        $service = app(
            PbrV2CapitalCallRuleReadService::class
        );

        foreach ([
            0,
            -10,
            999999,
            $foreign->id,
            $wrongTarget->id,
        ] as $artifactId) {
            expect(
                fn () =>
                    $service->workArtifact(
                        $owner,
                        $workspace,
                        $artifactId
                    )
            )->toThrow(
                ValidationException::class
            );
        }
    }
);

test(
    'capital call rule work cursor must be positive',
    function () {
        $owner = c2dReadOwner();

        $workspace =
            c2dReadWorkspace($owner);

        expect(
            fn () => app(
                PbrV2CapitalCallRuleReadService::class
            )->workArtifacts(
                $owner,
                $workspace,
                0
            )
        )->toThrow(
            ValidationException::class
        );
    }
);

test(
    'capital call rule read never falls back to approved capital plan',
    function () {
        $source = file_get_contents(
            app_path(
                'Services/PbrTools/PbrV2CapitalCallRuleReadService.php'
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
                'history('
            );
    }
);
