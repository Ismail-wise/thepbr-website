<?php

namespace App\Services\Ai;

use App\Models\BusinessFeasibilityAssessment;
use App\Models\BusinessValuation;
use App\Models\PartnerDynamicsAssessment;
use App\Models\PartnerDynamicsReport;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceToolOutput;
use App\Services\PbrTools\PbrOperatingSystemService;

class PbrAiContextBuilder
{
    public function __construct(
        private readonly PbrOperatingSystemService $operatingSystem
    ) {
    }

    public function build(User $actor, PartnershipWorkspace $workspace): array
    {
        $canManage = $this->operatingSystem->canManage($actor, $workspace);

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

        // Business operating rules supplied to AI are approved-only for every
        // actor, including owners/admins. Proposed working changes remain in
        // the workflow until someone explicitly approves and activates them.
        $latestOutputs = WorkspaceToolOutput::query()
            ->with([
                'tool:id,course_chapter_id,tool_key,slug,title_en,title_mm',
                'tool.chapter:id,chapter_number,title_en,title_mm',
            ])
            ->where('workspace_id', $workspace->id)
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->values()
            ->map(function (WorkspaceToolOutput $output): array {
                $internalNumber = (int) ($output->tool?->chapter?->chapter_number ?? 0);
                $area = config('pbr_business_operating_system.areas.'.$internalNumber, []);

                return [
                    'business_area' => [
                        'domain' => $area['domain'] ?? null,
                        'name_mm' => $area['name_mm'] ?? null,
                        'name_en' => $area['name_en'] ?? null,
                    ],
                    'tool' => [
                        'key' => $output->tool?->tool_key,
                        'slug' => $output->tool?->slug,
                        'title_mm' => $output->tool?->title_mm,
                        'title_en' => $output->tool?->title_en,
                    ],
                    'revision' => $output->revision,
                    'status' => $output->status,
                    'output' => $this->limitValue($output->output_data, 6000),
                    'generated_at' => $output->generated_at?->toIso8601String(),
                    'agreed_at' => $output->agreed_at?->toIso8601String(),
                ];
            })
            ->all();

        $operatingSystem = $this->operatingSystem->agreedDomainMap(
            $actor,
            $workspace
        );

        $context = [
            'context_version' => 'pbr-ai-v3-approved-business-rules',
            'access_scope' => [
                'actor_type' => $canManage ? 'owner_or_admin' : 'accepted_partner',
                'manager_sensitive_context_included' => $canManage,
                'workspace_tool_output_scope' => 'agreed_only',
                'operating_rule_scope' => 'approved_current_rules_only',
                'instruction' => $canManage
                    ? 'This actor may receive owner/admin-sensitive context where explicitly included, but operating rules and business-tool outputs in this snapshot are approved-only. Never treat an unapproved working change as current policy. Refer to operating domains by their business-area names, not by internal chapter numbers.'
                    : 'This actor is an accepted partner. Operating rules and business-tool outputs are approved-only. Do not infer, request, or reveal owner/admin-only or draft data that is absent from this snapshot. Refer to operating domains by their business-area names, not by internal chapter numbers.',
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
                'result' => $this->limitValue($latestFeasibility->result, 9000),
                'inputs' => $canManage
                    ? $this->limitValue($latestFeasibility->inputs, 9000)
                    : null,
                'calculated_at' => $latestFeasibility->created_at?->toIso8601String(),
            ] : null,
            'valuation' => $latestValuation ? [
                'result' => $this->limitValue($latestValuation->result, 10000),
                'inputs' => $canManage
                    ? $this->limitValue($latestValuation->inputs, 10000)
                    : null,
                'calculated_at' => $latestValuation->created_at?->toIso8601String(),
            ] : null,
            'operating_system' => $this->limitValue($operatingSystem, 26000),
            'business_tool_outputs' => $latestOutputs,
        ];

        if ($canManage) {
            $report = PartnerDynamicsReport::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'ready')
                ->latest('id')
                ->first();

            $context['partner_dynamics'] = $report ? $this->limitValue([
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
            ], 12000) : null;
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

        if ($this->encodedLength($context) <= $maxChars) {
            return $context;
        }

        foreach ([6, 4, 3, 2] as $toolLimit) {
            $context['business_tool_outputs'] = collect($context['business_tool_outputs'] ?? [])
                ->take($toolLimit)
                ->values()
                ->all();

            if ($this->encodedLength($context) <= $maxChars) {
                $context['_pbr_context_note'] = 'Older saved tool outputs were omitted because this workspace contains a large amount of data.';
                return $context;
            }
        }

        if (is_array($context['feasibility'] ?? null)) {
            $context['feasibility']['inputs'] = null;
            $context['feasibility']['inputs_note'] = 'Detailed feasibility inputs were omitted for context size; the latest result remains available.';
        }

        if (is_array($context['valuation'] ?? null)) {
            $context['valuation']['inputs'] = null;
            $context['valuation']['inputs_note'] = 'Detailed valuation inputs were omitted for context size; the latest result remains available.';
        }

        if ($this->encodedLength($context) <= $maxChars) {
            $context['_pbr_context_note'] = 'Some detailed inputs and older tool outputs were omitted because this workspace contains a large amount of data.';
            return $context;
        }

        $context['business_tool_outputs'] = collect($context['business_tool_outputs'] ?? [])
            ->take(1)
            ->values()
            ->all();
        $context['_pbr_context_note'] = 'Context was reduced to the latest high-value business results, the connected operating-system snapshot, and the most recent approved business-tool output.';

        return $context;
    }

    private function encodedLength(array $context): int
    {
        $json = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? 0 : mb_strlen($json);
    }
}
