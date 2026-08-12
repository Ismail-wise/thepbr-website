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
            'Draft အမည်ပြောင်းပြီးပါပြီ။'
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
            'Draft Plan မိတ္တူအသစ် ဖန်တီးပြီးပါပြီ။'
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
            ->with('status', 'Draft ကို ဖျက်ပြီးပါပြီ။');
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
            'Draft Result revision '.$output->revision.' သိမ်းပြီးပါပြီ။'
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
            'Business Rule revision '.$output->revision.' အဖြစ် အတည်ပြုအသုံးပြုပြီးပါပြီ။ PBR AI နဲ့ အခြား Business Systems တွေက ဒီ data ကို လက်ရှိအသုံးပြုနေသော rule အဖြစ်ယူနိုင်ပါပြီ။'
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
        if ($tool->tool_key === 'startup_capital_planner') {
            return route('workspaces.tools.startup-capital.show', $workspace);
        }

        if ((int) $tool->chapter?->chapter_number === 1) {
            return route('workspaces.tools.chapter-one.show', [
                $workspace,
                $tool->slug,
            ]);
        }

        return route('workspaces.tools.operating.show', [
            $workspace,
            $tool->slug,
        ]);
    }
}
