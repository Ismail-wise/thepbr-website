<?php

namespace App\Services\PbrTools;

use DomainException;
use InvalidArgumentException;
use LogicException;

final class PbrV2ApprovalEngineService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $components,
        private readonly PbrV2LifecycleService $lifecycle,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function decisions(): array
    {
        return $this->configArray('decisions');
    }

    /**
     * Return the immutable approval contract for one proposed
     * canonical component.
     *
     * This method authorizes no publication and performs no persistence.
     *
     * @return array<string, mixed>
     */
    public function approvalContract(
        string $domainKey,
        string $componentKey,
        string $proposalState,
    ): array {
        $component = $this->component(
            $domainKey,
            $componentKey
        );

        $this->assertProposalState($proposalState);

        $this->assertCanonicalApprovalContract(
            $componentKey,
            $component
        );

        $transition = $this->lifecycle
            ->transitionDecision(
                $componentKey,
                'proposed',
                'approved_current'
            );

        if (
            ($transition['decision'] ?? null)
                !== 'reserved'
            || ($transition['authority'] ?? null)
                !== 'approval_engine'
            || ($transition['publication_owner'] ?? null)
                !== 'canonical_output_foundation'
        ) {
            throw new LogicException(
                'Frozen F6/F7 lifecycle ownership boundary is invalid.'
            );
        }

        return [
            'domain_key' => $domainKey,
            'component_key' => $componentKey,
            'component_type' =>
                $component['component_type'],
            'canonical_capability' =>
                $component['canonical_capability'],
            'proposal_state' => 'proposed',
            'approval_required' => true,
            'approval_authority' =>
                'approval_engine',
            'publication_owner' =>
                'canonical_output_foundation',
            'publish_authority_contract' =>
                'approval_engine_only',
            'write_mode' =>
                'revisioned_current',
            'stale_source_guard' => true,
            'approved_target_state' =>
                'approved_current',
        ];
    }

    /**
     * Approve a proposal as a decision only.
     *
     * The returned authorization may later be consumed by F7.
     * No canonical revision or current state is created here.
     *
     * @return array<string, mixed>
     */
    public function approve(
        string $domainKey,
        string $componentKey,
        string $proposalState,
        bool $readinessPassed,
        bool $sourcesFresh,
    ): array {
        $contract = $this->approvalContract(
            $domainKey,
            $componentKey,
            $proposalState
        );

        if (! $readinessPassed) {
            throw new DomainException(
                'Proposal is not approval ready.'
            );
        }

        if (! $sourcesFresh) {
            throw new DomainException(
                'Proposal sources are stale.'
            );
        }

        return array_merge(
            $contract,
            [
                'decision' => 'approved',
                'decision_authority' =>
                    'approval_engine',
                'readiness_passed' => true,
                'sources_fresh' => true,
                'publication_authorized' => true,
                'canonical_published' => false,
                'canonical_revision_created' => false,
                'current_state_changed' => false,
            ]
        );
    }

    /**
     * Reject a proposal as a decision only.
     *
     * Rejection never grants publication authority and does not mutate
     * canonical state.
     *
     * @return array<string, mixed>
     */
    public function reject(
        string $domainKey,
        string $componentKey,
        string $proposalState,
        string $reason,
    ): array {
        $contract = $this->approvalContract(
            $domainKey,
            $componentKey,
            $proposalState
        );

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Rejection reason is required.'
            );
        }

        return array_merge(
            $contract,
            [
                'decision' => 'rejected',
                'decision_authority' =>
                    'approval_engine',
                'reason' => $reason,
                'publication_authorized' => false,
                'canonical_published' => false,
                'canonical_revision_created' => false,
                'current_state_changed' => false,
                'proposal_state_after_decision' =>
                    'proposed',
                'revision_path' =>
                    'working_draft',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function component(
        string $domainKey,
        string $componentKey,
    ): array {
        $domainKey = trim($domainKey);
        $componentKey = trim($componentKey);

        if ($domainKey === '') {
            throw new InvalidArgumentException(
                'Domain key is required.'
            );
        }

        if ($componentKey === '') {
            throw new InvalidArgumentException(
                'Component key is required.'
            );
        }

        $component = $this->components
            ->find($componentKey);

        if (! is_array($component)) {
            throw new InvalidArgumentException(
                'Unknown PBR V2 component: '
                .$componentKey
            );
        }

        if (
            ($component['domain_key'] ?? null)
            !== $domainKey
        ) {
            throw new DomainException(
                'PBR V2 component '
                .$componentKey
                .' does not belong to domain '
                .$domainKey
                .'.'
            );
        }

        return $component;
    }

    private function assertProposalState(
        string $proposalState
    ): void {
        if ($proposalState !== 'proposed') {
            throw new DomainException(
                'F6 may decide only a proposed canonical artifact.'
            );
        }
    }

    /**
     * @param array<string, mixed> $component
     */
    private function assertCanonicalApprovalContract(
        string $componentKey,
        array $component,
    ): void {
        if (
            ! $this->lifecycle
                ->canEnterCanonicalLifecycle(
                    $componentKey
                )
        ) {
            throw new DomainException(
                'Component is not eligible for canonical approval.'
            );
        }

        $type =
            $component['component_type']
            ?? null;

        if (
            ! in_array(
                $type,
                ['rule', 'register', 'plan'],
                true
            )
            || ($component['canonical_capability'] ?? null)
                !== $type
        ) {
            throw new LogicException(
                'Canonical component contract is invalid.'
            );
        }

        if (
            ($component['approval_required'] ?? null)
            !== true
        ) {
            throw new LogicException(
                'Canonical component must require explicit approval.'
            );
        }

        if (
            ($component['publish_authority'] ?? null)
            !== 'approval_engine_only'
        ) {
            throw new LogicException(
                'Canonical component approval authority contract is invalid.'
            );
        }

        if (
            ($component['write_mode'] ?? null)
            !== 'revisioned_current'
        ) {
            throw new LogicException(
                'Canonical component write mode must be revisioned_current.'
            );
        }

        if (
            ($component['stale_source_guard'] ?? null)
            !== true
        ) {
            throw new LogicException(
                'Canonical approval requires stale source protection.'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function configArray(string $key): array
    {
        $value = config(
            'pbr_v2_approval.'.$key,
            []
        );

        if (! is_array($value)) {
            throw new LogicException(
                'PBR V2 approval config '
                .$key
                .' must be an array.'
            );
        }

        return $value;
    }
}
