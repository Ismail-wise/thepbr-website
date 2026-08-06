@extends('layouts.student-portal')

@section('title', $workspace->name)

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Private Workspace</span>
            <h1>{{ $workspace->name }}</h1>
            <p>This workspace is isolated from other student workspaces. Decisions, documents, approvals, and comments will be added here in the next build phase.</p>
        </div>

        <div class="auth-card">
            <div class="auth-note">
                <strong>Owner:</strong> {{ $workspace->owner?->name ?? 'Unknown' }}
            </div>
            <div class="auth-note">
                <strong>Status:</strong> {{ ucfirst($workspace->status) }}
            </div>
            <div class="auth-note">
                <strong>Accepted members:</strong> {{ $workspace->acceptedMemberships->count() }}
            </div>
            <a class="portal-button" href="{{ route('workspaces.index') }}">Back to Workspaces</a>
        </div>
    </div>
</section>
@endsection
