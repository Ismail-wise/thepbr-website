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
    private const SHAREABLE_EMAIL_DOMAIN =
        '@invite.thepbr.local';

    public function store(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $this->authorizeInvitationManagement(
            $request,
            $workspace
        );

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255'
            ],
        ]);

        $email = strtolower(
            trim($validated['email'])
        );

        $workspace->loadMissing('owner');

        if (
            strtolower(
                (string) $workspace->owner?->email
            ) === $email
        ) {
            throw ValidationException::withMessages([
                'email' =>
                    'The workspace owner is already a member of this workspace.',
            ]);
        }

        $existingUser = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        $alreadyAccepted =
            WorkspaceMember::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->where(
                    'invitation_status',
                    'accepted'
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $email,
                        $existingUser
                    ): void {
                        $query->whereRaw(
                            'LOWER(invited_email) = ?',
                            [$email]
                        );

                        if ($existingUser) {
                            $query->orWhere(
                                'user_id',
                                $existingUser->id
                            );
                        }
                    }
                )
                ->exists();

        if ($alreadyAccepted) {
            throw ValidationException::withMessages([
                'email' =>
                    'This person already has access to the workspace.',
            ]);
        }

        $rawToken = Str::random(64);

        $invitation =
            WorkspaceMember::query()
                ->where(
                    'workspace_id',
                    $workspace->id
                )
                ->whereIn(
                    'invitation_status',
                    ['pending', 'revoked']
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $email,
                        $existingUser
                    ): void {
                        $query->whereRaw(
                            'LOWER(invited_email) = ?',
                            [$email]
                        );

                        if ($existingUser) {
                            $query->orWhere(
                                'user_id',
                                $existingUser->id
                            );
                        }
                    }
                )
                ->latest('id')
                ->first()
                ?? new WorkspaceMember();

        $invitation->fill([
            'workspace_id' =>
                $workspace->id,

            'user_id' =>
                $existingUser?->id,

            'member_role' =>
                'partner',

            'invitation_status' =>
                'pending',

            'invited_email' =>
                $email,

            'invitation_token_hash' =>
                WorkspaceMember::fingerprintInvitationToken(
                    $rawToken
                ),

            'invited_by_user_id' =>
                $request->user()->id,

            'invited_at' =>
                now(),

            'accepted_at' =>
                null,

            'permissions' => [
                'decisions',
                'comments',
                'approvals',
                'documents',
            ],
        ]);

        $invitation->save();

        return redirect()
            ->route(
                'workspaces.show',
                $workspace
            )
            ->with(
                'success',
                'Partner invitation created.'
            )
            ->with(
                'invitation_link',
                route(
                    'workspace-invitations.show',
                    ['token' => $rawToken]
                )
            );
    }

    public function storeShareable(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $this->authorizeInvitationManagement(
            $request,
            $workspace
        );

        /*
         * Keep only one active shareable link
         * per workspace.
         */
        WorkspaceMember::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'invitation_status',
                'pending'
            )
            ->where(
                'invited_email',
                'like',
                '%' . self::SHAREABLE_EMAIL_DOMAIN
            )
            ->update([
                'invitation_status' =>
                    'revoked',

                'invitation_token_hash' =>
                    null,
            ]);

        $rawToken = Str::random(64);

        $sentinelEmail =
            'shareable+'
            . strtolower(Str::random(16))
            . self::SHAREABLE_EMAIL_DOMAIN;

        WorkspaceMember::create([
            'workspace_id' =>
                $workspace->id,

            'user_id' =>
                null,

            'member_role' =>
                'partner',

            'invitation_status' =>
                'pending',

            'invited_email' =>
                $sentinelEmail,

            'invitation_token_hash' =>
                WorkspaceMember::fingerprintInvitationToken(
                    $rawToken
                ),

            'invited_by_user_id' =>
                $request->user()->id,

            'invited_at' =>
                now(),

            'accepted_at' =>
                null,

            'permissions' => [
                'decisions',
                'comments',
                'approvals',
                'documents',
            ],
        ]);

        return redirect()
            ->route(
                'workspaces.show',
                $workspace
            )
            ->with(
                'success',
                'Shareable Partner Invitation Link created. This link can be used once.'
            )
            ->with(
                'invitation_link',
                route(
                    'workspace-invitations.show',
                    ['token' => $rawToken]
                )
            )
            ->with(
                'shareable_invitation',
                true
            );
    }

    public function connect(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'invitation_link' => [
                'required',
                'string',
                'max:2048',
            ],
        ]);

        $value = trim(
            $validated['invitation_link']
        );

        $token = $this->extractToken(
            $value
        );

        if (! $token) {
            throw ValidationException::withMessages([
                'invitation_link' =>
                    'Invitation Link မမှန်ပါ။ Complete link ကို ပြန်ထည့်ပါ။',
            ]);
        }

        $this->findPendingInvitation(
            $token
        );

        return redirect()->route(
            'workspace-invitations.show',
            ['token' => $token]
        );
    }

    public function show(
        Request $request,
        string $token
    ): View {
        $invitation =
            $this->findPendingInvitation(
                $token
            );

        $invitation->load([
            'workspace.owner',
            'invitedBy',
        ]);

        $isShareable =
            $this->isShareableInvitation(
                $invitation
            );

        if (! $request->user()) {
            $request->session()->put(
                'url.intended',
                route(
                    'workspace-invitations.show',
                    ['token' => $token]
                ),
            );
        }

        return view(
            'workspace-invitations.show',
            compact(
                'invitation',
                'token',
                'isShareable'
            )
        );
    }

    public function accept(
        Request $request,
        string $token
    ): RedirectResponse {
        $user = $request->user();

        $workspace = DB::transaction(
            function () use (
                $token,
                $user
            ): PartnershipWorkspace {
                $invitation =
                    WorkspaceMember::query()
                        ->where(
                            'invitation_token_hash',
                            WorkspaceMember::fingerprintInvitationToken(
                                $token
                            )
                        )
                        ->where(
                            'invitation_status',
                            'pending'
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $workspace =
                    PartnershipWorkspace::query()
                        ->findOrFail(
                            $invitation->workspace_id
                        );

                if (
                    $workspace->owner_user_id
                    === $user->id
                ) {
                    throw ValidationException::withMessages([
                        'invitation' =>
                            'You already own this workspace.',
                    ]);
                }

                $isShareable =
                    $this->isShareableInvitation(
                        $invitation
                    );

                if (
                    ! $isShareable
                    && strtolower($user->email)
                        !== strtolower(
                            (string)
                            $invitation->invited_email
                        )
                ) {
                    throw ValidationException::withMessages([
                        'invitation' =>
                            'This invitation was sent to a different email address. Log in with the invited account.',
                    ]);
                }

                $existingMembership =
                    WorkspaceMember::query()
                        ->where(
                            'workspace_id',
                            $invitation->workspace_id
                        )
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'invitation_status',
                            'accepted'
                        )
                        ->where(
                            'id',
                            '!=',
                            $invitation->id
                        )
                        ->first();

                if ($existingMembership) {
                    $invitation->update([
                        'invitation_status' =>
                            'revoked',

                        'invitation_token_hash' =>
                            null,
                    ]);

                    return $existingMembership
                        ->workspace;
                }

                $updates = [
                    'user_id' =>
                        $user->id,

                    'invitation_status' =>
                        'accepted',

                    'accepted_at' =>
                        now(),

                    'invitation_token_hash' =>
                        null,
                ];

                /*
                 * For a shareable link,
                 * replace the internal placeholder
                 * with the real accepting account.
                 */
                if ($isShareable) {
                    $updates['invited_email'] =
                        strtolower($user->email);
                }

                $invitation->update(
                    $updates
                );

                return $workspace;
            }
        );

        return redirect()
            ->route(
                'workspaces.show',
                $workspace
            )
            ->with(
                'success',
                'Workspace ချိတ်ဆက်ပြီးပါပြီ။ ယခု Partner အဖြစ် ဒီ Workspace ကို အသုံးပြုနိုင်ပါပြီ။'
            );
    }

    public function revoke(
        Request $request,
        PartnershipWorkspace $workspace,
        WorkspaceMember $invitation,
    ): RedirectResponse {
        $this->authorizeInvitationManagement(
            $request,
            $workspace
        );

        abort_unless(
            $invitation->workspace_id
                === $workspace->id,
            404
        );

        abort_unless(
            $invitation->invitation_status
                === 'pending',
            422
        );

        $invitation->update([
            'invitation_status' =>
                'revoked',

            'invitation_token_hash' =>
                null,
        ]);

        return redirect()
            ->route(
                'workspaces.show',
                $workspace
            )
            ->with(
                'success',
                'Partner invitation revoked.'
            );
    }

    private function extractToken(
        string $value
    ): ?string {
        if (
            preg_match(
                '/^[A-Za-z0-9]{64}$/',
                $value
            )
        ) {
            return $value;
        }

        $path = parse_url(
            $value,
            PHP_URL_PATH
        );

        if (! is_string($path)) {
            return null;
        }

        if (
            preg_match(
                '#/workspace-invitations/([A-Za-z0-9]{64})/?$#',
                $path,
                $matches
            )
        ) {
            return $matches[1];
        }

        return null;
    }

    private function isShareableInvitation(
        WorkspaceMember $invitation
    ): bool {
        return str_ends_with(
            strtolower(
                (string)
                $invitation->invited_email
            ),
            self::SHAREABLE_EMAIL_DOMAIN
        );
    }

    private function findPendingInvitation(
        string $token
    ): WorkspaceMember {
        return WorkspaceMember::query()
            ->where(
                'invitation_token_hash',
                WorkspaceMember::fingerprintInvitationToken(
                    $token
                )
            )
            ->where(
                'invitation_status',
                'pending'
            )
            ->firstOrFail();
    }

    private function authorizeInvitationManagement(
        Request $request,
        PartnershipWorkspace $workspace,
    ): void {
        abort_unless(
            $request->user()->isAdmin()
                || $workspace->owner_user_id
                    === $request->user()->id,
            403,
        );
    }
}
