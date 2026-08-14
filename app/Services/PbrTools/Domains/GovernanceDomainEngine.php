<?php

namespace App\Services\PbrTools\Domains;

class GovernanceDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'partner_roles' =>
                $tools['partner_role_matrix']['data']
                ?? null,

            'decision_rights' =>
                $tools['decision_rights_matrix']['data']
                ?? null,

            'authority_levels' =>
                $tools['authority_level_builder']['data']
                ?? null,

            'voting' =>
                $tools['voting_simulator']['data']
                ?? null,

            'deadlock_rule' =>
                $tools['deadlock_detector']['data']
                ?? null,

            'structure' =>
                $tools['governance_structure_chart']['data']
                ?? null,
        ];
    }
}
