<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceInvitationController extends Controller
{
    private const INVITATION_EXPIRY_DAYS = 7;

    private const PARTNER_PERMISSIONS = [
        'approved_workspace_read_only',
    ];

    public function store(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $this->authorizeInvitationManagement($request, $workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $workspace->loadMissing('owner');

        if (strtolower((string) $workspace->owner?->email) === $email) {
            throw ValidationException::withMessages([
                'email' => 'The workspace owner already has full access.',
            ]);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $matchingMembership = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_role', 'partner')
            ->where(function ($query) use ($email, $existingUser): void {
                $query->whereRaw('LOWER(invited_email) = ?', [$email]);

                if ($existingUser) {
                    $query->orWhere('user_id', $existingUser->id);
                }
            })
            ->latest('id')
            ->first();

        if ($matchingMembership?->isAccepted()) {
            throw ValidationException::withMessages([
                'email' => 'This person already has access to the workspace.',
            ]);
        }

        $rawToken = Str::random(64);
        $expiresAt = now()->addDays(self::INVITATION_EXPIRY_DAYS);
        $invitation = $matchingMembership ?? new WorkspaceMember();

        $invitation->fill([
            'workspace_id' => $workspace->id,
            'user_id' => $existingUser?->id,
            'member_role' => 'partner',
            'invitation_status' => 'pending',
            'invited_email' => $email,
            'invitation_token_hash' => WorkspaceMember::fingerprintInvitationToken($rawToken),
            'invited_by_user_id' => $request->user()->id,
            'invited_at' => now(),
            'invitation_expires_at' => $expiresAt,
            'accepted_at' => null,
            'permissions' => self::PARTNER_PERMISSIONS,
        ]);
        $invitation->save();

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with(
                'success',
                'Secure invitation link created. PBR has not emailed it automatically; copy and send it only to '.$email.'.'
            )
            ->with(
                'invitation_link',
                route('workspace-invitations.show', ['token' => $rawToken])
            )
            ->with('invitation_email', $email)
            ->with('invitation_expires_at', $expiresAt->toIso8601String());
    }

    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invitation_link' => ['required', 'string', 'max:2048'],
        ]);

        $token = $this->extractToken(trim($validated['invitation_link']));

        if (! $token) {
            throw ValidationException::withMessages([
                'invitation_link' => 'Invitation Link မမှန်ပါ။ Complete link ကို ပြန်ထည့်ပါ။',
            ]);
        }

        $this->findPendingInvitation($token);

        return redirect()->route(
            'workspace-invitations.show',
            ['token' => $token]
        );
    }

    public function show(Request $request, string $token): View
    {
        $invitation = $this->findPendingInvitation($token);
        $invitation->load(['workspace.owner', 'invitedBy']);

        if (! $request->user()) {
            $request->session()->put(
                'url.intended',
                route('workspace-invitations.show', ['token' => $token]),
            );
        }

        return view('workspace-invitations.show', compact(
            'invitation',
            'token'
        ));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        $workspace = DB::transaction(
            function () use ($token, $user): PartnershipWorkspace {
                $invitation = WorkspaceMember::query()
                    ->where(
                        'invitation_token_hash',
                        WorkspaceMember::fingerprintInvitationToken($token)
                    )
                    ->where('invitation_status', 'pending')
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $invitation->isInvitationUsable(),
                    410,
                    'This invitation has expired. Ask the workspace owner for a new link.'
                );

                $workspace = PartnershipWorkspace::query()
                    ->findOrFail($invitation->workspace_id);

                if ((int) $workspace->owner_user_id === (int) $user->id) {
                    throw ValidationException::withMessages([
                        'invitation' => 'You already own this workspace.',
                    ]);
                }

                if (strtolower($user->email) !== strtolower((string) $invitation->invited_email)) {
                    throw ValidationException::withMessages([
                        'invitation' => 'This invitation belongs to a different email address. Log in with the invited account.',
                    ]);
                }

                $existingMembership = WorkspaceMember::query()
                    ->where('workspace_id', $invitation->workspace_id)
                    ->where('user_id', $user->id)
                    ->where('invitation_status', 'accepted')
                    ->where('id', '!=', $invitation->id)
                    ->first();

                if ($existingMembership) {
                    $invitation->update([
                        'invitation_status' => 'revoked',
                        'invitation_token_hash' => null,
                    ]);

                    return $existingMembership->workspace;
                }

                $invitation->update([
                    'user_id' => $user->id,
                    'member_role' => 'partner',
                    'invitation_status' => 'accepted',
                    'accepted_at' => now(),
                    'invitation_token_hash' => null,
                    'permissions' => self::PARTNER_PERMISSIONS,
                ]);

                return $workspace;
            }
        );

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with(
                'success',
                'Workspace ချိတ်ဆက်ပြီးပါပြီ။ Approved Current Rules နဲ့ shared workspace data ကို Partner Read-Only အဖြစ် ကြည့်နိုင်ပါပြီ။'
            );
    }

    public function revoke(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspaceMember $invitation,
    ): RedirectResponse {
        $this->authorizeInvitationManagement($request, $workspace);

        abort_unless((int) $invitation->workspace_id === (int) $workspace->id, 404);
        abort_unless(
            $invitation->member_role === 'partner'
                && $invitation->invitation_status === 'pending',
            422
        );

        $invitation->update([
            'invitation_status' => 'revoked',
            'invitation_token_hash' => null,
        ]);

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with('success', 'Partner invitation revoked.');
    }

    public function remove(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspaceMember $membership,
    ): RedirectResponse {
        $this->authorizeInvitationManagement($request, $workspace);

        abort_unless((int) $membership->workspace_id === (int) $workspace->id, 404);
        abort_unless(
            $membership->member_role === 'partner'
                && $membership->invitation_status === 'accepted',
            422
        );

        $membership->update([
            'invitation_status' => 'removed',
            'invitation_token_hash' => null,
            'permissions' => null,
        ]);

        return redirect()
            ->route('workspaces.show', $workspace)
            ->with('success', 'Partner access removed immediately.');
    }

    private function extractToken(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9]{64}$/', $value)) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if (preg_match(
            '#/workspace-invitations/([A-Za-z0-9]{64})/?$#',
            $path,
            $matches
        )) {
            return $matches[1];
        }

        return null;
    }

    private function findPendingInvitation(string $token): WorkspaceMember
    {
        $invitation = WorkspaceMember::query()
            ->where(
                'invitation_token_hash',
                WorkspaceMember::fingerprintInvitationToken($token)
            )
            ->where('invitation_status', 'pending')
            ->firstOrFail();

        abort_unless(
            $invitation->isInvitationUsable(),
            410,
            'This invitation has expired. Ask the workspace owner for a new link.'
        );

        return $invitation;
    }

    private function authorizeInvitationManagement(
        Request $request,
        PartnershipWorkspace $workspace,
    ): void {
        abort_unless(
            $request->user()->isAdmin()
                || (
                    $request->user()->isStudent()
                    && (int) $workspace->owner_user_id === (int) $request->user()->id
                ),
            403,
        );
    }
}
