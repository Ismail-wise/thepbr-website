<?php

namespace App\Services\PbrTools;

use LogicException;

class PbrV2ComponentRegistryService
{
    public function __construct(
        private readonly PbrDomainRegistryService $domainRegistry
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $components = config(
            'pbr_v2_components.components',
            []
        );

        if (! is_array($components)) {
            throw new LogicException(
                'PBR V2 component registry must be an array.'
            );
        }

        $resolved = [];

        foreach ($components as $componentKey => $definition) {
            $componentKey = trim(
                (string) $componentKey
            );

            if ($componentKey === '') {
                throw new LogicException(
                    'Every PBR V2 component must have a stable component key.'
                );
            }

            if (! is_array($definition)) {
                throw new LogicException(
                    'Every PBR V2 component definition must be an array.'
                );
            }

            if (array_key_exists($componentKey, $resolved)) {
                throw new LogicException(
                    'Duplicate PBR V2 component key: '
                    .$componentKey
                );
            }

            $definition = array_merge(
                [
                    'legacy_tool_key' => null,
                ],
                $definition,
                [
                    'component_key' => $componentKey,
                ]
            );

            $this->validateDefinition(
                $componentKey,
                $definition
            );

            $resolved[$componentKey] = $definition;
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $componentKey): bool
    {
        return array_key_exists(
            $componentKey,
            $this->all()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $componentKey): ?array
    {
        return $this->all()[$componentKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function component(string $componentKey): array
    {
        $component = $this->find($componentKey);

        abort_unless(
            is_array($component),
            404
        );

        return $component;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forDomain(string $domainKey): array
    {
        $this->domainRegistry->masterDomain(
            $domainKey
        );

        return array_filter(
            $this->all(),
            fn (array $component): bool =>
                $component['domain_key'] === $domainKey
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function validateDefinition(
        string $componentKey,
        array $definition
    ): void {
        $requiredFields = [
            'domain_key',
            'component_type',
            'operating_purpose',
            'canonical_capability',
            'approval_required',
            'can_create_proposal',
            'publish_authority',
            'write_mode',
            'can_create_actions',
            'can_trigger_review',
            'source_policy',
            'reads_from',
            'cross_domain_write',
            'stale_source_guard',
            'status',
        ];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $definition)) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' is missing required field '
                    .$field
                    .'.'
                );
            }
        }

        $domainKey = trim(
            (string) $definition['domain_key']
        );

        if (
            $domainKey === ''
            || ! $this->domainRegistry->masterHas(
                $domainKey
            )
        ) {
            throw new LogicException(
                'PBR V2 component '
                .$componentKey
                .' references unknown domain '
                .$domainKey
                .'.'
            );
        }

        $purpose = trim(
            (string) $definition['operating_purpose']
        );

        if ($purpose === '') {
            throw new LogicException(
                'PBR V2 component '
                .$componentKey
                .' must declare an operating purpose.'
            );
        }

        $componentType = $this->validatedAllowedValue(
            $componentKey,
            'component_type',
            $definition['component_type'],
            'component_types'
        );

        $canonicalCapability =
            $this->validatedAllowedValue(
                $componentKey,
                'canonical_capability',
                $definition['canonical_capability'],
                'canonical_capabilities'
            );

        $publishAuthority =
            $this->validatedAllowedValue(
                $componentKey,
                'publish_authority',
                $definition['publish_authority'],
                'publish_authorities'
            );

        $writeMode = $this->validatedAllowedValue(
            $componentKey,
            'write_mode',
            $definition['write_mode'],
            'write_modes'
        );

        $this->validatedAllowedValue(
            $componentKey,
            'source_policy',
            $definition['source_policy'],
            'source_policies'
        );

        $this->validatedAllowedValue(
            $componentKey,
            'status',
            $definition['status'],
            'statuses'
        );

        foreach ([
            'approval_required',
            'can_create_proposal',
            'can_create_actions',
            'can_trigger_review',
            'cross_domain_write',
            'stale_source_guard',
        ] as $booleanField) {
            if (! is_bool($definition[$booleanField])) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' field '
                    .$booleanField
                    .' must be boolean.'
                );
            }
        }

        if (! is_array($definition['reads_from'])) {
            throw new LogicException(
                'PBR V2 component '
                .$componentKey
                .' reads_from must be an array.'
            );
        }

        $this->validateDependencies(
            $componentKey,
            $domainKey,
            $definition['reads_from']
        );

        if ($definition['cross_domain_write'] !== false) {
            throw new LogicException(
                'PBR V2 components cannot directly write across domains.'
            );
        }

        $canonicalTypes = [
            'rule',
            'register',
            'plan',
        ];

        $isCanonicalType = in_array(
            $componentType,
            $canonicalTypes,
            true
        );

        if ($isCanonicalType) {
            if ($canonicalCapability !== $componentType) {
                throw new LogicException(
                    'Canonical PBR V2 component '
                    .$componentKey
                    .' must match its canonical capability.'
                );
            }

            if ($definition['approval_required'] !== true) {
                throw new LogicException(
                    'Canonical PBR V2 component '
                    .$componentKey
                    .' requires explicit approval.'
                );
            }

            if (
                $publishAuthority
                !== 'approval_engine_only'
            ) {
                throw new LogicException(
                    'Canonical PBR V2 component '
                    .$componentKey
                    .' may publish only through the approval engine.'
                );
            }

            if ($writeMode !== 'revisioned_current') {
                throw new LogicException(
                    'Canonical PBR V2 component '
                    .$componentKey
                    .' must use revisioned_current write mode.'
                );
            }

            if ($definition['stale_source_guard'] !== true) {
                throw new LogicException(
                    'Canonical PBR V2 component '
                    .$componentKey
                    .' requires stale-source protection.'
                );
            }
        } else {
            if ($canonicalCapability !== 'none') {
                throw new LogicException(
                    'Non-canonical PBR V2 component '
                    .$componentKey
                    .' cannot declare canonical capability.'
                );
            }

            if ($publishAuthority !== 'none') {
                throw new LogicException(
                    'Non-canonical PBR V2 component '
                    .$componentKey
                    .' cannot publish current state.'
                );
            }

            $expectedWriteMode = match (
                $componentType
            ) {
                'record' => 'append_only_record',
                'action' => 'action_state',
                default => 'none',
            };

            if ($writeMode !== $expectedWriteMode) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' has invalid write mode for type '
                    .$componentType
                    .'.'
                );
            }
        }

        if (
            $definition['can_create_proposal'] === true
            && $definition['stale_source_guard'] !== true
        ) {
            throw new LogicException(
                'Proposal-capable PBR V2 component '
                .$componentKey
                .' requires stale-source protection.'
            );
        }

        if (
            $componentType === 'scenario'
            && (
                $definition['can_create_actions'] === true
                || $definition['can_trigger_review'] === true
            )
        ) {
            throw new LogicException(
                'Scenario PBR V2 components cannot create actions or review triggers.'
            );
        }

        if (
            $componentType === 'analysis'
            && $definition['can_create_actions'] === true
        ) {
            throw new LogicException(
                'Analysis PBR V2 components cannot create actions.'
            );
        }

        if (
            in_array(
                $componentType,
                ['monitor', 'action'],
                true
            )
            && $definition['can_create_proposal'] === true
        ) {
            throw new LogicException(
                'Monitor and action PBR V2 components cannot create proposals.'
            );
        }

        $legacyToolKey =
            $definition['legacy_tool_key'];

        if ($legacyToolKey !== null) {
            if (
                ! is_string($legacyToolKey)
                || trim($legacyToolKey) === ''
            ) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' has an invalid legacy tool key.'
                );
            }

            if (
                ! in_array(
                    $legacyToolKey,
                    $this->legacyToolKeys(),
                    true
                )
            ) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' references unknown legacy tool '
                    .$legacyToolKey
                    .'.'
                );
            }
        }
    }

    /**
     * @param mixed $value
     */
    private function validatedAllowedValue(
        string $componentKey,
        string $field,
        mixed $value,
        string $allowedGroup
    ): string {
        if (! is_string($value)) {
            throw new LogicException(
                'PBR V2 component '
                .$componentKey
                .' field '
                .$field
                .' must be a string.'
            );
        }

        $allowed = config(
            'pbr_v2_components.allowed.'
            .$allowedGroup,
            []
        );

        if (
            ! is_array($allowed)
            || ! in_array($value, $allowed, true)
        ) {
            throw new LogicException(
                'PBR V2 component '
                .$componentKey
                .' has invalid '
                .$field
                .' value '
                .$value
                .'.'
            );
        }

        return $value;
    }

    /**
     * @param array<int, mixed> $readsFrom
     */
    private function validateDependencies(
        string $componentKey,
        string $domainKey,
        array $readsFrom
    ): void {
        $allowedDependencies = config(
            'pbr_canonical_data.domains_v2.'
            .$domainKey
            .'.reads_from',
            []
        );

        if (! is_array($allowedDependencies)) {
            throw new LogicException(
                'PBR V2 dependency contract is invalid for '
                .$domainKey
                .'.'
            );
        }

        $seen = [];

        foreach ($readsFrom as $dependency) {
            if (
                ! is_string($dependency)
                || trim($dependency) === ''
            ) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' has an invalid dependency.'
                );
            }

            if (in_array($dependency, $seen, true)) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' declares duplicate dependency '
                    .$dependency
                    .'.'
                );
            }

            $seen[] = $dependency;

            if (
                ! in_array(
                    $dependency,
                    $allowedDependencies,
                    true
                )
            ) {
                throw new LogicException(
                    'PBR V2 component '
                    .$componentKey
                    .' may not read from '
                    .$dependency
                    .'.'
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function legacyToolKeys(): array
    {
        $chapters = config(
            'pbr_course.chapters',
            []
        );

        $keys = [];

        if (! is_array($chapters)) {
            return $keys;
        }

        foreach ($chapters as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            foreach (
                $chapter['tools'] ?? []
                as $tool
            ) {
                if (
                    is_array($tool)
                    && is_string($tool['key'] ?? null)
                ) {
                    $keys[] = $tool['key'];
                }
            }
        }

        return array_values(
            array_unique($keys)
        );
    }
}
