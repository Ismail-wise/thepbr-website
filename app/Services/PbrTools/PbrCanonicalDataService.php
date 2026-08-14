<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspacePartnerProfile;
use Illuminate\Support\Collection;

class PbrCanonicalDataService
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem
    ) {
    }

    /**
     * Internal application read path for current approved domain summaries.
     *
     * Draft snapshots are deliberately excluded. Controllers/services are
     * still responsible for workspace authorization before exposing data.
     */
    public function approvedDomainSummaries(
        PartnershipWorkspace $workspace
    ): array {
        $contracts = config(
            'pbr_canonical_data.domains',
            []
        );

        $summaries = [];

        foreach ($contracts as $domainKey => $contract) {
            $snapshot = $this->operatingSystem->latestSnapshot(
                $workspace,
                $domainKey,
                'agreed'
            );

            if (! $snapshot) {
                continue;
            }

            $summaries[$domainKey] = is_array($snapshot->summary)
                ? $snapshot->summary
                : [];
        }

        return $summaries;
    }

    public function approvedState(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        abort_unless(
            $actor->canAccessWorkspace($workspace),
            403
        );

        $contracts = config(
            'pbr_canonical_data.domains',
            []
        );

        $domains = [];

        foreach ($contracts as $domainKey => $contract) {
            $snapshot = $this->operatingSystem->latestSnapshot(
                $workspace,
                $domainKey,
                'agreed'
            );

            $domains[$domainKey] = [
                'chapter' => $contract['chapter'] ?? null,
                'name' => $contract['name'] ?? $domainKey,
                'reads_from' => $contract['reads_from'] ?? [],
                'status' => $snapshot
                    ? 'agreed'
                    : 'not_configured',
                'revision' => $snapshot?->revision,
                'schema_version' => $snapshot?->schema_version,
                'summary' => is_array($snapshot?->summary)
                    ? $snapshot->summary
                    : [],
                'agreed_at' => $snapshot?->agreed_at?->toIso8601String(),
            ];
        }

        $canManage = $this->operatingSystem->canManage(
            $actor,
            $workspace
        );

        return [
            'schema_version' => config(
                'pbr_canonical_data.schema_version',
                'pbr-canonical-v1'
            ),

            'source_policy' => config(
                'pbr_canonical_data.source_policy',
                []
            ),

            'business' => [
                'workspace_id' => $workspace->id,
                'business_name' => $workspace->business_name
                    ?: $workspace->name,
                'business_stage' => $workspace->business_stage,
                'currency_code' => $workspace->currency_code,
            ],

            'actor' => [
                'can_manage' => $canManage,
            ],

            'partners' => $this->partnerRoster(
                $workspace,
                $canManage
            ),

            'domains' => $domains,
        ];
    }

    public function approvedDomainSummary(
        User $actor,
        PartnershipWorkspace $workspace,
        string $domainKey
    ): array {
        $state = $this->approvedState(
            $actor,
            $workspace
        );

        abort_unless(
            array_key_exists(
                $domainKey,
                $state['domains']
            ),
            404
        );

        return $state['domains'][$domainKey]['summary'];
    }

    public function domainContract(
        string $domainKey
    ): array {
        $contract = config(
            'pbr_canonical_data.domains.'.$domainKey
        );

        abort_unless(
            is_array($contract),
            404
        );

        return $contract;
    }

    private function partnerRoster(
        PartnershipWorkspace $workspace,
        bool $canManage
    ): array {
        $profiles = WorkspacePartnerProfile::query()
            ->where('workspace_id', $workspace->id)
            ->when(
                ! $canManage,
                fn ($query) => $query->where(
                    'status',
                    'active'
                )
            )
            ->whereIn(
                'status',
                $canManage
                    ? ['active', 'planned']
                    : ['active']
            )
            ->orderBy('id')
            ->get();

        return [
            'active' => $this->serializePartners(
                $profiles->where('status', 'active')
            ),

            'planned' => $canManage
                ? $this->serializePartners(
                    $profiles->where('status', 'planned')
                )
                : [],
        ];
    }

    private function serializePartners(
        Collection $profiles
    ): array {
        return $profiles
            ->values()
            ->map(
                fn (WorkspacePartnerProfile $profile): array => [
                    'partner_key' => $profile->partner_key,
                    'display_name' => $profile->display_name,
                    'status' => $profile->status,
                ]
            )
            ->all();
    }
}
