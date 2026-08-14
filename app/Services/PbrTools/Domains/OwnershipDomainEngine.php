<?php

namespace App\Services\PbrTools\Domains;

class OwnershipDomainEngine
{
    public function summarize(array $tools): array
    {
        $cap = $tools['cap_table_builder']['data'] ?? [];
        $chart = $tools['ownership_chart']['data'] ?? [];
        $share = $tools['share_value_calculator']['data'] ?? [];

        $holders = $cap['holders']
            ?? $chart['holders']
            ?? [];

        return [
            'total_units' =>
                $cap['issued_units']
                ?? $chart['total_units']
                ?? $share['total_units']
                ?? null,

            'reserved_units' =>
                $cap['reserved_units'] ?? null,

            'fully_diluted_units' =>
                $cap['fully_diluted_units'] ?? null,

            'holders' => $holders,

            'per_unit_value' =>
                $share['per_unit'] ?? null,

            'one_percent_value' =>
                $share['one_percent_value'] ?? null,

            'latest_dilution' =>
                $tools['future_dilution_simulator']['data']
                ?? null,
        ];
    }
}
