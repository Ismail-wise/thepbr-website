<?php

namespace App\Services\PbrTools\Domains;

class ContinuityDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'key_person_dependencies' =>
                $tools['key_person_dependency_map']['data']
                ?? null,

            'succession' =>
                $tools['succession_planner']['data']
                ?? null,

            'emergency_authority' =>
                $tools['emergency_authority_planner']['data']
                ?? null,

            'ownership_transition' =>
                $tools['ownership_transition_simulator']['data']
                ?? null,

            'continuity_checklist' =>
                $tools['continuity_checklist']['data']
                ?? null,

            'insurance_gap' =>
                $tools['insurance_coverage_gap_calculator']['data']
                ?? null,
        ];
    }
}
