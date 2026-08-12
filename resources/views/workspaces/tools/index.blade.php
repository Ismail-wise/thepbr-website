@extends('layouts.student-portal')

@section('title', 'PBR Partnership Operating System')

@section('content')
@php
    $capitalCurrency = $workspace->currency_code ?? 'THB';
    $totalTools = $chapters->sum(fn ($chapter) => $chapter->tools->count());
    $totalAgreed = collect($chapterProgress)->sum('agreed');
    $overallPercent = $totalTools > 0 ? round(($totalAgreed / $totalTools) * 100) : 0;
    $chapterMeta = [
        1 => 'မတည်ငွေ ထည့်ဝင်ခြင်း',
        2 => 'ပိုင်ဆိုင်မှုနှင့် Share Structure',
        3 => 'လုပ်အားနှင့် တန်ဖိုး ထည့်ဝင်မှု',
        4 => 'အမြတ်အရှုံး ခွဲဝေမှု',
        5 => 'ငွေကြေး စီမံခန့်ခွဲမှု',
        6 => 'ဦးဆောင်မှုနှင့် Governance',
        7 => 'Withdrawal & Exit',
        8 => 'Death, Disability & Continuity',
        9 => 'Share Transfer',
        10 => 'Dispute Resolution',
    ];
@endphp

<section class="pbr-os-page">
    <div class="portal-wrap pbr-os-wrap">
        <nav class="pbr-os-breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            <span>›</span>
            <span>Partnership Operating System</span>
        </nav>

        <header class="pbr-os-hero pbr-system-dashboard-hero">
            <div class="pbr-os-hero-copy">
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">10 Chapters</span>
                    <span class="pbr-os-type-pill">{{ $totalTools }} Practical Tools</span>
                    <span class="pbr-os-agreed-pill">{{ $totalAgreed }} Agreed Rules</span>
                </div>
                <h1>Partnership Business Operating System</h1>
                <p class="pbr-os-en-title">Learn → Plan → Agree → Operate → Protect → Exit</p>
                <p class="pbr-os-purpose">
                    Business တစ်ခုထဲမှာ Capital, Ownership, Work Contribution, Profit/Loss,
                    Financial Controls, Governance, Exit, Continuity, Share Transfer နဲ့
                    Dispute Resolution ကို တစ်ဆက်တည်းတည်ဆောက်ပါ။ Tool တစ်ခုချင်းစီရဲ့
                    <b>Agreed Business Rule</b> က နောက် Chapter တွေနဲ့ PBR AI Advisor ကို data feed လုပ်ပေးပါတယ်။
                </p>
            </div>

            <aside class="pbr-os-business-context">
                <span>လက်ရှိ Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <div>
                    <small>{{ $businessStages[$workspace->business_stage] ?? 'Stage not set' }}</small>
                    <small>{{ $capitalCurrency }}</small>
                    <small>{{ $workspace->acceptedMemberships->count() + 1 }} People</small>
                </div>
            </aside>
        </header>

        <section class="pbr-system-health-grid">
            <article class="pbr-system-health-card primary">
                <span>Operating System Completion</span>
                <strong>{{ $overallPercent }}%</strong>
                <div class="pbr-system-progress"><i style="width: {{ $overallPercent }}%"></i></div>
                <small>{{ $totalAgreed }} / {{ $totalTools }} tools have an agreed rule</small>
            </article>
            <article class="pbr-system-health-card">
                <span>Capital Required</span>
                <strong>{{ $capitalCurrency }} {{ number_format($chapterOneSummary['capital_required'] ?? 0, 2) }}</strong>
                <small>Chapter 1 connected summary</small>
            </article>
            <article class="pbr-system-health-card">
                <span>Funding Gap</span>
                <strong>{{ $capitalCurrency }} {{ number_format($chapterOneSummary['funding_gap'] ?? 0, 2) }}</strong>
                <small>{{ ($chapterOneSummary['funding_gap'] ?? 0) > 0 ? 'Attention required' : 'Current requirement covered' }}</small>
            </article>
            <article class="pbr-system-health-card">
                <span>Connected Domains</span>
                <strong>{{ collect($operatingDomains)->filter()->count() }} / 10</strong>
                <small>Capital → Dispute Resolution</small>
            </article>
        </section>

        <section class="pbr-context-card pbr-system-context-card">
            <div class="pbr-context-header">
                <div>
                    <span class="portal-kicker">Business Context</span>
                    <h2>ဒီ Business အတွက် default settings</h2>
                    <p>Chapter 1–10 tools, Valuation နဲ့ AI Advisor တွေက ဒီ Business Stage နဲ့ Currency ကိုအသုံးပြုပါတယ်။</p>
                </div>
                <span class="{{ $workspace->hasBusinessContext() ? 'pbr-ready-badge' : 'pbr-setup-badge' }}">
                    {{ $workspace->hasBusinessContext() ? 'Ready' : 'Setup Required' }}
                </span>
            </div>

            @if($canManageContext)
                <form method="POST" action="{{ route('workspaces.business-context.update', $workspace) }}">
                    @csrf
                    @method('PUT')
                    <div class="pbr-context-grid">
                        <div class="pbr-tools-field">
                            <label for="business_stage">Partnership Stage</label>
                            <select id="business_stage" name="business_stage" required>
                                @foreach($businessStages as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business_stage', $workspace->business_stage) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small>Planning အသစ်လား၊ ရှိပြီးသား Business ကို manage လုပ်နေတာလားရွေးပါ။</small>
                        </div>
                        <div class="pbr-tools-field">
                            <label for="currency_code">Primary Currency</label>
                            <select id="currency_code" name="currency_code" required>
                                @foreach($currencies as $value => $label)
                                    <option value="{{ $value }}" @selected(old('currency_code', $workspace->currency_code) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small>Financial tools အားလုံးအတွက် default currency ဖြစ်ပါတယ်။</small>
                        </div>
                    </div>
                    <div class="pbr-context-actions">
                        <button type="submit" class="pbr-tools-primary-button">Save Partnership Settings</button>
                    </div>
                </form>
            @else
                <div class="pbr-context-readonly">
                    <div><span>Partnership Stage</span><strong>{{ $businessStages[$workspace->business_stage] ?? 'Not selected' }}</strong></div>
                    <div><span>Primary Currency</span><strong>{{ $workspace->currency_code ?? 'Not selected' }}</strong></div>
                </div>
                <p class="pbr-owner-note">Partner account က agreed operating data ကို read-only အသုံးပြုနိုင်ပါတယ်။ Business settings ကို Owner/Admin ကပဲပြင်နိုင်ပါတယ်။</p>
            @endif
        </section>

        <div class="pbr-system-heading">
            <span class="portal-kicker">Connected Business Architecture</span>
            <h2>Chapter 1 ကနေ Chapter 10 အထိ</h2>
            <p>Scenario ကိုစမ်း → Review → Save Draft → Approve as Agreed Business Rule လုပ်ပြီး နောက် Chapter ဆီ data ဆက်သွားပါမယ်။</p>
        </div>

        <div class="pbr-system-flow-strip" aria-label="Chapter flow">
            @foreach($chapters as $chapter)
                @php
                    $num = (int) $chapter->chapter_number;
                    $progress = $chapterProgress[$num] ?? ['percentage' => 0];
                @endphp
                <a href="#chapter-{{ $num }}" class="{{ $progress['percentage'] >= 100 ? 'complete' : ($progress['percentage'] > 0 ? 'active' : '') }}">
                    <b>{{ str_pad((string) $num, 2, '0', STR_PAD_LEFT) }}</b>
                    <span>{{ $progress['percentage'] }}%</span>
                </a>
            @endforeach
        </div>

        <div class="pbr-chapter-list pbr-system-chapters">
            @foreach($chapters as $chapter)
                @php
                    $chapterNumber = (int) $chapter->chapter_number;
                    $progress = $chapterProgress[$chapterNumber] ?? ['total' => $chapter->tools->count(), 'agreed' => 0, 'percentage' => 0];
                    $domain = $operatingDomains[$chapterNumber] ?? null;
                @endphp

                <details
                    id="chapter-{{ $chapterNumber }}"
                    class="pbr-chapter-card pbr-system-chapter-card"
                    @if($chapterNumber === 1 || $progress['percentage'] > 0) open @endif
                >
                    <summary>
                        <div class="pbr-chapter-number">{{ str_pad((string) $chapterNumber, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="pbr-chapter-title">
                            <span>{{ strtoupper(str_replace('_', ' ', $chapter->phase)) }}</span>
                            <h3>{{ $chapterMeta[$chapterNumber] ?? $chapter->title_mm }}</h3>
                            <p>{{ $chapter->title_en }}</p>
                        </div>
                        <div class="pbr-system-chapter-progress">
                            <div><strong>{{ $progress['agreed'] }}/{{ $progress['total'] }}</strong><span>Agreed</span></div>
                            <div class="pbr-system-mini-progress"><i style="width: {{ $progress['percentage'] }}%"></i></div>
                            @if($domain && ($domain['status'] ?? null) === 'agreed')
                                <small class="connected">Connected · Rev {{ $domain['revision'] }}</small>
                            @elseif($domain)
                                <small>Draft data exists</small>
                            @else
                                <small>Not configured yet</small>
                            @endif
                        </div>
                    </summary>

                    <div class="pbr-chapter-body">
                        <p class="pbr-chapter-description">{{ $chapter->description }}</p>

                        <div class="pbr-tool-grid pbr-system-tool-grid">
                            @foreach($chapter->tools as $tool)
                                @php
                                    $definition = $toolDefinitions[$tool->tool_key] ?? null;
                                    $toolTitleMm = $definition['title_mm'] ?? $tool->title_mm ?? $tool->title_en;
                                    $purpose = $definition['purpose_mm'] ?? ($chapterNumber === 1
                                        ? 'Capital planning အတွက် calculate, compare, save scenario နဲ့ agreed operating rule ကိုအသုံးပြုပါ။'
                                        : $tool->description);
                                    $isAgreed = $agreedToolIds->has((int) $tool->id);
                                @endphp

                                <article class="pbr-tool-card pbr-system-tool-card {{ $isAgreed ? 'agreed' : '' }}">
                                    <div class="pbr-tool-top">
                                        <span class="pbr-tool-type">{{ ucfirst($tool->tool_type) }}</span>
                                        <span class="pbr-tool-status {{ $isAgreed ? 'ready' : '' }}">{{ $isAgreed ? 'Agreed' : 'Ready' }}</span>
                                    </div>
                                    <h4>{{ $toolTitleMm }}</h4>
                                    @if($toolTitleMm !== $tool->title_en)
                                        <small class="pbr-system-tool-en">{{ $tool->title_en }}</small>
                                    @endif
                                    <p>{{ $purpose }}</p>
                                    <div class="pbr-stage-tags">
                                        @if($tool->supports_new_business)<span>New Partnership</span>@endif
                                        @if($tool->supports_existing_business)<span>Existing Business</span>@endif
                                    </div>

                                    @if($tool->tool_key === 'startup_capital_planner' && $workspace->business_stage === 'new')
                                        <a class="pbr-open-tool" href="{{ route('workspaces.tools.startup-capital.show', $workspace) }}">Open Tool →</a>
                                    @elseif($chapterNumber === 1)
                                        <a class="pbr-open-tool" href="{{ route('workspaces.tools.chapter-one.show', [$workspace, $tool->slug]) }}">Open Tool →</a>
                                    @else
                                        <a class="pbr-open-tool" href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug]) }}">Open Tool →</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <section class="pbr-system-ai-bridge">
            <div>
                <span class="portal-kicker">Connected Intelligence</span>
                <h2>Agreed Rules → PBR AI Advisor</h2>
                <p>Approved operating data ကို AI Advisor က Old RAG Knowledge, Feasibility, Valuation နဲ့ Partner Dynamics data တို့နဲ့ပေါင်းပြီး Business-specific guidance ပေးနိုင်ပါတယ်။</p>
            </div>
            <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}">PBR AI Advisor ဖွင့်ရန် →</a>
        </section>

        <div class="pbr-os-legal-note">
            <strong>Important</strong>
            <p>ဒီ tools တွေက planning, internal controls နဲ့ partner discussion support အတွက်ဖြစ်ပါတယ်။ Legal document, tax/accounting advice, certified valuation သို့မဟုတ် insurance advice ကို အစားမထိုးပါ။</p>
        </div>
    </div>
</section>
@endsection
