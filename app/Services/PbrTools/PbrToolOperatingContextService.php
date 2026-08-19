<?php

namespace App\Services\PbrTools;

class PbrToolOperatingContextService
{
    public const CONTEXT_STATUSES = [
        'planned',
        'in_progress',
        'blocked',
        'ready',
    ];

    public function rules(): array
    {
        return [
            'operating_context' => ['nullable', 'array'],
            'operating_context.owner_name' =>
                ['nullable', 'string', 'max:160'],
            'operating_context.status' => [
                'nullable',
                'string',
                'in:planned,in_progress,blocked,ready',
            ],
            'operating_context.effective_date' =>
                ['nullable', 'date'],
            'operating_context.review_date' => [
                'nullable',
                'date',
                'after_or_equal:operating_context.effective_date',
            ],
            'operating_context.decision_summary' =>
                ['nullable', 'string', 'max:2000'],
            'operating_context.evidence' =>
                ['nullable', 'string', 'max:3000'],

            'operating_actions' => ['nullable', 'array', 'max:12'],
            'operating_actions.*.title' =>
                ['nullable', 'string', 'max:180'],
            'operating_actions.*.description' =>
                ['nullable', 'string', 'max:2000'],
            'operating_actions.*.owner_name' =>
                ['nullable', 'string', 'max:160'],
            'operating_actions.*.priority' => [
                'nullable',
                'string',
                'in:low,normal,high,critical',
            ],
            'operating_actions.*.status' => [
                'nullable',
                'string',
                'in:open,in_progress,blocked,completed',
            ],
            'operating_actions.*.due_date' =>
                ['nullable', 'date'],
        ];
    }

    public function withDefaults(
        array $input,
        ?string $ownerName
    ): array {
        $context = is_array($input['operating_context'] ?? null)
            ? $input['operating_context']
            : [];

        $context = array_replace([
            'owner_name' => $ownerName,
            'status' => 'planned',
            'effective_date' => null,
            'review_date' => null,
            'decision_summary' => null,
            'evidence' => null,
        ], $context);

        $actions = is_array($input['operating_actions'] ?? null)
            ? array_values($input['operating_actions'])
            : [];

        if ($actions === []) {
            $actions = [[]];
        }

        $input['operating_context'] = $context;
        $input['operating_actions'] = $actions;

        return $input;
    }

    public function normalize(
        array $input,
        ?string $ownerName
    ): array {
        $input = $this->withDefaults($input, $ownerName);
        $context = $input['operating_context'];

        foreach ([
            'owner_name',
            'decision_summary',
            'evidence',
        ] as $key) {
            $context[$key] = filled($context[$key] ?? null)
                ? trim((string) $context[$key])
                : null;
        }

        $context['status'] = in_array(
            $context['status'] ?? null,
            self::CONTEXT_STATUSES,
            true
        ) ? $context['status'] : 'planned';

        $input['operating_context'] = $context;
        $input['operating_actions'] = collect(
            $input['operating_actions']
        )->filter(
            fn ($row): bool => is_array($row)
                && filled($row['title'] ?? null)
        )->map(function (array $row) use ($context): array {
            return [
                'title' => trim((string) $row['title']),
                'description' => filled($row['description'] ?? null)
                    ? trim((string) $row['description'])
                    : null,
                'owner_name' => filled($row['owner_name'] ?? null)
                    ? trim((string) $row['owner_name'])
                    : ($context['owner_name'] ?? null),
                'priority' => in_array(
                    $row['priority'] ?? null,
                    PbrToolActionService::PRIORITIES,
                    true
                ) ? $row['priority'] : 'normal',
                'status' => in_array(
                    $row['status'] ?? null,
                    PbrToolActionService::EDITABLE_STATUSES,
                    true
                ) ? $row['status'] : 'open',
                'due_date' => filled($row['due_date'] ?? null)
                    ? (string) $row['due_date']
                    : null,
            ];
        })->values()->all();

        return $input;
    }

    public function toolInput(array $input): array
    {
        unset(
            $input['operating_context'],
            $input['operating_actions']
        );

        return $input;
    }
}
