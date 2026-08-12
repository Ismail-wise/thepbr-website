<?php

namespace App\Services\Ai;

use App\Models\BusinessFeasibilityAssessment;
use App\Models\BusinessValuation;
use App\Models\PartnerDynamicsAssessment;
use App\Models\PartnerDynamicsReport;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceToolOutput;

class PbrAiContextBuilder
{
    public function build(User $actor, PartnershipWorkspace $workspace): array
    {
        $canManage = $actor->isAdmin()
            || (int) $workspace->owner_user_id === (int) $actor->id;

        $workspace->loadMissing([
            'owner:id,name,email',
            'acceptedMemberships.user:id,name,email',
        ]);

        $partners = $workspace->acceptedMemberships
            ->filter(fn ($membership) => $membership->member_role === 'partner')
            ->map(fn ($membership): array => [
                'name' => $membership->user?->name,
                'role' => 'partner',
            ])
            ->filter(fn (array $partner) => filled($partner['name']))
            ->values()
            ->all();

        $latestFeasibility = BusinessFeasibilityAssessment::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->first();

        $latestValuation = BusinessValuation::query()
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->first();

        $actorAssessment = PartnerDynamicsAssessment::query()
            ->where('user_id', $actor->id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->latest('id')
            ->first();

        $latestOutputs = WorkspaceToolOutput::query()
            ->with([
                'tool:id,course_chapter_id,tool_key,slug,title_en,title_mm',
                'tool.chapter:id,chapter_number,title_en,title_mm',
            ])
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->values()
            ->map(fn (WorkspaceToolOutput $output): array => [
                'chapter' => [
                    'number' => $output->tool?->chapter?->chapter_number,
                    'title_mm' => $output->tool?->chapter?->title_mm,
                    'title_en' => $output->tool?->chapter?->title_en,
                ],
                'tool' => [
                    'key' => $output->tool?->tool_key,
                    'slug' => $output->tool?->slug,
                    'title_mm' => $output->tool?->title_mm,
                    'title_en' => $output->tool?->title_en,
                ],
                'revision' => $output->revision,
                'status' => $output->status,
                'output' => $this->limitValue($output->output_data, 9000),
                'generated_at' => $output->generated_at?->toIso8601String(),
                'agreed_at' => $output->agreed_at?->toIso8601String(),
            ])
            ->all();

        $context = [
            'context_version' => 'pbr-ai-v1',
            'access_scope' => [
                'actor_type' => $canManage ? 'owner_or_admin' : 'accepted_partner',
                'manager_sensitive_context_included' => $canManage,
                'instruction' => $canManage
                    ? 'This actor may receive owner/admin business context included in this snapshot.'
                    : 'This actor is an accepted partner. Do not infer, request, or reveal owner/admin-only data that is absent from this snapshot.',
            ],
            'business' => [
                'workspace_id' => $workspace->id,
                'name' => $workspace->business_name ?: $workspace->name,
                'stage' => $workspace->business_stage,
                'stage_label' => PartnershipWorkspace::BUSINESS_STAGES[$workspace->business_stage] ?? $workspace->business_stage,
                'currency' => $workspace->currency_code,
                'status' => $workspace->status,
                'owner_name' => $workspace->owner?->name,
                'accepted_partner_count' => count($partners),
                'accepted_partners' => $partners,
            ],
            'actor_partner_profile' => $actorAssessment ? [
                'primary_profile' => $actorAssessment->primary_profile,
                'secondary_profile' => $actorAssessment->secondary_profile,
                'is_blended' => $actorAssessment->is_blended,
                'result_confidence' => $actorAssessment->result_confidence,
                'dimension_scores' => $actorAssessment->dimension_scores,
                'completed_at' => $actorAssessment->completed_at?->toIso8601String(),
                'scope_note' => 'This is the signed-in actor own latest completed Partner Dynamics profile only.',
            ] : null,
            'feasibility' => $latestFeasibility ? [
                'project_name' => $latestFeasibility->project_name,
                'result' => $this->limitValue($latestFeasibility->result, 12000),
                'inputs' => $canManage
                    ? $this->limitValue($latestFeasibility->inputs, 12000)
                    : null,
                'calculated_at' => $latestFeasibility->created_at?->toIso8601String(),
            ] : null,
            'valuation' => $latestValuation ? [
                'result' => $this->limitValue($latestValuation->result, 14000),
                'inputs' => $canManage
                    ? $this->limitValue($latestValuation->inputs, 14000)
                    : null,
                'calculated_at' => $latestValuation->created_at?->toIso8601String(),
            ] : null,
            'business_tool_outputs' => $latestOutputs,
        ];

        if ($canManage) {
            $report = PartnerDynamicsReport::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'ready')
                ->latest('id')
                ->first();

            $context['partner_dynamics'] = $report ? [
                'participants' => $report->participants,
                'alignment_summary' => $report->alignment_summary,
                'shared_strengths' => $report->shared_strengths,
                'complementary_areas' => $report->complementary_areas,
                'important_differences' => $report->important_differences,
                'shared_blind_spots' => $report->shared_blind_spots,
                'role_suggestions' => $report->role_suggestions,
                'decision_recommendations' => $report->decision_recommendations,
                'discussion_priorities' => $report->discussion_priorities,
                'generated_at' => $report->generated_at?->toIso8601String(),
            ] : null;
        }

        return $this->limitWholeContext($context);
    }

    private function limitValue(mixed $value, int $maxChars): mixed
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false || mb_strlen($json) <= $maxChars) {
            return $value;
        }

        return [
            '_pbr_context_note' => 'This section was shortened to keep the AI context within a safe size.',
            'preview_json' => mb_substr($json, 0, $maxChars),
        ];
    }

    private function limitWholeContext(array $context): array
    {
        $maxChars = max(12000, (int) config('pbr_ai.max_context_chars', 60000));
        $json = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false || mb_strlen($json) <= $maxChars) {
            return $context;
        }

        // Keep the highest-value sections intact first and shorten tool outputs last.
        $context['business_tool_outputs'] = collect($context['business_tool_outputs'] ?? [])
            ->take(12)
            ->values()
            ->all();
        $context['_pbr_context_note'] = 'Some older tool outputs were omitted because this workspace has a large amount of saved data. Latest high-value data remains included.';

        return $context;
    }
}
