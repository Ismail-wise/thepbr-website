@extends('layouts.student-portal')

@section('title', 'My Account')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">My Account</span>
            <h1>Hello, {{ $user->name }}</h1>
            <p>Your account is active. The areas shown below depend on the access attached to this account.</p>
        </div>

        <div class="auth-card">
            <div class="auth-note">
                <strong>Email:</strong> {{ $user->email }}
            </div>

            @if($user->isAdmin())
                <div class="auth-note">
                    <strong>Administrator:</strong> Full system access is enabled.
                </div>
                <a class="portal-button" href="{{ url('/admin') }}">Open Admin Portal</a>
            @endif

            @if($user->isStudent())
                <div class="auth-note">
                    <strong>Student:</strong> Active Student Portal access is enabled.
                </div>
                <a class="portal-button" href="{{ route('student.dashboard') }}">Open Student Portal</a>
            @endif

            @if($user->isAdmin() || $user->ownedWorkspaces->isNotEmpty() || $user->workspaceMemberships->where('invitation_status', 'accepted')->isNotEmpty())
                <div class="auth-note">
                    <strong>Workspace:</strong> You can open only workspaces permitted for this account.
                </div>
                <a class="portal-button" href="{{ route('workspaces.index') }}">Open Workspaces</a>
            @endif

            @unless($user->isAdmin() || $user->isStudent())
                <div class="auth-note">
                    Have a Student Access Code? Upgrade this existing account without registering again.
                </div>
                <a class="portal-button" href="{{ route('account.access-code.show') }}">Redeem Student Access Code</a>
            @endunless

            @unless($user->isAdmin() || $user->isStudent() || $user->workspaceMemberships->where('invitation_status', 'accepted')->isNotEmpty())
                <div class="auth-note">
                    This is currently a public account. Student Portal and private workspaces remain locked.
                </div>
            @endunless
        </div>
    </div>
</section>
@endsection
