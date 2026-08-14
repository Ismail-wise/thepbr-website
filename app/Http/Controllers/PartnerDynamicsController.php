<?php

namespace App\Http\Controllers;

use App\Models\PartnerDynamicsAssessment;
use App\Services\PartnerDynamics\PartnerDynamicsScoringService;
use App\Services\PartnerDynamics\PartnerMatchRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerDynamicsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $this->ensureAccess($user);

        $latestAssessment = PartnerDynamicsAssessment::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return view('partner-dynamics.index', compact('latestAssessment'));
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->ensureAccess($user);

        $assessment = PartnerDynamicsAssessment::query()
            ->where('user_id', $user->id)
            ->where('assessment_version', config('partner_dynamics.version', 'v1'))
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        if (!$assessment) {
            $assessment = PartnerDynamicsAssessment::create([
                'user_id' => $user->id,
                'assessment_version' => config('partner_dynamics.version', 'v1'),
                'status' => 'draft',
                'answers' => [],
                'started_at' => now(),
            ]);
        }

        return redirect()->route(
            'partner-dynamics.assessment.step',
            [$assessment, $this->nextIncompleteStep($assessment)]
        );
    }

    public function step(
        Request $request,
        PartnerDynamicsAssessment $assessment,
        int $step
    ): View|RedirectResponse {
        $this->ensureOwner($request, $assessment);

        if ($assessment->isCompleted()) {
            return redirect()->route(
                'partner-dynamics.result',
                $assessment
            );
        }

        abort_unless($step >= 1 && $step <= 5, 404);

        $questions = $this->questionsForStep($step);

        return view('partner-dynamics.assessment', [
            'assessment' => $assessment,
            'step' => $step,
            'questions' => $questions,
            'answers' => $assessment->answers ?? [],
        ]);
    }

    public function saveStep(
        Request $request,
        PartnerDynamicsAssessment $assessment,
        int $step,
        PartnerDynamicsScoringService $scoringService
    ): RedirectResponse {
        $this->ensureOwner($request, $assessment);

        abort_if($assessment->isCompleted(), 409);
        abort_unless($step >= 1 && $step <= 5, 404);

        $questions = $this->questionsForStep($step);
        $rules = [];

        foreach ($questions as $number => $question) {
            $rules["answers.$number"] = $step <= 4
                ? ['required', 'integer', 'between:1,5']
                : ['required', 'string', 'in:A,B,C,D'];
        }

        $validated = $request->validate($rules);

        $existingAnswers = $assessment->answers ?? [];

        foreach ($validated['answers'] as $number => $answer) {
            $existingAnswers[(int) $number] = $step <= 4
                ? (int) $answer
                : strtoupper((string) $answer);
        }

        ksort($existingAnswers);

        $assessment->update([
            'answers' => $existingAnswers,
        ]);

        if ($step < 5) {
            return redirect()
                ->route(
                    'partner-dynamics.assessment.step',
                    [$assessment, $step + 1]
                )
                ->with('success', 'သင့်အဖြေများကို Save လုပ်ပြီးပါပြီ။');
        }

        $result = $scoringService->calculate($existingAnswers);

        $assessment->update([
            'status' => 'completed',
            'dimension_scores' => $result['dimension_scores'],
            'profile_scores' => $result['profile_scores'],
            'primary_profile' => $result['primary_profile'],
            'primary_score' => $result['primary_score'],
            'secondary_profile' => $result['secondary_profile'],
            'secondary_score' => $result['secondary_score'],
            'is_blended' => $result['is_blended'],
            'result_confidence' => $result['result_confidence'],
            'consistency_data' => $result['consistency_data'],
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('partner-dynamics.result', $assessment)
            ->with('success', 'Partner Dynamics Assessment ပြီးဆုံးပါပြီ။');
    }

    public function result(
        Request $request,
        PartnerDynamicsAssessment $assessment,
        PartnerMatchRecommendationService $partnerMatchService
    ): View|RedirectResponse {
        $this->ensureOwner($request, $assessment);

        if (!$assessment->isCompleted()) {
            return redirect()->route(
                'partner-dynamics.assessment.step',
                [$assessment, $this->nextIncompleteStep($assessment)]
            );
        }

        $partnerMatch =
            $partnerMatchService->recommend(
                $assessment->dimension_scores ?? [],
                $assessment->primary_profile,
                $assessment->secondary_profile
            );

        return view(
            'partner-dynamics.result',
            compact(
                'assessment',
                'partnerMatch'
            )
        );
    }

    public function retake(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->ensureAccess($user);

        $assessment = PartnerDynamicsAssessment::create([
            'user_id' => $user->id,
            'assessment_version' => config('partner_dynamics.version', 'v1'),
            'status' => 'draft',
            'answers' => [],
            'started_at' => now(),
        ]);

        return redirect()->route(
            'partner-dynamics.assessment.step',
            [$assessment, 1]
        );
    }

    private function questionsForStep(int $step): array
    {
        if ($step === 5) {
            return config('partner_dynamics.scenario_questions', []);
        }

        $all = config('partner_dynamics.behaviour_questions', []);

        $start = (($step - 1) * 8) + 1;
        $end = $start + 7;

        return array_filter(
            $all,
            fn ($number) => $number >= $start && $number <= $end,
            ARRAY_FILTER_USE_KEY
        );
    }

    private function nextIncompleteStep(
        PartnerDynamicsAssessment $assessment
    ): int {
        $answers = $assessment->answers ?? [];

        for ($step = 1; $step <= 5; $step++) {
            foreach ($this->questionsForStep($step) as $number => $question) {
                if (!array_key_exists($number, $answers)) {
                    return $step;
                }
            }
        }

        return 5;
    }

    private function ensureAccess($user): void
    {
        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->isStudent()
            ),
            403
        );
    }

    private function ensureOwner(
        Request $request,
        PartnerDynamicsAssessment $assessment
    ): void {
        $user = $request->user();

        $this->ensureAccess($user);

        abort_unless(
            $user->isAdmin()
            || $assessment->user_id === $user->id,
            403
        );
    }
}
