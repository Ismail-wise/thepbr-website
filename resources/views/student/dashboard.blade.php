@extends('layouts.student-portal')

@section('title', 'My PBR Workspace')

@section('content')
<section class="workspace-section">
    <div class="portal-wrap">
        <div class="workspace-head">
            <div>
                <span class="portal-kicker">My PBR Workspace</span>
                <h1>Welcome, {{ $user->name }}</h1>
                <p>Your student account is active. The tools and chapter system will be added here step by step.</p>
            </div>
            <div class="status-pill">Active Student</div>
        </div>

        <div class="workspace-grid">
            <article class="workspace-card primary-card">
                <span>Class Batch</span>
                <h2>{{ $user->classSession?->title ?? 'Not assigned' }}</h2>
                @if($user->classSession)
                    <p>{{ $user->classSession->location }} · {{ $user->classSession->time_note ?: 'Schedule to be announced' }}</p>
                @endif
            </article>

            <article class="workspace-card">
                <span>Course Progress</span>
                <h2>0 of 10 Chapters</h2>
                <div class="progress-track"><i style="width:0%"></i></div>
                <p>Your learning journey has not started yet.</p>
            </article>

            <article class="workspace-card">
                <span>Student Tools</span>
                <h2>Coming Next</h2>
                <p>Calculators, templates, decisions, approvals and document generators will appear here.</p>
            </article>
        </div>

        <div class="coming-panel">
            <div>
                <span class="portal-kicker">Portal Foundation Complete</span>
                <h2>Your secure workspace is ready</h2>
                <p>This first version confirms that code-based registration, student login and protected portal access work correctly.</p>
            </div>
        </div>
    </div>
</section>
@endsection
