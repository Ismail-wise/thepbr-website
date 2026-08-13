@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
<section class="pbr-business-page pbr-portfolio-page">
    <div class="portal-wrap pbr-business-wrap">
        <header class="pbr-business-hero pbr-portfolio-hero">
            <div class="pbr-business-hero-copy">
                <span class="pbr-business-eyebrow">PBR BUSINESS OPERATING SYSTEM</span>
                <h1>မင်္ဂလာပါ၊ {{ $user->name }}</h1>
                <p class="pbr-business-hero-lead">
                    သင့် Partnership Business တွေရဲ့ <strong>အချက်အလက်၊ Partner တာဝန်၊ ငွေကြေး၊ Ownership၊ ဆုံးဖြတ်ချက်၊ Risk နဲ့ Active Business Rules</strong>
                    ကို နေ့စဉ်တကယ်စီမံပြီး လိုအပ်တဲ့ Action တွေကို တစ်နေရာတည်းက ထိန်းချုပ်နိုင်ပါတယ်။
                </p>
            </div>

            <div class="pbr-business-hero-actions">
                <a href="{{ route('workspaces.index') }}" class="pbr-business-btn secondary">Business အားလုံးကြည့်ရန်</a>
                @if($user->isAdmin() || $user->isStudent())
                    <a href="{{ route('workspaces.create') }}" class="pbr-business-btn">+ Business အသစ်ထည့်ရန်</a>
                @endif
            </div>
        </header>

        <section class="pbr-business-metrics" aria-label="Business portfolio overview">
            <article class="pbr-business-metric">
                <span class="pbr-mm-label">စီမံနေသော Business</span>
                <small class="pbr-en-label">Businesses</small>
                <strong>{{ $portfolioMetrics['business_count'] }}</strong>
                <div class="pbr-metric-foot"><span>သင်ဝင်ရောက်ခွင့်ရှိသော Workspace များ</span></div>
            </article>

            <article class="pbr-business-metric {{ $portfolioMetrics['businesses_needing_attention'] > 0 ? 'attention' : 'healthy' }}">
                <span class="pbr-mm-label">အာရုံစိုက်ရန်လိုသော Business</span>
                <small class="pbr-en-label">Needs Attention</small>
                <strong>{{ $portfolioMetrics['businesses_needing_attention'] }}</strong>
                <div class="pbr-metric-foot">
                    <b class="{{ $portfolioMetrics['businesses_needing_attention'] > 0 ? 'warning' : 'active' }}">
                        {{ $portfolioMetrics['businesses_needing_attention'] > 0 ? 'Action ရှိသည်' : 'အရေးပေါ်မရှိ' }}
                    </b>
                </div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">အသုံးပြုနေသော Business Rules</span>
                <small class="pbr-en-label">Active Rules</small>
                <strong>{{ $portfolioMetrics['active_rule_count'] }}</strong>
                <div class="pbr-metric-foot"><span>အတည်ပြုပြီး လက်ရှိအသုံးပြုနေသော Rules</span></div>
            </article>

            <article class="pbr-business-metric {{ $portfolioMetrics['working_change_count'] > 0 ? 'draft' : '' }}">
                <span class="pbr-mm-label">ပြန်စစ်ရန် ပြောင်းလဲမှု</span>
                <small class="pbr-en-label">Working Changes</small>
                <strong>{{ $portfolioMetrics['working_change_count'] }}</strong>
                <div class="pbr-metric-foot"><span>Active Rule မပြောင်းခင် Review လုပ်ရမည့် Draft များ</span></div>
            </article>
        </section>

        <section class="pbr-business-attention pbr-portfolio-section">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">MY BUSINESSES</span>
                    <h2>Business တစ်ခုရွေးပြီး တိုက်ရိုက်စီမံပါ</h2>
                    <p>Business တစ်ခုချင်းစီရဲ့ Data၊ Partner နဲ့ Rules တွေကို လုံးဝသီးခြားထားပြီး တစ်နေရာတည်းက စီမံနိုင်ပါတယ်။</p>
                </div>
            </div>

            @if($businesses->isEmpty())
                <div class="pbr-business-empty-real">
                    <div class="pbr-business-empty-icon">＋</div>
                    <div>
                        <h3>Business Workspace မရှိသေးပါ</h3>
                        <p>ကိုယ့် Partnership ရဲ့ actual data ကို စတင်သိမ်းပြီး Operating System တည်ဆောက်ဖို့ Business တစ်ခုအရင်ဖန်တီးပါ။</p>
                    </div>
                    @if($user->isAdmin() || $user->isStudent())
                        <a href="{{ route('workspaces.create') }}" class="pbr-business-btn">Business Workspace တည်ဆောက်ရန်</a>
                    @endif
                </div>
            @else
                <div class="pbr-business-portfolio-grid">
                    @foreach($businesses as $business)
                        @php
                            $workspace = $business['workspace'];
                            $metrics = $business['metrics'];
                            $actions = $business['action_items'];
                        @endphp

                        <article class="pbr-business-portfolio-card">
                            <div class="pbr-business-portfolio-head">
                                <div>
                                    <span class="pbr-business-eyebrow">
                                        {{ $workspace->business_stage === 'existing' ? 'OPERATING BUSINESS' : 'NEW BUSINESS PLAN' }}
                                    </span>
                                    <h3>{{ $workspace->business_name ?: $workspace->name }}</h3>
                                    <p>{{ $workspace->currency_code ?? 'THB' }} · Partner {{ $metrics['partner_count'] }} ဦး</p>
                                </div>
                                <span class="pbr-business-status-dot {{ $actions->isNotEmpty() ? 'attention' : 'healthy' }}">
                                    {{ $actions->isNotEmpty() ? 'Action လိုသည်' : 'ပုံမှန်' }}
                                </span>
                            </div>

                            <div class="pbr-business-portfolio-stats">
                                <div>
                                    <span>Active Rules</span>
                                    <strong>{{ $metrics['active_rule_count'] }}</strong>
                                </div>
                                <div>
                                    <span>Review</span>
                                    <strong>{{ $metrics['working_change_count'] }}</strong>
                                </div>
                                <div>
                                    <span>Records</span>
                                    <strong>{{ $metrics['operating_record_count'] }}</strong>
                                </div>
                                <div>
                                    <span>Funding Gap</span>
                                    <strong>{{ $workspace->currency_code ?? 'THB' }} {{ number_format((float) $metrics['funding_gap'], 0) }}</strong>
                                </div>
                            </div>

                            @if($actions->isNotEmpty())
                                <div class="pbr-business-mini-actions">
                                    @foreach($actions as $action)
                                        <a href="{{ $action['url'] }}" class="{{ $action['level'] }}">
                                            <span>{{ $action['title_mm'] }}</span>
                                            <small>{{ $action['action_mm'] }}</small>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="pbr-business-mini-healthy">
                                    <span>✓</span>
                                    <p>လက်ရှိမှတ်တမ်းအရ အရေးပေါ်ပြန်စစ်ရမယ့် Working Change မရှိပါ။</p>
                                </div>
                            @endif

                            <div class="pbr-business-portfolio-actions">
                                <a href="{{ route('workspaces.show', $workspace) }}" class="pbr-business-btn secondary">Business Overview</a>
                                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-business-btn">Operating System ဖွင့်ရန် →</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="pbr-business-utility-strip">
            <div>
                <span class="pbr-business-eyebrow">PARTNER INTELLIGENCE</span>
                <h2>Partner Dynamics</h2>
                <p>Partner တွေရဲ့ Working Style၊ Decision Style၊ Strengths နဲ့ Role Fit ကို Business Role စီမံရာမှာ အထောက်အကူဖြစ်အောင် အသုံးပြုပါ။</p>
            </div>
            <a href="{{ route('partner-dynamics.index') }}" class="pbr-business-btn secondary">Partner Dynamics ဖွင့်ရန် →</a>
        </section>
    </div>
</section>
@endsection
