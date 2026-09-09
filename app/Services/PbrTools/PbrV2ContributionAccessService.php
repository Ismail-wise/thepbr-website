<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PbrV2ContributionAccessService
{
    public function managementContext(User $actor, PartnershipWorkspace $workspace): array
    {
        [$actor, $workspace] = $this->freshContext($actor, $workspace);

        if (! $this->isManager($actor, $workspace)) {
            throw new AuthorizationException('Contribution management is not permitted.');
        }

        return ['actor' => $actor, 'workspace' => $workspace];
    }

    public function currentContext(User $actor, PartnershipWorkspace $workspace): array
    {
        [$actor, $workspace] = $this->freshContext($actor, $workspace);
        $manager = $this->isManager($actor, $workspace);

        if (! $manager && ! $this->isAcceptedPartner($actor, $workspace)) {
            throw new AuthorizationException('Contribution access is not permitted.');
        }

        return [
            'actor' => $actor,
            'workspace' => $workspace,
            'can_manage' => $manager,
        ];
    }

    /**
     * The caller must supply approver IDs from the persisted, locked subject.
     * This method grants neither publication nor general draft access.
     */
    public function approvalContext(
        User $actor,
        PartnershipWorkspace $workspace,
        array $requiredApproverIds,
    ): array {
        [$actor, $workspace] = $this->freshContext($actor, $workspace);

        $validIds = array_is_list($requiredApproverIds) && $requiredApproverIds !== [];
        $seen = [];
        foreach ($requiredApproverIds as $id) {
            if (! is_int($id) || $id < 1 || isset($seen[$id])) {
                $validIds = false;
                break;
            }
            $seen[$id] = true;
        }

        if (! $validIds
            || ! in_array((int) $actor->getKey(), $requiredApproverIds, true)
            || (! $this->isManager($actor, $workspace)
                && ! $this->isAcceptedPartner($actor, $workspace))) {
            throw new AuthorizationException('Contribution approval is not permitted.');
        }

        return ['actor' => $actor, 'workspace' => $workspace];
    }

    private function freshContext(User $actor, PartnershipWorkspace $workspace): array
    {
        if (! $actor->exists || (int) $actor->getKey() < 1) {
            throw (new ModelNotFoundException)->setModel(User::class);
        }

        if (! $workspace->exists || (int) $workspace->getKey() < 1) {
            throw (new ModelNotFoundException)->setModel(PartnershipWorkspace::class);
        }

        return [
            User::query()->findOrFail($actor->getKey()),
            PartnershipWorkspace::query()->findOrFail($workspace->getKey()),
        ];
    }

    private function isManager(User $actor, PartnershipWorkspace $workspace): bool
    {
        return $actor->isAdmin()
            || ((int) $workspace->owner_user_id === (int) $actor->getKey()
                && $actor->isStudent());
    }

    private function isAcceptedPartner(User $actor, PartnershipWorkspace $workspace): bool
    {
        return $actor->hasActiveAccount()
            && $actor->workspaceMemberships()
                ->where('workspace_id', $workspace->getKey())
                ->where('member_role', 'partner')
                ->where('invitation_status', 'accepted')
                ->exists();
    }
}
