@extends('layouts.student-portal')

@section('title', $workspace->name)

@section('content')
<section class="auth-section">
    <div class="auth-shell compact workspace-detail-shell">
        <div class="auth-copy">
            <span class="portal-kicker">Private Workspace</span>
            <h1>{{ $workspace->name }}</h1>
            <p>This workspace is isolated from other student workspaces. Only the owner, accepted partners, and administrators can open it.</p>

            <div class="auth-note">
                <strong>Owner:</strong> {{ $workspace->owner?->name ?? 'Unknown' }}
            </div>
            <div class="auth-note">
                <strong>Status:</strong> {{ ucfirst($workspace->status) }}
            </div>
            <div class="auth-note">
                <strong>Accepted members:</strong> {{ $workspace->acceptedMemberships->count() }}
            </div>

            <a class="portal-button secondary" href="{{ route('workspaces.index') }}">Back to Workspaces</a>
        </div>

        <div class="workspace-panels">
            @if(session('invitation_link'))
                <div class="auth-card invitation-link-card">
                    <span class="portal-kicker">Invitation Ready</span>
                    <h2>Share this secure link</h2>
                    <p>The link is displayed only after creating or refreshing an invitation.</p>
                    <div class="field">
                        <label for="invitation_link">Partner Invitation Link</label>
                        <input id="invitation_link" type="text" value="{{ session('invitation_link') }}" readonly>
                        <small class="field-help">Copy the complete link and send it only to the invited partner.</small>
                    </div>
                </div>
            @endif

            @if($canManageInvitations)
                <div class="auth-card">
                    <span class="portal-kicker">Invite Partner</span>
                    <h2>Add a workspace partner</h2>
                    <p class="panel-copy">The invited person receives access to this workspace only. Student lessons and Admin Portal remain locked.</p>

                    <form method="POST" action="{{ route('workspace-invitations.store', $workspace) }}" novalidate>
                        @csrf
                        <div class="field">
                            <label for="email">Partner Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                            @error('email')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <button class="portal-button" type="submit">Create Secure Invitation Link</button>
                    </form>
                </div>
            @endif

            <div class="auth-card">
                <span class="portal-kicker">Workspace Members</span>
                <h2>Accepted access</h2>

                <div class="member-list">
                    @forelse($workspace->memberships->where('invitation_status', 'accepted') as $membership)
                        <div class="member-row">
                            <div>
                                <strong>{{ $membership->user?->name ?? $membership->invited_email }}</strong>
                                <small>{{ ucfirst($membership->member_role) }}</small>
                            </div>
                            <span class="member-status accepted">Accepted</span>
                        </div>
                    @empty
                        <p class="panel-copy">No accepted members are recorded yet.</p>
                    @endforelse
                </div>
            </div>

            @if($canManageInvitations)
                <div class="auth-card">
                    <span class="portal-kicker">Pending Invitations</span>
                    <h2>Waiting for acceptance</h2>

                    <div class="member-list">
                        @forelse($workspace->memberships->where('invitation_status', 'pending') as $invitation)
                            <div class="member-row invite-row">
                                <div>
                                    <strong>{{ $invitation->invited_email }}</strong>
                                    <small>Invited {{ $invitation->invited_at?->diffForHumans() ?? 'recently' }}</small>
                                </div>
                                <form method="POST" action="{{ route('workspace-invitations.revoke', [$workspace, $invitation]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-button danger" type="submit">Revoke</button>
                                </form>
                            </div>
                        @empty
                            <p class="panel-copy">There are no pending partner invitations.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
