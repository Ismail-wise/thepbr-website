@extends('layouts.student-portal')

@section('title', $workspace->business_name ?: $workspace->name)

@section('content')
@php
    $businessName = $workspace->business_name ?: $workspace->name;
    $metrics = $businessState['metrics'];
    $systems = $businessState['systems'];
    $actions = $businessState['action_items'];
    $currency = $workspace->currency_code ?? 'THB';
    $acceptedPartners = $workspace->memberships
        ->where('member_role', 'partner')
        ->where('invitation_status', 'accepted');
    $pendingInvitations = $workspace->memberships
        ->where('invitation_status', 'pending');
@endphp

<section class="pbr-business-page">
    <div class="portal-wrap pbr-business-wrap">
        <nav class="pbr-os-breadcrumb pbr-business-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.index') }}">My Businesses</a>
            <span>›</span>
            <span>{{ $businessName }}</span>
        </nav>

        <header class="pbr-business-hero">
            <div class="pbr-business-hero-copy">
                <span class="pbr-business-eyebrow">BUSINESS CONTROL CENTER</span>
                <h1>{{ $businessName }}</h1>
                <p class="pbr-business-hero-lead">
                    ဒီ Business ရဲ့ Partner၊ Capital၊ Ownership၊ Profit၊ Finance၊ Governance၊ Risk နဲ့ Exit Rules တွေကို
                    <strong>တစ်နေရာတည်းက လက်တွေ့စီမံ</strong>ပြီး PBR AI ကို လက်ရှိအတည်ပြုထားတဲ့ Business Data နဲ့ အသုံးပြုနိုင်ပါတယ်။
                </p>
                <div class="pbr-business-tags">
                    <span>{{ $workspace->business_stage === 'existing' ? 'ရှိပြီးသား Business' : 'Business အသစ် စီစဉ်နေသည်' }}</span>
                    <span>{{ $currency }}</span>
                    <span>Partner {{ $metrics['partner_count'] }} ဦး</span>
                    @unless($businessState['can_manage'])
                        <span>Partner Read-Only</span>
                    @endunless
                </div>
            </div>

            <div class="pbr-business-hero-actions">
                @if($canManageBusiness)
                    <a class="pbr-business-btn secondary" href="{{ route('workspaces.edit', $workspace) }}">Business Settings</a>
                @endif
                <a class="pbr-business-btn" href="{{ route('workspaces.tools.index', $workspace) }}">Operating System ဖွင့်ရန် →</a>
            </div>
        </header>

        @unless($businessState['can_manage'])
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>Owner/Admin က အတည်ပြုထားတဲ့ Active Business Rules နဲ့ shared operating data ကိုသာ မြင်ရပါတယ်။ Private Working Draft တွေ မပြပါဘူး။</p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        <section class="pbr-business-metrics" aria-label="Business overview">
            <article class="pbr-business-metric {{ $metrics['funding_gap'] > 0 ? 'attention' : 'healthy' }}">
                <span class="pbr-mm-label">လိုအပ်နေသေးသော ရင်းနှီးငွေ</span>
                <small class="pbr-en-label">Funding Gap</small>
                <strong>{{ $currency }} {{ number_format((float) $metrics['funding_gap'], 2) }}</strong>
                <div class="pbr-metric-foot">
                    <b class="{{ $metrics['funding_gap'] > 0 ? 'warning' : 'active' }}">
                        {{ $metrics['funding_gap'] > 0 ? 'စီမံရန်လိုသည်' : 'Gap မရှိ' }}
                    </b>
                </div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">အသုံးပြုနေသော Business Rules</span>
                <small class="pbr-en-label">Active Rules</small>
                <strong>{{ $metrics['active_rule_count'] }}</strong>
                <div class="pbr-metric-foot"><span>အတည်ပြုပြီး လက်ရှိအသုံးပြုနေသော Rules</span></div>
            </article>

            <article class="pbr-business-metric {{ $metrics['working_change_count'] > 0 ? 'draft' : '' }}">
                <span class="pbr-mm-label">Review လုပ်ရန် ပြောင်းလဲမှု</span>
                <small class="pbr-en-label">Working Changes</small>
                <strong>{{ $metrics['working_change_count'] }}</strong>
                <div class="pbr-metric-foot"><span>Active Rule မပြောင်းခင် စစ်ဆေးရမည့် Draft</span></div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">Business Records</span>
                <small class="pbr-en-label">Operating Records</small>
                <strong>{{ $metrics['operating_record_count'] }}</strong>
                <div class="pbr-metric-foot"><span>အတည်ပြုမှုမှ ထွက်လာသော လက်တွေ့မှတ်တမ်းများ</span></div>
            </article>
        </section>

        <section class="pbr-business-attention">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">ACTION CENTER</span>
                    <h2>အခုအရင်ဆုံး စီမံရမယ့်အရာများ</h2>
                    <p>Working Draft၊ Funding Gap နဲ့ မသတ်မှတ်ရသေးတဲ့ အရေးကြီး Business Area တွေကို Action အလိုက် ပြပါတယ်။</p>
                </div>
            </div>

            @if($actions->isNotEmpty())
                <div class="pbr-business-attention-grid">
                    @foreach($actions->take(6) as $action)
                        <a href="{{ $action['url'] }}" class="pbr-business-attention-card {{ $action['level'] }}">
                            <span class="pbr-en-label">{{ strtoupper(str_replace('_', ' ', $action['domain'])) }}</span>
                            <strong>{{ $action['title_mm'] }}</strong>
                            <small>{{ $action['detail_mm'] }}</small>
                            <b>{{ $action['action_mm'] }}</b>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="pbr-business-healthy-banner">
                    <span>✓</span>
                    <div>
                        <strong>လက်ရှိအရေးပေါ် Action မရှိပါ</strong>
                        <p>လက်ရှိ Active Rules နဲ့ Funding အခြေအနေအရ အရေးပေါ်ပြန်စစ်ရမယ့် Working Change မရှိပါ။</p>
                    </div>
                </div>
            @endif
        </section>

        <section class="pbr-business-systems">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">BUSINESS AREAS</span>
                    <h2>Partnership တစ်ခုလုံးကို စနစ်တကျ ထိန်းချုပ်ပါ</h2>
                    <p>Chapter မဟုတ်ပါဘူး။ တစ်ခုချင်းစီက ဒီ Business မှာ ဆက်တိုက်အသုံးပြုရမယ့် Operating Area ဖြစ်ပါတယ်။</p>
                </div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-business-btn secondary">Full Operating System →</a>
            </div>

            <div class="pbr-business-area-grid">
                @foreach($systems as $system)
                    <a href="{{ $system['url'] }}" class="pbr-business-area-card {{ $system['state']['key'] }}">
                        <div class="pbr-business-area-top">
                            <span class="pbr-business-state {{ $system['state']['key'] }}">{{ $system['state']['label_mm'] }}</span>
                            <small>{{ $system['name_en'] }}</small>
                        </div>
                        <h3>{{ $system['name_mm'] }}</h3>
                        <p>{{ $system['short_mm'] }}</p>
                        <div class="pbr-business-area-foot">
                            <span>Active {{ $system['active_count'] }}</span>
                            @if($businessState['can_manage'])
                                <span>Review {{ $system['working_count'] }}</span>
                            @endif
                            <b>စီမံရန် →</b>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="pbr-business-utility-grid">
            <article class="pbr-business-utility-card ai">
                <span class="pbr-business-eyebrow">PBR AI ADVISOR</span>
                <h3>သင့် Business Data ကိုသိတဲ့ AI Advisor</h3>
                <p>Permission-safe Active Rules၊ Partner Data၊ Valuation၊ Feasibility နဲ့ Operating Records ကို PBR Knowledge နဲ့ တွဲပြီး မေးမြန်းနိုင်ပါတယ်။</p>
                <a class="pbr-business-btn" href="{{ route('workspaces.ai-advisor.index', $workspace) }}">PBR AI ကို မေးရန် ✦</a>
            </article>

            <article class="pbr-business-utility-card">
                <span class="pbr-business-eyebrow">PARTNERS</span>
                <h3>Partner Roster & Roles</h3>
                <p>တစ်ကြိမ်ထည့်ထားတဲ့ Partner Profile ကို Capital၊ Ownership၊ Roles၊ Governance နဲ့ Exit အပိုင်းတွေမှာ ပြန်သုံးနိုင်ပါတယ်။</p>
                <a class="pbr-business-btn secondary" href="{{ route('workspaces.partner-roster.index', $workspace) }}">Partner များ စီမံရန် →</a>
            </article>

            <article class="pbr-business-utility-card">
                <span class="pbr-business-eyebrow">DECISION SUPPORT</span>
                <h3>Feasibility</h3>
                <p>Business အသစ်၊ Branch၊ Product သို့မဟုတ် Project တစ်ခုကို လက်ရှိအခြေအနေမှာ လုပ်သင့်မလုပ်သင့် အချက်အလက်ပေါ်မူတည်ပြီး စစ်ဆေးပါ။</p>
                <a class="pbr-business-btn secondary" href="{{ route('workspaces.feasibility.show', $workspace) }}">Feasibility စစ်ဆေးရန် →</a>
            </article>

            @if($workspace->isExistingPartnership())
                <article class="pbr-business-utility-card">
                    <span class="pbr-business-eyebrow">BUSINESS VALUE</span>
                    <h3>Business Valuation</h3>
                    <p>Financial Data နဲ့ Valuation Methods အမျိုးမျိုးကို သုံးပြီး Conservative၊ Base နဲ့ Optimistic Value Range ကို စစ်ဆေးပါ။</p>
                    <a class="pbr-business-btn secondary" href="{{ route('workspaces.valuation.show', $workspace) }}">Valuation Center →</a>
                </article>
            @endif

            <article class="pbr-business-utility-card">
                <span class="pbr-business-eyebrow">PARTNER INTELLIGENCE</span>
                <h3>Partner Dynamics</h3>
                <p>Partner တွေရဲ့ Strengths၊ Decision Style နဲ့ Role Fit ကို Partner Role စီမံရာမှာ အထောက်အကူဖြစ်အောင် အသုံးပြုပါ။</p>
                <a class="pbr-business-btn secondary" href="{{ route('workspaces.partner-dynamics.show', $workspace) }}">Partner Alignment →</a>
            </article>
        </section>

        @if(session('invitation_link'))
            <section class="pbr-business-section-card">
                <span class="pbr-business-eyebrow">INVITATION READY</span>
                <h2>Partner ဆီပို့ရန် Link အသင့်ဖြစ်ပါပြီ</h2>
                <p>ဒီ Link က single-use ဖြစ်ပါတယ်။ ဖိတ်ချင်တဲ့ Partner ကိုပဲ ပို့ပါ။</p>
                <div class="pbr2-copy-box">{{ session('invitation_link') }}</div>
            </section>
        @endif

        <section class="pbr-business-section-card">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">PARTNERS & ACCESS</span>
                    <h2>Partner Access ကို စီမံပါ</h2>
                    <p>Accepted Partner တွေက အတည်ပြုထားတဲ့ Business Data ကို Permission အတိုင်း ကြည့်နိုင်ပါတယ်။</p>
                </div>
            </div>

            <div class="pbr-business-partner-grid">
                <div class="pbr-business-partner-panel">
                    <h3>ချိတ်ဆက်ထားသော Partner {{ $acceptedPartners->count() }} ဦး</h3>
                    @forelse($acceptedPartners as $membership)
                        <div class="pbr-business-person-row">
                            <div>
                                <strong>{{ $membership->user?->name ?? $membership->invited_email }}</strong>
                                <small>{{ $membership->user?->email ?? $membership->invited_email }}</small>
                            </div>
                            <span>Partner</span>
                        </div>
                    @empty
                        <p class="pbr-business-muted">ချိတ်ဆက်ထားတဲ့ Partner မရှိသေးပါ။</p>
                    @endforelse
                </div>

                @if($canManageInvitations)
                    <div class="pbr-business-partner-panel">
                        <h3>Partner ဖိတ်ရန်</h3>
                        <p>Single-use Link သို့မဟုတ် Email နဲ့ ဖိတ်နိုင်ပါတယ်။</p>
                        <form method="POST" action="{{ route('workspace-invitations.shareable.store', $workspace) }}" class="pbr-business-inline-form">
                            @csrf
                            <button class="pbr-business-btn secondary" type="submit">Shareable Link ဖန်တီးရန်</button>
                        </form>

                        <form method="POST" action="{{ route('workspace-invitations.store', $workspace) }}" class="pbr-business-email-form">
                            @csrf
                            <label for="partner-email">Partner Email</label>
                            <div>
                                <input id="partner-email" name="email" type="email" value="{{ old('email') }}" placeholder="partner@example.com" required>
                                <button class="pbr-business-btn" type="submit">ဖိတ်ရန်</button>
                            </div>
                            @error('email')<small class="pbr2-error">{{ $message }}</small>@enderror
                        </form>
                    </div>
                @endif
            </div>

            @if($canManageInvitations && $pendingInvitations->isNotEmpty())
                <div class="pbr-business-pending-list">
                    <h3>Pending Invitations</h3>
                    @foreach($pendingInvitations as $invitation)
                        <div class="pbr-business-person-row">
                            <div>
                                <strong>
                                    @if(str_ends_with(strtolower((string) $invitation->invited_email), '@invite.thepbr.local'))
                                        Shareable Invitation Link
                                    @else
                                        {{ $invitation->invited_email }}
                                    @endif
                                </strong>
                                <small>{{ optional($invitation->invited_at)->diffForHumans() }}</small>
                            </div>
                            <form method="POST" action="{{ route('workspace-invitations.revoke', [$workspace, $invitation]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="pbr-business-text-button" type="submit">ပယ်ဖျက်ရန်</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="pbr-business-legal-note">
            <strong>Planning & Governance Note</strong>
            <p>{{ config('pbr_business_operating_system.legal_note_mm') }}</p>
        </section>
    </div>
</section>
@endsection
