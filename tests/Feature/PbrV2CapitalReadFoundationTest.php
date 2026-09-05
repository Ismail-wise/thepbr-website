<?php

use App\Models\PartnershipWorkspace;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ApprovalEngineService;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CapitalReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function capitalReadOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' => now()->addYear(),
    ]);
}

function capitalReadAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => 'active',
    ]);
}

function capitalReadPartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function capitalReadWorkspace(
    User $owner,
    string $businessStage = 'new',
    string $status = 'active'
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital Read Workspace',
        'business_name' => 'Capital Read Business',
        'business_stage' => $businessStage,
        'currency_code' => 'THB',
        'status' => $status,
    ]);
}

function capitalReadMembership(
    User $partner,
    PartnershipWorkspace $workspace,
    string $status = 'accepted'
): WorkspaceMember {
    return WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => $status,
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);
}

function capitalReadRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    array $payload = ['plan' => 'approved']
): WorkspaceV2CanonicalRevision {
    $decision = app(
        PbrV2ApprovalEngineService::class
    )->approve(
        'capital',
        'approved_capital_plan',
        'proposed',
        true,
        true
    );

    return app(
        PbrV2CanonicalOutputService::class
    )->publish(
        $workspace,
        'capital',
        'approved_capital_plan',
        $payload,
        $decision,
        $publisher
    );
}

function capitalReadArtifact(
    PartnershipWorkspace $workspace,
    User $creator,
    string $state = 'working_draft',
    string $domain = 'capital',
    string $component = 'approved_capital_plan',
    array $payload = ['plan' => 'draft'],
    int $sourceGeneration = 1
): WorkspaceV2CanonicalWorkArtifact {
    return WorkspaceV2CanonicalWorkArtifact::query()
        ->forceCreate([
            'workspace_id' => $workspace->id,
            'target_domain_key' => $domain,
            'target_component_key' => $component,
            'producer_domain_key' => 'capital',
            'producer_component_key' =>
                'startup_capital_planner',
            'state' => $state,
            'payload' => $payload,
            'source_generation' => $sourceGeneration,
            'created_by_user_id' => $creator->id,
            'submitted_by_user_id' =>
                $state === 'proposed'
                    ? $creator->id
                    : null,
            'submitted_at' =>
                $state === 'proposed'
                    ? now()
                    : null,
            'published_canonical_revision_id' => null,
            'consumed_at' => null,
        ]);
}

function capitalReadConsume(
    WorkspaceV2CanonicalWorkArtifact $artifact
): WorkspaceV2CanonicalWorkArtifact {
    DB::table(
        'workspace_v2_canonical_work_artifacts'
    )
        ->where('id', $artifact->id)
        ->update([
            'consumed_at' => now(),
        ]);

    return $artifact->fresh();
}

function capitalReadExpectWorkArtifactValidation(
    callable $operation
): void {
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))
            ->toBe(['work_artifact'])
            ->and(
                $exception->errors()[
                    'work_artifact'
                ][0]
            )
            ->toBe(
                'Capital work artifact is unavailable.'
            );

        return;
    }

    throw new RuntimeException(
        'Expected work_artifact validation failure.'
    );
}

test(
    'capital read exposes exactly the agreed constructor and public surface',
    function () {
        $class = new ReflectionClass(
            PbrV2CapitalReadService::class
        );

        expect($class->isFinal())->toBeTrue();

        $public = array_map(
            fn ($method) => $method->getName(),
            $class->getMethods(
                ReflectionMethod::IS_PUBLIC
            )
        );

        sort($public);

        expect($public)->toBe([
            '__construct',
            'current',
            'workArtifact',
            'workArtifacts',
        ]);

        expect($class->getProperties())
            ->toHaveCount(1);

        $canonical = $class->getProperty(
            'canonical'
        );

        expect($canonical->isPrivate())
            ->toBeTrue()
            ->and($canonical->isReadOnly())
            ->toBeTrue()
            ->and(
                (string) $canonical->getType()
            )
            ->toBe(
                PbrV2CanonicalOutputService::class
            );

        $constructor = $class->getConstructor();

        expect(
            $constructor->getParameters()
        )->toHaveCount(1)
            ->and(
                $constructor
                    ->getParameters()[0]
                    ->getName()
            )
            ->toBe('canonical')
            ->and(
                (string) $constructor
                    ->getParameters()[0]
                    ->getType()
            )
            ->toBe(
                PbrV2CanonicalOutputService::class
            );

        $current = $class->getMethod(
            'current'
        );

        expect(
            (string) $current->getReturnType()
        )->toBe('?array')
            ->and(array_map(
                fn ($parameter) => [
                    $parameter->getName(),
                    (string) $parameter->getType(),
                    $parameter
                        ->isDefaultValueAvailable(),
                ],
                $current->getParameters()
            ))
            ->toBe([
                ['actor', User::class, false],
                [
                    'workspace',
                    PartnershipWorkspace::class,
                    false,
                ],
            ]);

        $list = $class->getMethod(
            'workArtifacts'
        );

        expect(
            (string) $list->getReturnType()
        )->toBe('array')
            ->and(array_map(
                fn ($parameter) => [
                    $parameter->getName(),
                    (string) $parameter->getType(),
                    $parameter
                        ->isDefaultValueAvailable()
                        ? $parameter->getDefaultValue()
                        : '__required__',
                ],
                $list->getParameters()
            ))
            ->toBe([
                [
                    'actor',
                    User::class,
                    '__required__',
                ],
                [
                    'workspace',
                    PartnershipWorkspace::class,
                    '__required__',
                ],
                ['beforeId', '?int', null],
            ]);

        $single = $class->getMethod(
            'workArtifact'
        );

        expect(
            (string) $single->getReturnType()
        )->toBe('array')
            ->and(array_map(
                fn ($parameter) => [
                    $parameter->getName(),
                    (string) $parameter->getType(),
                ],
                $single->getParameters()
            ))
            ->toBe([
                ['actor', User::class],
                [
                    'workspace',
                    PartnershipWorkspace::class,
                ],
                ['artifactId', 'int'],
            ]);
    }
);

test(
    'capital read current returns only approved current exact projection and immutable payload',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        $service = app(
            PbrV2CapitalReadService::class
        );

        expect(
            $service->current(
                $owner,
                $workspace
            )
        )->toBeNull();

        $revision = capitalReadRevision(
            $owner,
            $workspace,
            [
                'schema_version' =>
                    'capital-plan-v1',
                'currency_code' => 'THB',
                'approved_value' => '500.00',
            ]
        );

        $result = $service->current(
            $owner,
            $workspace
        );

        expect(array_keys($result))->toBe([
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
            ->and($result['id'])
            ->toBe((int) $revision->id)
            ->and($result['workspace_id'])
            ->toBe((int) $workspace->id)
            ->and($result['domain_key'])
            ->toBe('capital')
            ->and($result['component_key'])
            ->toBe('approved_capital_plan')
            ->and($result['revision'])
            ->toBe(1)
            ->and($result['state'])
            ->toBe('approved_current')
            ->and($result['payload'])
            ->toBe($revision->payload)
            ->and(
                $result[
                    'published_by_user_id'
                ]
            )
            ->toBe((int) $owner->id)
            ->and(
                $result['published_at']
            )
            ->toBeString()
            ->and(
                str_ends_with(
                    $result['published_at'],
                    'Z'
                )
            )
            ->toBeTrue();

        $result['payload'][
            'currency_code'
        ] = 'USD';

        expect(
            $revision
                ->fresh()
                ->payload[
                    'currency_code'
                ]
        )->toBe('THB');
    }
);

test(
    'capital read accepted partner sees approved current only and is denied draft reads before draft query',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $partner = capitalReadPartner();

        capitalReadMembership(
            $partner,
            $workspace
        );

        $revision = capitalReadRevision(
            $owner,
            $workspace,
            ['approved' => 'partner-visible']
        );

        $draft = capitalReadArtifact(
            $workspace,
            $owner,
            'working_draft',
            'capital',
            'approved_capital_plan',
            ['private' => 'owner-only']
        );

        $service = app(
            PbrV2CapitalReadService::class
        );

        $current = $service->current(
            $partner,
            $workspace
        );

        expect($current['id'])
            ->toBe((int) $revision->id)
            ->and($current['payload'])
            ->toBe([
                'approved' =>
                    'partner-visible',
            ]);

        $artifactQueries = [];

        DB::listen(
            function ($query) use (
                &$artifactQueries
            ): void {
                if (
                    str_contains(
                        strtolower($query->sql),
                        'workspace_v2_canonical_work_artifacts'
                    )
                ) {
                    $artifactQueries[] =
                        $query->sql;
                }
            }
        );

        expect(
            fn () =>
                $service->workArtifacts(
                    $partner,
                    $workspace,
                    0
                )
        )->toThrow(
            AuthorizationException::class
        );

        expect($artifactQueries)
            ->toBe([]);

        $artifactQueries = [];

        expect(
            fn () =>
                $service->workArtifact(
                    $partner,
                    $workspace,
                    0
                )
        )->toThrow(
            AuthorizationException::class
        );

        expect($artifactQueries)
            ->toBe([]);

        expect($draft->fresh()->payload)
            ->toBe([
                'private' =>
                    'owner-only',
            ]);
    }
);

test(
    'capital read current denies inactive pending revoked removed and cross workspace partners',
    function (string $case) {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $partner = capitalReadPartner();

        if ($case === 'inactive') {
            capitalReadMembership(
                $partner,
                $workspace
            );

            $partner->forceFill([
                'account_status' =>
                    'inactive',
            ])->save();
        } elseif (
            $case === 'cross_workspace'
        ) {
            $other = capitalReadWorkspace(
                $owner
            );

            capitalReadMembership(
                $partner,
                $other
            );
        } else {
            capitalReadMembership(
                $partner,
                $workspace,
                $case
            );
        }

        expect(
            fn () =>
                app(
                    PbrV2CapitalReadService::class
                )->current(
                    $partner,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );
    }
)->with([
    'inactive',
    'pending',
    'revoked',
    'removed',
    'cross_workspace',
]);

test(
    'capital read reloads actor workspace entitlement and membership evidence',
    function () {
        $service = app(
            PbrV2CapitalReadService::class
        );

        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        User::query()
            ->whereKey($owner->id)
            ->update([
                'portal_access_expires_at' =>
                    now()->subDay(),
            ]);

        $owner->setRelation(
            'studentEnrollments',
            collect([
                new StudentEnrollment([
                    'status' => 'active',
                    'access_expires_at' =>
                        now()->addYear(),
                ]),
            ])
        );

        expect(
            fn () =>
                $service->current(
                    $owner,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );

        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $replacement = capitalReadOwner();

        PartnershipWorkspace::query()
            ->whereKey($workspace->id)
            ->update([
                'owner_user_id' =>
                    $replacement->id,
            ]);

        expect(
            fn () =>
                $service->workArtifacts(
                    $owner,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );

        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $partner = capitalReadPartner();

        $membership = capitalReadMembership(
            $partner,
            $workspace
        );

        $partner->load(
            'workspaceMemberships'
        );

        WorkspaceMember::query()
            ->whereKey($membership->id)
            ->update([
                'invitation_status' =>
                    'removed',
            ]);

        expect(
            fn () =>
                $service->current(
                    $partner,
                    $workspace
                )
        )->toThrow(
            AuthorizationException::class
        );
    }
);

test(
    'capital read requires persisted actor and workspace for every public read',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $service = app(
            PbrV2CapitalReadService::class
        );

        $operations = [
            fn (
                User $actor,
                PartnershipWorkspace $context
            ) =>
                $service->current(
                    $actor,
                    $context
                ),
            fn (
                User $actor,
                PartnershipWorkspace $context
            ) =>
                $service->workArtifacts(
                    $actor,
                    $context
                ),
            fn (
                User $actor,
                PartnershipWorkspace $context
            ) =>
                $service->workArtifact(
                    $actor,
                    $context,
                    1
                ),
        ];

        foreach ($operations as $operation) {
            expect(
                fn () =>
                    $operation(
                        new User(),
                        $workspace
                    )
            )->toThrow(
                ModelNotFoundException::class
            );

            expect(
                fn () =>
                    $operation(
                        $owner,
                        new PartnershipWorkspace()
                    )
            )->toThrow(
                ModelNotFoundException::class
            );
        }
    }
);

test(
    'capital read does not invent workspace status or business stage restrictions',
    function () {
        $owner = capitalReadOwner();

        $workspace = capitalReadWorkspace(
            $owner,
            'existing',
            'inactive'
        );

        $service = app(
            PbrV2CapitalReadService::class
        );

        expect(
            $service->current(
                $owner,
                $workspace
            )
        )->toBeNull()
            ->and(
                $service->workArtifacts(
                    $owner,
                    $workspace
                )
            )
            ->toBe([
                'items' => [],
                'next_before_id' => null,
            ]);
    }
);

test(
    'capital read manager list is workspace wide target isolated unconsumed and paginated',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );
        $otherManager = capitalReadAdmin();

        $valid = [];

        for ($index = 1; $index <= 27; $index++) {
            $creator =
                $index === 1
                    ? $otherManager
                    : $owner;

            $valid[] = capitalReadArtifact(
                $workspace,
                $creator,
                $index % 2 === 0
                    ? 'proposed'
                    : 'working_draft',
                'capital',
                'approved_capital_plan',
                [
                    'sequence' => $index,
                ],
                $index
            );
        }

        capitalReadArtifact(
            $workspace,
            $owner,
            'working_draft',
            'capital',
            'capital_call_rule',
            ['wrong' => 'target']
        );

        $consumed = capitalReadArtifact(
            $workspace,
            $owner,
            'proposed',
            'capital',
            'approved_capital_plan',
            ['consumed' => true]
        );

        capitalReadConsume($consumed);

        $expectedIds = array_map(
            fn ($artifact) =>
                (int) $artifact->id,
            $valid
        );

        rsort($expectedIds);

        $service = app(
            PbrV2CapitalReadService::class
        );

        $first = $service->workArtifacts(
            $owner,
            $workspace
        );

        expect(array_keys($first))->toBe([
            'items',
            'next_before_id',
        ])
            ->and($first['items'])
            ->toHaveCount(25)
            ->and(array_column(
                $first['items'],
                'id'
            ))
            ->toBe(
                array_slice(
                    $expectedIds,
                    0,
                    25
                )
            )
            ->and(
                $first[
                    'next_before_id'
                ]
            )
            ->toBe(
                $expectedIds[24]
            );

        foreach ($first['items'] as $item) {
            expect(array_keys($item))->toBe([
                'id',
                'workspace_id',
                'target_domain_key',
                'target_component_key',
                'state',
                'payload',
                'source_generation',
                'submitted_at',
            ])
                ->and($item['id'])
                ->toBeInt()
                ->and(
                    $item[
                        'workspace_id'
                    ]
                )
                ->toBe(
                    (int) $workspace->id
                )
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
                    'approved_capital_plan'
                )
                ->and(
                    $item[
                        'source_generation'
                    ]
                )
                ->toBeInt();

            if (
                $item['submitted_at']
                !== null
            ) {
                expect(
                    $item[
                        'submitted_at'
                    ]
                )
                    ->toBeString()
                    ->and(
                        str_ends_with(
                            $item[
                                'submitted_at'
                            ],
                            'Z'
                        )
                    )
                    ->toBeTrue();
            }
        }

        $second = $service->workArtifacts(
            $owner,
            $workspace,
            $first['next_before_id']
        );

        expect(
            array_column(
                $second['items'],
                'id'
            )
        )
            ->toBe(
                array_slice(
                    $expectedIds,
                    25
                )
            )
            ->and(
                $second[
                    'next_before_id'
                ]
            )
            ->toBeNull();

        $allReturned = array_merge(
            array_column(
                $first['items'],
                'id'
            ),
            array_column(
                $second['items'],
                'id'
            )
        );

        expect($allReturned)
            ->toContain(
                (int) $valid[0]->id
            )
            ->not->toContain(
                (int) $consumed->id
            );
    }
);

test(
    'capital read validates pagination cursor only after management authorization',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        $service = app(
            PbrV2CapitalReadService::class
        );

        foreach ([0, -1] as $cursor) {
            try {
                $service->workArtifacts(
                    $owner,
                    $workspace,
                    $cursor
                );

                throw new RuntimeException(
                    'Expected before_id validation.'
                );
            } catch (
                ValidationException $exception
            ) {
                expect(
                    array_keys(
                        $exception->errors()
                    )
                )->toBe([
                    'before_id',
                ]);
            }
        }
    }
);

test(
    'capital read single artifact returns exact projection and does not expose model metadata',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        $artifact = capitalReadArtifact(
            $workspace,
            $owner,
            'proposed',
            'capital',
            'approved_capital_plan',
            [
                'schema_version' =>
                    'capital-plan-v1',
                'private_payload' =>
                    'persisted',
            ],
            7
        );

        $result = app(
            PbrV2CapitalReadService::class
        )->workArtifact(
            $owner,
            $workspace,
            $artifact->id
        );

        expect(array_keys($result))->toBe([
            'id',
            'workspace_id',
            'target_domain_key',
            'target_component_key',
            'state',
            'payload',
            'source_generation',
            'submitted_at',
        ])
            ->and($result['id'])
            ->toBe((int) $artifact->id)
            ->and($result['workspace_id'])
            ->toBe((int) $workspace->id)
            ->and(
                $result[
                    'target_domain_key'
                ]
            )
            ->toBe('capital')
            ->and(
                $result[
                    'target_component_key'
                ]
            )
            ->toBe(
                'approved_capital_plan'
            )
            ->and($result['state'])
            ->toBe('proposed')
            ->and(
                $result[
                    'source_generation'
                ]
            )
            ->toBe(7)
            ->and(
                $result[
                    'submitted_at'
                ]
            )
            ->toBeString()
            ->and(
                str_ends_with(
                    $result[
                        'submitted_at'
                    ],
                    'Z'
                )
            )
            ->toBeTrue();

        $result['payload'][
            'private_payload'
        ] = 'caller-change';

        expect(
            $artifact
                ->fresh()
                ->payload[
                    'private_payload'
                ]
        )->toBe('persisted');
    }
);

test(
    'capital read single artifact gives the same work_artifact validation for missing foreign wrong target consumed and invalid id',
    function (string $case) {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        $artifactId = 0;

        if ($case === 'missing') {
            $artifactId = 999999;
        } elseif ($case === 'foreign') {
            $foreignWorkspace =
                capitalReadWorkspace(
                    $owner
                );

            $artifactId =
                capitalReadArtifact(
                    $foreignWorkspace,
                    $owner
                )->id;
        } elseif (
            $case === 'wrong_target'
        ) {
            $artifactId =
                capitalReadArtifact(
                    $workspace,
                    $owner,
                    'working_draft',
                    'capital',
                    'capital_call_rule'
                )->id;
        } elseif ($case === 'consumed') {
            $artifact =
                capitalReadArtifact(
                    $workspace,
                    $owner,
                    'proposed'
                );

            $artifactId =
                capitalReadConsume(
                    $artifact
                )->id;
        } elseif ($case === 'negative') {
            $artifactId = -10;
        }

        capitalReadExpectWorkArtifactValidation(
            fn () =>
                app(
                    PbrV2CapitalReadService::class
                )->workArtifact(
                    $owner,
                    $workspace,
                    $artifactId
                )
        );
    }
)->with([
    'invalid_zero',
    'negative',
    'missing',
    'foreign',
    'wrong_target',
    'consumed',
]);

test(
    'capital read active admin can read another owners approved current list and single draft',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        $revision = capitalReadRevision(
            $owner,
            $workspace
        );

        $artifact = capitalReadArtifact(
            $workspace,
            $owner
        );

        $admin = capitalReadAdmin();

        $service = app(
            PbrV2CapitalReadService::class
        );

        expect(
            $service->current(
                $admin,
                $workspace
            )['id']
        )
            ->toBe((int) $revision->id)
            ->and(
                array_column(
                    $service
                        ->workArtifacts(
                            $admin,
                            $workspace
                        )['items'],
                    'id'
                )
            )
            ->toContain(
                (int) $artifact->id
            )
            ->and(
                $service
                    ->workArtifact(
                        $admin,
                        $workspace,
                        $artifact->id
                    )['id']
            )
            ->toBe(
                (int) $artifact->id
            );
    }
);

test(
    'capital read performs no database writes',
    function () {
        $owner = capitalReadOwner();
        $workspace = capitalReadWorkspace(
            $owner
        );

        capitalReadRevision(
            $owner,
            $workspace
        );

        $artifact = capitalReadArtifact(
            $workspace,
            $owner
        );

        $writes = [];

        DB::listen(
            function ($query) use (
                &$writes
            ): void {
                if (
                    preg_match(
                        '/^\s*(insert|update|delete|replace|alter|create|drop)\b/i',
                        $query->sql
                    )
                ) {
                    $writes[] = $query->sql;
                }
            }
        );

        $service = app(
            PbrV2CapitalReadService::class
        );

        $service->current(
            $owner,
            $workspace
        );

        $service->workArtifacts(
            $owner,
            $workspace
        );

        $service->workArtifact(
            $owner,
            $workspace,
            $artifact->id
        );

        expect($writes)->toBe([]);
    }
);
