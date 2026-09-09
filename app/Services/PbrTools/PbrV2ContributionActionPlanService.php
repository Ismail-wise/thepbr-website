<?php

namespace App\Services\PbrTools;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2Action;
use App\Models\WorkspaceV2OperatingRecord;
use Illuminate\Validation\ValidationException;

final class PbrV2ContributionActionPlanService
{
    public function __construct(
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ApplicationService $application,
        private readonly PbrV2ExecutionService $execution,
    ) {
    }

    public function actionStates(): array
    {
        return $this->execution->actionStates();
    }

    public function priorities(): array
    {
        return $this->execution->priorities();
    }

    public function actions(
        User $actor,
        PartnershipWorkspace $workspace
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $workspace = $context['workspace'];

        return WorkspaceV2Action::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'action_domain_key',
                'contribution'
            )
            ->where(
                'action_component_key',
                'contribution_action_plan'
            )
            ->with([
                'canonicalSources' =>
                    fn ($query) =>
                        $query->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(
                fn (
                    WorkspaceV2Action $action
                ): array => $this->projection(
                    $action
                )
            )
            ->all();
    }

    public function createFromDecisionRecord(
        User $actor,
        PartnershipWorkspace $workspace,
        int $decisionRecordId,
        array $attributes
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $actor = $context['actor'];
        $workspace = $context['workspace'];

        $record = WorkspaceV2OperatingRecord::query()
            ->whereKey($decisionRecordId)
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
            ->where(
                'state',
                'recorded'
            )
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'decision_record' =>
                    'Contribution Action Plan requires an exact recorded Contribution Decision Record.',
            ]);
        }

        $sources = $record
            ->canonicalSources()
            ->orderBy('id')
            ->get();

        if ($sources->count() !== 1) {
            throw ValidationException::withMessages([
                'decision_record' =>
                    'Contribution Decision Record must pin exactly one approved Contribution Register revision.',
            ]);
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
            throw ValidationException::withMessages([
                'decision_record' =>
                    'Contribution Decision Record source must be an approved Contribution Register revision.',
            ]);
        }

        $action = $this->application
            ->createAction(
                $actor,
                $workspace,
                'contribution',
                'contribution_decision_record',
                [
                    (int) $source
                        ->canonical_revision_id,
                ],
                $this->normalizedCreateAttributes(
                    $attributes
                ),
                null,
                (int) $record->id
            );

        return $this->projection($action);
    }

    public function transition(
        User $actor,
        PartnershipWorkspace $workspace,
        int $actionId,
        string $toState
    ): array {
        $context = $this->access->managementContext(
            $actor,
            $workspace
        );

        $actor = $context['actor'];
        $workspace = $context['workspace'];

        $action = WorkspaceV2Action::query()
            ->whereKey($actionId)
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'action_domain_key',
                'contribution'
            )
            ->where(
                'action_component_key',
                'contribution_action_plan'
            )
            ->first();

        if (! $action) {
            throw ValidationException::withMessages([
                'action' =>
                    'Contribution Action Plan action does not exist in this workspace.',
            ]);
        }

        $action = $this->application
            ->transitionAction(
                $actor,
                $workspace,
                $action,
                $toState
            );

        return $this->projection($action);
    }

    private function normalizedCreateAttributes(
        array $attributes
    ): array {
        $title = trim(
            (string) (
                $attributes['title'] ?? ''
            )
        );

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' =>
                    'Contribution Action Plan title is required.',
            ]);
        }

        $priority = (string) (
            $attributes['priority'] ?? 'normal'
        );

        if (! in_array(
            $priority,
            $this->execution->priorities(),
            true
        )) {
            throw ValidationException::withMessages([
                'priority' =>
                    'Invalid Contribution Action Plan priority.',
            ]);
        }

        $dueDate = $this->nullableTrimmed(
            $attributes['due_date'] ?? null
        );

        if (
            $dueDate !== null
            && ! $this->validDate($dueDate)
        ) {
            throw ValidationException::withMessages([
                'due_date' =>
                    'Contribution Action Plan due date must use YYYY-MM-DD.',
            ]);
        }

        return [
            'title' => $title,
            'description' =>
                $this->nullableTrimmed(
                    $attributes['description']
                        ?? null
                ),
            'owner_name' =>
                $this->nullableTrimmed(
                    $attributes['owner_name']
                        ?? null
                ),
            'priority' => $priority,
            'status' => 'open',
            'due_date' => $dueDate,
        ];
    }

    private function nullableTrimmed(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
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
        WorkspaceV2Action $action
    ): array {
        $action->loadMissing([
            'canonicalSources' =>
                fn ($query) =>
                    $query->orderBy('id'),
        ]);

        return [
            'id' => (int) $action->id,
            'workspace_id' =>
                (int) $action->workspace_id,
            'action_domain_key' =>
                (string) $action
                    ->action_domain_key,
            'action_component_key' =>
                (string) $action
                    ->action_component_key,
            'title' =>
                (string) $action->title,
            'description' =>
                $action->description,
            'owner_name' =>
                $action->owner_name,
            'priority' =>
                (string) $action->priority,
            'status' =>
                (string) $action->status,
            'due_date' =>
                $action->due_date
                    ?->format('Y-m-d'),
            'completed_at' =>
                $action->completed_at
                    ?->utc()
                    ->toISOString(),
            'created_by_user_id' =>
                $action->created_by_user_id
                    !== null
                    ? (int) $action
                        ->created_by_user_id
                    : null,
            'created_at' =>
                $action->created_at
                    ?->utc()
                    ->toISOString(),
            'updated_at' =>
                $action->updated_at
                    ?->utc()
                    ->toISOString(),
            'producer' => [
                'domain_key' =>
                    (string) $action
                        ->producer_domain_key,
                'component_key' =>
                    (string) $action
                        ->producer_component_key,
                'canonical_revision_id' =>
                    $action
                        ->producer_canonical_revision_id
                        !== null
                        ? (int) $action
                            ->producer_canonical_revision_id
                        : null,
                'operating_record_id' =>
                    $action
                        ->producer_operating_record_id
                        !== null
                        ? (int) $action
                            ->producer_operating_record_id
                        : null,
            ],
            'sources' =>
                $action->canonicalSources
                    ->map(
                        fn ($source): array => [
                            'canonical_revision_id' =>
                                (int) $source
                                    ->canonical_revision_id,
                            'domain_key' =>
                                (string) $source
                                    ->source_domain_key,
                            'component_key' =>
                                (string) $source
                                    ->source_component_key,
                            'revision' =>
                                (int) $source
                                    ->source_revision,
                            'pinned_at' =>
                                $source->pinned_at
                                    ?->utc()
                                    ->toISOString(),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}
