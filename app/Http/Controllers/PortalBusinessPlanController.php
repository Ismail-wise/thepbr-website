<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceBusinessPlan;
use App\Models\WorkspaceBusinessPlanRevision;
use App\Models\WorkspaceKnowledgeSource;
use App\Services\Portal\BusinessPlanContent;
use App\Services\Portal\PrivateWorkspaceAccess;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PortalBusinessPlanController extends Controller
{
    public function __construct(private PrivateWorkspaceAccess $access, private BusinessPlanContent $content) {}

    private function plan(PartnershipWorkspace $workspace): ?WorkspaceBusinessPlan
    {
        return WorkspaceBusinessPlan::where('workspace_id', $workspace->id)->first();
    }

    private function latest(?WorkspaceBusinessPlan $plan): ?WorkspaceBusinessPlanRevision
    {
        return $plan?->revisions()->latest('revision')->first();
    }

    private function revision(PartnershipWorkspace $workspace, int $revision): WorkspaceBusinessPlanRevision
    {
        return WorkspaceBusinessPlanRevision::whereHas('businessPlan', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->findOrFail($revision);
    }

    private function conflict(PartnershipWorkspace $workspace, ?WorkspaceBusinessPlan $plan, ?array $submitted, bool $busy = false): never
    {
        throw new HttpResponseException(response()->view('portal.workspaces.business-plan.conflict',
            compact('workspace', 'plan', 'submitted', 'busy'), 409)->withHeaders([
                'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer',
            ]));
    }

    private function mutate(Request $request, PartnershipWorkspace $workspace, Closure $change, ?array $submitted = null): mixed
    {
        $request->validate(['version' => ['required', 'integer', 'min:0', 'max:2147483647']]);
        try {
            $lock = $this->access->lock($workspace);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() === 409) {
                $this->conflict($workspace, $this->plan($workspace), $submitted, true);
            }
            throw $error;
        }
        try {
            return DB::transaction(function () use ($request, $workspace, $change, $submitted): mixed {
                // Serialize the first plan creation too, when no plan row exists yet.
                PartnershipWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                $plan = WorkspaceBusinessPlan::firstOrCreate(['workspace_id' => $workspace->id]);
                $plan->refresh();
                if ($plan->lock_version !== $request->integer('version')) {
                    $this->conflict($workspace, $plan, $submitted);
                }
                $result = $change($plan);
                $plan->lock_version++;
                $plan->save();

                return $result;
            });
        } finally {
            $lock->release();
        }
    }

    public function edit(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->access->authorize($request, $workspace);
        $plan = $this->plan($workspace);
        $latest = $this->latest($plan);
        $values = $plan?->draft ?? $latest?->content ?? ['business_name' => $workspace->name];

        return view('portal.workspaces.business-plan.edit', compact('workspace', 'plan', 'latest', 'values'));
    }

    public function save(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $values = $request->validate($this->content->rules() + ['intent' => ['nullable', Rule::in(['save', 'review'])]]);
        $content = $this->content->normalize($values);
        $this->mutate($request, $workspace, function (WorkspaceBusinessPlan $plan) use ($content): void {
            if ($plan->draft === null) {
                $plan->draft_base_revision = $this->latest($plan)?->revision;
            }
            $plan->draft = $content;
        }, $content);

        return redirect()->route($request->input('intent') === 'review' ? 'portal.workspaces.business-plan.review' : 'portal.workspaces.business-plan.edit', $workspace)
            ->with('success', 'Draft သိမ်းပြီးပါပြီ။');
    }

    public function review(Request $request, PartnershipWorkspace $workspace): View|RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $plan = $this->plan($workspace);
        if ($plan?->draft === null) {
            return redirect()->route('portal.workspaces.business-plan.edit', $workspace);
        }
        $values = $plan->draft;
        $missing = $this->content->missing($values);

        return view('portal.workspaces.business-plan.review', compact('workspace', 'plan', 'values', 'missing'));
    }

    public function confirm(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $request->validate(['reviewed' => ['required', 'accepted']]);
        $revision = $this->mutate($request, $workspace, function (WorkspaceBusinessPlan $plan) use ($request): WorkspaceBusinessPlanRevision {
            abort_unless($plan->draft !== null, 409, 'Draft ကို အရင်သိမ်းပေးပါ။');
            $this->content->validateConfirmation($plan->draft);
            $revision = $plan->revisions()->create([
                'revision' => ($this->latest($plan)?->revision ?? 0) + 1,
                'based_on_revision' => $plan->draft_base_revision,
                'content' => $plan->draft,
                'confirmed_by_user_id' => $request->user()->id,
                'created_at' => now(),
            ]);
            $plan->draft = null;
            $plan->draft_base_revision = null;

            return $revision;
        });

        return redirect()->route('portal.workspaces.business-plan.revision', [$workspace, $revision->id])
            ->with('success', 'Business Plan ကို အတည်ပြုပြီးပါပြီ။');
    }

    public function history(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->access->authorize($request, $workspace);
        $plan = $this->plan($workspace);
        $revisions = WorkspaceBusinessPlanRevision::where('business_plan_id', $plan?->id ?? 0)->latest('revision')->paginate(15);

        return view('portal.workspaces.business-plan.history', compact('workspace', 'plan', 'revisions'));
    }

    public function showRevision(Request $request, PartnershipWorkspace $workspace, int $revision): View
    {
        $this->access->authorize($request, $workspace);
        $revision = $this->revision($workspace, $revision);
        $plan = $revision->businessPlan;
        $latest = $this->latest($plan);
        $values = $revision->content;
        $source = $revision->knowledge_source_id ? WorkspaceKnowledgeSource::where('workspace_id', $workspace->id)->findOrFail($revision->knowledge_source_id) : null;

        return view('portal.workspaces.business-plan.revision', compact('workspace', 'plan', 'revision', 'latest', 'values', 'source'));
    }

    public function copyRevision(Request $request, PartnershipWorkspace $workspace, int $revision): RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $revision = $this->revision($workspace, $revision);
        $this->mutate($request, $workspace, function (WorkspaceBusinessPlan $plan) use ($revision): void {
            $plan->draft = $revision->content;
            $plan->draft_base_revision = $revision->revision;
        });

        return redirect()->route('portal.workspaces.business-plan.edit', $workspace)->with('success', 'Draft အဖြစ် ယူထားပါပြီ။ ပြင်ပြီးမှ အတည်ပြုနိုင်ပါတယ်။');
    }

    public function prepareKnowledge(Request $request, PartnershipWorkspace $workspace, int $revision): RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $lock = $this->access->lock($workspace);
        try {
            $source = DB::transaction(function () use ($workspace, $revision): WorkspaceKnowledgeSource {
                PartnershipWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                $revision = $this->revision($workspace, $revision);
                if ($revision->knowledge_source_id) {
                    // Reopening must not replace an Owner's reviewed/approved text.
                    return WorkspaceKnowledgeSource::where('workspace_id', $workspace->id)->findOrFail($revision->knowledge_source_id);
                }
                $text = $this->content->knowledgeText($revision);
                $source = WorkspaceKnowledgeSource::create([
                    'workspace_id' => $workspace->id,
                    'title' => 'Business Plan · Version '.$revision->revision,
                    'kind' => 'text',
                    'business_status' => $this->latest($revision->businessPlan)?->id === $revision->id ? 'current' : 'historical',
                    'processing_status' => 'needs_review',
                    'extracted_text' => $text,
                    'reviewed_text' => $text,
                ]);
                $revision->update(['knowledge_source_id' => $source->id]);

                return $source;
            });
        } finally {
            $lock->release();
        }

        return redirect()->route('portal.workspaces.knowledge.show', [$workspace, $source])
            ->with('success', 'AI အတွက် စာသားကို စစ်ပြီး Approve လုပ်ပေးပါ။');
    }
}
