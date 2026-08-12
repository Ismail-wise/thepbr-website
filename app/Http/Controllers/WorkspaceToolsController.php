<?php

namespace App\Http\Controllers;

use App\Models\CourseChapter;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use App\Models\WorkspaceToolOutput;
use App\Services\PbrTools\ChapterOneIntegrationService;
use App\Services\PbrTools\PbrOperatingSystemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceToolsController extends Controller
{
    public function index(
        Request $request,
        PartnershipWorkspace $workspace,
        ChapterOneIntegrationService $integration,
        PbrOperatingSystemService $operatingSystem
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $workspace->load([
            'owner',
            'acceptedMemberships.user',
        ]);

        $businessStage = $workspace->business_stage;

        $chapters = CourseChapter::query()
            ->with([
                'tools' => function ($query) use ($businessStage) {
                    $query->orderBy('sort_order');

                    if ($businessStage === 'new') {
                        $query->where('supports_new_business', true);
                    }

                    if ($businessStage === 'existing') {
                        $query->where('supports_existing_business', true);
                    }
                },
            ])
            ->orderBy('chapter_number')
            ->get();

        $canManageContext = $operatingSystem->canManage(
            $request->user(),
            $workspace
        );

        if ($canManageContext) {
            $operatingSystem->syncWorkspacePartners($workspace);
        }

        $workspace->loadMissing('partnerProfiles');

        $businessStages = PartnershipWorkspace::BUSINESS_STAGES;
        $currencies = PartnershipWorkspace::CURRENCIES;

        if ($canManageContext) {
            $chapterOneSummary = $integration->summary($workspace);
        } else {
            $capitalSnapshot = $operatingSystem->readableSnapshot(
                $request->user(),
                $workspace,
                'capital'
            );
            $chapterOneSummary = is_array($capitalSnapshot?->summary)
                ? $capitalSnapshot->summary
                : [];
        }

        $toolDefinitions = config('pbr_operating_tools.definitions', []);

        $latestAgreedOutputs = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'agreed')
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('chapter_tool_id')
            ->keyBy(fn ($output) => (int) $output->chapter_tool_id);

        $agreedToolIds = $latestAgreedOutputs
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->flip();

        $latestDraftSessions = $canManageContext
            ? ToolSession::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'draft')
                ->orderByDesc('last_saved_at')
                ->orderByDesc('id')
                ->get()
                ->unique('chapter_tool_id')
                ->keyBy(fn ($session) => (int) $session->chapter_tool_id)
            : collect();

        $draftToolIds = $latestDraftSessions
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->flip();

        $chapterProgress = [];
        foreach ($chapters as $chapter) {
            $total = $chapter->tools->count();
            $agreed = $chapter->tools
                ->filter(fn ($tool) => $agreedToolIds->has((int) $tool->id))
                ->count();

            $chapterProgress[(int) $chapter->chapter_number] = [
                'total' => $total,
                'agreed' => $agreed,
                'percentage' => $total > 0
                    ? round(($agreed / $total) * 100)
                    : 0,
            ];
        }

        $operatingDomains = [];
        foreach (PbrOperatingSystemService::DOMAINS as $chapterNumber => $domainKey) {
            $snapshot = $operatingSystem->readableSnapshot(
                $request->user(),
                $workspace,
                $domainKey
            );

            $operatingDomains[$chapterNumber] = $snapshot ? [
                'domain' => $domainKey,
                'revision' => $snapshot->revision,
                'status' => $snapshot->status,
                'summary' => $snapshot->summary,
                'agreed_at' => $snapshot->agreed_at,
            ] : null;
        }

        return view('workspaces.tools.index', compact(
            'workspace',
            'chapters',
            'canManageContext',
            'businessStages',
            'currencies',
            'chapterOneSummary',
            'toolDefinitions',
            'chapterProgress',
            'operatingDomains',
            'agreedToolIds',
            'draftToolIds',
            'latestAgreedOutputs',
            'latestDraftSessions'
        ));
    }

    public function updateContext(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $request->user()->isAdmin()
            || (int) $workspace->owner_user_id === (int) $request->user()->id,
            403
        );

        $validated = $request->validate([
            'business_stage' => [
                'required',
                Rule::in(array_keys(PartnershipWorkspace::BUSINESS_STAGES)),
            ],
            'currency_code' => [
                'required',
                Rule::in(array_keys(PartnershipWorkspace::CURRENCIES)),
            ],
        ]);

        $workspace->update($validated);

        return redirect()
            ->route('workspaces.tools.index', $workspace)
            ->with('success', 'Business settings ကို သိမ်းပြီးပါပြီ။');
    }
}
