<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class PbrV2CapitalCallRuleReadService
{
    public function __construct(
        private readonly PbrV2CanonicalOutputService $canonical
    ) {
    }

    public function current(
        User $actor,
        PartnershipWorkspace $workspace
    ): ?array {
        [, $workspace] =
            $this->readContext(
                $actor,
                $workspace
            );

        $revision =
            $this->canonical->current(
                $workspace,
                'capital',
                'capital_call_rule'
            );

        if (! $revision) {
            return null;
        }

        return [
            'id' =>
                (int) $revision->id,
            'workspace_id' =>
                (int) $revision->workspace_id,
            'domain_key' =>
                $revision->domain_key,
            'component_key' =>
                $revision->component_key,
            'revision' =>
                (int) $revision->revision,
            'state' =>
                $revision->state,
            'payload' =>
                $revision->payload,
            'published_by_user_id' =>
                $revision
                    ->published_by_user_id
                    === null
                    ? null
                    : (int) $revision
                        ->published_by_user_id,
            'published_at' =>
                $this->isoTimestamp(
                    $revision->published_at
                ),
        ];
    }

    public function workArtifacts(
        User $actor,
        PartnershipWorkspace $workspace,
        ?int $beforeId = null
    ): array {
        [, $workspace] =
            $this->managementContext(
                $actor,
                $workspace
            );

        if (
            $beforeId !== null
            && $beforeId < 1
        ) {
            throw ValidationException::withMessages([
                'before_id' =>
                    'Capital-call rule work-artifact cursor must be a positive integer.',
            ]);
        }

        $query =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->select([
                    'id',
                    'workspace_id',
                    'target_domain_key',
                    'target_component_key',
                    'state',
                    'payload',
                    'source_generation',
                    'submitted_at',
                ])
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
                ->whereIn(
                    'state',
                    [
                        'working_draft',
                        'proposed',
                    ]
                )
                ->whereNull(
                    'consumed_at'
                )
                ->whereNull(
                    'published_canonical_revision_id'
                );

        if ($beforeId !== null) {
            $query->where(
                'id',
                '<',
                $beforeId
            );
        }

        $rows = $query
            ->orderByDesc('id')
            ->limit(26)
            ->get();

        $hasMore =
            $rows->count() > 25;

        $items = $rows
            ->take(25)
            ->map(
                fn (
                    WorkspaceV2CanonicalWorkArtifact $artifact
                ): array =>
                    $this->workArtifactProjection(
                        $artifact
                    )
            )
            ->values()
            ->all();

        $nextBeforeId = null;

        if (
            $hasMore
            && $items !== []
        ) {
            $nextBeforeId =
                (int) $items[
                    count($items) - 1
                ]['id'];
        }

        return [
            'items' => $items,
            'next_before_id' =>
                $nextBeforeId,
        ];
    }

    public function workArtifact(
        User $actor,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): array {
        [, $workspace] =
            $this->managementContext(
                $actor,
                $workspace
            );

        if ($artifactId < 1) {
            $this->unavailableWorkArtifact();
        }

        $artifact =
            WorkspaceV2CanonicalWorkArtifact::query()
                ->select([
                    'id',
                    'workspace_id',
                    'target_domain_key',
                    'target_component_key',
                    'state',
                    'payload',
                    'source_generation',
                    'submitted_at',
                ])
                ->whereKey(
                    $artifactId
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
                ->whereIn(
                    'state',
                    [
                        'working_draft',
                        'proposed',
                    ]
                )
                ->whereNull(
                    'consumed_at'
                )
                ->whereNull(
                    'published_canonical_revision_id'
                )
                ->first();

        if (! $artifact) {
            $this->unavailableWorkArtifact();
        }

        return $this->workArtifactProjection(
            $artifact
        );
    }

    private function readContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $actor = User::query()
            ->findOrFail(
                $actor->getKey()
            );

        $workspace =
            PartnershipWorkspace::query()
                ->findOrFail(
                    $workspace->getKey()
                );

        $manager =
            $actor->isAdmin()
            || (
                (int) $workspace
                    ->owner_user_id
                    === (int) $actor->id
                && $actor->isStudent()
            );

        if ($manager) {
            return [
                $actor,
                $workspace,
                true,
            ];
        }

        $acceptedPartner =
            $actor->hasActiveAccount()
            && $actor
                ->workspaceMemberships()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'member_role',
                    'partner'
                )
                ->where(
                    'invitation_status',
                    'accepted'
                )
                ->exists();

        if (! $acceptedPartner) {
            throw new AuthorizationException(
                'V2 workspace read is not permitted.'
            );
        }

        return [
            $actor,
            $workspace,
            false,
        ];
    }

    private function managementContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        [
            $actor,
            $workspace,
            $manager,
        ] = $this->readContext(
            $actor,
            $workspace
        );

        if (! $manager) {
            throw new AuthorizationException(
                'V2 workspace draft read is not permitted.'
            );
        }

        return [
            $actor,
            $workspace,
        ];
    }

    private function workArtifactProjection(
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): array {
        return [
            'id' =>
                (int) $artifact->id,
            'workspace_id' =>
                (int) $artifact->workspace_id,
            'target_domain_key' =>
                $artifact->target_domain_key,
            'target_component_key' =>
                $artifact->target_component_key,
            'state' =>
                $artifact->state,
            'payload' =>
                $artifact->payload,
            'source_generation' =>
                (int) $artifact
                    ->source_generation,
            'submitted_at' =>
                $this->isoTimestamp(
                    $artifact->submitted_at
                ),
        ];
    }

    private function isoTimestamp(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        return $value
            ->copy()
            ->utc()
            ->toISOString();
    }

    private function unavailableWorkArtifact(): never
    {
        throw ValidationException::withMessages([
            'work_artifact' =>
                'Capital-call rule work artifact is unavailable.',
        ]);
    }
}
