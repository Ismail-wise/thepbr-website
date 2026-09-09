<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspacePartnerProfile;
use App\Models\WorkspaceV2CanonicalWorkArtifact;
use App\Services\PbrTools\PbrV2ContributionAccessService;
use App\Services\PbrTools\PbrV2ContributionReadService;
use App\Services\PbrTools\PbrV2ContributionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WorkspaceV2ContributionController extends Controller
{
    public function __construct(
        private readonly PbrV2ContributionAccessService $access,
        private readonly PbrV2ContributionReadService $read,
        private readonly PbrV2ContributionWorkflowService $workflow,
    ) {
    }

    public function show(Request $request, PartnershipWorkspace $workspace): View
    {
        $context = $this->access->currentContext($request->user(), $workspace);
        $actor = $context['actor'];
        $workspace = $context['workspace'];
        $canManage = $context['can_manage'];

        // No draft selectors, even malformed ones, for current-only partners.
        if (! $canManage) {
            abort_if($request->hasAny(['artifact_id', 'before_id']), 403);
        }

        $current = $this->read->current($actor, $workspace);
        $queue = ['items' => [], 'next_before_id' => null];
        $artifact = null;
        $profiles = collect();
        $approvers = collect();
        $fieldsByType = [];

        if ($canManage) {
            $query = $request->validate([
                'artifact_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
                'before_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            ]);
            $queue = $this->read->workArtifacts(
                $actor, $workspace,
                isset($query['before_id']) ? (int) $query['before_id'] : null
            );
            $id = isset($query['artifact_id'])
                ? (int) $query['artifact_id']
                : ($queue['items'][0]['id'] ?? null);
            if ($id !== null) {
                $artifact = $this->read->workArtifact($actor, $workspace, $id);
            }
            $profiles = WorkspacePartnerProfile::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'planned'])
                ->orderBy('id')->get();

            $workspace->load(['owner', 'acceptedMemberships.user']);
            $approvers = collect([$workspace->owner, $actor])
                ->merge($workspace->acceptedMemberships
                    ->where('member_role', 'partner')->pluck('user'))
                ->filter()->unique('id')->values();
            foreach (config('pbr_v2_contribution.contribution_types') as $type) {
                $fieldsByType[$type] = $this->itemFields($type);
            }
        }

        return view('workspaces.v2.contribution', compact(
            'workspace', 'canManage', 'current', 'queue', 'artifact',
            'profiles', 'approvers', 'fieldsByType'
        ));
    }

    public function store(Request $request, PartnershipWorkspace $workspace): RedirectResponse
    {
        $this->access->managementContext($request->user(), $workspace);
        $this->envelope($request, []);
        $draft = DB::transaction(function () use ($request, $workspace) {
            $lockedWorkspace = PartnershipWorkspace::query()
                ->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            if ($this->read->current($request->user(), $lockedWorkspace) !== null) {
                throw ValidationException::withMessages([
                    'work_artifact' => 'An approved register requires the review/amendment path.',
                ]);
            }
            return $this->workflow->createDraft($request->user(), $lockedWorkspace);
        });
        return $this->draftRedirect($workspace, (int) $draft->id);
    }

    public function saveSetup(
        Request $request, PartnershipWorkspace $workspace, int $artifactId
    ): RedirectResponse {
        $artifact = $this->editable($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version', 'setup']);
        $data = $request->validate([
            'edit_version' => $this->versionRules(),
            'setup' => ['required', 'array:valuation_date,currency_code,contribution_period,valuation_owner_reference,approver_user_ids'],
            'setup.valuation_date' => ['required', 'date_format:Y-m-d'],
            'setup.currency_code' => ['required', 'string'],
            'setup.contribution_period' => ['required', 'array:start_date,end_date'],
            'setup.contribution_period.start_date' => ['required', 'date_format:Y-m-d'],
            'setup.contribution_period.end_date' => ['required', 'date_format:Y-m-d'],
            'setup.valuation_owner_reference' => ['required', 'string'],
            'setup.approver_user_ids' => ['required', 'array', 'min:1'],
            'setup.approver_user_ids.*' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'distinct'],
        ]);
        $setup = $data['setup'];
        $setup['approver_user_ids'] = array_values(array_map(
            static fn ($id): int => (int) $id, $setup['approver_user_ids']
        ));
        $this->workflow->saveSetup(
            $request->user(), $workspace, $artifact, (int) $data['edit_version'], $setup
        );
        return $this->draftRedirect($workspace, $artifactId);
    }

    public function savePartners(
        Request $request, PartnershipWorkspace $workspace, int $artifactId
    ): RedirectResponse {
        $artifact = $this->editable($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version', 'profile_ids']);
        $data = $request->validate([
            'edit_version' => $this->versionRules(),
            'profile_ids' => ['required', 'array', 'min:1'],
            'profile_ids.*' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'distinct'],
        ]);
        $this->workflow->savePartners(
            $request->user(), $workspace, $artifact, (int) $data['edit_version'],
            array_values(array_map(static fn ($id): int => (int) $id, $data['profile_ids']))
        );
        return $this->draftRedirect($workspace, $artifactId);
    }

    public function saveItem(
        Request $request, PartnershipWorkspace $workspace,
        int $artifactId, ?string $itemId = null
    ): RedirectResponse {
        $artifact = $this->editable($request, $workspace, $artifactId);
        $this->envelope($request, ['edit_version', 'item']);
        $typeData = $request->validate([
            'item' => ['required', 'array'],
            'item.contribution_type' => [
                'required', 'string', Rule::in(config('pbr_v2_contribution.contribution_types')),
            ],
        ]);
        $type = $typeData['item']['contribution_type'];
        $fields = $this->itemFields($type);
        $allowed = array_merge(['contribution_type', 'partner_key'], $fields);
        $rules = [
            'edit_version' => $this->versionRules(),
            'item' => ['required', 'array:'.implode(',', $allowed)],
            'item.contribution_type' => ['required', 'string', Rule::in([$type])],
            'item.partner_key' => ['required', 'string'],
        ];
        foreach ($fields as $field) {
            $rules['item.'.$field] = ['nullable', 'string'];
        }
        if ($type === 'property_asset') {
            $rules['item.ownership_transferred'] = [
                'nullable', 'string', Rule::in(['0', '1']),
            ];
        }
        $data = $request->validate($rules);
        $input = $data['item'];
        // Blank optional fields are omitted. Conditions and notes use empty text.
        foreach ($fields as $field) {
            if (($input[$field] ?? null) === null || ($input[$field] ?? null) === '') {
                unset($input[$field]);
            }
        }
        $input['conditions'] = $data['item']['conditions'] ?? '';
        $input['notes'] = $data['item']['notes'] ?? '';
        $input['evidence_references'] = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $data['item']['evidence_references'] ?? '')),
            static fn (string $line): bool => $line !== ''
        ));
        if (isset($input['ownership_transferred'])) {
            $input['ownership_transferred'] = $input['ownership_transferred'] === '1';
        }
        $this->workflow->saveItem(
            $request->user(), $workspace, $artifact,
            (int) $data['edit_version'], $input, $itemId
        );
        return $this->draftRedirect($workspace, $artifactId);
    }

    private function editable(
        Request $request, PartnershipWorkspace $workspace, int $artifactId
    ): WorkspaceV2CanonicalWorkArtifact {
        $this->access->managementContext($request->user(), $workspace);
        $projection = $this->read->workArtifact($request->user(), $workspace, $artifactId);
        if ($projection['state'] !== 'working_draft') {
            throw ValidationException::withMessages([
                'work_artifact' => 'Only an active working draft can be edited.',
            ]);
        }
        // Mutation services re-read and lock this exact identity.
        return WorkspaceV2CanonicalWorkArtifact::query()
            ->where('workspace_id', $workspace->id)->whereKey($artifactId)->firstOrFail();
    }

    private function itemFields(string $type): array
    {
        return array_merge([
            'description', 'owner_reference', 'proposed_value', 'valuation_method',
            'evidence_references', 'conditions', 'notes', 'committed_date', 'due_date',
        ], config('pbr_v2_contribution.type_fields.'.$type));
    }

    private function versionRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.(PHP_INT_MAX - 1)];
    }

    private function envelope(Request $request, array $allowed): void
    {
        $unknown = array_diff(
            array_keys($request->all()), array_merge($allowed, ['_token', '_method', '_form'])
        );
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'request' => 'Unexpected or server-controlled request fields.',
            ]);
        }
    }

    private function draftRedirect(PartnershipWorkspace $workspace, int $artifactId): RedirectResponse
    {
        return redirect()->route('workspaces.tools.v2.contribution.show', [
            'workspace' => $workspace, 'artifact_id' => $artifactId,
        ])->with('status', 'Contribution draft ကို သိမ်းပြီးပါပြီ။');
    }
}
