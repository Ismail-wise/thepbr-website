@extends('layouts.student-portal')

@section('title', 'My PBR Workspace')

@section('content')
<section class="workspace-section">
    <div class="portal-wrap">
        <div class="workspace-head">
            <div>
                <span class="portal-kicker">My PBR Workspace</span>
                <h1>Welcome, {{ $user->name }}</h1>
                <p>Your student account is active. Your PBR 10-chapter operating system and connected business tools are ready below.</p>
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
                <span>PBR Operating System</span>
                <h2>{{ $chapterCount }} Chapters · {{ $toolCount }} Tools</h2>
                <p>Capital, ownership, contribution, distribution, financial controls, governance, exit, continuity, share transfer and dispute-resolution tools are connected.</p>
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
                <span>Business Workspaces</span>
                <h2>{{ $workspaces->count() }} Connected</h2>
                @if($workspaces->isNotEmpty())
                    @php($firstWorkspace = $workspaces->first())
                    <p>Open your business workspace to use the full chapter and tool library.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $firstWorkspace) }}">
                        Open {{ $toolCount }} Tools →
                    </a>
                @else
                    <p>Create a Business Workspace first, then the chapter tools will connect to that business data.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">
                        Create Business Workspace →
                    </a>
                @endif
            </article>
        </div>

        <div class="coming-panel">
            <div style="width:100%;">
                <span class="portal-kicker">PBR Operating System Live</span>

                @if($workspaces->isNotEmpty())
                    <h2>Choose a Business and open its 10-chapter tool system</h2>
                    <p>Each Business Workspace keeps its own partner data, calculations, drafts, agreed outputs and operating snapshots.</p>

                    <div style="display:grid;gap:12px;margin-top:18px;">
                        @foreach($workspaces as $workspace)
                            @php($agreedCount = (int) ($agreedToolCounts[$workspace->id] ?? 0))
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid rgba(30,92,42,.14);border-radius:14px;background:#fff;flex-wrap:wrap;">
                                <div>
                                    <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                                    <div style="margin-top:4px;font-size:13px;opacity:.72;">
                                        {{ strtoupper($workspace->currency_code ?? 'THB') }} · {{ $agreedCount }} agreed tool{{ $agreedCount === 1 ? '' : 's' }}
                                    </div>
                                </div>
                                <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $workspace) }}">
                                    Open Chapters & Tools →
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <h2>Your tool library is ready — connect a Business Workspace</h2>
                    <p>The PBR tools store calculations and agreed rules by business, so a Business Workspace is required before using them.</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">Create Business Workspace →</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
