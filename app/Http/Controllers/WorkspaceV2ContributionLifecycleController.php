<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ContributionAccessService;
use App\Services\PbrTools\PbrV2ContributionApprovalService;
use App\Services\PbrTools\PbrV2ContributionReadService;
use App\Services\PbrTools\PbrV2ContributionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WorkspaceV2ContributionLifecycleController extends Controller
{
    public function __construct(
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ContributionReadService $read,
        private readonly PbrV2ContributionWorkflowService $workflow,
        private readonly PbrV2ContributionApprovalService $approval,
    ) {
    }

    public function review(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): RedirectResponse
    {
        $artifact = $this->managedArtifact($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version', 'reviewed_value']);
        $data = $request->validate([
            'edit_version' => $this->versionRules(),
            'reviewed_value' => $this->moneyRules(),
        ]);
        $this->workflow->reviewItem(
            $request->user(), $workspace, $artifact,
            (int) $data['edit_version'], $itemId, $data['reviewed_value']
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    public function deliver(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): RedirectResponse
    {
        $artifact = $this->managedArtifact($request, $workspace, $artifactId);
        $item = $this->item($artifact, $itemId);
        $this->envelope($request, ['edit_version', 'delivery', 'evidence_reference']);
        $fields = ['accepted_value', 'committed_date', 'due_date', 'delivered_date',
            'delivery_extent', 'accepted_scope', 'adjustment_basis'];
        $rules = [
            'edit_version' => $this->versionRules(),
            'evidence_reference' => ['required', 'string'],
            // Canonical read projection requires exact two-decimal accepted values.
            'delivery.accepted_value' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})\.[0-9]{2}\z/'],
            'delivery.committed_date' => ['required', 'date_format:Y-m-d'],
            'delivery.due_date' => ['required', 'date_format:Y-m-d'],
            'delivery.delivered_date' => ['required', 'date_format:Y-m-d'],
            'delivery.delivery_extent' => ['required', Rule::in(['full', 'partial'])],
            'delivery.accepted_scope' => ['nullable', 'string'],
            'delivery.adjustment_basis' => ['nullable', 'string'],
        ];
        if ($item['contribution_type'] === 'cash') {
            $fields = array_merge($fields, ['amount_received', 'payment_date']);
            $rules['delivery.amount_received'] = $this->moneyRules();
            $rules['delivery.payment_date'] = ['required', 'date_format:Y-m-d'];
        }
        $rules['delivery'] = ['required', 'array:'.implode(',', $fields)];
        $data = $request->validate($rules);
        $this->workflow->recordDelivery(
            $request->user(), $workspace, $artifact, (int) $data['edit_version'],
            $itemId, $data['delivery'], $data['evidence_reference']
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    public function close(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): RedirectResponse
    {
        $artifact = $this->managedArtifact($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version', 'target_status', 'reason', 'evidence_reference']);
        $data = $request->validate([
            'edit_version' => $this->versionRules(),
            'target_status' => ['required', Rule::in(['rejected', 'cancelled', 'defaulted'])],
            'reason' => ['required', 'string'],
            'evidence_reference' => ['required', 'string'],
        ]);
        $this->workflow->closeItem(
            $request->user(), $workspace, $artifact, (int) $data['edit_version'],
            $itemId, $data['target_status'], $data['reason'], $data['evidence_reference']
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    public function submit(Request $request, PartnershipWorkspace $workspace, int $artifactId): RedirectResponse
    {
        $artifact = $this->managedArtifact($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version']);
        $data = $request->validate(['edit_version' => $this->versionRules()]);
        $this->approval->submitForPublication(
            $request->user(), $workspace, $artifact, (int) $data['edit_version']
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    public function publish(Request $request, PartnershipWorkspace $workspace, int $artifactId): RedirectResponse
    {
        $artifact = $this->managedArtifact($request, $workspace, $artifactId);
        $this->envelope($request, []);
        $this->approval->approveAndPublish($request->user(), $workspace, $artifact);
        return $this->landing($request, $workspace);
    }

    public function showApproval(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): View
    {
        return $this->withApprovalSubject(
            $request, $workspace, $artifactId, $itemId,
            function ($lockedWorkspace, $artifact, $item) {
                $setup = $artifact->payload['setup'];
                $subject = array_diff_key($item, array_flip(['event_history', 'approval_records']));
                $records = $item['approval_records'] ?? [];
                $approverNames = User::query()->whereIn('id', $setup['approver_user_ids'])
                    ->pluck('name', 'id');
                return view('workspaces.v2.contribution-approval', [
                    'workspace' => $lockedWorkspace,
                    'artifactId' => (int) $artifact->id,
                    'version' => $artifact->payload['edit_version'],
                    'setup' => $setup, 'subject' => $subject,
                    'records' => $records, 'approverNames' => $approverNames,
                ]);
            }
        );
    }

    public function agree(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): RedirectResponse
    {
        $this->withApprovalSubject(
            $request, $workspace, $artifactId, $itemId,
            function ($lockedWorkspace, $artifact) use ($request, $itemId) {
                $this->envelope($request, ['edit_version', 'agreed_value', 'evidence_reference', 'notes']);
                $data = $request->validate([
                    'edit_version' => $this->versionRules(),
                    'agreed_value' => $this->moneyRules(),
                    'evidence_reference' => ['required', 'string'],
                    'notes' => ['nullable', 'string'],
                ]);
                return $this->approval->approveItem(
                    $request->user(), $lockedWorkspace, $artifact,
                    (int) $data['edit_version'], $itemId, $data['agreed_value'],
                    $data['evidence_reference'], $data['notes'] ?? ''
                );
            }
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    public function accept(Request $request, PartnershipWorkspace $workspace, int $artifactId, string $itemId): RedirectResponse
    {
        $this->withApprovalSubject(
            $request, $workspace, $artifactId, $itemId,
            function ($lockedWorkspace, $artifact) use ($request, $itemId) {
                $this->envelope($request, ['edit_version', 'evidence_reference', 'notes']);
                $data = $request->validate([
                    'edit_version' => $this->versionRules(),
                    'evidence_reference' => ['required', 'string'],
                    'notes' => ['nullable', 'string'],
                ]);
                return $this->approval->acceptItem(
                    $request->user(), $lockedWorkspace, $artifact,
                    (int) $data['edit_version'], $itemId,
                    $data['evidence_reference'], $data['notes'] ?? ''
                );
            }
        );
        return $this->landing($request, $workspace, $artifactId);
    }

    private function withApprovalSubject(
        Request $request, PartnershipWorkspace $workspace,
        int $artifactId, string $itemId, callable $callback
    ): mixed {
        $this->access->currentContext($request->user(), $workspace);
        return DB::transaction(function () use ($request, $workspace, $artifactId, $itemId, $callback) {
            $lockedWorkspace = PartnershipWorkspace::query()
                ->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $artifact = WorkspaceV2CanonicalWorkArtifact::query()
                ->where('workspace_id', $lockedWorkspace->id)->whereKey($artifactId)
                ->where('target_domain_key', 'contribution')
                ->where('target_component_key', 'accepted_contribution_register')
                ->where('producer_domain_key', 'contribution')
                ->where('producer_component_key', 'partner_contributions_workflow')
                ->lockForUpdate()->first();
            abort_unless($artifact, 403);
            $ids = $artifact->payload['setup']['approver_user_ids'] ?? [];
            $this->access->approvalContext(
                $request->user(), $lockedWorkspace, is_array($ids) ? $ids : []
            );
            // Authorization uses the persisted locked designation before validation.
            if ($artifact->state !== 'working_draft'
                || $artifact->consumed_at !== null
                || $artifact->published_canonical_revision_id !== null) {
                throw ValidationException::withMessages([
                    'work_artifact' => 'This approval subject is no longer active.',
                ]);
            }
            return $callback($lockedWorkspace, $artifact, $this->item($artifact, $itemId));
        });
    }

    private function managedArtifact(Request $request, PartnershipWorkspace $workspace, int $artifactId): WorkspaceV2CanonicalWorkArtifact
    {
        $this->access->managementContext($request->user(), $workspace);
        $this->read->workArtifact($request->user(), $workspace, $artifactId);
        return WorkspaceV2CanonicalWorkArtifact::query()
            ->where('workspace_id', $workspace->id)->whereKey($artifactId)->firstOrFail();
    }

    private function item(WorkspaceV2CanonicalWorkArtifact $artifact, string $itemId): array
    {
        $matches = array_values(array_filter(
            $artifact->payload['items'] ?? [],
            static fn ($row): bool => is_array($row) && ($row['id'] ?? null) === $itemId
        ));
        if (count($matches) !== 1) {
            throw ValidationException::withMessages(['item_id' => 'Contribution item unavailable.']);
        }
        return $matches[0];
    }

    private function versionRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.(PHP_INT_MAX - 1)];
    }

    private function moneyRules(): array
    {
        return ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/'];
    }

    private function envelope(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), array_merge($allowed, ['_token', '_method'])) !== []) {
            throw ValidationException::withMessages(['request' => 'Unexpected or server-controlled request fields.']);
        }
    }

    private function landing(Request $request, PartnershipWorkspace $workspace, ?int $artifactId = null): RedirectResponse
    {
        $context = $this->access->currentContext($request->user(), $workspace);
        $params = ['workspace' => $workspace];
        if ($context['can_manage'] && $artifactId !== null) {
            $params['artifact_id'] = $artifactId;
        }
        return redirect()->route('workspaces.tools.v2.contribution.show', $params)
            ->with('status', 'Contribution လုပ်ဆောင်မှုကို မှတ်တမ်းတင်ပြီးပါပြီ။');
    }
}
