@extends('layouts.student-portal')

@section('title', $tool->title_mm ?: $tool->title_en)

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $toolTitleMm = $tool->title_mm ?: $tool->title_en;
    $toolPurpose = $tool->description;
    $stageLabel = $workspace->business_stage === 'new'
        ? 'Partnership အသစ် စီစဉ်နေသည်'
        : 'ရှိပြီးသား Partnership ကို စီမံနေသည်';
@endphp

<section class="pbr-tools-section">
    <div class="portal-wrap">
        <div class="pbr-tool-page-head">
            <div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-tools-back">← Business Operating System သို့ ပြန်ရန်</a>
                <span class="portal-kicker">မတည်ငွေနှင့် ရင်းနှီးငွေ · Capital & Funding</span>
                <h1>{{ $toolTitleMm }}</h1>
                @if($toolTitleMm !== $tool->title_en)
                    <p class="pbr-tool-en-subtitle">{{ $tool->title_en }}</p>
                @endif
                <p>{{ $toolPurpose }}</p>
            </div>

            <div class="pbr-tool-context-box">
                <span>လက်ရှိ Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <small>{{ $stageLabel }}</small>
                <small>ငွေကြေး · {{ $currency }}</small>
                @if($latestAgreedOutput)
                    <small style="color:#bbf0ce;">✓ အသုံးပြုနေသော Rule · Revision {{ $latestAgreedOutput->revision }}</small>
                @endif
            </div>
        </div>

        @include(
            'workspaces.tools.partials.capital-tool-workflow',
            [
                'capitalWorkflow' => $capitalWorkflow,
                'tool' => $tool,
                'canManage' => $canManage,
            ]
        )

        @if(session('status'))
            <div class="pbr-save-success">{{ session('status') }}</div>
        @endif

        @unless($canManage)
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner ကြည့်ရှုရန်သာ</strong>
                    <p>Owner/Admin အတည်ပြုပြီး လက်ရှိအသုံးပြုနေတဲ့ Business Rule ကိုပဲ မြင်ရပါတယ်။ Draft တွေ၊ စမ်းသပ်တွက်ချက်မှုတွေနဲ့ management controls တွေကို Partner account မှာ မပြပါဘူး။</p>
                </div>
                <span>Read-only</span>
            </div>
        @endunless

        @if($canManage)
            <form
                id="chapter-one-tool-form"
                method="POST"
                action="{{ route('workspaces.tools.chapter-one.calculate', [$workspace, $tool->slug]) }}"
                data-currency="{{ $currency }}"
            >
                @csrf

                @if($activeSession || request('tool_session_id'))
                    <input
                        type="hidden"
                        name="tool_session_id"
                        value="{{ $activeSession?->id ?? request('tool_session_id') }}"
                    >
                @endif

                <div class="pbr-scenario-box">
                    <div>
                        <label for="scenario_name">Draft အမည်</label>
                        <small>မတူညီတဲ့ plan တွေကို မရှုပ်အောင် မှတ်မိလွယ်တဲ့နာမည်ပေးပါ။ ဥပမာ — Base Plan 2026</small>
                    </div>
                    <input
                        id="scenario_name"
                        name="scenario_name"
                        type="text"
                        maxlength="120"
                        placeholder="ဥပမာ: Base Plan 2026"
                        value="{{ old('scenario_name', $activeSession?->scenario_name ?? request('scenario_name', '')) }}"
                    >
                </div>

                @if($activeSession)
                    <div class="pbr-active-draft">
                        <span>သိမ်းထားသော Draft ကို ပြင်နေသည်</span>
                        <strong>{{ $activeSession->scenario_name }}</strong>
                        <small>နောက်ဆုံးသိမ်းထားသည် · {{ $activeSession->last_saved_at ? $activeSession->last_saved_at->diffForHumans() : 'မကြာသေးမီက' }}</small>
                    </div>
                @endif

                @if($errors->any())
                    <div class="pbr-form-errors">
                        <strong>ထည့်ထားတဲ့အချက်အလက်ကို ပြန်စစ်ပါ။</strong>
                        <p>{{ $errors->first() }}</p>
                    </div>
                @endif

                <div class="pbr-calculator-layout">
                    <div class="pbr-calculator-panel">
                        @include('workspaces.tools.chapter-one.'.$tool->slug)

                        <div class="pbr-calculator-actions">
                            <button
                                type="submit"
                                class="pbr-save-draft-button"
                                formaction="{{ route('workspaces.tools.chapter-one.save', [$workspace, $tool->slug]) }}"
                                formmethod="POST"
                            >Draft သိမ်းရန်</button>

                            <button type="submit" class="pbr-tools-primary-button">Result စစ်ရန်</button>
                        </div>
                    </div>

                    <aside class="pbr-calculator-results">
                        <span class="portal-kicker">တွက်ချက်ရလဒ် · Result</span>
                        @include('workspaces.tools.chapter-one.results', ['toolKey' => $tool->tool_key])
                    </aside>
                </div>
            </form>

            @if($activeSession && $result)
                <div class="pbr-os-approval-zone" style="margin:18px 0;">
                    <div>
                        <span>Business Rule</span>
                        <h3>ဒီ Draft ကို လုပ်ငန်းမှာ တကယ်အသုံးပြုမလား?</h3>
                        <p>Draft က စမ်းသပ်/ပြင်ဆင်နိုင်တဲ့ version ဖြစ်ပါတယ်။ <b>Rule အဖြစ် အတည်ပြုအသုံးပြုရန်</b> ကိုရွေးမှ Ownership, Funding, Financial Controls နဲ့ PBR AI က ဒီ result ကို လက်ရှိ official business data အဖြစ်ယူသုံးပါမယ်။</p>
                    </div>
                    <div class="pbr-os-approval-actions">
                        <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $activeSession->id]) }}">
                            @csrf
                            <button type="submit" class="pbr-os-btn secondary">Draft Result သိမ်းရန်</button>
                        </form>
                        <form method="POST" action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $activeSession->id]) }}" data-confirm-agreed>
                            @csrf
                            <button type="submit" class="pbr-os-btn approve">✓ Rule အဖြစ် အတည်ပြုအသုံးပြုရန်</button>
                        </form>
                    </div>
                </div>
            @endif

            @include('workspaces.tools.partials.scenario-manager')
        @else
            @if($result)
                <div class="pbr-calculator-layout">
                    <div class="pbr-calculator-panel">
                        <span class="portal-kicker">လက်ရှိ အသုံးပြုနေသော Business Rule</span>
                        <h2 style="margin:7px 0 7px;">{{ $toolTitleMm }}</h2>
                        <p style="color:#6b7a80;line-height:1.7;">ဒီအချက်အလက်က Owner/Admin အတည်ပြုပြီး လက်ရှိအသုံးပြုနေတဲ့ capital rule ဖြစ်ပါတယ်။</p>
                        @if($latestAgreedOutput)
                            <div class="pbr-os-current-rule" style="margin-top:16px;">
                                <div>
                                    <span class="pbr-os-agreed-pill">✓ အသုံးပြုနေသည်</span>
                                    <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                                    <p>{{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <aside class="pbr-calculator-results">
                        <span class="portal-kicker">လက်ရှိ Result</span>
                        @include('workspaces.tools.chapter-one.results', ['toolKey' => $tool->tool_key])
                    </aside>
                </div>
            @else
                <section class="pbr-os-panel pbr-os-empty-state">
                    <div class="pbr-os-empty-icon">◎</div>
                    <h2>ဒီ Business System အတွက် အသုံးပြုနေသော Rule မရှိသေးပါ</h2>
                    <p>Owner/Admin က plan တစ်ခုကို Rule အဖြစ်အတည်ပြုပြီး အသုံးပြုလာတဲ့အခါ ဒီနေရာမှာ လက်ရှိ result ကိုမြင်ရပါမယ်။</p>
                </section>
            @endif
        @endif

        @if($latestAgreedOutput && $canManage)
            <section class="pbr-os-panel pbr-os-current-rule" style="margin-top:18px;">
                <div>
                    <span class="pbr-os-agreed-pill">✓ လက်ရှိ အသုံးပြုနေသော Business Rule</span>
                    <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                    <p>အတည်ပြုခဲ့သည် · {{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                </div>
                <p>ဒီ revision ကို Capital & Funding ရဲ့ လက်ရှိ business data အဖြစ် အခြား Business Systems နဲ့ PBR AI မှာ အသုံးပြုနိုင်ပါတယ်။</p>
            </section>
        @endif

        <div class="pbr-os-legal-note" style="margin-top:18px;">
            <strong>သတိပြုရန်</strong>
            <p>ဒီ planning result က management decision အတွက် အထောက်အကူပြုတာဖြစ်ပြီး accounting, tax, financing သို့မဟုတ် legal advice ကို အစားမထိုးပါ။</p>
        </div>
    </div>
</section>

@if($canManage)
    <script src="/js/chapter-one-tools.js"></script>
@endif
@endsection
