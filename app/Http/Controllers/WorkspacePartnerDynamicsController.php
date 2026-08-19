<?php

namespace App\Http\Controllers;

use App\Models\PartnerDynamicsAssessment;
use App\Models\PartnerDynamicsReport;
use App\Models\PartnershipWorkspace;
use App\Services\PartnerDynamics\PartnerDynamicsAlignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspacePartnerDynamicsController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        PartnerDynamicsAlignmentService $alignmentService
    ): View {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $workspace->load(['owner', 'acceptedMemberships.user']);
        [$participantRows, $participants] = $this->participantData($workspace);

        $storedReport = PartnerDynamicsReport::query()
            ->where('workspace_id', $workspace->id)
            ->where('report_version', 'v1')
            ->where('status', 'ready')
            ->latest('id')
            ->first();

        $canManageReport = $this->canManage($request, $workspace);
        $reportNeedsRefresh = $canManageReport
            && count($participants) >= 2
            && ! $this->reportMatchesParticipants($storedReport, $participants);
        $reportIsPreview = false;
        $report = $storedReport;

        // GET remains read-only. Managers can preview fresh calculations, then
        // explicitly save them with the POST action below.
        if ($reportNeedsRefresh) {
            $report = new PartnerDynamicsReport(
                $this->reportAttributes(
                    $participants,
                    $alignmentService->analyze($participants)
                )
            );
            $report->workspace_id = $workspace->id;
            $report->report_version = 'v1';
            $reportIsPreview = true;
        }

        return view('workspaces.partner-dynamics', [
            'workspace' => $workspace,
            'participantRows' => $participantRows,
            'completedParticipantCount' => count($participants),
            'report' => $report,
            'canManageReport' => $canManageReport,
            'reportNeedsRefresh' => $reportNeedsRefresh,
            'reportIsPreview' => $reportIsPreview,
        ]);
    }

    public function generate(
        Request $request,
        PartnershipWorkspace $workspace,
        PartnerDynamicsAlignmentService $alignmentService
    ): RedirectResponse {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);
        abort_unless($this->canManage($request, $workspace), 403);

        $workspace->load(['owner', 'acceptedMemberships.user']);
        [, $participants] = $this->participantData($workspace);

        if (count($participants) < 2) {
            throw ValidationException::withMessages([
                'partner_dynamics' => 'At least two completed Partner Dynamics profiles are required.',
            ]);
        }

        $report = PartnerDynamicsReport::query()
            ->where('workspace_id', $workspace->id)
            ->where('report_version', 'v1')
            ->latest('id')
            ->first()
            ?? new PartnerDynamicsReport([
                'workspace_id' => $workspace->id,
                'report_version' => 'v1',
            ]);

        $report->fill(
            $this->reportAttributes(
                $participants,
                $alignmentService->analyze($participants)
            )
        );
        $report->workspace_id = $workspace->id;
        $report->report_version = 'v1';
        $report->save();

        return redirect()
            ->route('workspaces.partner-dynamics.show', $workspace)
            ->with('success', 'Partner Dynamics report generated and saved.');
    }

    public function profile(
        Request $request,
        PartnershipWorkspace $workspace,
        PartnerDynamicsAssessment $assessment
    ): View {
        abort_unless(
            $request->user()
                && $request->user()->canAccessWorkspace($workspace),
            403
        );

        $participantUserIds = collect([$workspace->owner_user_id])
            ->merge(
                $workspace->acceptedMemberships()
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        abort_unless(
            $participantUserIds->contains((int) $assessment->user_id),
            403
        );

        abort_unless($assessment->isCompleted(), 404);

        $latestAssessment = PartnerDynamicsAssessment::query()
            ->where('user_id', $assessment->user_id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->latest('id')
            ->first();

        abort_unless(
            $latestAssessment
                && $latestAssessment->id === $assessment->id,
            404
        );

        $assessment->load('user');

        $participantRole =
            $workspace->owner_user_id === $assessment->user_id
                ? 'Workspace Owner'
                : 'Partner';

        return view(
            'workspaces.partner-dynamics-profile',
            compact('workspace', 'assessment', 'participantRole')
        );
    }

    private function participantData(PartnershipWorkspace $workspace): array
    {
        $userIds = collect([$workspace->owner_user_id])
            ->merge(
                $workspace->acceptedMemberships
                    ->pluck('user_id')
                    ->filter()
            )
            ->unique()
            ->values();

        $participants = [];
        $participantRows = [];

        foreach ($userIds as $userId) {
            $user = (int) $workspace->owner_user_id === (int) $userId
                ? $workspace->owner
                : $workspace->acceptedMemberships
                    ->firstWhere('user_id', $userId)
                    ?->user;

            if (! $user) {
                continue;
            }

            $assessment = PartnerDynamicsAssessment::query()
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->latest('id')
                ->first();

            $participantRows[] = [
                'user' => $user,
                'assessment' => $assessment,
                'is_owner' => (int) $workspace->owner_user_id === (int) $userId,
            ];

            if (! $assessment) {
                continue;
            }

            $participants[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'assessment_id' => $assessment->id,
                'primary_profile' => $assessment->primary_profile,
                'secondary_profile' => $assessment->secondary_profile,
                'dimension_scores' => $assessment->dimension_scores,
            ];
        }

        return [$participantRows, $participants];
    }

    private function reportAttributes(array $participants, array $analysis): array
    {
        return [
            'status' => 'ready',
            'participants' => array_map(
                fn (array $participant): array => [
                    'user_id' => $participant['user_id'],
                    'name' => $participant['name'],
                    'assessment_id' => $participant['assessment_id'],
                    'primary_profile' => $participant['primary_profile'],
                    'secondary_profile' => $participant['secondary_profile'],
                ],
                $participants
            ),
            'alignment_summary' => $analysis['alignment_summary'],
            'shared_strengths' => $analysis['shared_strengths'],
            'complementary_areas' => $analysis['complementary_areas'],
            'important_differences' => $analysis['important_differences'],
            'shared_blind_spots' => $analysis['shared_blind_spots'],
            'role_suggestions' => $analysis['role_suggestions'],
            'decision_recommendations' => $analysis['decision_recommendations'],
            'discussion_priorities' => $analysis['discussion_priorities'],
            'generated_at' => now(),
        ];
    }

    private function reportMatchesParticipants(
        ?PartnerDynamicsReport $report,
        array $participants
    ): bool {
        if (! $report) {
            return false;
        }

        $signature = static fn (array $items): array => collect($items)
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['user_id'] => (int) $item['assessment_id'],
            ])
            ->sortKeys()
            ->all();

        return $signature($report->participants ?? [])
            === $signature($participants);
    }

    private function canManage(
        Request $request,
        PartnershipWorkspace $workspace
    ): bool {
        return $request->user()->isAdmin()
            || (
                $request->user()->isStudent()
                && (int) $workspace->owner_user_id === (int) $request->user()->id
            );
    }
}
