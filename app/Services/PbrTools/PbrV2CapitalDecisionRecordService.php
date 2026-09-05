<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2OperatingRecord;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PbrV2CapitalDecisionRecordService
{
    public function __construct(
        private readonly PbrV2CapitalReadService $read,
        private readonly PbrV2ApplicationService $application
    ) {
    }

    public function records(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        [, $workspace] = $this->managementContext(
            $actor,
            $workspace
        );

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
                'capital'
            )
            ->where(
                'component_key',
                'capital_decision_record'
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
        PartnershipWorkspace $workspace
    ): array {
        [$actor, $workspace] =
            $this->managementContext(
                $actor,
                $workspace
            );

        $current = $this->read->current(
            $actor,
            $workspace
        );

        if ($current === null) {
            $this->validationError(
                'approved_capital_plan',
                'No approved current Capital Plan exists to record.'
            );
        }

        if (
            ($current['domain_key'] ?? null)
                !== 'capital'
            || ($current['component_key'] ?? null)
                !== 'approved_capital_plan'
            || ($current['state'] ?? null)
                !== 'approved_current'
        ) {
            $this->validationError(
                'approved_capital_plan',
                'Current Capital Plan identity is invalid.'
            );
        }

        $revisionId = (int) (
            $current['id'] ?? 0
        );

        if ($revisionId <= 0) {
            $this->validationError(
                'approved_capital_plan',
                'Current Capital Plan revision identity is invalid.'
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
                    'capital'
                )
                ->where(
                    'component_key',
                    'capital_decision_record'
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
                'This approved Capital Plan revision already has a Decision Record.'
            );
        }

        $payload = is_array(
            $current['payload'] ?? null
        )
            ? $current['payload']
            : [];

        $decision = $payload[
            'decision_record'
        ] ?? null;

        $this->assertDecisionEvidence(
            $decision
        );

        $approvedByUserId =
            $current[
                'published_by_user_id'
            ] ?? null;

        $approvedAt =
            $current[
                'published_at'
            ] ?? null;

        if (
            ! is_int($approvedByUserId)
            || $approvedByUserId <= 0
        ) {
            $this->validationError(
                'approved_by',
                'Approved Capital Plan publisher identity is missing.'
            );
        }

        if (
            ! is_string($approvedAt)
            || trim($approvedAt) === ''
        ) {
            $this->validationError(
                'approved_at',
                'Approved Capital Plan publication time is missing.'
            );
        }

        $planName = $payload[
            'plan_name'
        ] ?? null;

        if (
            ! is_string($planName)
            || trim($planName) === ''
        ) {
            $this->validationError(
                'approved_capital_plan',
                'Approved Capital Plan name is missing.'
            );
        }

        $effectiveDate =
            $decision['effective_date'];

        $record = $this->application
            ->appendRecord(
                $actor,
                $workspace,
                'capital',
                'capital_decision_record',
                [$revisionId],
                [
                    'schema_version' =>
                        'capital-decision-record-v1',
                    'owner_reference' =>
                        $decision[
                            'owner_reference'
                        ],
                    'effective_date' =>
                        $effectiveDate,
                    'review_date' =>
                        $decision[
                            'review_date'
                        ],
                    'decision_summary' =>
                        $decision[
                            'decision_summary'
                        ],
                    'evidence_references' =>
                        array_values(
                            $decision[
                                'evidence_references'
                            ]
                        ),
                    'approved_by_user_id' =>
                        $approvedByUserId,
                    'approved_at' =>
                        $approvedAt,
                    'source_plan_revision' =>
                        (int) $current[
                            'revision'
                        ],
                ],
                [
                    'title' =>
                        'Capital Decision Record - '
                        .$planName,
                    'record_date' =>
                        $effectiveDate,
                    'effective_at' =>
                        CarbonImmutable::createFromFormat(
                            '!Y-m-d',
                            $effectiveDate,
                            'UTC'
                        ),
                ]
            );

        return $this->projection($record);
    }

    private function managementContext(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $actor = User::query()
            ->findOrFail(
                $actor->getKey()
            );

        $workspace =
            PartnershipWorkspace::query()
                ->findOrFail(
                    $workspace->getKey()
                );

        if (! (
            $actor->isAdmin()
            || (
                (int) $workspace
                    ->owner_user_id
                    === (int) $actor->id
                && $actor->isStudent()
            )
        )) {
            throw new AuthorizationException(
                'V2 capital Decision Record management is not permitted.'
            );
        }

        return [
            $actor,
            $workspace,
        ];
    }

    private function assertDecisionEvidence(
        mixed $decision
    ): void {
        if (
            ! is_array($decision)
            || array_is_list($decision)
        ) {
            $this->validationError(
                'decision_record',
                'Approved Capital Plan Decision Record evidence is invalid.'
            );
        }

        foreach ([
            'owner_reference',
            'effective_date',
            'review_date',
            'decision_summary',
        ] as $key) {
            if (
                ! isset($decision[$key])
                || ! is_string(
                    $decision[$key]
                )
                || trim(
                    $decision[$key]
                ) === ''
            ) {
                $this->validationError(
                    'decision_record',
                    'Approved Capital Plan Decision Record evidence is incomplete.'
                );
            }
        }

        if (
            ! isset(
                $decision[
                    'evidence_references'
                ]
            )
            || ! is_array(
                $decision[
                    'evidence_references'
                ]
            )
            || $decision[
                'evidence_references'
            ] === []
            || ! array_is_list(
                $decision[
                    'evidence_references'
                ]
            )
        ) {
            $this->validationError(
                'decision_record',
                'Approved Capital Plan Decision Record evidence references are invalid.'
            );
        }

        foreach (
            $decision[
                'evidence_references'
            ] as $reference
        ) {
            if (
                ! is_string($reference)
                || trim($reference) === ''
            ) {
                $this->validationError(
                    'decision_record',
                    'Approved Capital Plan Decision Record evidence references are invalid.'
                );
            }
        }
    }

    private function projection(
        WorkspaceV2OperatingRecord $record
    ): array {
        $sources = $record
            ->relationLoaded(
                'canonicalSources'
            )
                ? $record
                    ->canonicalSources
                : $record
                    ->canonicalSources()
                    ->orderBy('id')
                    ->get();

        if ($sources->count() !== 1) {
            throw new LogicException(
                'Capital Decision Record must pin exactly one canonical source.'
            );
        }

        $source = $sources->first();

        if (
            $source->source_domain_key
                !== 'capital'
            || $source->source_component_key
                !== 'approved_capital_plan'
        ) {
            throw new LogicException(
                'Capital Decision Record source identity is invalid.'
            );
        }

        return [
            'id' => (int) $record->id,
            'workspace_id' =>
                (int) $record
                    ->workspace_id,
            'state' =>
                $record->state,
            'title' =>
                $record->title,
            'record_date' =>
                $record->record_date
                    ? $record
                        ->record_date
                        ->format('Y-m-d')
                    : null,
            'effective_at' =>
                $this->isoTimestamp(
                    $record->effective_at
                ),
            'payload' =>
                $record->payload,
            'recorded_by_user_id' =>
                $record
                    ->recorded_by_user_id
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
                    $source
                        ->source_domain_key,
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
