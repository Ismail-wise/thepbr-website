<?php

namespace App\Http\Controllers;

use App\Models\CourseChapter;
use App\Models\PartnershipWorkspace;
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

        $businessStages = PartnershipWorkspace::BUSINESS_STAGES;
        $currencies = PartnershipWorkspace::CURRENCIES;
        $chapterOneSummary = $integration->summary($workspace);
        $toolDefinitions = config('pbr_operating_tools.definitions', []);

        $agreedToolIds = WorkspaceToolOutput::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'agreed')
            ->pluck('chapter_tool_id')
            ->unique()
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
            'operatingDomains'
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
            ->with('success', 'Partnership settings ကိုသိမ်းပြီးပါပြီ။');
    }
}
