@extends('layouts.student-portal')

@section('title', $workspace->name)

@section('content')
<section class="auth-section">
    <div class="auth-shell compact workspace-detail-shell">
        <div class="auth-copy">
            <span class="portal-kicker">Private Workspace</span>
            <h1>{{ $workspace->business_name ?: $workspace->name }}</h1>
            <p>ဒီ Business Workspace ရဲ့ Partner, Tools, Assessment နဲ့ Business Data တွေကို ဒီနေရာကနေ စီမံနိုင်ပါတယ်။</p>

            <div class="auth-note"><strong>Owner:</strong> {{ $workspace->owner?->name ?? 'Unknown' }}</div>
            <div class="auth-note"><strong>Status:</strong> {{ ucfirst($workspace->status) }}</div>
            <div class="auth-note"><strong>Stage:</strong> {{ \App\Models\PartnershipWorkspace::BUSINESS_STAGES[$workspace->business_stage] ?? 'Not configured' }}</div>
            <div class="auth-note"><strong>Currency:</strong> {{ $workspace->currency_code ?? 'Not set' }}</div>
            <div class="auth-note"><strong>Accepted members:</strong> {{ $workspace->acceptedMemberships->count() }}</div>

            <a class="portal-button secondary" href="{{ route('workspaces.index') }}">Back to My Businesses</a>
        </div>

        <div class="workspace-panels">
            <div class="auth-card pd-workspace-entry">
                <span class="portal-kicker">Partner Dynamics</span>
                <h2>Partnership Alignment</h2>
                <p class="panel-copy">Partner တစ်ယောက်ချင်းစီရဲ့ operating style, shared strengths, important differences နဲ့ role suggestions တွေကို ကြည့်ပါ။</p>
                <a class="portal-button" href="{{ route('workspaces.partner-dynamics.show', $workspace) }}">Open Partnership Alignment</a>
            </div>

            <div class="auth-card">
                <span class="portal-kicker">PBR Business System</span>
                <h2>Business Tools</h2>
                <p class="panel-copy">Partnership planning, finance, ownership, governance, exit နဲ့ dispute management အတွက် practical business tools တွေကို အသုံးပြုပါ။</p>
                <a class="portal-button" href="{{ route('workspaces.tools.index', $workspace) }}">Open PBR Business Tools</a>
            </div>

            @include('workspaces._decision-tools')

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
                    <span class="portal-kicker">Quick Partner Invitation</span>
                    <h2>Shareable Invitation Link</h2>
                    <p class="panel-copy">Email ကြိုထည့်စရာမလိုပါဘူး။ Link ကို Partner ဆီပို့ပါ။ Account ရှိသူက Login ဝင်ပြီး ဒီ Workspace ကို Partner အဖြစ် ချိတ်ဆက်နိုင်ပါတယ်။</p>
                    <div class="auth-note"><strong>Single-use Link</strong><br>လူတစ်ယောက် Accept လုပ်ပြီးတာနဲ့ Link က အလိုအလျောက် အသုံးမပြုနိုင်တော့ပါ။</div>
                    <form method="POST" action="{{ route('workspace-invitations.shareable.store', $workspace) }}">
                        @csrf
                        <button class="portal-button" type="submit">Create Shareable Invitation Link</button>
                    </form>
                </div>

                <div class="auth-card">
                    <span class="portal-kicker">Invite by Email</span>
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
                                    <strong>
                                        @if(str_ends_with(strtolower((string) $invitation->invited_email), '@invite.thepbr.local'))
                                            Shareable Invitation Link
                                        @else
                                            {{ $invitation->invited_email }}
                                        @endif
                                    </strong>
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
