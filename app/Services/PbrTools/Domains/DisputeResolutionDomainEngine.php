<?php

namespace App\Services\PbrTools\Domains;

class DisputeResolutionDomainEngine
{
    public function summarize(array $tools): array
    {
        return [
            'escalation_ladder' =>
                $tools['conflict_escalation_ladder']['data']
                ?? null,

            'latest_dispute' =>
                $tools['dispute_log']['data']
                ?? null,

            'resolution' =>
                $tools['resolution_tracker']['data']
                ?? null,

            'deadlock' =>
                $tools['deadlock_decision_tool']['data']
                ?? null,

            'priorities' =>
                $tools['issue_priority_matrix']['data']
                ?? null,

            'timeline' =>
                $tools['escalation_timeline']['data']
                ?? null,
        ];
    }
}
