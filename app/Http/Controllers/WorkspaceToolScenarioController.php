<?php

namespace App\Http\Controllers;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\ToolScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceToolScenarioController extends Controller
{
    public function rename(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        int $session,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $validated = $request->validate([
            'scenario_name' => ['required', 'string', 'max:120'],
        ]);

        $draft = $scenarios->renameDraft(
            $request->user(),
            $workspace,
            $tool,
            $session,
            $validated['scenario_name']
        );

        return $this->toolRedirect(
            $workspace,
            $tool,
            $draft->id,
            'Scenario အမည်ပြောင်းပြီးပါပြီ။'
        );
    }

    public function duplicate(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        int $session,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);

        $draft = $scenarios->duplicateDraft(
            $request->user(),
            $workspace,
            $tool,
            $session
        );

        return $this->toolRedirect(
            $workspace,
            $tool,
            $draft->id,
            'Scenario copy အသစ်ဖန်တီးပြီးပါပြီ။'
        );
    }

    public function destroy(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        int $session,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);

        $scenarios->deleteDraft(
            $request->user(),
            $workspace,
            $tool,
            $session
        );

        return redirect($this->toolUrl($workspace, $tool))
            ->with('status', 'Scenario Draft ကိုဖျက်ပြီးပါပြီ။');
    }

    public function output(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        int $session,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $draft = $scenarios->ownedDraft(
            $request->user(),
            $workspace,
            $tool,
            $session
        );

        $output = $scenarios->createWorkspaceOutput(
            $request->user(),
            $workspace,
            $tool,
            $draft
        );

        return $this->toolRedirect(
            $workspace,
            $tool,
            $draft->id,
            'Workspace Draft Output revision '.$output->revision.' ဖန်တီးပြီးပါပြီ။'
        );
    }

    public function approve(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug,
        int $session,
        ToolScenarioService $scenarios
    ): RedirectResponse {
        $tool = $this->resolveTool($request, $workspace, $toolSlug);
        $draft = $scenarios->ownedDraft(
            $request->user(),
            $workspace,
            $tool,
            $session
        );

        $output = $scenarios->publishAgreedOutput(
            $request->user(),
            $workspace,
            $tool,
            $draft
        );

        return $this->toolRedirect(
            $workspace,
            $tool,
            $draft->id,
            'Agreed Business Rule revision '.$output->revision.' အဖြစ်အတည်ပြုပြီးပါပြီ။ နောက် Chapter တွေနဲ့ AI Advisor မှာ ဒီ agreed data ကိုအသုံးပြုနိုင်ပါပြီ။'
        );
    }

    private function resolveTool(
        Request $request,
        PartnershipWorkspace $workspace,
        string $toolSlug
    ): ChapterTool {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $tool = ChapterTool::query()
            ->where('slug', $toolSlug)
            ->with('chapter:id,chapter_number')
            ->firstOrFail();

        $supported = $workspace->business_stage === 'new'
            ? $tool->supports_new_business
            : $tool->supports_existing_business;

        abort_unless($supported, 404);

        return $tool;
    }

    private function toolRedirect(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        int $sessionId,
        string $message
    ): RedirectResponse {
        return redirect(
            $this->toolUrl($workspace, $tool).'?session='.$sessionId
        )->with('status', $message);
    }

    private function toolUrl(
        PartnershipWorkspace $workspace,
        ChapterTool $tool
    ): string {
        return url(
            '/workspaces/'.$workspace->id.'/tools/'.$tool->slug
        );
    }
}
