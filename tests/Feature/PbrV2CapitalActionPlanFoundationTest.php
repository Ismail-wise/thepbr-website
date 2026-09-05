<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalActionPlanService;
use App\Services\PbrTools\PbrV2ExecutionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function capActionOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function capActionWorkspace(
    User $owner,
    string $name = 'Action Plan Workspace'
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => $name,
        'business_name' => $name,
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function capActionRevision(
    User $owner,
    PartnershipWorkspace $workspace,
    int $revision = 1,
    string $state = 'approved_current'
): WorkspaceV2CanonicalRevision {
    return WorkspaceV2CanonicalRevision::query()
        ->create([
            'workspace_id' => $workspace->id,
            'domain_key' => 'capital',
            'component_key' =>
                'approved_capital_plan',
            'component_type' => 'plan',
            'revision' => $revision,
            'state' => $state,
            'payload' => [
                'schema_version' =>
                    'capital-plan-v1',
                'plan_name' =>
                    'Action Plan Source '.$revision,
            ],
            'approval_decision' => [
                'decision' => 'approved',
            ],
            'published_by_user_id' =>
                $owner->id,
            'published_at' => now(),
            'superseded_at' =>
                $state === 'superseded'
                    ? now()
                    : null,
        ]);
}

function capActionDecisionRecord(
    User $actor,
    PartnershipWorkspace $workspace,
    WorkspaceV2CanonicalRevision $revision
): WorkspaceV2OperatingRecord {
    return app(
        PbrV2ApplicationService::class
    )->appendRecord(
        $actor,
        $workspace,
        'capital',
        'capital_decision_record',
        [$revision->id],
        [
            'decision_summary' =>
                'Execute the approved capital decision.',
        ],
        [
            'title' =>
                'Action Plan Source Decision',
            'record_date' =>
                now()->toDateString(),
        ]
    );
}

function capActionAttributes(
    string $title = 'Fund opening inventory'
): array {
    return [
        'title' => $title,
        'description' =>
            'Execute the recorded capital decision.',
        'owner_name' => 'Finance Lead',
        'priority' => 'high',
        'due_date' => '2026-09-30',
    ];
}

test(
    'capital action plan exposes exact thin capital specific public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalActionPlanService::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull()
            ->and(
                array_map(
                    fn ($parameter) =>
                        $parameter->getName(),
                    $constructor->getParameters()
                )
            )
            ->toBe([
                'application',
                'execution',
            ])
            ->and(
                array_map(
                    fn ($parameter) =>
                        (string) $parameter->getType(),
                    $constructor->getParameters()
                )
            )
            ->toBe([
                PbrV2ApplicationService::class,
                PbrV2ExecutionService::class,
            ]);

        $public = [];

        foreach (
            $reflection->getMethods(
                ReflectionMethod::IS_PUBLIC
            ) as $method
        ) {
            if (
                $method
                    ->getDeclaringClass()
                    ->getName()
                === PbrV2CapitalActionPlanService::class
            ) {
                $public[] = $method->getName();
            }
        }

        sort($public);

        expect($public)->toBe([
            '__construct',
            'actionStates',
            'actions',
            'createFromDecisionRecord',
            'priorities',
            'transition',
        ]);
    }
);

test(
    'capital action plan delegates frozen execution vocabulary without inventing states',
    function () {
        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        expect($service->actionStates())
            ->toBe([
                'open',
                'in_progress',
                'blocked',
                'completed',
                'superseded',
            ])
            ->and($service->priorities())
            ->toBe([
                'low',
                'normal',
                'high',
                'critical',
            ]);
    }
);

test(
    'manager creates action from exact immutable decision record with canonical source pin',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $action = app(
            PbrV2CapitalActionPlanService::class
        )->createFromDecisionRecord(
            $owner,
            $workspace,
            $record->id,
            capActionAttributes()
        );

        expect(array_keys($action))->toBe([
            'id',
            'workspace_id',
            'action_domain_key',
            'action_component_key',
            'title',
            'description',
            'owner_name',
            'priority',
            'status',
            'due_date',
            'completed_at',
            'created_by_user_id',
            'created_at',
            'updated_at',
            'producer',
            'sources',
        ])
            ->and($action['workspace_id'])
            ->toBe($workspace->id)
            ->and($action['action_domain_key'])
            ->toBe('capital')
            ->and($action['action_component_key'])
            ->toBe('capital_action_plan')
            ->and($action['status'])
            ->toBe('open')
            ->and($action['priority'])
            ->toBe('high')
            ->and($action['owner_name'])
            ->toBe('Finance Lead')
            ->and($action['due_date'])
            ->toBe('2026-09-30')
            ->and($action['completed_at'])
            ->toBeNull()
            ->and($action['producer'])
            ->toBe([
                'domain_key' => 'capital',
                'component_key' =>
                    'capital_decision_record',
                'canonical_revision_id' => null,
                'operating_record_id' =>
                    $record->id,
            ])
            ->and($action['sources'])
            ->toHaveCount(1)
            ->and(
                $action['sources'][0]
                    ['canonical_revision_id']
            )
            ->toBe($revision->id)
            ->and(
                $action['sources'][0]
                    ['component_key']
            )
            ->toBe('approved_capital_plan');

        $persisted = WorkspaceV2Action::query()
            ->findOrFail($action['id']);

        expect(
            $persisted
                ->producer_operating_record_id
        )
            ->toBe($record->id)
            ->and(
                $persisted
                    ->producer_canonical_revision_id
            )
            ->toBeNull();
    }
);

test(
    'one recorded capital decision may produce multiple independent action plan items',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $first = $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                capActionAttributes(
                    'Secure premises deposit'
                )
            );

        $second = $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                capActionAttributes(
                    'Purchase opening inventory'
                )
            );

        expect($first['id'])
            ->not->toBe($second['id'])
            ->and(
                WorkspaceV2Action::query()
                    ->count()
            )
            ->toBe(2);

        $actions = $service->actions(
            $owner,
            $workspace
        );

        expect($actions)
            ->toHaveCount(2)
            ->and($actions[0]['id'])
            ->toBe($second['id'])
            ->and($actions[1]['id'])
            ->toBe($first['id']);
    }
);

test(
    'new action creation fails closed when decision record canonical source is no longer current',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $revision->state = 'superseded';
        $revision->superseded_at = now();
        $revision->save();

        expect(
            fn () => app(
                PbrV2CapitalActionPlanService::class
            )->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                capActionAttributes()
            )
        )->toThrow(
            ValidationException::class
        );

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'capital action plan rejects foreign or missing decision record before action creation',
    function () {
        $owner = capActionOwner();

        $workspace =
            capActionWorkspace(
                $owner,
                'Action Workspace A'
            );

        $otherWorkspace =
            capActionWorkspace(
                $owner,
                'Action Workspace B'
            );

        $revision = capActionRevision(
            $owner,
            $otherWorkspace
        );

        $foreignRecord =
            capActionDecisionRecord(
                $owner,
                $otherWorkspace,
                $revision
            );

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        foreach ([
            $foreignRecord->id,
            999999,
        ] as $recordId) {
            expect(
                fn () =>
                    $service
                        ->createFromDecisionRecord(
                            $owner,
                            $workspace,
                            $recordId,
                            capActionAttributes()
                        )
            )->toThrow(
                ValidationException::class
            );
        }

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'accepted partner is denied capital action plan reads creates and transitions',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $action = $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                capActionAttributes()
            );

        $partner = User::factory()->create([
            'role' => 'public',
            'is_admin' => false,
            'account_status' => 'active',
        ]);

        WorkspaceMember::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $partner->id,
            'member_role' => 'partner',
            'invitation_status' => 'accepted',
            'permissions' => [
                'approved_workspace_read_only',
            ],
        ]);

        $before = WorkspaceV2Action::query()
            ->count();

        expect(
            fn () => $service->actions(
                $partner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            fn () =>
                $service
                    ->createFromDecisionRecord(
                        $partner,
                        $workspace,
                        $record->id,
                        capActionAttributes(
                            'Partner must not create'
                        )
                    )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            fn () => $service->transition(
                $partner,
                $workspace,
                $action['id'],
                'in_progress'
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            WorkspaceV2Action::query()
                ->count()
        )->toBe($before)
            ->and(
                WorkspaceV2Action::query()
                    ->findOrFail($action['id'])
                    ->status
            )
            ->toBe('open');
    }
);

test(
    'active admin may execute another owners recorded capital decision',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $admin = capActionOwner();

        $admin->forceFill([
            'role' => 'admin',
            'is_admin' => true,
            'account_status' => 'active',
        ])->save();

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $action = $service
            ->createFromDecisionRecord(
                $admin,
                $workspace,
                $record->id,
                capActionAttributes(
                    'Admin execution action'
                )
            );

        expect($action['created_by_user_id'])
            ->toBe($admin->id)
            ->and(
                $service->actions(
                    $admin,
                    $workspace
                )
            )
            ->toHaveCount(1);
    }
);

test(
    'capital action transition delegates exact frozen lifecycle and terminal states remain terminal',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $action = $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                capActionAttributes(
                    'Lifecycle action'
                )
            );

        $action = $service->transition(
            $owner,
            $workspace,
            $action['id'],
            'in_progress'
        );

        expect($action['status'])
            ->toBe('in_progress');

        $action = $service->transition(
            $owner,
            $workspace,
            $action['id'],
            'blocked'
        );

        expect($action['status'])
            ->toBe('blocked');

        $action = $service->transition(
            $owner,
            $workspace,
            $action['id'],
            'completed'
        );

        expect($action['status'])
            ->toBe('completed')
            ->and($action['completed_at'])
            ->not->toBeNull();

        expect(
            fn () => $service->transition(
                $owner,
                $workspace,
                $action['id'],
                'in_progress'
            )
        )->toThrow(
            ValidationException::class
        );
    }
);

test(
    'capital action plan reloads management entitlement and ignores caller tampering',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $intruder = capActionOwner();

        $intruder->is_admin = true;
        $workspace->owner_user_id =
            $intruder->id;

        expect(
            fn () => $service->actions(
                $intruder,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );
    }
);

test(
    'capital action projection hides metadata and keeps provenance explicit',
    function () {
        $owner = capActionOwner();
        $workspace =
            capActionWorkspace($owner);

        $revision = capActionRevision(
            $owner,
            $workspace
        );

        $record = capActionDecisionRecord(
            $owner,
            $workspace,
            $revision
        );

        $service = app(
            PbrV2CapitalActionPlanService::class
        );

        $action = $service
            ->createFromDecisionRecord(
                $owner,
                $workspace,
                $record->id,
                [
                    ...capActionAttributes(),
                    'metadata' => [
                        'private' => 'must not persist',
                    ],
                    'status' => 'completed',
                ]
            );

        expect($action['status'])
            ->toBe('open')
            ->and($action)
            ->not->toHaveKey('metadata')
            ->and($action)
            ->not->toHaveKey(
                'producer_operating_record_id'
            )
            ->and($action)
            ->not->toHaveKey(
                'producer_canonical_revision_id'
            )
            ->and($action['producer'])
            ->toBe([
                'domain_key' => 'capital',
                'component_key' =>
                    'capital_decision_record',
                'canonical_revision_id' => null,
                'operating_record_id' =>
                    $record->id,
            ]);

        $persisted =
            WorkspaceV2Action::query()
                ->findOrFail($action['id']);

        expect($persisted->metadata)
            ->toBeNull()
            ->and($persisted->status)
            ->toBe('open');
    }
);
