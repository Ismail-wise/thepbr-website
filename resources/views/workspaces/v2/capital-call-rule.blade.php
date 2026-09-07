@extends('layouts.student-portal')

@section('title', 'Capital-Call Rule')

@section('content')

@php
    $drafts = $draftPage['items'] ?? [];
    $isActive = is_array($activeArtifact);
    $isWorking =
        ($activeArtifact['state'] ?? null)
        === 'working_draft';
    $isProposed =
        ($activeArtifact['state'] ?? null)
        === 'proposed';

    $currentPayload =
        $current['payload'] ?? null;

    $triggerConditionsText = old(
        'trigger_conditions',
        implode(
            PHP_EOL,
            is_array(
                $payload['trigger_conditions']
                ?? null
            )
                ? $payload['trigger_conditions']
                : []
        )
    );

    $procedureStepsText = old(
        'procedure_steps',
        implode(
            PHP_EOL,
            is_array(
                $payload['procedure_steps']
                ?? null
            )
                ? $payload['procedure_steps']
                : []
        )
    );

    $evidenceReferencesText = old(
        'evidence_references',
        implode(
            PHP_EOL,
            is_array(
                $payload['evidence_references']
                ?? null
            )
                ? $payload['evidence_references']
                : []
        )
    );
@endphp

<section
    class="pbr-os-page pbr-premium-tool-page pbr-premium-capital-tool-page"
    data-pbr-premium-tool
    data-pbr-tool-chapter="1"
>
    <div class="pbr-os-wrap portal-wrap">

        @include(
            'workspaces.tools.partials.premium-tool-command-bar',
            [
                'premiumToolTitleMm' => 'မတည်ငွေ ထပ်မံခေါ်ယူမှု စည်းမျဉ်း',
                'premiumToolTitleEn' => 'Capital-Call Rule',
                'premiumToolAreaMm' => 'မတည်ငွေနှင့် ရင်းနှီးငွေ',
                'premiumToolAreaEn' => 'Capital & Funding',
                'premiumToolCanManage' => $canManage,
                'premiumToolHasDraft' => $isActive,
                'premiumToolHasActiveRule' => (bool) $current,
                'premiumToolWorkspaceTarget' => 'v2-capital-call-rule-workspace',
                'premiumToolResultTarget' => 'v2-capital-call-rule-current',
                'premiumToolResultLabel' => 'Approved Result',
                'premiumToolIndexLabel' => 'Tool Library',
            ]
        )

        <header class="pbr-os-hero">
            <div>
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">
                        CHAPTER 1
                    </span>

                    <span class="pbr-os-type-pill">
                        V2 CANONICAL RULE
                    </span>

                    @if($current)
                        <span class="pbr-os-agreed-pill">
                            APPROVED CURRENT
                        </span>
                    @endif
                </div>

                <h1>Capital-Call Rule</h1>

                <p>
                    Partnership မှာ additional capital လိုအပ်လာတဲ့အခါ
                    အသုံးပြုမယ့် agreed trigger, procedure နဲ့ evidence
                    references ကို canonical rule အဖြစ် ထိန်းထားပါတယ်။
                </p>
            </div>

            <div>
                <a
                    href="{{ route('workspaces.tools.v2.capital.show', $workspace) }}"
                    class="pbr-os-btn secondary"
                >
                    ← Capital Plan
                </a>
            </div>
        </header>

        @if(session('status'))
            <div class="pbr-os-legal-note">
                <strong>Status</strong>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <section class="pbr-os-panel">
                <div class="pbr-os-panel-head">
                    <div>
                        <span class="portal-kicker">
                            VALIDATION
                        </span>

                        <h2>Rule data ကို ပြန်စစ်ပါ</h2>
                    </div>
                </div>

                <ul>
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="pbr-os-legal-note">
            <strong>Rule Boundary</strong>

            <p>
                ဒီ rule က partner percentage, equity/share allocation,
                capital-call amount formula, dilution, interest, penalty,
                voting threshold သို့မဟုတ် payment deadline ကို
                အလိုအလျောက် မဖန်တီးပါ။
            </p>
        </section>

        <div
            class="pbr-os-layout {{ $canManage ? '' : 'readonly' }}"
            id="v2-capital-call-rule-workspace"
        >
            @if($canManage)
                <aside class="pbr-os-sidebar">
                    <section class="pbr-os-side-card">
                        <span class="pbr-os-side-label">
                            Working Rules
                        </span>

                        @forelse($drafts as $draft)
                            <a
                                href="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.show',
                                    [
                                        'workspace' => $workspace,
                                        'artifact' => $draft['id'],
                                    ]
                                ) }}"
                            >
                                <strong>
                                    {{
                                        $draft['payload']['rule_name']
                                        ?? 'Untitled Rule'
                                    }}
                                </strong>

                                <small>
                                    {{ $draft['state'] }}
                                </small>
                            </a>
                        @empty
                            <p>
                                Working Rule မရှိသေးပါ။
                            </p>
                        @endforelse
                    </section>
                </aside>
            @endif

            <main class="pbr-os-main">
                @if($canManage)
                    @if(! $isActive)
                        <section class="pbr-os-panel">
                            <div class="pbr-os-panel-head">
                                <div>
                                    <span class="portal-kicker">
                                        CREATE RULE
                                    </span>

                                    <h2>Create Rule</h2>

                                    <p>
                                        New working draft တစ်ခုစပြီး
                                        formal proposal မတင်မီ
                                        procedure ကိုပြင်ဆင်နိုင်ပါတယ်။
                                    </p>
                                </div>
                            </div>

                            <form
                                method="POST"
                                action="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.store',
                                    $workspace
                                ) }}"
                            >
                                @csrf

                                <div class="pbr-os-grid">
                                    <label>
                                        <span>Rule Name</span>
                                        <input
                                            type="text"
                                            name="rule_name"
                                            maxlength="120"
                                            value="{{ old('rule_name', $payload['rule_name'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Owner Reference</span>
                                        <input
                                            type="text"
                                            name="owner_reference"
                                            maxlength="120"
                                            value="{{ old('owner_reference', $payload['owner_reference'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Effective Date</span>
                                        <input
                                            type="date"
                                            name="effective_date"
                                            value="{{ old('effective_date', $payload['effective_date'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Review Date</span>
                                        <input
                                            type="date"
                                            name="review_date"
                                            value="{{ old('review_date', $payload['review_date'] ?? '') }}"
                                            required
                                        >
                                    </label>
                                </div>

                                <label>
                                    <span>Rule Summary</span>
                                    <textarea
                                        name="rule_summary"
                                        maxlength="2000"
                                        rows="5"
                                        required
                                    >{{ old('rule_summary', $payload['rule_summary'] ?? '') }}</textarea>
                                </label>

                                <label>
                                    <span>Trigger Conditions</span>
                                    <textarea
                                        name="trigger_conditions"
                                        maxlength="50000"
                                        rows="7"
                                        required
                                    >{{ $triggerConditionsText }}</textarea>
                                    <small>
                                        One condition per line.
                                    </small>
                                </label>

                                <label>
                                    <span>Procedure Steps</span>
                                    <textarea
                                        name="procedure_steps"
                                        maxlength="50000"
                                        rows="8"
                                        required
                                    >{{ $procedureStepsText }}</textarea>
                                    <small>
                                        One procedure step per line.
                                    </small>
                                </label>

                                <label>
                                    <span>Evidence / References</span>
                                    <textarea
                                        name="evidence_references"
                                        maxlength="50000"
                                        rows="6"
                                        required
                                    >{{ $evidenceReferencesText }}</textarea>
                                    <small>
                                        One reference per line.
                                    </small>
                                </label>

                                <button
                                    type="submit"
                                    class="pbr-os-btn"
                                >
                                    Create Working Rule
                                </button>
                            </form>
                        </section>
                    @elseif($isWorking)
                        <section class="pbr-os-panel">
                            <div class="pbr-os-panel-head">
                                <div>
                                    <span class="portal-kicker">
                                        WORKING DRAFT
                                    </span>

                                    <h2>
                                        {{
                                            $payload['rule_name']
                                            ?: 'Capital-Call Rule'
                                        }}
                                    </h2>

                                    <p>
                                        Draft #{{ $activeArtifact['id'] }}
                                    </p>
                                </div>
                            </div>

                            @if($assessment)
                                <div class="pbr-os-legal-note">
                                    <strong>
                                        Readiness:
                                        {{
                                            $assessment['passed']
                                                ? 'READY'
                                                : 'NOT READY'
                                        }}
                                    </strong>

                                    @if(! $assessment['passed'])
                                        <ul>
                                            @foreach($assessment['errors'] as $issue)
                                                <li>
                                                    {{ $issue['path'] }}
                                                    —
                                                    {{ $issue['message'] }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            <form
                                method="POST"
                                action="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.update',
                                    [
                                        'workspace' => $workspace,
                                        'artifactId' => $activeArtifact['id'],
                                    ]
                                ) }}"
                            >
                                @csrf
                                @method('PUT')

                                <div class="pbr-os-grid">
                                    <label>
                                        <span>Rule Name</span>
                                        <input
                                            type="text"
                                            name="rule_name"
                                            maxlength="120"
                                            value="{{ old('rule_name', $payload['rule_name'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Owner Reference</span>
                                        <input
                                            type="text"
                                            name="owner_reference"
                                            maxlength="120"
                                            value="{{ old('owner_reference', $payload['owner_reference'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Effective Date</span>
                                        <input
                                            type="date"
                                            name="effective_date"
                                            value="{{ old('effective_date', $payload['effective_date'] ?? '') }}"
                                            required
                                        >
                                    </label>

                                    <label>
                                        <span>Review Date</span>
                                        <input
                                            type="date"
                                            name="review_date"
                                            value="{{ old('review_date', $payload['review_date'] ?? '') }}"
                                            required
                                        >
                                    </label>
                                </div>

                                <label>
                                    <span>Rule Summary</span>
                                    <textarea
                                        name="rule_summary"
                                        maxlength="2000"
                                        rows="5"
                                        required
                                    >{{ old('rule_summary', $payload['rule_summary'] ?? '') }}</textarea>
                                </label>

                                <label>
                                    <span>Trigger Conditions</span>
                                    <textarea
                                        name="trigger_conditions"
                                        maxlength="50000"
                                        rows="7"
                                        required
                                    >{{ $triggerConditionsText }}</textarea>
                                </label>

                                <label>
                                    <span>Procedure Steps</span>
                                    <textarea
                                        name="procedure_steps"
                                        maxlength="50000"
                                        rows="8"
                                        required
                                    >{{ $procedureStepsText }}</textarea>
                                </label>

                                <label>
                                    <span>Evidence / References</span>
                                    <textarea
                                        name="evidence_references"
                                        maxlength="50000"
                                        rows="6"
                                        required
                                    >{{ $evidenceReferencesText }}</textarea>
                                </label>

                                <button
                                    type="submit"
                                    class="pbr-os-btn secondary"
                                >
                                    Save Draft
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.submit',
                                    [
                                        'workspace' => $workspace,
                                        'artifactId' => $activeArtifact['id'],
                                    ]
                                ) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="pbr-os-btn"
                                >
                                    Submit Proposal
                                </button>
                            </form>
                        </section>
                    @elseif($isProposed)
                        <section class="pbr-os-panel">
                            <div class="pbr-os-panel-head">
                                <div>
                                    <span class="portal-kicker">
                                        PROPOSED RULE
                                    </span>

                                    <h2>
                                        {{ $payload['rule_name'] }}
                                    </h2>

                                    <p>
                                        Proposal #{{ $activeArtifact['id'] }}
                                        ကို edit မလုပ်ဘဲ approve
                                        သို့မဟုတ် Working Draft သို့
                                        ပြန်ပို့နိုင်ပါတယ်။
                                    </p>
                                </div>
                            </div>

                            <div class="pbr-os-metrics">
                                <article>
                                    <span>Owner Reference</span>
                                    <strong>
                                        {{ $payload['owner_reference'] }}
                                    </strong>
                                </article>

                                <article>
                                    <span>Effective Date</span>
                                    <strong>
                                        {{ $payload['effective_date'] }}
                                    </strong>
                                </article>

                                <article>
                                    <span>Review Date</span>
                                    <strong>
                                        {{ $payload['review_date'] }}
                                    </strong>
                                </article>
                            </div>

                            <div class="pbr-os-legal-note">
                                <strong>Rule Summary</strong>
                                <p>{{ $payload['rule_summary'] }}</p>
                            </div>

                            <form
                                method="POST"
                                action="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.approve',
                                    [
                                        'workspace' => $workspace,
                                        'artifactId' => $activeArtifact['id'],
                                    ]
                                ) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="pbr-os-btn"
                                >
                                    Approve &amp; Publish
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route(
                                    'workspaces.tools.v2.capital.call-rule.reject',
                                    [
                                        'workspace' => $workspace,
                                        'artifactId' => $activeArtifact['id'],
                                    ]
                                ) }}"
                            >
                                @csrf

                                <label>
                                    <span>Return Reason</span>
                                    <textarea
                                        name="reason"
                                        maxlength="2000"
                                        rows="4"
                                        required
                                    ></textarea>
                                </label>

                                <button
                                    type="submit"
                                    class="pbr-os-btn secondary"
                                >
                                    Return to Draft
                                </button>
                            </form>
                        </section>
                    @endif
                @endif

                <section
                    class="pbr-os-panel"
                    id="v2-capital-call-rule-current"
                >
                    <div class="pbr-os-panel-head">
                        <div>
                            <span class="portal-kicker">
                                APPROVED CAPITAL-CALL RULE
                            </span>

                            <h2>Approved Result</h2>
                        </div>

                        @if($current)
                            <span class="pbr-os-session-badge">
                                Revision {{ $current['revision'] }}
                            </span>
                        @endif
                    </div>

                    @if($current)
                        @if(! $canManage)
                            <div class="pbr-os-legal-note">
                                <strong>APPROVED READ-ONLY</strong>
                                <p>
                                    Accepted partner အတွက်
                                    approved-current Capital-Call Rule
                                    ကိုသာ ပြထားပါတယ်။
                                </p>
                            </div>
                        @endif

                        <div class="pbr-os-metrics">
                            <article>
                                <span>Rule Name</span>
                                <strong>
                                    {{ $currentPayload['rule_name'] }}
                                </strong>
                            </article>

                            <article>
                                <span>Owner Reference</span>
                                <strong>
                                    {{ $currentPayload['owner_reference'] }}
                                </strong>
                            </article>

                            <article>
                                <span>Effective Date</span>
                                <strong>
                                    {{ $currentPayload['effective_date'] }}
                                </strong>
                            </article>

                            <article>
                                <span>Review Date</span>
                                <strong>
                                    {{ $currentPayload['review_date'] }}
                                </strong>
                            </article>

                            <article>
                                <span>Revision</span>
                                <strong>
                                    {{ $current['revision'] }}
                                </strong>
                            </article>

                            <article>
                                <span>Published At</span>
                                <strong>
                                    {{ $current['published_at'] }}
                                </strong>
                            </article>
                        </div>

                        <div class="pbr-os-legal-note">
                            <strong>Rule Summary</strong>
                            <p>
                                {{ $currentPayload['rule_summary'] }}
                            </p>
                        </div>

                        <section>
                            <h3>Trigger Conditions</h3>
                            <ol>
                                @foreach(
                                    $currentPayload['trigger_conditions']
                                    as $condition
                                )
                                    <li>{{ $condition }}</li>
                                @endforeach
                            </ol>
                        </section>

                        <section>
                            <h3>Procedure Steps</h3>
                            <ol>
                                @foreach(
                                    $currentPayload['procedure_steps']
                                    as $step
                                )
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        </section>

                        <section>
                            <h3>Evidence / References</h3>
                            <ul>
                                @foreach(
                                    $currentPayload['evidence_references']
                                    as $reference
                                )
                                    <li>{{ $reference }}</li>
                                @endforeach
                            </ul>
                        </section>

                        @if(
                            $currentAssessment
                            && ! $currentAssessment['passed']
                        )
                            <div class="pbr-os-legal-note">
                                <strong>
                                    Approved Rule Integrity Warning
                                </strong>

                                <p>
                                    Current canonical payload က
                                    present readiness contract နဲ့
                                    မကိုက်တော့ပါ။
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="pbr-os-empty-state">
                            <div class="pbr-os-empty-icon">○</div>

                            <h2>Not approved yet</h2>

                            <p>
                                Capital-Call Rule ကို approve &amp;
                                publish လုပ်ပြီးတဲ့အခါ
                                ဒီနေရာမှာ approved current
                                အဖြစ်ပေါ်လာပါမယ်။
                            </p>
                        </div>
                    @endif
                </section>
            </main>
        </div>
    </div>
</section>

@endsection
