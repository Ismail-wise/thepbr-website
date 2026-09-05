<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use Illuminate\Auth\Access\AuthorizationException;

final class PbrV2CapitalHistoryService
{
    public function __construct(
        private readonly PbrV2CanonicalOutputService $canonical
    ) {
    }

    public function history(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        [, $workspace] = $this->managementContext(
            $actor,
            $workspace
        );

        return $this->canonical
            ->history(
                $workspace,
                'capital',
                'approved_capital_plan'
            )
            ->map(
                fn (
                    WorkspaceV2CanonicalRevision $revision
                ): array => $this->projection(
                    $revision
                )
            )
            ->values()
            ->all();
    }

    private function managementContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $actor = User::query()->findOrFail(
            $actor->getKey()
        );

        $workspace = PartnershipWorkspace::query()
            ->findOrFail(
                $workspace->getKey()
            );

        $manager = $actor->isAdmin()
            || (
                (int) $workspace->owner_user_id
                    === (int) $actor->id
                && $actor->isStudent()
            );

        if (! $manager) {
            throw new AuthorizationException(
                'V2 capital history read is not permitted.'
            );
        }

        return [
            $actor,
            $workspace,
        ];
    }

    private function projection(
        WorkspaceV2CanonicalRevision $revision
    ): array {
        return [
            'id' => (int) $revision->id,
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
                $revision->published_by_user_id === null
                    ? null
                    : (int) $revision
                        ->published_by_user_id,
            'published_at' =>
                $this->isoTimestamp(
                    $revision->published_at
                ),
            'superseded_at' =>
                $this->isoTimestamp(
                    $revision->superseded_at
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
}
