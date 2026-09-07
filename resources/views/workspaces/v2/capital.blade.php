@extends('layouts.student-portal')

@section('title', 'Capital Plan Authority')

@section('content')

@php
    $drafts = $draftPage['items'] ?? [];
    $isActive = is_array($activeArtifact);
    $isWorking = ($activeArtifact['state'] ?? null) === 'working_draft';
    $isProposed = ($activeArtifact['state'] ?? null) === 'proposed';

    $assumptions = $payload['assumptions'] ?? [];
    $decision = $payload['decision_record'] ?? [];
    $shortfall = $payload['shortfall_response'] ?? null;

    $costItems = old('cost_items', $payload['cost_items'] ?? []);

    if (! is_array($costItems) || $costItems === []) {
        $costItems = [[
            'id' => 'cost-1',
            'category' => 'pre_opening',
            'description' => '',
            'amount' => '',
            'basis' => 'one_time',
            'evidence_reference' => '',
        ]];
    }

    $fundingSources = old(
        'funding_sources',
        $payload['funding_sources'] ?? []
    );

    if (! is_array($fundingSources) || $fundingSources === []) {
        $fundingSources = [[
            'id' => 'funding-1',
            'source_type' => 'partner_cash',
            'description' => '',
            'confirmed_amount' => '',
            'confirmation_date' => '',
            'evidence_reference' => '',
        ]];
    }

    $currentPayload = $current['payload'] ?? null;
    $currentCalc = $currentAssessment['calculations'] ?? null;
    $draftCalc = $assessment['calculations'] ?? null;

    $comparisonPlans = is_array($comparison['plans'] ?? null)
        ? $comparison['plans']
        : [];

    $comparisonMissing = is_array(
        $comparison['missing_scenarios'] ?? null
    )
        ? $comparison['missing_scenarios']
        : [];

    $decisionRecords = is_array(
        $decisionRecords ?? null
    )
        ? $decisionRecords
        : [];

    $currentDecisionRecorded = (bool) (
        $currentDecisionRecorded ?? false
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
                'premiumToolTitleMm' => 'မတည်ငွေ အတည်ပြုအစီအစဉ်',
                'premiumToolTitleEn' => 'Capital Plan Authority',
                'premiumToolAreaMm' => 'မတည်ငွေနှင့် ရင်းနှီးငွေ',
                'premiumToolAreaEn' => 'Capital & Funding',
                'premiumToolCanManage' => $canManage,
                'premiumToolHasDraft' => $isActive,
                'premiumToolHasActiveRule' => (bool) $current,
                'premiumToolWorkspaceTarget' => 'v2-capital-workspace',
                'premiumToolResultTarget' => 'v2-capital-current',
            ]
        )

        <header class="pbr-os-hero">
            <div>
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">
                        CHAPTER 1
                    </span>
                    <span class="pbr-os-type-pill">
                        V2 CANONICAL PLAN
                    </span>

                    @if($current)
                        <span class="pbr-os-agreed-pill">
                            APPROVED CURRENT
                        </span>
                    @endif
                </div>

                <h1>မတည်ငွေ အတည်ပြုအစီအစဉ်</h1>
                <p class="pbr-os-en-title">
                    Capital Plan Authority
                </p>

                <p class="pbr-os-purpose">
                    Working Draft မှစပြီး Readiness စစ်ဆေးခြင်း၊
                    Formal Proposal တင်ခြင်းနဲ့ Approved Current
                    Capital Plan အဖြစ် publish လုပ်ခြင်းကို
                    V2 canonical workflow တစ်ခုတည်းနဲ့ ထိန်းချုပ်ပါတယ်။
                </p>
            </div>

            <aside class="pbr-os-business-context">
                <span>Current Workspace</span>
                <strong>
                    {{ $workspace->business_name ?: $workspace->name }}
                </strong>

                <div>
                    <small>
                        {{ ucfirst((string) $workspace->business_stage) }}
                    </small>

                    @if($canManage)
                        <small>Management View</small>
                    @else
                        <small>Approved Read-only</small>
                    @endif
                </div>
            </aside>
        </header>

        @if(session('status'))
            <div class="pbr-os-alert success">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="pbr-form-errors">
                <strong>
                    အချက်အလက်တချို့ကို ပြန်စစ်ပေးပါ။
                </strong>

                <p>{{ $errors->first() }}</p>
            </div>
        @endif

        @unless($canManage)
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>
                        Approved Current Plan ကိုသာ ကြည့်ရှုနိုင်ပါသည်
                    </strong>
                    <p>
                        Partner view မှာ Working Draft၊ Proposal
                        သို့မဟုတ် private management metadata ကို
                        မဖော်ပြပါ။
                    </p>
                </div>

                <span>Permission Safe</span>
            </div>
        @endunless

        <div
            class="pbr-os-layout {{ $canManage ? '' : 'readonly' }}"
            id="v2-capital-workspace"
        >
            @if($canManage)
                <aside class="pbr-os-sidebar">
                    <section class="pbr-os-side-card">
                        <span class="pbr-os-side-label">
                            V2 Lifecycle
                        </span>

                        <ol class="pbr-os-steps">
                            <li class="{{ ! $isActive ? 'active' : '' }}">
                                <span>1</span>
                                <div>
                                    <b>Working Draft</b>
                                    <small>
                                        Full capital-plan-v1 data ကို
                                        ပြင်ဆင်ပါ။
                                    </small>
                                </div>
                            </li>

                            <li class="{{ $isWorking ? 'active' : '' }}">
                                <span>2</span>
                                <div>
                                    <b>Readiness</b>
                                    <small>
                                        Required capital, funding gap
                                        နဲ့ decision evidence စစ်ပါ။
                                    </small>
                                </div>
                            </li>

                            <li class="{{ $isProposed ? 'active' : '' }}">
                                <span>3</span>
                                <div>
                                    <b>Proposal</b>
                                    <small>
                                        Ready draft ကို formal proposal
                                        အဖြစ် lock လုပ်ပါ။
                                    </small>
                                </div>
                            </li>

                            <li class="{{ $current ? 'active' : '' }}">
                                <span>4</span>
                                <div>
                                    <b>Approved Current</b>
                                    <small>
                                        Approval Engine က publish
                                        လုပ်ထားတဲ့ canonical plan။
                                    </small>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section class="pbr-os-side-card">
                        <div class="pbr-os-side-head">
                            <strong>Working Plans</strong>

                            <a
                                href="{{ route('workspaces.tools.v2.capital.show', $workspace) }}"
                            >
                                + New
                            </a>
                        </div>

                        @forelse($drafts as $draft)
                            <a
                                class="pbr-os-draft-link {{ (int) ($activeArtifact['id'] ?? 0) === (int) $draft['id'] ? 'active' : '' }}"
                                href="{{ route('workspaces.tools.v2.capital.show', ['workspace' => $workspace, 'artifact' => $draft['id']]) }}"
                            >
                                <span>
                                    {{ $draft['payload']['plan_name'] ?: 'Untitled Capital Plan' }}
                                </span>
                                <small>
                                    {{ strtoupper($draft['state']) }}
                                    · ID {{ $draft['id'] }}
                                </small>
                            </a>
                        @empty
                            <p class="pbr-os-empty-small">
                                V2 Capital Draft မရှိသေးပါ။
                            </p>
                        @endforelse
                    </section>
                </aside>
            @endif

            <main class="pbr-os-main">

                @if($current)
                    <section
                        class="pbr-os-panel pbr-os-current-rule"
                        id="v2-capital-current"
                    >
                        <div>
                            <span class="portal-kicker">
                                APPROVED CURRENT
                            </span>

                            <h2>
                                {{ $currentPayload['plan_name'] }}
                            </h2>

                            <p>
                                Revision {{ $current['revision'] }}
                                · {{ ucfirst($currentPayload['scenario_type']) }}
                                · Published {{ $current['published_at'] }}
                            </p>
                        </div>

                        <div>
                            <strong>
                                {{ $currentPayload['currency_code'] }}
                                {{ $currentCalc['total_required'] ?? '—' }}
                            </strong>
                        </div>
                    </section>

                    <section class="pbr-os-panel pbr-os-result-panel">
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">
                                    CURRENT CAPITAL POSITION
                                </span>
                                <h2>Approved Result</h2>
                                <p>
                                    Workspace ရဲ့ current currency နဲ့
                                    relabel မလုပ်ဘဲ published payload
                                    ထဲက currency ကိုပဲ ပြထားပါတယ်။
                                </p>
                            </div>

                            <span class="pbr-os-session-badge">
                                Rev {{ $current['revision'] }}
                            </span>
                        </div>

                        @if($currentCalc)
                            <div class="pbr-os-metrics">
                                <article>
                                    <span>Total Required</span>
                                    <strong>
                                        {{ $currentPayload['currency_code'] }}
                                        {{ $currentCalc['total_required'] }}
                                    </strong>
                                </article>

                                <article>
                                    <span>Confirmed Funding</span>
                                    <strong>
                                        {{ $currentPayload['currency_code'] }}
                                        {{ $currentCalc['confirmed_funding'] }}
                                    </strong>
                                </article>

                                <article>
                                    <span>Funding Gap</span>
                                    <strong>
                                        {{ $currentPayload['currency_code'] }}
                                        {{ $currentCalc['funding_gap'] }}
                                    </strong>
                                </article>

                                <article>
                                    <span>Contingency</span>
                                    <strong>
                                        {{ $currentPayload['currency_code'] }}
                                        {{ $currentCalc['contingency_reserve'] }}
                                    </strong>
                                </article>
                            </div>
                        @endif

                        <div class="pbr-os-result-table-wrap">
                            <div class="pbr-os-table-head">
                                <h3>Approved Cost Items</h3>
                                <span>
                                    {{ count($currentPayload['cost_items'] ?? []) }}
                                    items
                                </span>
                            </div>

                            <div class="pbr-os-table-scroll">
                                <table class="pbr-os-result-table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Category</th>
                                            <th>Basis</th>
                                            <th>Amount</th>
                                            <th>Evidence</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach($currentPayload['cost_items'] ?? [] as $item)
                                            <tr>
                                                <td>{{ $item['description'] }}</td>
                                                <td>{{ $item['category'] }}</td>
                                                <td>{{ $item['basis'] }}</td>
                                                <td>
                                                    {{ $currentPayload['currency_code'] }}
                                                    {{ $item['amount'] }}
                                                </td>
                                                <td>
                                                    {{ $item['evidence_reference'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @elseif(! $canManage)
                    <section class="pbr-os-panel pbr-os-empty-state">
                        <div class="pbr-os-empty-icon">✓</div>
                        <h2>
                            Approved Capital Plan မရှိသေးပါ
                        </h2>
                        <p>
                            Management က V2 Capital Plan ကို
                            approve & publish လုပ်ပြီးတဲ့အခါ
                            ဒီနေရာမှာပဲ မြင်ရပါမယ်။
                        </p>
                    </section>
                @endif

                @if($canManage)
                    <section
                        class="pbr-os-panel"
                        id="v2-capital-decision-record"
                    >
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">
                                    CAPITAL DECISION RECORD
                                </span>

                                <h2>
                                    Approved Decision Record
                                </h2>

                                <p>
                                    Approved Capital Plan ကို
                                    immutable append-only operating
                                    record အဖြစ် မှတ်တမ်းတင်ပြီး
                                    ဆုံးဖြတ်ချက် evidence ကို
                                    audit လုပ်နိုင်အောင်ထားပါတယ်။
                                </p>
                            </div>

                            <span class="pbr-os-session-badge">
                                {{ count($decisionRecords) }}
                                {{ count($decisionRecords) === 1 ? 'Record' : 'Records' }}
                            </span>
                        </div>

                        @if(
                            $current
                            && ! $currentDecisionRecorded
                        )
                            <div class="pbr-os-approval-zone">
                                <div>
                                    <span>Record Boundary</span>

                                    <h3>
                                        Approved Revision
                                        {{ $current['revision'] }}
                                        ကို Decision Record အဖြစ်
                                        မှတ်တမ်းတင်မလား?
                                    </h3>

                                    <p>
                                        ဒီ action က approved plan
                                        payload ထဲက Decision Owner,
                                        Effective / Review Date,
                                        Summary နဲ့ Evidence ကို
                                        exact canonical revision နဲ့
                                        pin လုပ်ပြီး immutable record
                                        တစ်ခုဖန်တီးပါတယ်။
                                    </p>
                                </div>

                                <div class="pbr-os-approval-actions">
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'workspaces.tools.v2.capital.decision-record.store',
                                            $workspace
                                        ) }}"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="pbr-os-btn approve"
                                        >
                                            Record Decision
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @elseif(
                            $current
                            && $currentDecisionRecorded
                        )
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    ✓
                                </div>

                                <div>
                                    <h3>
                                        Current approved revision
                                        recorded
                                    </h3>

                                    <p>
                                        Revision
                                        {{ $current['revision'] }}
                                        အတွက် immutable Decision
                                        Record ရှိပြီးသားဖြစ်ပါတယ်။
                                        Same revision ကို duplicate
                                        record ထပ်မဖန်တီးပါ။
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Approved decision မရှိသေးပါ
                                    </h3>

                                    <p>
                                        Capital Plan ကို approve &
                                        publish လုပ်ပြီးမှ Decision
                                        Record ဖန်တီးနိုင်ပါတယ်။
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($decisionRecords !== [])
                            <div class="pbr-os-result-table-wrap">
                                <div class="pbr-os-table-head">
                                    <h3>
                                        Immutable Decision Records
                                    </h3>

                                    <span>
                                        Newest record first
                                    </span>
                                </div>

                                <div class="pbr-os-table-scroll">
                                    <table class="pbr-os-result-table">
                                        <thead>
                                            <tr>
                                                <th>Record</th>
                                                <th>Owner</th>
                                                <th>Status</th>
                                                <th>Effective Date</th>
                                                <th>Review Date</th>
                                                <th>Decision Summary</th>
                                                <th>Evidence / Reference</th>
                                                <th>Approved By</th>
                                                <th>Approval Date</th>
                                                <th>Last Updated</th>
                                                <th>Source</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($decisionRecords as $record)
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            {{ $record['title'] }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        {{ $record['payload']['owner_reference'] }}
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            {{ strtoupper($record['state']) }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        {{ $record['payload']['effective_date'] }}
                                                    </td>

                                                    <td>
                                                        {{ $record['payload']['review_date'] }}
                                                    </td>

                                                    <td>
                                                        {{ $record['payload']['decision_summary'] }}
                                                    </td>

                                                    <td>
                                                        {{ implode(
                                                            ' · ',
                                                            $record['payload']['evidence_references']
                                                                ?? []
                                                        ) }}
                                                    </td>

                                                    <td>
                                                        User #{{ $record['payload']['approved_by_user_id'] }}
                                                    </td>

                                                    <td>
                                                        {{ $record['payload']['approved_at'] }}
                                                    </td>

                                                    <td>
                                                        {{ $record['recorded_at'] }}
                                                    </td>

                                                    <td>
                                                        Revision
                                                        {{ $record['source']['revision'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Decision Record history
                                        မရှိသေးပါ
                                    </h3>

                                    <p>
                                        Approved decision ကို
                                        Record Decision လုပ်ပြီးတဲ့အခါ
                                        immutable record ကို
                                        ဒီနေရာမှာပြပါမယ်။
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="pbr-os-legal-note">
                            <strong>
                                Decision Record Boundary
                            </strong>

                            <p>
                                ဒီ section က append-only operating
                                record ဖြစ်ပြီး Version History
                                မဟုတ်ပါ။ Record ဖန်တီးခြင်းက
                                approved canonical plan ကို
                                ပြန်မပြင်သလို approval authority
                                အသစ်လည်းမဖန်တီးပါ။
                            </p>
                        </div>
                    </section>

                    <section
                        class="pbr-os-panel"
                        id="v2-capital-action-plan"
                    >
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">
                                    ACT
                                </span>

                                <h2>
                                    Capital Action Plan
                                </h2>

                                <p>
                                    Immutable Capital Decision Record
                                    ကို execution items အဖြစ်
                                    ပြောင်းပြီး Owner, Priority,
                                    Due Date နဲ့ lifecycle status
                                    ကို track လုပ်နိုင်ပါတယ်။
                                </p>
                            </div>

                            <span class="pbr-os-session-badge">
                                Record → Act
                            </span>
                        </div>

                        @if($decisionRecords !== [])
                            <div class="pbr-os-approval-zone">
                                <div>
                                    <span>
                                        Execution Boundary
                                    </span>

                                    <h3>
                                        Recorded decision ကို
                                        Action Plan အဖြစ် execute
                                        လုပ်နိုင်ပါပြီ
                                    </h3>

                                    <p>
                                        Action item တစ်ခုစီက exact
                                        Capital Decision Record နဲ့
                                        approved Capital Plan revision
                                        source evidence ကို pin ထားပါတယ်။
                                    </p>
                                </div>

                                <div class="pbr-os-approval-actions">
                                    <a
                                        class="pbr-os-btn approve"
                                        href="{{ route(
                                            'workspaces.tools.v2.capital.action-plan.show',
                                            $workspace
                                        ) }}"
                                    >
                                        Open Action Plan
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Record phase ကို
                                        အရင် complete လုပ်ပါ
                                    </h3>

                                    <p>
                                        Approved Capital Plan ကို
                                        Decision Record အဖြစ်
                                        မှတ်တမ်းတင်ပြီးမှ ACT phase မှာ
                                        Action items ဖန်တီးနိုင်ပါတယ်။
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="pbr-os-legal-note">
                            <strong>
                                Action Plan Boundary
                            </strong>

                            <p>
                                ACT က approved canonical Capital Plan
                                သို့မဟုတ် immutable Decision Record
                                ကို ပြန်မပြင်ပါ။
                                Execution mutation ကို frozen
                                F12 → F9 → F5 lifecycle authority
                                ကသာ ထိန်းချုပ်ပါတယ်။
                            </p>
                        </div>
                    </section>

                    <section
                        class="pbr-os-panel"
                        id="v2-capital-history"
                    >
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">
                                    VERSION HISTORY
                                </span>

                                <h2>
                                    Approved Capital Plan Versions
                                </h2>

                                <p>
                                    Approved Current နဲ့ superseded
                                    canonical Capital Plan revisions
                                    တွေကို newest-first အစဉ်နဲ့
                                    ပြထားပါတယ်။
                                </p>
                            </div>

                            <span class="pbr-os-session-badge">
                                {{ count($history) }}
                                {{ count($history) === 1 ? 'Revision' : 'Revisions' }}
                            </span>
                        </div>

                        @if($history !== [])
                            <div class="pbr-os-result-table-wrap">
                                <div class="pbr-os-table-head">
                                    <h3>
                                        Canonical Revision History
                                    </h3>

                                    <span>
                                        Approved versions only
                                    </span>
                                </div>

                                <div class="pbr-os-table-scroll">
                                    <table class="pbr-os-result-table">
                                        <thead>
                                            <tr>
                                                <th>Revision</th>
                                                <th>Plan</th>
                                                <th>Scenario</th>
                                                <th>State</th>
                                                <th>Currency</th>
                                                <th>Published</th>
                                                <th>Superseded</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($history as $version)
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            Revision {{ $version['revision'] }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        {{ $version['payload']['plan_name']
                                                            ?? 'Untitled Capital Plan' }}
                                                    </td>

                                                    <td>
                                                        {{ ucfirst(
                                                            (string) (
                                                                $version['payload']['scenario_type']
                                                                ?? '—'
                                                            )
                                                        ) }}
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            {{ $version['state'] === 'approved_current'
                                                                ? 'CURRENT'
                                                                : 'SUPERSEDED' }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        {{ $version['payload']['currency_code']
                                                            ?? '—' }}
                                                    </td>

                                                    <td>
                                                        {{ $version['published_at'] ?? '—' }}
                                                    </td>

                                                    <td>
                                                        {{ $version['superseded_at'] ?? '—' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Approved version history မရှိသေးပါ
                                    </h3>

                                    <p>
                                        Capital Plan တစ်ခု approve &
                                        publish ဖြစ်လာတဲ့အခါ canonical
                                        revision history ကို ဒီနေရာမှာ
                                        ပြပါမယ်။
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="pbr-os-legal-note">
                            <strong>
                                Canonical Version History Boundary
                            </strong>

                            <p>
                                ဒီနေရာက approved Capital Plan ရဲ့
                                read-only revision evidence ပဲဖြစ်ပြီး
                                Capital Decision Record မဟုတ်ပါ။
                                Version တစ်ခုကိုကြည့်ခြင်းက approval,
                                publication သို့မဟုတ် action authority
                                အသစ်မဖန်တီးပါ။
                            </p>
                        </div>
                    </section>

                    <section
                        class="pbr-os-panel"
                        id="v2-capital-comparison"
                    >
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">
                                    PLAN COMPARISON
                                </span>

                                <h2>
                                    Lean / Base / Growth နှိုင်းယှဉ်မှု
                                </h2>

                                <p>
                                    Formal Proposal မတင်ခင်
                                    latest Working Plan သုံးမျိုးကို
                                    တူညီတဲ့ V2 Readiness calculation
                                    နဲ့ side-by-side နှိုင်းယှဉ်ပါ။
                                </p>
                            </div>

                            <span class="pbr-os-session-badge">
                                {{ count($comparisonPlans) }}/3 Scenarios
                            </span>
                        </div>

                        @if($comparisonPlans !== [])
                            <div class="pbr-os-result-table-wrap">
                                <div class="pbr-os-table-head">
                                    <h3>
                                        Capital Plan Comparison
                                    </h3>

                                    <span>
                                        Latest candidate per scenario
                                    </span>
                                </div>

                                <div class="pbr-os-table-scroll">
                                    <table class="pbr-os-result-table">
                                        <thead>
                                            <tr>
                                                <th>Scenario</th>
                                                <th>Plan</th>
                                                <th>State</th>
                                                <th>Working Capital</th>
                                                <th>Contingency</th>
                                                <th>Total Required</th>
                                                <th>Funding Gap</th>
                                                <th>Readiness</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($comparisonPlans as $plan)
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            {{ strtoupper($plan['scenario_type']) }}
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        <a
                                                            href="{{ route(
                                                                'workspaces.tools.v2.capital.show',
                                                                [
                                                                    'workspace' => $workspace,
                                                                    'artifact' => $plan['artifact_id'],
                                                                ]
                                                            ) }}"
                                                        >
                                                            {{ $plan['plan_name'] !== ''
                                                                ? $plan['plan_name']
                                                                : 'Untitled Capital Plan' }}
                                                        </a>
                                                    </td>

                                                    <td>
                                                        {{ strtoupper($plan['state']) }}
                                                    </td>

                                                    <td>
                                                        @if($plan['working_capital_months'] !== null)
                                                            {{ $plan['working_capital_months'] }}
                                                            months
                                                        @else
                                                            —
                                                        @endif
                                                    </td>

                                                    <td>
                                                        @if($plan['contingency_percentage'] !== null)
                                                            {{ $plan['contingency_percentage'] }}%
                                                        @else
                                                            —
                                                        @endif
                                                    </td>

                                                    <td>
                                                        @if($plan['total_required'] !== null)
                                                            {{ $plan['currency_code'] }}
                                                            {{ $plan['total_required'] }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>

                                                    <td>
                                                        @if($plan['funding_gap'] !== null)
                                                            {{ $plan['currency_code'] }}
                                                            {{ $plan['funding_gap'] }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            {{ $plan['readiness_passed']
                                                                ? 'READY'
                                                                : 'NOT READY' }}
                                                        </strong>

                                                        @unless($plan['readiness_passed'])
                                                            <small>
                                                                {{ $plan['readiness_error_count'] }}
                                                                error(s)
                                                            </small>
                                                        @endunless
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Comparison candidate မရှိသေးပါ
                                    </h3>

                                    <p>
                                        Lean, Base သို့မဟုတ် Growth
                                        Working Draft တစ်ခုစီကို
                                        တည်ဆောက်ပြီး ဒီနေရာမှာ
                                        နှိုင်းယှဉ်နိုင်ပါတယ်။
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($comparisonMissing !== [])
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">
                                    i
                                </div>

                                <div>
                                    <h3>
                                        Missing scenarios
                                    </h3>

                                    <p>
                                        @foreach($comparisonMissing as $missing)
                                            {{ ucfirst($missing) }}@if(! $loop->last), @endif
                                        @endforeach
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="pbr-os-legal-note">
                            <strong>
                                Comparison Authority Boundary
                            </strong>

                            <p>
                                ဒီ comparison က read-only scenario
                                analysis ပဲဖြစ်ပြီး canonical authority
                                မဟုတ်ပါ။ Plan ကိုဖွင့်ကြည့်ခြင်း သို့မဟုတ်
                                နှိုင်းယှဉ်ခြင်းက approval / publication
                                မဖြစ်စေပါ။
                            </p>
                        </div>
                    </section>

                    @if($isProposed)
                        <section class="pbr-os-panel">
                            <div class="pbr-os-panel-head">
                                <div>
                                    <span class="portal-kicker">
                                        FORMAL PROPOSAL
                                    </span>
                                    <h2>
                                        {{ $payload['plan_name'] }}
                                    </h2>
                                    <p>
                                        Proposed state မှာ payload
                                        ကိုပြင်လို့မရပါ။ Approve လုပ်မယ်
                                        သို့မဟုတ် Working Draft အဖြစ်
                                        ပြန်ပို့ရပါမယ်။
                                    </p>
                                </div>

                                <span class="pbr-os-session-badge">
                                    PROPOSED
                                </span>
                            </div>

                            @if($draftCalc)
                                <div class="pbr-os-metrics">
                                    <article>
                                        <span>Total Required</span>
                                        <strong>
                                            {{ $payload['currency_code'] }}
                                            {{ $draftCalc['total_required'] }}
                                        </strong>
                                    </article>

                                    <article>
                                        <span>Confirmed</span>
                                        <strong>
                                            {{ $payload['currency_code'] }}
                                            {{ $draftCalc['confirmed_funding'] }}
                                        </strong>
                                    </article>

                                    <article>
                                        <span>Gap</span>
                                        <strong>
                                            {{ $payload['currency_code'] }}
                                            {{ $draftCalc['funding_gap'] }}
                                        </strong>
                                    </article>

                                    <article>
                                        <span>Coverage</span>
                                        <strong>
                                            {{ $draftCalc['funded_percentage'] }}%
                                        </strong>
                                    </article>
                                </div>
                            @endif

                            <div class="pbr-os-result-table-wrap">
                                <div class="pbr-os-table-head">
                                    <h3>Proposal Cost Items</h3>
                                </div>

                                <div class="pbr-os-table-scroll">
                                    <table class="pbr-os-result-table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Category</th>
                                                <th>Basis</th>
                                                <th>Amount</th>
                                                <th>Evidence</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($payload['cost_items'] ?? [] as $item)
                                                <tr>
                                                    <td>{{ $item['description'] }}</td>
                                                    <td>{{ $item['category'] }}</td>
                                                    <td>{{ $item['basis'] }}</td>
                                                    <td>
                                                        {{ $payload['currency_code'] }}
                                                        {{ $item['amount'] }}
                                                    </td>
                                                    <td>
                                                        {{ $item['evidence_reference'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="pbr-os-approval-zone">
                                <div>
                                    <span>Approval Boundary</span>
                                    <h3>
                                        Canonical Current အဖြစ် publish လုပ်မလား?
                                    </h3>
                                    <p>
                                        ဒီ approval က technical workspace
                                        management authority ဖြစ်ပြီး
                                        partner voting / consent record
                                        ကို ကိုယ်စားမပြုပါ။
                                    </p>
                                </div>

                                <div class="pbr-os-approval-actions">
                                    <form
                                        method="POST"
                                        action="{{ route('workspaces.tools.v2.capital.approve', [$workspace, $activeArtifact['id']]) }}"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="pbr-os-btn approve"
                                        >
                                            Approve & Publish
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('workspaces.tools.v2.capital.reject', [$workspace, $activeArtifact['id']]) }}"
                                    >
                                        @csrf

                                        <input
                                            type="text"
                                            name="reason"
                                            maxlength="2000"
                                            placeholder="Revision reason"
                                            required
                                        >

                                        <button
                                            type="submit"
                                            class="pbr-os-btn secondary"
                                        >
                                            Return to Draft
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </section>
                    @else
                        <form
                            class="pbr-os-form"
                            method="POST"
                            action="{{ $isWorking
                                ? route('workspaces.tools.v2.capital.update', [$workspace, $activeArtifact['id']])
                                : route('workspaces.tools.v2.capital.store', $workspace) }}"
                        >
                            @csrf

                            @if($isWorking)
                                @method('PUT')
                            @endif

                            <section class="pbr-os-panel pbr-os-input-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            CAPITAL PLAN V1
                                        </span>

                                        <h2>
                                            {{ $isWorking
                                                ? 'Working Draft ကို ပြင်ဆင်ရန်'
                                                : 'V2 Capital Plan အသစ်' }}
                                        </h2>

                                        <p>
                                            Legacy Startup Capital data ကို
                                            auto-convert မလုပ်ပါ။
                                            Canonical approval အတွက်လိုတဲ့
                                            evidence နဲ့ decision fields ကို
                                            ဒီမှာ explicit ဖြည့်ပါ။
                                        </p>
                                    </div>

                                    @if($isWorking)
                                        <span class="pbr-os-session-badge">
                                            Draft #{{ $activeArtifact['id'] }}
                                        </span>
                                    @endif
                                </div>

                                <div class="pbr-os-fields">
                                    <div class="pbr-os-field">
                                        <label>
                                            Plan Name
                                            <span>
                                                Lean / Base / Growth plan အမည်
                                            </span>
                                        </label>

                                        <input
                                            type="text"
                                            name="plan_name"
                                            maxlength="120"
                                            value="{{ old('plan_name', $payload['plan_name'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Scenario Type
                                            <span>
                                                Plan comparison identity
                                            </span>
                                        </label>

                                        @php
                                            $scenario = old(
                                                'scenario_type',
                                                $payload['scenario_type'] ?? ''
                                            );
                                        @endphp

                                        <select name="scenario_type">
                                            <option value="">
                                                Select
                                            </option>
                                            <option value="lean" @selected($scenario === 'lean')>
                                                Lean
                                            </option>
                                            <option value="base" @selected($scenario === 'base')>
                                                Base
                                            </option>
                                            <option value="growth" @selected($scenario === 'growth')>
                                                Growth
                                            </option>
                                            <option value="custom" @selected($scenario === 'custom')>
                                                Custom
                                            </option>
                                        </select>
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Assessment Date
                                        </label>

                                        <input
                                            type="date"
                                            name="assessment_date"
                                            value="{{ old('assessment_date', $payload['assessment_date'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Opening Date
                                            <span>
                                                New business အတွက် required
                                            </span>
                                        </label>

                                        <input
                                            type="date"
                                            name="opening_date"
                                            value="{{ old('opening_date', $assumptions['opening_date'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Working Capital Months
                                        </label>

                                        <input
                                            type="number"
                                            name="working_capital_months"
                                            min="1"
                                            max="24"
                                            value="{{ old('working_capital_months', $assumptions['working_capital_months'] ?: '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Monthly Burn
                                        </label>

                                        <input
                                            type="number"
                                            name="monthly_burn"
                                            min="0"
                                            step="0.01"
                                            value="{{ old('monthly_burn', $assumptions['monthly_burn'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Contingency Percentage
                                        </label>

                                        <input
                                            type="number"
                                            name="contingency_percentage"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value="{{ old('contingency_percentage', $assumptions['contingency_percentage'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>
                                            Canonical Currency
                                        </label>

                                        <input
                                            type="text"
                                            value="{{ $workspace->currency_code }}"
                                            disabled
                                        >
                                    </div>
                                </div>
                            </section>

                            <section class="pbr-os-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            COST REQUIREMENT
                                        </span>
                                        <h2>
                                            Capital Cost Items
                                        </h2>
                                        <p>
                                            Pre-opening, initial assets /
                                            inventory နဲ့ working capital
                                            ကို ခွဲထားပါ။
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="pbr-os-add-row"
                                        data-v2-add-cost
                                    >
                                        + Cost Item
                                    </button>
                                </div>

                                <div
                                    class="pbr-os-repeater-rows"
                                    data-v2-cost-list
                                >
                                    @foreach($costItems as $index => $item)
                                        <div
                                            class="pbr-os-repeater-row"
                                            style="--pbr-cols: 5"
                                            data-v2-row
                                        >
                                            <input
                                                type="hidden"
                                                name="cost_items[{{ $index }}][id]"
                                                value="{{ $item['id'] ?? 'cost-'.($index + 1) }}"
                                            >

                                            <div class="pbr-os-mini-field">
                                                <label>Category</label>
                                                <select
                                                    name="cost_items[{{ $index }}][category]"
                                                >
                                                    <option
                                                        value="pre_opening"
                                                        @selected(($item['category'] ?? '') === 'pre_opening')
                                                    >
                                                        Pre-opening
                                                    </option>
                                                    <option
                                                        value="initial_assets_inventory"
                                                        @selected(($item['category'] ?? '') === 'initial_assets_inventory')
                                                    >
                                                        Assets / Inventory
                                                    </option>
                                                    <option
                                                        value="working_capital"
                                                        @selected(($item['category'] ?? '') === 'working_capital')
                                                    >
                                                        Working Capital
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Description</label>
                                                <input
                                                    type="text"
                                                    maxlength="150"
                                                    name="cost_items[{{ $index }}][description]"
                                                    value="{{ $item['description'] ?? '' }}"
                                                >
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Amount</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    name="cost_items[{{ $index }}][amount]"
                                                    value="{{ $item['amount'] ?? '' }}"
                                                >
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Basis</label>
                                                <select
                                                    name="cost_items[{{ $index }}][basis]"
                                                >
                                                    <option
                                                        value="one_time"
                                                        @selected(($item['basis'] ?? '') === 'one_time')
                                                    >
                                                        One-time
                                                    </option>
                                                    <option
                                                        value="monthly"
                                                        @selected(($item['basis'] ?? '') === 'monthly')
                                                    >
                                                        Monthly
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Evidence</label>
                                                <input
                                                    type="text"
                                                    maxlength="500"
                                                    name="cost_items[{{ $index }}][evidence_reference]"
                                                    value="{{ $item['evidence_reference'] ?? '' }}"
                                                >
                                            </div>

                                            <button
                                                type="button"
                                                class="pbr-os-remove-row"
                                                data-v2-remove-row
                                                aria-label="Remove cost item"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="pbr-os-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            FUNDING POSITION
                                        </span>
                                        <h2>Confirmed Funding</h2>
                                        <p>
                                            Confirmed ဖြစ်ပြီး evidence
                                            ရှိတဲ့ funding ကိုပဲ ဒီမှာထည့်ပါ။
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="pbr-os-add-row"
                                        data-v2-add-funding
                                    >
                                        + Funding Source
                                    </button>
                                </div>

                                <div
                                    class="pbr-os-repeater-rows"
                                    data-v2-funding-list
                                >
                                    @foreach($fundingSources as $index => $source)
                                        <div
                                            class="pbr-os-repeater-row"
                                            style="--pbr-cols: 5"
                                            data-v2-row
                                        >
                                            <input
                                                type="hidden"
                                                name="funding_sources[{{ $index }}][id]"
                                                value="{{ $source['id'] ?? 'funding-'.($index + 1) }}"
                                            >

                                            <div class="pbr-os-mini-field">
                                                <label>Type</label>
                                                <select
                                                    name="funding_sources[{{ $index }}][source_type]"
                                                >
                                                    @foreach([
                                                        'partner_cash' => 'Partner Cash',
                                                        'business_cash' => 'Business Cash',
                                                        'loan' => 'Loan',
                                                        'grant' => 'Grant',
                                                        'other' => 'Other',
                                                    ] as $value => $label)
                                                        <option
                                                            value="{{ $value }}"
                                                            @selected(($source['source_type'] ?? '') === $value)
                                                        >
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Description</label>
                                                <input
                                                    type="text"
                                                    maxlength="150"
                                                    name="funding_sources[{{ $index }}][description]"
                                                    value="{{ $source['description'] ?? '' }}"
                                                >
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Confirmed</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    name="funding_sources[{{ $index }}][confirmed_amount]"
                                                    value="{{ $source['confirmed_amount'] ?? '' }}"
                                                >
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Confirmed Date</label>
                                                <input
                                                    type="date"
                                                    name="funding_sources[{{ $index }}][confirmation_date]"
                                                    value="{{ $source['confirmation_date'] ?? '' }}"
                                                >
                                            </div>

                                            <div class="pbr-os-mini-field">
                                                <label>Evidence</label>
                                                <input
                                                    type="text"
                                                    maxlength="500"
                                                    name="funding_sources[{{ $index }}][evidence_reference]"
                                                    value="{{ $source['evidence_reference'] ?? '' }}"
                                                >
                                            </div>

                                            <button
                                                type="button"
                                                class="pbr-os-remove-row"
                                                data-v2-remove-row
                                                aria-label="Remove funding source"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="pbr-os-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            FUNDING GAP RESPONSE
                                        </span>
                                        <h2>
                                            Shortfall Response
                                        </h2>
                                        <p>
                                            Funding Gap ရှိမှ response ကို
                                            enable လုပ်ပါ။
                                        </p>
                                    </div>
                                </div>

                                <div class="pbr-os-fields">
                                    <div class="pbr-os-field pbr-os-field-wide">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="shortfall_enabled"
                                                value="1"
                                                @checked(old('shortfall_enabled', $shortfall !== null))
                                            >
                                            Funding gap response ရှိသည်
                                        </label>
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>Strategy</label>

                                        @php
                                            $shortfallStrategy = old(
                                                'shortfall_strategy',
                                                $shortfall['strategy'] ?? ''
                                            );
                                        @endphp

                                        <select name="shortfall_strategy">
                                            <option value="">Select</option>

                                            @foreach([
                                                'reduce_scope' => 'Reduce Scope',
                                                'delay' => 'Delay',
                                                'borrow' => 'Borrow',
                                                'capital_call' => 'Capital Call',
                                                'mixed' => 'Mixed',
                                            ] as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    @selected($shortfallStrategy === $value)
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>Owner Reference</label>
                                        <input
                                            type="text"
                                            maxlength="120"
                                            name="shortfall_owner_reference"
                                            value="{{ old('shortfall_owner_reference', $shortfall['owner_reference'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>Target Date</label>
                                        <input
                                            type="date"
                                            name="shortfall_target_date"
                                            value="{{ old('shortfall_target_date', $shortfall['target_date'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field pbr-os-field-wide">
                                        <label>Response Summary</label>
                                        <textarea
                                            name="shortfall_summary"
                                            maxlength="2000"
                                        >{{ old('shortfall_summary', $shortfall['summary'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </section>

                            <section class="pbr-os-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            DECISION EVIDENCE
                                        </span>
                                        <h2>
                                            Capital Decision Record Data
                                        </h2>
                                        <p>
                                            ဒီ fields တွေက approved plan
                                            payload ရဲ့ decision evidence ဖြစ်ပါတယ်။
                                            Plan ကို approve & publish လုပ်ပြီးနောက်
                                            အပေါ်က append-only Decision Record
                                            section မှာ exact revision ကို
                                            record လုပ်နိုင်ပါတယ်။
                                        </p>
                                    </div>
                                </div>

                                <div class="pbr-os-fields">
                                    <div class="pbr-os-field">
                                        <label>Decision Owner</label>
                                        <input
                                            type="text"
                                            maxlength="120"
                                            name="decision_owner_reference"
                                            value="{{ old('decision_owner_reference', $decision['owner_reference'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>Effective Date</label>
                                        <input
                                            type="date"
                                            name="decision_effective_date"
                                            value="{{ old('decision_effective_date', $decision['effective_date'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field">
                                        <label>Review Date</label>
                                        <input
                                            type="date"
                                            name="decision_review_date"
                                            value="{{ old('decision_review_date', $decision['review_date'] ?? '') }}"
                                        >
                                    </div>

                                    <div class="pbr-os-field pbr-os-field-wide">
                                        <label>Decision Summary</label>
                                        <textarea
                                            name="decision_summary"
                                            maxlength="2000"
                                        >{{ old('decision_summary', $decision['decision_summary'] ?? '') }}</textarea>
                                    </div>

                                    <div class="pbr-os-field pbr-os-field-wide">
                                        <label>
                                            Evidence References
                                            <span>
                                                One reference per line
                                            </span>
                                        </label>

                                        <textarea
                                            name="decision_evidence_references"
                                            maxlength="50000"
                                        >{{ old('decision_evidence_references', implode("\n", $decision['evidence_references'] ?? [])) }}</textarea>
                                    </div>
                                </div>

                                <div class="pbr-os-form-actions">
                                    <div>
                                        <small class="pbr-os-help">
                                            Draft ကို incomplete state နဲ့
                                            save လုပ်နိုင်ပါတယ်။
                                            Proposal မတင်ခင် readiness PASS
                                            ဖြစ်ရပါမယ်။
                                        </small>
                                    </div>

                                    <button
                                        type="submit"
                                        class="pbr-os-btn primary"
                                    >
                                        {{ $isWorking
                                            ? 'Save Draft Changes'
                                            : 'Create V2 Draft' }}
                                    </button>
                                </div>
                            </section>
                        </form>

                        @if($isWorking && $assessment)
                            <section class="pbr-os-panel pbr-os-result-panel">
                                <div class="pbr-os-panel-head">
                                    <div>
                                        <span class="portal-kicker">
                                            READINESS
                                        </span>

                                        <h2>
                                            {{ $assessment['passed']
                                                ? 'Capital Plan Ready'
                                                : 'Capital Plan မပြည့်စုံသေးပါ' }}
                                        </h2>

                                        <p>
                                            Rule:
                                            {{ $assessment['rule_version'] }}
                                        </p>
                                    </div>

                                    <span class="pbr-os-session-badge">
                                        {{ $assessment['passed'] ? 'PASS' : 'NOT READY' }}
                                    </span>
                                </div>

                                @if($draftCalc)
                                    <div class="pbr-os-metrics">
                                        <article>
                                            <span>Total Required</span>
                                            <strong>
                                                {{ $payload['currency_code'] }}
                                                {{ $draftCalc['total_required'] }}
                                            </strong>
                                        </article>

                                        <article>
                                            <span>Confirmed</span>
                                            <strong>
                                                {{ $payload['currency_code'] }}
                                                {{ $draftCalc['confirmed_funding'] }}
                                            </strong>
                                        </article>

                                        <article>
                                            <span>Funding Gap</span>
                                            <strong>
                                                {{ $payload['currency_code'] }}
                                                {{ $draftCalc['funding_gap'] }}
                                            </strong>
                                        </article>

                                        <article>
                                            <span>Contingency</span>
                                            <strong>
                                                {{ $payload['currency_code'] }}
                                                {{ $draftCalc['contingency_reserve'] }}
                                            </strong>
                                        </article>
                                    </div>
                                @endif

                                @if(! $assessment['passed'])
                                    <div class="pbr-os-insight warning">
                                        <div class="pbr-os-insight-icon">!</div>
                                        <div>
                                            <h3>
                                                Proposal မတင်ခင် ပြင်ရန်
                                            </h3>
                                            <ul>
                                                @foreach(array_slice($assessment['errors'], 0, 12) as $issue)
                                                    <li>
                                                        <strong>
                                                            {{ $issue['path'] }}
                                                        </strong>
                                                        — {{ $issue['message'] }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                @foreach($assessment['warnings'] as $warning)
                                    <div class="pbr-os-insight note">
                                        <div class="pbr-os-insight-icon">i</div>
                                        <div>
                                            <h3>{{ $warning['code'] }}</h3>
                                            <p>{{ $warning['message'] }}</p>
                                        </div>
                                    </div>
                                @endforeach

                                @if($assessment['passed'])
                                    <div class="pbr-os-approval-zone">
                                        <div>
                                            <span>Next Step</span>
                                            <h3>
                                                Formal Proposal အဖြစ်တင်ရန်
                                            </h3>
                                            <p>
                                                Submit ပြီးသွားရင် payload
                                                ကို edit လို့မရတော့ဘဲ
                                                approval decision ကို
                                                စောင့်ရပါမယ်။
                                            </p>
                                        </div>

                                        <form
                                            method="POST"
                                            action="{{ route('workspaces.tools.v2.capital.submit', [$workspace, $activeArtifact['id']]) }}"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="pbr-os-btn approve"
                                            >
                                                Submit Proposal
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </section>
                        @endif
                    @endif
                @endif

                <div class="pbr-os-legal-note">
                    <strong>Authority Boundary</strong>
                    <p>
                        V1 Startup Capital scenarios ကို ဒီ V2 page
                        က read, convert, approve သို့မဟုတ် overwrite
                        မလုပ်ပါ။ Canonical authority က
                        capital / approved_capital_plan တစ်ခုတည်းဖြစ်ပါတယ်။
                    </p>
                </div>
            </main>
        </div>
    </div>
</section>

@if($canManage && ! $isProposed)
    <template id="v2-capital-cost-template">
        <div
            class="pbr-os-repeater-row"
            style="--pbr-cols: 5"
            data-v2-row
        >
            <input
                type="hidden"
                name="cost_items[__INDEX__][id]"
                value="cost-new-__INDEX__"
            >

            <div class="pbr-os-mini-field">
                <label>Category</label>
                <select name="cost_items[__INDEX__][category]">
                    <option value="pre_opening">Pre-opening</option>
                    <option value="initial_assets_inventory">
                        Assets / Inventory
                    </option>
                    <option value="working_capital">
                        Working Capital
                    </option>
                </select>
            </div>

            <div class="pbr-os-mini-field">
                <label>Description</label>
                <input
                    type="text"
                    maxlength="150"
                    name="cost_items[__INDEX__][description]"
                >
            </div>

            <div class="pbr-os-mini-field">
                <label>Amount</label>
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    name="cost_items[__INDEX__][amount]"
                >
            </div>

            <div class="pbr-os-mini-field">
                <label>Basis</label>
                <select name="cost_items[__INDEX__][basis]">
                    <option value="one_time">One-time</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            <div class="pbr-os-mini-field">
                <label>Evidence</label>
                <input
                    type="text"
                    maxlength="500"
                    name="cost_items[__INDEX__][evidence_reference]"
                >
            </div>

            <button
                type="button"
                class="pbr-os-remove-row"
                data-v2-remove-row
            >
                ×
            </button>
        </div>
    </template>

    <template id="v2-capital-funding-template">
        <div
            class="pbr-os-repeater-row"
            style="--pbr-cols: 5"
            data-v2-row
        >
            <input
                type="hidden"
                name="funding_sources[__INDEX__][id]"
                value="funding-new-__INDEX__"
            >

            <div class="pbr-os-mini-field">
                <label>Type</label>
                <select name="funding_sources[__INDEX__][source_type]">
                    <option value="partner_cash">Partner Cash</option>
                    <option value="business_cash">Business Cash</option>
                    <option value="loan">Loan</option>
                    <option value="grant">Grant</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="pbr-os-mini-field">
                <label>Description</label>
                <input
                    type="text"
                    maxlength="150"
                    name="funding_sources[__INDEX__][description]"
                >
            </div>

            <div class="pbr-os-mini-field">
                <label>Confirmed</label>
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    name="funding_sources[__INDEX__][confirmed_amount]"
                >
            </div>

            <div class="pbr-os-mini-field">
                <label>Confirmed Date</label>
                <input
                    type="date"
                    name="funding_sources[__INDEX__][confirmation_date]"
                >
            </div>

            <div class="pbr-os-mini-field">
                <label>Evidence</label>
                <input
                    type="text"
                    maxlength="500"
                    name="funding_sources[__INDEX__][evidence_reference]"
                >
            </div>

            <button
                type="button"
                class="pbr-os-remove-row"
                data-v2-remove-row
            >
                ×
            </button>
        </div>
    </template>

    <script>
        (() => {
            const costList = document.querySelector('[data-v2-cost-list]');
            const fundingList = document.querySelector('[data-v2-funding-list]');
            const costTemplate = document.getElementById('v2-capital-cost-template');
            const fundingTemplate = document.getElementById('v2-capital-funding-template');

            const appendTemplate = (list, template) => {
                if (!list || !template) {
                    return;
                }

                const index = Date.now().toString()
                    + Math.floor(Math.random() * 1000).toString();

                const html = template.innerHTML.replaceAll(
                    '__INDEX__',
                    index
                );

                list.insertAdjacentHTML('beforeend', html);
            };

            document.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-v2-remove-row]');

                if (remove) {
                    remove.closest('[data-v2-row]')?.remove();
                    return;
                }

                if (event.target.closest('[data-v2-add-cost]')) {
                    appendTemplate(costList, costTemplate);
                    return;
                }

                if (event.target.closest('[data-v2-add-funding]')) {
                    appendTemplate(fundingList, fundingTemplate);
                }
            });
        })();
    </script>
@endif

@endsection
