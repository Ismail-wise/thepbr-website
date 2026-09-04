<?php

namespace App\Services\PbrTools;

use DomainException;
use InvalidArgumentException;
use LogicException;

final class PbrV2LifecycleService
{
    public function __construct(
        private readonly PbrV2ComponentRegistryService $components,
    ) {
    }

    /**
     * Canonical lifecycle vocabulary only.
     *
     * @return array<int, string>
     */
    public function states(): array
    {
        return $this->configArray('canonical_states');
    }

    /**
     * Action-specific lifecycle vocabulary.
     *
     * @return array<int, string>
     */
    public function actionStates(): array
    {
        return $this->configArray('action_states');
    }

    /**
     * Expose frozen lifecycle policy without performing persistence.
     *
     * @return array<string, array>
     */
    public function transitionRules(): array
    {
        return [
            'canonical' =>
                $this->configArray(
                    'canonical_transitions'
                ),

            'actions' =>
                $this->configArray(
                    'action_transitions'
                ),

            'artifacts' =>
                $this->configArray(
                    'artifact_rules'
                ),
        ];
    }

    /**
     * A proposal-capable component may produce or inform a proposal.
     *
     * This does not mean the component itself may become canonical.
     */
    public function canCreateProposal(
        string $componentKey
    ): bool {
        $component =
            $this->component($componentKey);

        return
            $component['can_create_proposal']
            === true;
    }

    /**
     * Only a canonical rule/register/plan may enter the canonical
     * working_draft -> proposed -> approved_current lifecycle.
     */
    public function canEnterCanonicalLifecycle(
        string $componentKey
    ): bool {
        return $this->isCanonicalEligible(
            $this->component($componentKey)
        );
    }

    /**
     * Evaluate a canonical lifecycle transition.
     *
     * The result is policy metadata only. This service never persists,
     * approves, publishes or supersedes authoritative business state.
     *
     * @return array{
     *     decision:string,
     *     authority:string,
     *     publication_owner:?string,
     *     reason:string
     * }
     */
    public function transitionDecision(
        string $componentKey,
        string $from,
        string $to
    ): array {
        $component =
            $this->component($componentKey);

        /*
         * Non-canonical components may produce or inform proposals when their
         * component contract permits it, but their own artifact must never
         * enter the canonical state machine.
         */
        if (! $this->isCanonicalEligible($component)) {
            return $this->forbiddenDecision(
                'component_not_canonical_lifecycle_eligible'
            );
        }

        $this->assertCanonicalState($from);
        $this->assertCanonicalState($to);

        $rule =
            $this->canonicalTransitionRule(
                $from,
                $to
            );

        /*
         * Submission from working_draft to proposed is additionally guarded
         * by the component contract established in F4.
         */
        if (
            $from === 'working_draft'
            && $to === 'proposed'
        ) {
            if (
                $component['can_create_proposal']
                !== true
            ) {
                return $this->forbiddenDecision(
                    'component_cannot_create_proposal'
                );
            }

            if (
                $component['stale_source_guard']
                !== true
            ) {
                return $this->forbiddenDecision(
                    'stale_source_guard_required'
                );
            }
        }

        return $rule;
    }

    /**
     * True means F5 policy marks the transition as directly allowed.
     *
     * Reserved transitions return false because another foundation authority
     * must perform them.
     */
    public function canTransition(
        string $componentKey,
        string $from,
        string $to
    ): bool {
        return $this->transitionDecision(
            $componentKey,
            $from,
            $to
        )['decision'] === 'allowed';
    }

    /**
     * Guard callers against forbidden or reserved direct transitions.
     *
     * @return array{
     *     decision:string,
     *     authority:string,
     *     publication_owner:?string,
     *     reason:string
     * }
     */
    public function assertTransitionAllowed(
        string $componentKey,
        string $from,
        string $to
    ): array {
        $decision =
            $this->transitionDecision(
                $componentKey,
                $from,
                $to
            );

        if ($decision['decision'] === 'allowed') {
            return $decision;
        }

        throw new DomainException(
            sprintf(
                'V2 lifecycle transition %s -> %s is %s (%s).',
                $from,
                $to,
                $decision['decision'],
                $decision['reason'],
            )
        );
    }

    /**
     * Return authority metadata for a canonical transition shape.
     *
     * This method does not grant that authority.
     */
    public function transitionAuthority(
        string $from,
        string $to
    ): string {
        $this->assertCanonicalState($from);
        $this->assertCanonicalState($to);

        return $this->canonicalTransitionRule(
            $from,
            $to
        )['authority'];
    }

    /**
     * @param array<string, mixed> $component
     */
    private function isCanonicalEligible(
        array $component
    ): bool {
        $type =
            $component['component_type']
            ?? null;

        $capability =
            $component['canonical_capability']
            ?? null;

        return
            in_array(
                $type,
                [
                    'rule',
                    'register',
                    'plan',
                ],
                true
            )
            && $capability === $type;
    }

    /**
     * @return array<string, mixed>
     */
    private function component(
        string $componentKey
    ): array {
        $componentKey =
            trim($componentKey);

        if ($componentKey === '') {
            throw new InvalidArgumentException(
                'V2 component key must not be empty.'
            );
        }

        $component =
            $this->components->component(
                $componentKey
            );

        if (! is_array($component)) {
            throw new InvalidArgumentException(
                "Unknown V2 component [{$componentKey}]."
            );
        }

        return $component;
    }

    private function assertCanonicalState(
        string $state
    ): void {
        if (
            ! in_array(
                $state,
                $this->states(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                "Unknown V2 canonical lifecycle state [{$state}]."
            );
        }
    }

    /**
     * @return array{
     *     decision:string,
     *     authority:string,
     *     publication_owner:?string,
     *     reason:string
     * }
     */
    private function canonicalTransitionRule(
        string $from,
        string $to
    ): array {
        $rules =
            $this->configArray(
                'canonical_transitions'
            );

        $rule =
            $rules[$from][$to]
            ?? null;

        if (! is_array($rule)) {
            throw new LogicException(
                "Missing V2 lifecycle rule [{$from} -> {$to}]."
            );
        }

        return $rule;
    }

    /**
     * @return array{
     *     decision:string,
     *     authority:string,
     *     publication_owner:?string,
     *     reason:string
     * }
     */
    private function forbiddenDecision(
        string $reason
    ): array {
        return [
            'decision' => 'forbidden',
            'authority' => 'none',
            'publication_owner' => null,
            'reason' => $reason,
        ];
    }

    /**
     * @return array
     */
    private function configArray(
        string $key
    ): array {
        $value =
            config(
                "pbr_v2_lifecycle.{$key}"
            );

        if (! is_array($value)) {
            throw new LogicException(
                "Missing V2 lifecycle configuration [{$key}]."
            );
        }

        return $value;
    }
}
