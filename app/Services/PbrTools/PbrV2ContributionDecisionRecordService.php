<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2OperatingRecord;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PbrV2ContributionDecisionRecordService
{
    public function __construct(
        private readonly PbrV2ContributionReadService $read,
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ApplicationService $application,
    ) {
    }

    public function records(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        return WorkspaceV2OperatingRecord::query()
            ->with([
                'canonicalSources' => static fn ($query) =>
                    $query->orderBy('id'),
            ])
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'domain_key',
                'contribution'
            )
            ->where(
                'component_key',
                'contribution_decision_record'
            )
            ->orderByDesc('id')
            ->get()
            ->map(
                fn (
                    WorkspaceV2OperatingRecord $record
                ): array => $this->projection($record)
            )
            ->all();
    }

    public function recordCurrent(
        User $actor,
        PartnershipWorkspace $workspace,
        array $attributes
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $actor = $context['actor'];
        $workspace = $context['workspace'];

        $current = $this->read->current(
            $actor,
            $workspace
        );

        if ($current === null) {
            $this->validationError(
                'accepted_contribution_register',
                'No approved current Contribution Register exists to record.'
            );
        }

        $canonical =
            $current['canonical_revision'] ?? null;

        if (
            ! is_array($canonical)
            || ($canonical['domain_key'] ?? null)
                !== 'contribution'
            || ($canonical['component_key'] ?? null)
                !== 'accepted_contribution_register'
            || ($canonical['state'] ?? null)
                !== 'approved_current'
        ) {
            $this->validationError(
                'accepted_contribution_register',
                'Current Contribution Register identity is invalid.'
            );
        }

        $revisionId = (int) (
            $canonical['id'] ?? 0
        );

        $revisionNumber = (int) (
            $canonical['revision'] ?? 0
        );

        if ($revisionId <= 0 || $revisionNumber <= 0) {
            $this->validationError(
                'accepted_contribution_register',
                'Current Contribution Register revision identity is invalid.'
            );
        }

        if (
            WorkspaceV2OperatingRecord::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'domain_key',
                    'contribution'
                )
                ->where(
                    'component_key',
                    'contribution_decision_record'
                )
                ->whereHas(
                    'canonicalSources',
                    static fn ($query) =>
                        $query->where(
                            'canonical_revision_id',
                            $revisionId
                        )
                )
                ->exists()
        ) {
            $this->validationError(
                'decision_record',
                'This approved Contribution Register revision already has a Decision Record.'
            );
        }

        $decision = $this->normalizeDecision(
            $attributes
        );

        $approvedBy = $canonical[
            'published_by_user_id'
        ] ?? null;

        $approvedAt = $canonical[
            'published_at'
        ] ?? null;

        if (
            ! is_int($approvedBy)
            || $approvedBy <= 0
        ) {
            $this->validationError(
                'approved_by',
                'Approved Contribution Register publisher identity is missing.'
            );
        }

        if (
            ! is_string($approvedAt)
            || trim($approvedAt) === ''
        ) {
            $this->validationError(
                'approved_at',
                'Approved Contribution Register publication time is missing.'
            );
        }

        $record = $this->application
            ->appendRecord(
                $actor,
                $workspace,
                'contribution',
                'contribution_decision_record',
                [$revisionId],
                [
                    'schema_version' =>
                        'contribution-decision-record-v1',
                    'owner_reference' =>
                        $decision['owner_reference'],
                    'status' =>
                        $decision['status'],
                    'effective_date' =>
                        $decision['effective_date'],
                    'review_date' =>
                        $decision['review_date'],
                    'decision_summary' =>
                        $decision['decision_summary'],
                    'evidence_references' =>
                        $decision['evidence_references'],
                    'approved_by' =>
                        $approvedBy,
                    'approved_at' =>
                        $approvedAt,
                    'source_register_revision' =>
                        $revisionNumber,
                ],
                [
                    'title' =>
                        'Contribution Decision Record - Revision '
                        .$revisionNumber,
                    'record_date' =>
                        $decision['effective_date'],
                    'effective_at' =>
                        CarbonImmutable::createFromFormat(
                            '!Y-m-d',
                            $decision['effective_date'],
                            'UTC'
                        ),
                ]
            );

        return $this->projection($record);
    }

    private function normalizeDecision(
        array $attributes
    ): array {
        $allowed = [
            'owner_reference',
            'status',
            'effective_date',
            'review_date',
            'decision_summary',
            'evidence_references',
        ];

        foreach (array_keys($attributes) as $key) {
            if (
                ! is_string($key)
                || ! in_array(
                    $key,
                    $allowed,
                    true
                )
            ) {
                $this->validationError(
                    'decision_record',
                    'Contribution Decision Record contains unsupported fields.'
                );
            }
        }

        $owner = $this->requiredText(
            $attributes,
            'owner_reference',
            160
        );

        $status = $this->requiredText(
            $attributes,
            'status',
            80
        );

        $effectiveDate = $this->requiredDate(
            $attributes,
            'effective_date'
        );

        $reviewDate = $this->requiredDate(
            $attributes,
            'review_date'
        );

        if ($reviewDate < $effectiveDate) {
            $this->validationError(
                'review_date',
                'Contribution Decision Record review date cannot be before effective date.'
            );
        }

        $summary = $this->requiredText(
            $attributes,
            'decision_summary',
            5000
        );

        $evidence =
            $attributes['evidence_references']
            ?? null;

        if (
            ! is_array($evidence)
            || ! array_is_list($evidence)
            || $evidence === []
        ) {
            $this->validationError(
                'evidence_references',
                'Contribution Decision Record requires evidence references.'
            );
        }

        $normalizedEvidence = [];

        foreach ($evidence as $reference) {
            if (! is_string($reference)) {
                $this->validationError(
                    'evidence_references',
                    'Contribution Decision Record evidence references must be text.'
                );
            }

            $reference = trim($reference);

            if (
                $reference === ''
                || strlen($reference) > 500
            ) {
                $this->validationError(
                    'evidence_references',
                    'Contribution Decision Record evidence reference is invalid.'
                );
            }

            if (in_array(
                $reference,
                $normalizedEvidence,
                true
            )) {
                $this->validationError(
                    'evidence_references',
                    'Contribution Decision Record evidence references must be unique.'
                );
            }

            $normalizedEvidence[] = $reference;
        }

        return [
            'owner_reference' => $owner,
            'status' => $status,
            'effective_date' => $effectiveDate,
            'review_date' => $reviewDate,
            'decision_summary' => $summary,
            'evidence_references' =>
                $normalizedEvidence,
        ];
    }

    private function requiredText(
        array $attributes,
        string $key,
        int $maxLength
    ): string {
        $value = $attributes[$key] ?? null;

        if (! is_string($value)) {
            $this->validationError(
                $key,
                'Contribution Decision Record field is required.'
            );
        }

        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > $maxLength
        ) {
            $this->validationError(
                $key,
                'Contribution Decision Record field is invalid.'
            );
        }

        return $value;
    }

    private function requiredDate(
        array $attributes,
        string $key
    ): string {
        $value = $attributes[$key] ?? null;

        if (
            ! is_string($value)
            || ! $this->validDate($value)
        ) {
            $this->validationError(
                $key,
                'Contribution Decision Record date must use a valid YYYY-MM-DD value.'
            );
        }

        return $value;
    }

    private function validDate(
        string $value
    ): bool {
        if (preg_match(
            '/\A\d{4}-\d{2}-\d{2}\z/D',
            $value
        ) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map(
            'intval',
            explode('-', $value)
        );

        return checkdate(
            $month,
            $day,
            $year
        );
    }

    private function projection(
        WorkspaceV2OperatingRecord $record
    ): array {
        $sources = $record->relationLoaded(
            'canonicalSources'
        )
            ? $record->canonicalSources
            : $record
                ->canonicalSources()
                ->orderBy('id')
                ->get();

        if ($sources->count() !== 1) {
            throw new LogicException(
                'Contribution Decision Record must pin exactly one canonical source.'
            );
        }

        $source = $sources->first();

        if (
            $source->source_domain_key
                !== 'contribution'
            || $source->source_component_key
                !== 'accepted_contribution_register'
            || (int) $source
                ->canonical_revision_id <= 0
        ) {
            throw new LogicException(
                'Contribution Decision Record source identity is invalid.'
            );
        }

        return [
            'id' => (int) $record->id,
            'workspace_id' =>
                (int) $record->workspace_id,
            'state' => $record->state,
            'title' => $record->title,
            'record_date' =>
                $record->record_date
                    ?->format('Y-m-d'),
            'effective_at' =>
                $this->isoTimestamp(
                    $record->effective_at
                ),
            'payload' => $record->payload,
            'recorded_by_user_id' =>
                $record->recorded_by_user_id
                    === null
                    ? null
                    : (int) $record
                        ->recorded_by_user_id,
            'recorded_at' =>
                $this->isoTimestamp(
                    $record->recorded_at
                ),
            'source' => [
                'canonical_revision_id' =>
                    (int) $source
                        ->canonical_revision_id,
                'domain_key' =>
                    $source->source_domain_key,
                'component_key' =>
                    $source
                        ->source_component_key,
                'revision' =>
                    (int) $source
                        ->source_revision,
            ],
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

    private function validationError(
        string $key,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $key => $message,
        ]);
    }
}
