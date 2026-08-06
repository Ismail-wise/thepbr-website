@extends('layouts.student-portal')

@section('title', 'My Workspaces')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Partnership Workspaces</span>
            <h1>Available workspaces</h1>
            <p>You can see only workspaces you own, workspaces you were invited to, or all workspaces when you are an administrator.</p>
        </div>

        <div class="auth-card">
            @forelse($workspaces as $workspace)
                <div class="auth-note">
                    <strong>{{ $workspace->name }}</strong><br>
                    Owner: {{ $workspace->owner?->name ?? 'Unknown' }}<br>
                    Your access:
                    @if($user->isAdmin())
                        Administrator
                    @elseif($workspace->owner_user_id === $user->id)
                        Owner
                    @else
                        Partner
                    @endif
                    <br><br>
                    <a href="{{ route('workspaces.show', $workspace) }}">Open workspace</a>
                </div>
            @empty
                <div class="auth-note">
                    No private workspace is currently available for this account.
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
