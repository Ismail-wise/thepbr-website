<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2OperatingRecord;
use App\Models\WorkspaceV2OperatingRecordCanonicalSource;
use App\Services\PbrTools\PbrV2ApplicationService;
use App\Services\PbrTools\PbrV2CapitalDecisionRecordService;
use App\Services\PbrTools\PbrV2CapitalReadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function v2CapitalDecisionRecordOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function v2CapitalDecisionRecordAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => 'active',
    ]);
}

function v2CapitalDecisionRecordPartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function v2CapitalDecisionRecordWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Decision Record Workspace',
        'business_name' =>
            'Decision Record Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function v2CapitalDecisionRecordMembership(
    User $partner,
    PartnershipWorkspace $workspace
): WorkspaceMember {
    return WorkspaceMember::query()->forceCreate([
        'workspace_id' => $workspace->id,
        'user_id' => $partner->id,
        'member_role' => 'partner',
        'invitation_status' => 'accepted',
        'permissions' => [
            'approved_workspace_read_only',
        ],
    ]);
}

function v2CapitalDecisionRecordPayload(
    string $planName = 'Base Capital Plan',
    string $ownerReference = 'Finance Lead',
    string $effectiveDate = '2026-09-10',
    string $reviewDate = '2026-12-10',
    string $summary = 'Proceed with approved capital plan.'
): array {
    return [
        'schema_version' =>
            'capital-plan-v1',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'plan_name' => $planName,
        'scenario_type' => 'base',
        'assessment_date' => '2026-09-05',
        'assumptions' => [
            'opening_date' => '2026-10-01',
            'working_capital_months' => 3,
            'contingency_percentage' => '10.00',
            'monthly_burn' => '1000.00',
        ],
        'cost_items' => [],
        'funding_sources' => [],
        'shortfall_response' => null,
        'decision_record' => [
            'owner_reference' =>
                $ownerReference,
            'effective_date' =>
                $effectiveDate,
            'review_date' =>
                $reviewDate,
            'decision_summary' =>
                $summary,
            'evidence_references' => [
                'board-minute-001',
                'capital-worksheet-001',
            ],
        ],
    ];
}

function v2CapitalDecisionRecordRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    int $revision = 1,
    string $planName = 'Base Capital Plan'
): WorkspaceV2CanonicalRevision {
    return WorkspaceV2CanonicalRevision::query()->create([
        'workspace_id' => $workspace->id,
        'domain_key' => 'capital',
        'component_key' =>
            'approved_capital_plan',
        'component_type' => 'plan',
        'revision' => $revision,
        'state' => 'approved_current',
        'payload' =>
            v2CapitalDecisionRecordPayload(
                $planName
            ),
        'approval_decision' => [
            'decision' => 'approved',
        ],
        'published_by_user_id' =>
            $publisher->id,
        'published_at' =>
            now()->subMinute(),
        'superseded_at' => null,
    ]);
}

test(
    'capital decision record exposes exact thin capital specific public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalDecisionRecordService::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull();

        $dependencies = [];

        foreach (
            $constructor->getParameters()
            as $parameter
        ) {
            $dependencies[
                $parameter->getName()
            ] = (string) $parameter->getType();
        }

        expect($dependencies)->toBe([
            'read' =>
                PbrV2CapitalReadService::class,
            'application' =>
                PbrV2ApplicationService::class,
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
                === PbrV2CapitalDecisionRecordService::class
            ) {
                $public[] = $method->getName();
            }
        }

        sort($public);

        expect($public)->toBe([
            '__construct',
            'recordCurrent',
            'records',
        ]);

        $source = file_get_contents(
            app_path(
                'Services/PbrTools/PbrV2CapitalDecisionRecordService.php'
            )
        );

        expect($source)
            ->toContain(
                '$this->read->current('
            )
            ->toContain(
                '$this->application'
            )
            ->toContain(
                '->appendRecord('
            )
            ->not->toContain(
                'PbrV2ExecutionService'
            )
            ->not->toContain(
                'WorkspaceV2OperatingRecord::create'
            )
            ->not->toContain(
                'WorkspaceV2OperatingRecordCanonicalSource::create'
            )
            ->not->toContain('DB::')
            ->not->toContain(
                'WorkspaceOperatingRecord'
            )
            ->not->toContain(
                'ToolSession'
            )
            ->not->toContain(
                'publishAgreedOutput'
            );
    }
);

test(
    'manager records exact current approved capital decision through frozen application execution authority',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        $revision =
            v2CapitalDecisionRecordRevision(
                $owner,
                $workspace
            );

        $publishedAt =
            $revision
                ->published_at
                ->copy()
                ->utc()
                ->toISOString();

        $result = app(
            PbrV2CapitalDecisionRecordService::class
        )->recordCurrent(
            $owner,
            $workspace
        );

        expect($result['workspace_id'])
            ->toBe($workspace->id)
            ->and($result['state'])
            ->toBe('recorded')
            ->and($result['title'])
            ->toBe(
                'Capital Decision Record - Base Capital Plan'
            )
            ->and($result['record_date'])
            ->toBe('2026-09-10')
            ->and($result['payload'])
            ->toBe([
                'schema_version' =>
                    'capital-decision-record-v1',
                'owner_reference' =>
                    'Finance Lead',
                'effective_date' =>
                    '2026-09-10',
                'review_date' =>
                    '2026-12-10',
                'decision_summary' =>
                    'Proceed with approved capital plan.',
                'evidence_references' => [
                    'board-minute-001',
                    'capital-worksheet-001',
                ],
                'approved_by_user_id' =>
                    $owner->id,
                'approved_at' =>
                    $publishedAt,
                'source_plan_revision' => 1,
            ])
            ->and($result['recorded_by_user_id'])
            ->toBe($owner->id)
            ->and($result['recorded_at'])
            ->not->toBeNull()
            ->and($result['source'])
            ->toBe([
                'canonical_revision_id' =>
                    $revision->id,
                'domain_key' => 'capital',
                'component_key' =>
                    'approved_capital_plan',
                'revision' => 1,
            ]);

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(1)
            ->and(
                WorkspaceV2OperatingRecordCanonicalSource::query()
                    ->count()
            )
            ->toBe(1)
            ->and(
                WorkspaceV2CanonicalRevision::query()
                    ->count()
            )
            ->toBe(1);
    }
);

test(
    'same approved capital revision cannot create duplicate decision records',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        v2CapitalDecisionRecordRevision(
            $owner,
            $workspace
        );

        $service = app(
            PbrV2CapitalDecisionRecordService::class
        );

        $service->recordCurrent(
            $owner,
            $workspace
        );

        expect(
            fn () => $service->recordCurrent(
                $owner,
                $workspace
            )
        )->toThrow(
            ValidationException::class
        );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(1)
            ->and(
                WorkspaceV2OperatingRecordCanonicalSource::query()
                    ->count()
            )
            ->toBe(1);
    }
);

test(
    'new approved revision may create a new immutable record and records are newest first',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        $service = app(
            PbrV2CapitalDecisionRecordService::class
        );

        $first =
            v2CapitalDecisionRecordRevision(
                $owner,
                $workspace,
                1,
                'First Plan'
            );

        $firstRecord =
            $service->recordCurrent(
                $owner,
                $workspace
            );

        $first->state = 'superseded';
        $first->superseded_at = now();
        $first->save();

        $second =
            v2CapitalDecisionRecordRevision(
                $owner,
                $workspace,
                2,
                'Second Plan'
            );

        $secondRecord =
            $service->recordCurrent(
                $owner,
                $workspace
            );

        $records = $service->records(
            $owner,
            $workspace
        );

        expect($records)
            ->toHaveCount(2)
            ->and(
                array_column(
                    $records,
                    'id'
                )
            )
            ->toBe([
                $secondRecord['id'],
                $firstRecord['id'],
            ])
            ->and(
                $records[0][
                    'source'
                ][
                    'canonical_revision_id'
                ]
            )
            ->toBe($second->id)
            ->and(
                $records[0][
                    'payload'
                ][
                    'source_plan_revision'
                ]
            )
            ->toBe(2)
            ->and(
                $records[1][
                    'payload'
                ][
                    'source_plan_revision'
                ]
            )
            ->toBe(1);
    }
);

test(
    'active admin may record another owners approved capital decision',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        $revision =
            v2CapitalDecisionRecordRevision(
                $owner,
                $workspace
            );

        $admin =
            v2CapitalDecisionRecordAdmin();

        $result = app(
            PbrV2CapitalDecisionRecordService::class
        )->recordCurrent(
            $admin,
            $workspace
        );

        expect($result['state'])
            ->toBe('recorded')
            ->and($result['recorded_by_user_id'])
            ->toBe($admin->id)
            ->and(
                $result[
                    'payload'
                ][
                    'approved_by_user_id'
                ]
            )
            ->toBe($owner->id)
            ->and(
                $result[
                    'source'
                ][
                    'canonical_revision_id'
                ]
            )
            ->toBe($revision->id);
    }
);

test(
    'accepted partner is denied decision record management before canonical or operating record queries',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        v2CapitalDecisionRecordRevision(
            $owner,
            $workspace
        );

        $partner =
            v2CapitalDecisionRecordPartner();

        v2CapitalDecisionRecordMembership(
            $partner,
            $workspace
        );

        $queries = [];

        DB::listen(
            function ($query) use (&$queries): void {
                $sql = strtolower(
                    $query->sql
                );

                if (
                    str_contains(
                        $sql,
                        'workspace_v2_canonical_revisions'
                    )
                    || str_contains(
                        $sql,
                        'workspace_v2_operating_records'
                    )
                ) {
                    $queries[] = $sql;
                }
            }
        );

        $service = app(
            PbrV2CapitalDecisionRecordService::class
        );

        expect(
            fn () => $service->recordCurrent(
                $partner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect($queries)->toBe([]);

        expect(
            fn () => $service->records(
                $partner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect($queries)->toBe([]);
    }
);

test(
    'decision record reloads manager entitlement before any record access',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        v2CapitalDecisionRecordRevision(
            $owner,
            $workspace
        );

        $owner->forceFill([
            'portal_access_expires_at' =>
                now()->subDay(),
        ])->save();

        expect(
            fn () => app(
                PbrV2CapitalDecisionRecordService::class
            )->recordCurrent(
                $owner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'decision record fails closed when no approved current capital plan exists',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        expect(
            fn () => app(
                PbrV2CapitalDecisionRecordService::class
            )->recordCurrent(
                $owner,
                $workspace
            )
        )->toThrow(
            ValidationException::class
        );

        expect(
            WorkspaceV2OperatingRecord::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'decision record listing is read only and exposes only the safe capital projection',
    function () {
        $owner =
            v2CapitalDecisionRecordOwner();

        $workspace =
            v2CapitalDecisionRecordWorkspace(
                $owner
            );

        v2CapitalDecisionRecordRevision(
            $owner,
            $workspace
        );

        $service = app(
            PbrV2CapitalDecisionRecordService::class
        );

        $created = $service->recordCurrent(
            $owner,
            $workspace
        );

        $writes = [];

        DB::listen(
            function ($query) use (&$writes): void {
                $sql = strtolower(
                    ltrim($query->sql)
                );

                if (
                    str_starts_with(
                        $sql,
                        'insert '
                    )
                    || str_starts_with(
                        $sql,
                        'update '
                    )
                    || str_starts_with(
                        $sql,
                        'delete '
                    )
                ) {
                    $writes[] = $sql;
                }
            }
        );

        $records = $service->records(
            $owner,
            $workspace
        );

        expect($writes)->toBe([])
            ->and($records)
            ->toHaveCount(1)
            ->and(
                array_keys(
                    $records[0]
                )
            )
            ->toBe([
                'id',
                'workspace_id',
                'state',
                'title',
                'record_date',
                'effective_at',
                'payload',
                'recorded_by_user_id',
                'recorded_at',
                'source',
            ])
            ->and($records[0])
            ->toBe($created)
            ->and($records[0])
            ->not->toHaveKeys([
                'metadata',
                'created_at',
                'updated_at',
                'approval_decision',
                'canonicalSources',
            ]);
    }
);
