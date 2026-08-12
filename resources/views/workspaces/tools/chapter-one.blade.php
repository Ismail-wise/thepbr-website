@extends('layouts.student-portal')

@section('title', $tool->title_en)

@section('content')
<section class="pbr-tools-section">
    <div class="portal-wrap">
        <div class="pbr-tool-page-head">
            <div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-tools-back">← Back to Business Operating System</a>
                <span class="portal-kicker">Capital & Funding</span>
                <h1>{{ $tool->title_en }}</h1>
                <p>{{ $tool->description }}</p>
            </div>

            <div class="pbr-tool-context-box">
                <span>Current Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <small>Mode: {{ $workspace->business_stage === 'new' ? 'Planning a New Partnership' : 'Managing an Existing Partnership' }}</small>
                <small>Currency: {{ $workspace->currency_code ?? 'THB' }}</small>
                @if($latestAgreedOutput)
                    <small style="color:#bbf0ce;">✓ Active Business Rule · Revision {{ $latestAgreedOutput->revision }}</small>
                @endif
            </div>
        </div>

        @if(session('status'))
            <div class="pbr-save-success">{{ session('status') }}</div>
        @endif

        @unless($canManage)
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>Owner/Admin အတည်ပြုထားတဲ့ Active Business Rule ကိုသာပြထားပါတယ်။ Draft scenarios, private calculations နဲ့ management controls တွေကို Partner account က မမြင်နိုင်ပါဘူး။</p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        @if($canManage)
            <form
                id="chapter-one-tool-form"
                method="POST"
                action="{{ route('workspaces.tools.chapter-one.calculate', [$workspace, $tool->slug]) }}"
                data-currency="{{ $workspace->currency_code ?? 'THB' }}"
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
                        <label for="scenario_name">Scenario Name</label>
                        <small>မတူညီတဲ့ Capital plan versions တွေကို Draft အဖြစ်သိမ်းပါ။</small>
                    </div>
                    <input
                        id="scenario_name"
                        name="scenario_name"
                        type="text"
                        maxlength="120"
                        placeholder="ဥပမာ: Base Capital Plan"
                        value="{{ old('scenario_name', $activeSession?->scenario_name ?? request('scenario_name', '')) }}"
                    >
                </div>

                @if($activeSession)
                    <div class="pbr-active-draft">
                        <span>Editing Saved Scenario</span>
                        <strong>{{ $activeSession->scenario_name }}</strong>
                        <small>Last saved {{ $activeSession->last_saved_at ? $activeSession->last_saved_at->diffForHumans() : 'recently' }}</small>
                    </div>
                @endif

                @if($errors->any())
                    <div class="pbr-form-errors">
                        <strong>ထည့်ထားတဲ့ data ကိုပြန်စစ်ပါ။</strong>
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
                            >Save Draft</button>

                            <button type="submit" class="pbr-tools-primary-button">Calculate / Review</button>
                        </div>
                    </div>

                    <aside class="pbr-calculator-results">
                        <span class="portal-kicker">Result Summary</span>
                        @include('workspaces.tools.chapter-one.results', ['toolKey' => $tool->tool_key])
                    </aside>
                </div>
            </form>

            @if($activeSession && $result)
                <div class="pbr-os-approval-zone" style="margin:18px 0;">
                    <div>
                        <span>Business Rule</span>
                        <h3>ဒီ Result ကို Draft သို့မဟုတ် Active Business Rule အဖြစ်သတ်မှတ်ပါ</h3>
                        <p>Approve လုပ်ပြီးမှ Ownership, Funding, Financial Controls နဲ့ PBR AI Advisor တို့က ဒီ result ကို current business rule အဖြစ်အသုံးပြုပါမယ်။</p>
                    </div>
                    <div class="pbr-os-approval-actions">
                        <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $activeSession->id]) }}">
                            @csrf
                            <button type="submit" class="pbr-os-btn secondary">Create Draft Output</button>
                        </form>
                        <form method="POST" action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $activeSession->id]) }}" data-confirm-agreed>
                            @csrf
                            <button type="submit" class="pbr-os-btn approve">✓ Activate Business Rule</button>
                        </form>
                    </div>
                </div>
            @endif

            @include('workspaces.tools.partials.scenario-manager')
        @else
            @if($result)
                <div class="pbr-calculator-layout">
                    <div class="pbr-calculator-panel">
                        <span class="portal-kicker">Current Active Business Rule</span>
                        <h2 style="margin:7px 0 7px;">{{ $tool->title_en }}</h2>
                        <p style="color:#6b7a80;line-height:1.7;">ဒီ data က Owner/Admin အတည်ပြုထားတဲ့ current capital rule ဖြစ်ပါတယ်။</p>
                        @if($latestAgreedOutput)
                            <div class="pbr-os-current-rule" style="margin-top:16px;">
                                <div>
                                    <span class="pbr-os-agreed-pill">✓ Active</span>
                                    <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                                    <p>{{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <aside class="pbr-calculator-results">
                        <span class="portal-kicker">Active Result</span>
                        @include('workspaces.tools.chapter-one.results', ['toolKey' => $tool->tool_key])
                    </aside>
                </div>
            @else
                <section class="pbr-os-panel pbr-os-empty-state">
                    <div class="pbr-os-empty-icon">◎</div>
                    <h2>Active Business Rule မရှိသေးပါ</h2>
                    <p>Owner/Admin က ဒီ Capital & Funding system အတွက် scenario တစ်ခုကို activate လုပ်ပြီးနောက် ဒီနေရာမှာ result ပေါ်လာပါမယ်။</p>
                </section>
            @endif
        @endif

        @if($latestAgreedOutput && $canManage)
            <section class="pbr-os-panel pbr-os-current-rule" style="margin-top:18px;">
                <div>
                    <span class="pbr-os-agreed-pill">✓ Current Active Business Rule</span>
                    <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                    <p>Approved {{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                </div>
                <p>ဒီ revision ကို Capital & Funding system ရဲ့ current active data အဖြစ် အခြား business systems နဲ့ PBR AI Advisor မှာ အသုံးပြုနိုင်ပါတယ်။</p>
            </section>
        @endif

        <div class="pbr-os-legal-note" style="margin-top:18px;">
            <strong>Important</strong>
            <p>Capital planning result က management planning အတွက်ဖြစ်ပြီး accounting, tax, financing သို့မဟုတ် legal advice ကို အစားမထိုးပါ။</p>
        </div>
    </div>
</section>

@if($canManage)
    <script src="/js/chapter-one-tools.js"></script>
@endif
@endsection
