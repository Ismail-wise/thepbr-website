<?php

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Services\PbrTools\PbrV2CanonicalOutputService;
use App\Services\PbrTools\PbrV2CapitalHistoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function capitalHistoryOwner(): User
{
    return User::factory()->create([
        'role' => 'student',
        'is_admin' => false,
        'account_status' => 'active',
        'portal_access_expires_at' =>
            now()->addYear(),
    ]);
}

function capitalHistoryAdmin(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => 'active',
    ]);
}

function capitalHistoryPartner(): User
{
    return User::factory()->create([
        'role' => 'public',
        'is_admin' => false,
        'account_status' => 'active',
    ]);
}

function capitalHistoryWorkspace(
    User $owner
): PartnershipWorkspace {
    return PartnershipWorkspace::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Capital History Workspace',
        'business_name' =>
            'Capital History Business',
        'business_stage' => 'new',
        'currency_code' => 'THB',
        'status' => 'active',
    ]);
}

function capitalHistoryMembership(
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

function capitalHistoryRevision(
    User $publisher,
    PartnershipWorkspace $workspace,
    int $revision,
    string $state,
    array $payload
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
            'payload' => $payload,
            'approval_decision' => [
                'decision' => 'approved',
                'internal' =>
                    'must-not-be-projected',
            ],
            'published_by_user_id' =>
                $publisher->id,
            'published_at' =>
                now()->subDays(
                    max(0, 3 - $revision)
                ),
            'superseded_at' =>
                $state === 'superseded'
                    ? now()->subDay()
                    : null,
        ]);
}

test(
    'capital history exposes the exact read only public contract',
    function () {
        $reflection = new ReflectionClass(
            PbrV2CapitalHistoryService::class
        );

        expect($reflection->isFinal())
            ->toBeTrue();

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
                === PbrV2CapitalHistoryService::class
            ) {
                $public[] = $method->getName();
            }
        }

        sort($public);

        expect($public)->toBe([
            '__construct',
            'history',
        ]);

        $constructor =
            $reflection->getConstructor();

        expect($constructor)
            ->not->toBeNull()
            ->and(
                count(
                    $constructor
                        ->getParameters()
                )
            )
            ->toBe(1);

        $dependency =
            $constructor->getParameters()[0];

        expect($dependency->getName())
            ->toBe('canonical')
            ->and(
                (string) $dependency->getType()
            )
            ->toBe(
                PbrV2CanonicalOutputService::class
            );

        $history =
            $reflection->getMethod('history');

        expect(
            (string) $history->getReturnType()
        )->toBe('array');

        $parameters =
            $history->getParameters();

        expect($parameters)
            ->toHaveCount(2)
            ->and(
                $parameters[0]->getName()
            )
            ->toBe('actor')
            ->and(
                (string) $parameters[0]->getType()
            )
            ->toBe(User::class)
            ->and(
                $parameters[1]->getName()
            )
            ->toBe('workspace')
            ->and(
                (string) $parameters[1]->getType()
            )
            ->toBe(
                PartnershipWorkspace::class
            );

        $source = file_get_contents(
            app_path(
                'Services/PbrTools/'
                .'PbrV2CapitalHistoryService.php'
            )
        );

        expect($source)
            ->toMatch(
                '/\\$this->canonical\\s*->history\\(/'
            )
            ->not->toContain('DB::')
            ->not->toContain(
                'WorkspaceV2CanonicalRevision::query'
            )
            ->not->toContain(
                "'approval_decision' =>"
            )
            ->not->toContain(
                'PbrV2CapitalReadService'
            )
            ->not->toContain(
                'ToolScenarioService'
            )
            ->not->toContain(
                'StartupCapitalCalculator'
            );
    }
);

test(
    'capital history returns exact newest first canonical revision projections',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        $first = capitalHistoryRevision(
            $owner,
            $workspace,
            1,
            'superseded',
            [
                'plan_name' =>
                    'Capital Version One',
                'currency_code' => 'THB',
            ]
        );

        $second = capitalHistoryRevision(
            $owner,
            $workspace,
            2,
            'approved_current',
            [
                'plan_name' =>
                    'Capital Version Two',
                'currency_code' => 'USD',
            ]
        );

        $history = app(
            PbrV2CapitalHistoryService::class
        )->history(
            $owner,
            $workspace
        );

        expect($history)
            ->toHaveCount(2);

        expect(
            array_column(
                $history,
                'revision'
            )
        )->toBe([
            2,
            1,
        ]);

        expect(array_keys($history[0]))
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
                'superseded_at',
            ]);

        expect($history[0])
            ->toMatchArray([
                'id' => (int) $second->id,
                'workspace_id' =>
                    (int) $workspace->id,
                'domain_key' => 'capital',
                'component_key' =>
                    'approved_capital_plan',
                'revision' => 2,
                'state' =>
                    'approved_current',
                'payload' => [
                    'plan_name' =>
                        'Capital Version Two',
                    'currency_code' =>
                        'USD',
                ],
                'published_by_user_id' =>
                    (int) $owner->id,
                'superseded_at' => null,
            ]);

        expect($history[1])
            ->toMatchArray([
                'id' => (int) $first->id,
                'revision' => 1,
                'state' => 'superseded',
                'payload' => [
                    'plan_name' =>
                        'Capital Version One',
                    'currency_code' =>
                        'THB',
                ],
            ]);

        expect($history[0]['published_at'])
            ->toBeString()
            ->and(
                str_ends_with(
                    $history[0][
                        'published_at'
                    ],
                    'Z'
                )
            )
            ->toBeTrue();

        expect($history[1]['superseded_at'])
            ->toBeString()
            ->and(
                str_ends_with(
                    $history[1][
                        'superseded_at'
                    ],
                    'Z'
                )
            )
            ->toBeTrue();

        expect($history[0])
            ->not->toHaveKey(
                'approval_decision'
            )
            ->not->toHaveKey('created_at')
            ->not->toHaveKey('updated_at');
    }
);

test(
    'capital history active admin may read another owners approved revisions',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        $revision =
            capitalHistoryRevision(
                $owner,
                $workspace,
                1,
                'approved_current',
                [
                    'plan_name' =>
                        'Admin Visible Version',
                ]
            );

        $admin = capitalHistoryAdmin();

        $history = app(
            PbrV2CapitalHistoryService::class
        )->history(
            $admin,
            $workspace
        );

        expect($history)
            ->toHaveCount(1)
            ->and($history[0]['id'])
            ->toBe((int) $revision->id);
    }
);

test(
    'accepted partner remains current only and is denied history before canonical revision query',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        capitalHistoryRevision(
            $owner,
            $workspace,
            1,
            'approved_current',
            [
                'plan_name' =>
                    'Partner Must Not Read History',
            ]
        );

        $partner =
            capitalHistoryPartner();

        capitalHistoryMembership(
            $partner,
            $workspace
        );

        $revisionQueries = [];

        DB::listen(
            function ($query) use (
                &$revisionQueries
            ): void {
                if (
                    str_contains(
                        strtolower($query->sql),
                        'workspace_v2_canonical_revisions'
                    )
                ) {
                    $revisionQueries[] =
                        $query->sql;
                }
            }
        );

        expect(
            fn () => app(
                PbrV2CapitalHistoryService::class
            )->history(
                $partner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect($revisionQueries)
            ->toBe([]);
    }
);

test(
    'capital history reloads manager entitlement before canonical history access',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        capitalHistoryRevision(
            $owner,
            $workspace,
            1,
            'approved_current',
            [
                'plan_name' =>
                    'Entitlement Protected',
            ]
        );

        User::query()
            ->whereKey($owner->id)
            ->update([
                'portal_access_expires_at' =>
                    now()->subDay(),
            ]);

        $revisionQueries = [];

        DB::listen(
            function ($query) use (
                &$revisionQueries
            ): void {
                if (
                    str_contains(
                        strtolower($query->sql),
                        'workspace_v2_canonical_revisions'
                    )
                ) {
                    $revisionQueries[] =
                        $query->sql;
                }
            }
        );

        expect(
            fn () => app(
                PbrV2CapitalHistoryService::class
            )->history(
                $owner,
                $workspace
            )
        )->toThrow(
            AuthorizationException::class
        );

        expect($revisionQueries)
            ->toBe([]);
    }
);

test(
    'capital history returns empty array when no approved revision history exists',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        expect(
            app(
                PbrV2CapitalHistoryService::class
            )->history(
                $owner,
                $workspace
            )
        )->toBe([]);
    }
);

test(
    'capital history performs no database writes',
    function () {
        $owner = capitalHistoryOwner();

        $workspace =
            capitalHistoryWorkspace(
                $owner
            );

        capitalHistoryRevision(
            $owner,
            $workspace,
            1,
            'approved_current',
            [
                'plan_name' =>
                    'Read Only Version',
            ]
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
                    $writes[] =
                        $query->sql;
                }
            }
        );

        $history = app(
            PbrV2CapitalHistoryService::class
        )->history(
            $owner,
            $workspace
        );

        expect($history)
            ->toHaveCount(1);

        expect($writes)
            ->toBe([]);
    }
);
