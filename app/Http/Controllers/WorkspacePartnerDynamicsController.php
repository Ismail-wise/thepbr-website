<?php

namespace App\Http\Controllers;

use App\Models\PartnerDynamicsAssessment;
use App\Models\PartnerDynamicsReport;
use App\Models\PartnershipWorkspace;
use App\Services\PartnerDynamics\PartnerDynamicsAlignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspacePartnerDynamicsController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        PartnerDynamicsAlignmentService $alignmentService
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $workspace->load([
            'owner',
            'acceptedMemberships.user',
        ]);

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
            $user = $workspace->owner_user_id === $userId
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
                'is_owner' => $workspace->owner_user_id === $userId,
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

        $report = null;

        if (count($participants) >= 2) {
            $analysis = $alignmentService->analyze($participants);

            $report = PartnerDynamicsReport::query()
                ->where('workspace_id', $workspace->id)
                ->where('report_version', 'v1')
                ->latest('id')
                ->first();

            if (! $report) {
                $report = new PartnerDynamicsReport();
                $report->workspace_id = $workspace->id;
                $report->report_version = 'v1';
            }

            $report->fill([
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
            ]);

            $report->save();
        }

        return view('workspaces.partner-dynamics', [
            'workspace' => $workspace,
            'participantRows' => $participantRows,
            'completedParticipantCount' => count($participants),
            'report' => $report,
        ]);
    }

    public function profile(
        Request $request,
        PartnershipWorkspace $workspace,
        PartnerDynamicsAssessment $assessment
    ): View {
        /*
         * Security layer 1:
         * The signed-in user must already have access to this workspace.
         */
        abort_unless(
            $request->user()
                && $request->user()->canAccessWorkspace($workspace),
            403
        );

        /*
         * Security layer 2:
         * The requested assessment owner must actually belong
         * to this workspace as either:
         *
         * - workspace owner
         * - accepted workspace member
         */
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

        /*
         * Security layer 3:
         * Workspace profiles expose completed results only.
         */
        abort_unless(
            $assessment->isCompleted(),
            404
        );

        /*
         * Security layer 4:
         * Only the participant's latest completed assessment
         * can be viewed inside the workspace.
         *
         * This prevents old assessment IDs being guessed
         * and opened after a retake.
         */
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
            compact(
                'workspace',
                'assessment',
                'participantRole'
            )
        );
    }

}
