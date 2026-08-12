@extends('layouts.student-portal')

@section('title', 'My PBR Workspace')

@section('content')
<section class="workspace-section">
    <div class="portal-wrap">
        <div class="workspace-head">
            <div>
                <span class="portal-kicker">My PBR Workspace</span>
                <h1>Welcome, {{ $user->name }}</h1>
                <p>Your course access and real Business Operating System are available from the same account.</p>
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
                <span>Course Structure</span>
                <h2>{{ $chapterCount }} Learning Chapters</h2>
                <p>The course teaches the partnership concepts. Your Business Workspace below is where you apply them to the real business.</p>
            </article>

            <article class="workspace-card">
                <span>Partner Dynamics</span>
                <h2>သင့် Partnership Operating Style ကို သိပါ</h2>
                <p>
                    သင့် strengths, decision style နဲ့ partnership ထဲမှာ
                    သဘာဝကျကျ အားသာတဲ့ role တွေကို ရှာဖွေပါ။
                </p>
                <a class="pd-inline-link"
                   href="{{ route('partner-dynamics.index') }}">
                    Start / Continue Assessment →
                </a>
            </article>

            <article class="workspace-card">
                <span>Business Operating System</span>
                <h2>{{ $workspaces->count() }} Business{{ $workspaces->count() === 1 ? '' : 'es' }} Connected</h2>
                @if($workspaces->isNotEmpty())
                    @php($firstWorkspace = $workspaces->first())
                    <p>Manage real partner rules, finance, ownership, governance, risks and decisions inside the business workspace.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $firstWorkspace) }}">
                        Open Business Operating System →
                    </a>
                @else
                    <p>Create a Business Workspace to start managing real partnership data and operating rules.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">
                        Create Business Workspace →
                    </a>
                @endif
            </article>
        </div>

        <div class="coming-panel">
            <div style="width:100%;">
                <span class="portal-kicker">Your Businesses</span>

                @if($workspaces->isNotEmpty())
                    <h2>Choose the business you want to manage</h2>
                    <p>Each Business Workspace keeps its own partners, financial data, drafts, active business rules and operating records.</p>

                    <div style="display:grid;gap:12px;margin-top:18px;">
                        @foreach($workspaces as $workspace)
                            @php($agreedCount = (int) ($agreedToolCounts[$workspace->id] ?? 0))
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid rgba(30,92,42,.14);border-radius:14px;background:#fff;flex-wrap:wrap;">
                                <div>
                                    <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                                    <div style="margin-top:4px;font-size:13px;opacity:.72;">
                                        {{ strtoupper($workspace->currency_code ?? 'THB') }} · {{ $agreedCount }} active business rule{{ $agreedCount === 1 ? '' : 's' }}
                                    </div>
                                </div>
                                <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $workspace) }}">
                                    Open Business System →
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <h2>Create your first Business Workspace</h2>
                    <p>The Business Operating System stores real calculations, partner rules and operating data separately for each business.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">Create Business Workspace →</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
