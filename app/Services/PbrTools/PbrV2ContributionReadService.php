<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalRevision;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use Illuminate\Validation\ValidationException;

final class PbrV2ContributionReadService
{
    private const TYPES = [
        'cash',
        'time_skill',
        'property_asset',
        'ip_intangible',
    ];

    public function __construct(
        private readonly PbrV2CanonicalOutputService $canonical,
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ContributionReadinessService $readiness,
    ) {
    }

    /**
     * Published Chapter 2 result.
     *
     * Accepted partners may read approved current output. Drafts and
     * canonical history remain management-only.
     */
    public function current(
        User $actor,
        PartnershipWorkspace $workspace
    ): ?array {
        $context = $this->access->currentContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        $revision = $this->canonical->current(
            $workspace,
            'contribution',
            'accepted_contribution_register'
        );

        if (! $revision) {
            return null;
        }

        return $this->canonicalProjection(
            $workspace,
            $revision
        );
    }

    /**
     * Canonical contribution history is management-only.
     */
    public function history(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        return $this->canonical
            ->history(
                $workspace,
                'contribution',
                'accepted_contribution_register'
            )
            ->map(
                fn (
                    WorkspaceV2CanonicalRevision $revision
                ): array => $this->canonicalProjection(
                    $workspace,
                    $revision
                )
            )
            ->values()
            ->all();
    }

    /**
     * Manager-only active Chapter 2 work queue.
     */
    public function workArtifacts(
        User $actor,
        PartnershipWorkspace $workspace,
        ?int $beforeId = null
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        if ($beforeId !== null && $beforeId < 1) {
            throw ValidationException::withMessages([
                'before_id' =>
                    'Contribution work-artifact cursor must be a positive integer.',
            ]);
        }

        $query = WorkspaceV2CanonicalWorkArtifact::query()
            ->select([
                'id',
                'workspace_id',
                'target_domain_key',
                'target_component_key',
                'producer_domain_key',
                'producer_component_key',
                'state',
                'payload',
                'source_generation',
                'amendment_review_case_id',
                'amendment_target_canonical_revision_id',
                'submitted_at',
            ])
            ->where('workspace_id', $workspace->id)
            ->where(
                'target_domain_key',
                'contribution'
            )
            ->where(
                'target_component_key',
                'accepted_contribution_register'
            )
            ->where(
                'producer_domain_key',
                'contribution'
            )
            ->where(
                'producer_component_key',
                'partner_contributions_workflow'
            )
            ->whereIn(
                'state',
                ['working_draft', 'proposed']
            )
            ->whereNull('consumed_at')
            ->whereNull(
                'published_canonical_revision_id'
            );

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query
            ->orderByDesc('id')
            ->limit(26)
            ->get();

        $hasMore = $rows->count() > 25;

        $items = $rows
            ->take(25)
            ->map(
                fn (
                    WorkspaceV2CanonicalWorkArtifact $artifact
                ): array => $this->workArtifactProjection(
                    $artifact
                )
            )
            ->values()
            ->all();

        $nextBeforeId = null;

        if ($hasMore && $items !== []) {
            $nextBeforeId = (int) $items[
                count($items) - 1
            ]['id'];
        }

        return [
            'items' => $items,
            'next_before_id' => $nextBeforeId,
        ];
    }

    /**
     * Manager-only single active Chapter 2 work artifact.
     */
    public function workArtifact(
        User $actor,
        PartnershipWorkspace $workspace,
        int $artifactId
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        if ($artifactId < 1) {
            $this->unavailableWorkArtifact();
        }

        $artifact = WorkspaceV2CanonicalWorkArtifact::query()
            ->select([
                'id',
                'workspace_id',
                'target_domain_key',
                'target_component_key',
                'producer_domain_key',
                'producer_component_key',
                'state',
                'payload',
                'source_generation',
                'amendment_review_case_id',
                'amendment_target_canonical_revision_id',
                'submitted_at',
            ])
            ->whereKey($artifactId)
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'target_domain_key',
                'contribution'
            )
            ->where(
                'target_component_key',
                'accepted_contribution_register'
            )
            ->where(
                'producer_domain_key',
                'contribution'
            )
            ->where(
                'producer_component_key',
                'partner_contributions_workflow'
            )
            ->whereIn(
                'state',
                ['working_draft', 'proposed']
            )
            ->whereNull('consumed_at')
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

    private function canonicalProjection(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalRevision $revision
    ): array {
        if ((int) $revision->workspace_id
                !== (int) $workspace->id
            || $revision->domain_key !== 'contribution'
            || $revision->component_key
                !== 'accepted_contribution_register'
            || ! in_array(
                $revision->state,
                ['approved_current', 'superseded'],
                true
            )) {
            $this->unavailableCanonicalRevision();
        }

        $artifact = $this->publicationArtifact(
            $workspace,
            $revision
        );

        $payload = $revision->payload;

        if (! is_array($payload)
            || $artifact->payload !== $payload) {
            $this->unavailableCanonicalRevision();
        }

        $assessment = $this->readiness
            ->assessPublication(
                (int) $workspace->id,
                (int) $artifact->id,
                $payload
            );

        if (! $assessment['passed']
            || ! is_array(
                $assessment['calculations']
            )) {
            $this->unavailableCanonicalRevision();
        }

        $derived = $this->derivePublishedResult(
            $workspace,
            $revision,
            $payload,
            $assessment['calculations']
        );

        return [
            'canonical_revision' => [
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
                'published_by_user_id' =>
                    $revision->published_by_user_id
                        === null
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
                'source_work_artifact_id' =>
                    (int) $artifact->id,
            ],
            'payload' => $payload,
            'currency_code' =>
                $derived['currency_code'],
            'contribution_matrix' =>
                $derived['contribution_matrix'],
            'contribution_register' =>
                $derived['contribution_register'],
            'accepted_item_count' =>
                $derived['accepted_item_count'],
            'excluded_item_count' =>
                $derived['excluded_item_count'],
            'total_accepted_contribution_value' =>
                $derived[
                    'total_accepted_contribution_value'
                ],
            'equity_handoff' =>
                $derived['equity_handoff'],
        ];
    }

    private function publicationArtifact(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalRevision $revision
    ): WorkspaceV2CanonicalWorkArtifact {
        $rows = WorkspaceV2CanonicalWorkArtifact::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'target_domain_key',
                'contribution'
            )
            ->where(
                'target_component_key',
                'accepted_contribution_register'
            )
            ->where(
                'producer_domain_key',
                'contribution'
            )
            ->where(
                'producer_component_key',
                'partner_contributions_workflow'
            )
            ->where(
                'state',
                'proposed'
            )
            ->where(
                'published_canonical_revision_id',
                $revision->id
            )
            ->whereNotNull('consumed_at')
            ->get();

        if ($rows->count() !== 1) {
            $this->unavailableCanonicalRevision();
        }

        return $rows->first();
    }

    private function derivePublishedResult(
        PartnershipWorkspace $workspace,
        WorkspaceV2CanonicalRevision $revision,
        array $payload,
        array $readinessCalculations
    ): array {
        $currency = $payload['setup'][
            'currency_code'
        ] ?? null;

        if (! is_string($currency)
            || $currency === '') {
            $this->unavailableCanonicalRevision();
        }

        $partners = $payload['partners'] ?? null;
        $items = $payload['items'] ?? null;

        if (! is_array($partners)
            || ! array_is_list($partners)
            || ! is_array($items)
            || ! array_is_list($items)) {
            $this->unavailableCanonicalRevision();
        }

        $partnerOrder = [];
        $partnerRows = [];

        foreach ($partners as $snapshot) {
            if (! is_array($snapshot)) {
                $this->unavailableCanonicalRevision();
            }

            $key = $snapshot['partner_key'] ?? null;

            if (! is_string($key)
                || $key === ''
                || isset($partnerRows[$key])) {
                $this->unavailableCanonicalRevision();
            }

            $partnerOrder[] = $key;

            $partnerRows[$key] = [
                'partner_snapshot' => $snapshot,
                'units' => array_fill_keys(
                    self::TYPES,
                    0
                ),
                'accepted_total_units' => 0,
                'accepted_item_count' => 0,
            ];
        }

        $typeUnits = array_fill_keys(
            self::TYPES,
            0
        );

        $acceptedUnits = 0;
        $acceptedCount = 0;
        $excludedCount = 0;
        $register = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $this->unavailableCanonicalRevision();
            }

            $partnerKey =
                $item['partner_key'] ?? null;

            if (! is_string($partnerKey)
                || ! isset(
                    $partnerRows[$partnerKey]
                )) {
                $this->unavailableCanonicalRevision();
            }

            $type =
                $item['contribution_type'] ?? null;

            $status = $item['status'] ?? null;

            $register[] = [
                'item_id' =>
                    $item['id'] ?? null,
                'partner_key' =>
                    $partnerKey,
                'partner_snapshot' =>
                    $partnerRows[$partnerKey][
                        'partner_snapshot'
                    ],
                'contribution_type' =>
                    $type,
                'description' =>
                    $item['description'] ?? null,
                'agreed_value' =>
                    $item['agreed_value'] ?? null,
                'accepted_value' =>
                    $item['accepted_value'] ?? null,
                'evidence_references' =>
                    $item['evidence_references']
                        ?? [],
                'delivery_date' =>
                    $item['delivered_date'] ?? null,
                'condition' =>
                    $item['conditions'] ?? null,
                'owner_reference' =>
                    $item['owner_reference'] ?? null,
                'approval_status' =>
                    $status,
            ];

            if ($status !== 'accepted') {
                $excludedCount++;

                continue;
            }

            if (! is_string($type)
                || ! in_array(
                    $type,
                    self::TYPES,
                    true
                )) {
                $this->unavailableCanonicalRevision();
            }

            $value =
                $item['accepted_value'] ?? null;

            if (! is_string($value)) {
                $this->unavailableCanonicalRevision();
            }

            $units = $this->moneyUnits($value);

            $this->addUnits(
                $partnerRows[$partnerKey][
                    'units'
                ][$type],
                $units
            );

            $this->addUnits(
                $partnerRows[$partnerKey][
                    'accepted_total_units'
                ],
                $units
            );

            $this->addUnits(
                $typeUnits[$type],
                $units
            );

            $this->addUnits(
                $acceptedUnits,
                $units
            );

            $partnerRows[$partnerKey][
                'accepted_item_count'
            ]++;

            $acceptedCount++;
        }

        $matrixPartners = [];
        $equityHandoff = [];

        foreach ($partnerOrder as $partnerKey) {
            $source = $partnerRows[$partnerKey];

            $row = [
                'partner_key' => $partnerKey,
                'partner_snapshot' =>
                    $source['partner_snapshot'],
                'cash' => $this->moneyDecimal(
                    $source['units']['cash']
                ),
                'time_skill' =>
                    $this->moneyDecimal(
                        $source['units']['time_skill']
                    ),
                'property_asset' =>
                    $this->moneyDecimal(
                        $source['units'][
                            'property_asset'
                        ]
                    ),
                'ip_intangible' =>
                    $this->moneyDecimal(
                        $source['units'][
                            'ip_intangible'
                        ]
                    ),
                'accepted_total' =>
                    $this->moneyDecimal(
                        $source[
                            'accepted_total_units'
                        ]
                    ),
            ];

            $matrixPartners[] = $row;

            if ($source[
                'accepted_item_count'
            ] < 1) {
                continue;
            }

            $equityHandoff[] = [
                'workspace_id' =>
                    (int) $workspace->id,
                'canonical_revision_id' =>
                    (int) $revision->id,
                'revision' =>
                    (int) $revision->revision,
                'currency_code' =>
                    $currency,
                'partner_key' =>
                    $partnerKey,
                'partner_snapshot' =>
                    $source['partner_snapshot'],
                'accepted_contribution_value' =>
                    $row['accepted_total'],
                'contribution_status' =>
                    'accepted',
            ];
        }

        $total = $this->moneyDecimal(
            $acceptedUnits
        );

        if (($readinessCalculations[
                'accepted_item_count'
            ] ?? null) !== $acceptedCount
            || ($readinessCalculations[
                'excluded_item_count'
            ] ?? null) !== $excludedCount
            || ($readinessCalculations[
                'total_accepted_contribution_value'
            ] ?? null) !== $total) {
            $this->unavailableCanonicalRevision();
        }

        return [
            'currency_code' => $currency,
            'contribution_matrix' => [
                'partners' => $matrixPartners,
                'totals' => [
                    'cash' =>
                        $this->moneyDecimal(
                            $typeUnits['cash']
                        ),
                    'time_skill' =>
                        $this->moneyDecimal(
                            $typeUnits['time_skill']
                        ),
                    'property_asset' =>
                        $this->moneyDecimal(
                            $typeUnits[
                                'property_asset'
                            ]
                        ),
                    'ip_intangible' =>
                        $this->moneyDecimal(
                            $typeUnits[
                                'ip_intangible'
                            ]
                        ),
                    'accepted_total' =>
                        $total,
                ],
            ],
            'contribution_register' =>
                $register,
            'accepted_item_count' =>
                $acceptedCount,
            'excluded_item_count' =>
                $excludedCount,
            'total_accepted_contribution_value' =>
                $total,
            'equity_handoff' =>
                $equityHandoff,
        ];
    }

    private function moneyUnits(
        string $value
    ): int {
        if (preg_match(
            '/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/D',
            $value
        ) !== 1) {
            $this->unavailableCanonicalRevision();
        }

        [$whole, $fraction] = explode(
            '.',
            $value
        );

        return ((int) $whole * 100)
            + (int) $fraction;
    }

    private function addUnits(
        int &$target,
        int $units
    ): void {
        if ($units < 0
            || $units > PHP_INT_MAX - $target) {
            $this->unavailableCanonicalRevision();
        }

        $target += $units;
    }

    private function moneyDecimal(
        int $units
    ): string {
        return intdiv($units, 100)
            .'.'
            .str_pad(
                (string) ($units % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private function workArtifactProjection(
        WorkspaceV2CanonicalWorkArtifact $artifact
    ): array {
        return [
            'id' => (int) $artifact->id,
            'workspace_id' =>
                (int) $artifact->workspace_id,
            'target_domain_key' =>
                $artifact->target_domain_key,
            'target_component_key' =>
                $artifact->target_component_key,
            'producer_domain_key' =>
                $artifact->producer_domain_key,
            'producer_component_key' =>
                $artifact->producer_component_key,
            'state' => $artifact->state,
            'payload' => $artifact->payload,
            'source_generation' =>
                (int) $artifact->source_generation,
            'amendment_review_case_id' =>
                $artifact->amendment_review_case_id
                    === null
                    ? null
                    : (int) $artifact
                        ->amendment_review_case_id,
            'amendment_target_canonical_revision_id' =>
                $artifact
                    ->amendment_target_canonical_revision_id
                    === null
                    ? null
                    : (int) $artifact
                        ->amendment_target_canonical_revision_id,
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

    private function unavailableCanonicalRevision(): never
    {
        throw ValidationException::withMessages([
            'canonical_revision' =>
                'Contribution canonical revision is unavailable or inconsistent.',
        ]);
    }

    private function unavailableWorkArtifact(): never
    {
        throw ValidationException::withMessages([
            'work_artifact' =>
                'Contribution work artifact is unavailable.',
        ]);
    }
}
