@extends('layouts.student-portal')

@section('title', 'Workspace Invitation')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Partner Invitation</span>
            <h1>You have been invited to a private workspace</h1>
            <p>This invitation gives access only to the workspace shown here. It does not unlock Student Portal course materials or the Admin Portal.</p>
        </div>

        <div class="auth-card">
            <div class="auth-note first-note">
                <strong>Workspace:</strong> {{ $invitation->workspace?->name ?? 'Private Workspace' }}
            </div>
            <div class="auth-note">
                <strong>Owner:</strong> {{ $invitation->workspace?->owner?->name ?? 'Unknown' }}
            </div>
            <div class="auth-note">
                <strong>Invited email:</strong> {{ $invitation->invited_email }}
            </div>
            <div class="auth-note">
                <strong>Partner access:</strong> Decisions, comments, approvals, and documents inside this workspace only.
            </div>

            @error('invitation')
                <div class="field-error invite-error">{{ $message }}</div>
            @enderror

            @guest
                <div class="auth-note">
                    Log in with the invited email address. A new user can create a free public account first and then accept this invitation.
                </div>
                <a class="portal-button" href="{{ route('login', ['email' => $invitation->invited_email]) }}">Log In to Accept</a>
                <a class="portal-button secondary" href="{{ route('register', ['email' => $invitation->invited_email]) }}">Create Account</a>
            @else
                @if(strtolower(auth()->user()->email) === strtolower($invitation->invited_email))
                    <form method="POST" action="{{ route('workspace-invitations.accept', ['token' => $token]) }}">
                        @csrf
                        <button class="portal-button" type="submit">Accept Partner Invitation</button>
                    </form>
                @else
                    <div class="auth-note warning-note">
                        You are logged in as <strong>{{ auth()->user()->email }}</strong>. This invitation belongs to a different email address.
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="portal-button secondary" type="submit">Log Out and Use Invited Account</button>
                    </form>
                @endif
            @endguest
        </div>
    </div>
</section>
@endsection
