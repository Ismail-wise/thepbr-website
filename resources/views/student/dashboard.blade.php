@extends('layouts.student-portal')

@section('title', 'Business Control Center')

@section('content')
@php
    $primaryBusiness = $businesses->first();
    $primaryWorkspace = $primaryBusiness['workspace'] ?? null;
@endphp

<section class="pbr-portfolio-page pbr-premium-dashboard" data-premium-dashboard>
    <div class="portal-wrap pbr-business-wrap">
        <header class="pbr-premium-dashboard-hero">
            <div class="pbr-premium-dashboard-copy">
                <span class="pbr-business-eyebrow">PBR BUSINESS OPERATING SYSTEM</span>
                <div class="pbr-premium-dashboard-title-row">
                    <h1>Business Control Center</h1>
                    <span class="pbr-premium-live-status"><i></i> Current View</span>
                </div>
                <p>
                    မင်္ဂလာပါ <strong>{{ $user->name }}</strong>။ Business တစ်ခုချင်းစီရဲ့ လက်ရှိအခြေအနေ၊ Review လိုတာ၊
                    Setup ကျန်တာနဲ့ အရေးကြီးဆုံး Next Action ကို တစ်နေရာတည်းက စီမံနိုင်ပါတယ်။
                </p>
            </div>

            <div class="pbr-premium-dashboard-actions">
                @if($primaryWorkspace)
                    <a href="{{ route('workspaces.ai-advisor.index', $primaryWorkspace) }}" class="pbr-business-btn secondary">PBR AI ကို မေးရန် ✦</a>
                @endif

                @if($user->isAdmin() || $user->isStudent())
                    <a href="{{ route('workspaces.create') }}" class="pbr-business-btn">+ Business အသစ်ထည့်ရန်</a>
                @endif
            </div>
        </header>

        <section class="pbr-premium-portfolio-metrics" aria-label="Portfolio status overview">
            <article class="pbr-premium-metric neutral">
                <div>
                    <span>Businesses</span>
                    <small>စီမံနေသော Business</small>
                </div>
                <strong>{{ $portfolioMetrics['business_count'] }}</strong>
                <p>သင်ဝင်ရောက်ခွင့်ရှိသော Business Workspace အားလုံး</p>
            </article>

            <article class="pbr-premium-metric action {{ $portfolioMetrics['needs_action_count'] > 0 ? 'has-value' : '' }}">
                <div>
                    <span>Needs Action</span>
                    <small>ချက်ချင်းစီမံရန်</small>
                </div>
                <strong>{{ $portfolioMetrics['needs_action_count'] }}</strong>
                <p>Funding Gap ရှိနေပြီး Capital Action လိုသော Business</p>
            </article>

            <article class="pbr-premium-metric review {{ $portfolioMetrics['needs_review_count'] > 0 ? 'has-value' : '' }}">
                <div>
                    <span>Needs Review</span>
                    <small>ပြန်လည်စစ်ဆေးရန်</small>
                </div>
                <strong>{{ $portfolioMetrics['needs_review_count'] }}</strong>
                <p>Active Rule မပြောင်းခင် Review လိုသော Working Change ရှိသည်</p>
            </article>

            <article class="pbr-premium-metric setup {{ $portfolioMetrics['setup_required_count'] > 0 ? 'has-value' : '' }}">
                <div>
                    <span>Setup Required</span>
                    <small>စတင်သတ်မှတ်ရန်</small>
                </div>
                <strong>{{ $portfolioMetrics['setup_required_count'] }}</strong>
                <p>အရေးပေါ်မဟုတ်ဘဲ Operating Area တချို့ မသတ်မှတ်ရသေးသော Business</p>
            </article>
        </section>

        <section class="pbr-premium-businesses">
            <div class="pbr-premium-section-heading">
                <div>
                    <span class="pbr-business-eyebrow">MY BUSINESSES</span>
                    <h2>Business Portfolio</h2>
                    <p>အရေးကြီးဆုံး Action ရှိတဲ့ Business ကို အပေါ်ဆုံးမှာ အလိုအလျောက်ပြထားပါတယ်။</p>
                </div>

                <div class="pbr-premium-portfolio-health">
                    <span>Stable</span>
                    <strong>{{ $portfolioMetrics['stable_count'] }}</strong>
                    <small>လက်ရှိ Action မလို</small>
                </div>
            </div>

            @if($businesses->isEmpty())
                <div class="pbr-business-empty-real pbr-premium-empty-state">
                    <div class="pbr-business-empty-icon">＋</div>
                    <div>
                        <h3>ပထမဆုံး Business Workspace တည်ဆောက်ပါ</h3>
                        <p>Business data၊ Partner၊ Ownership၊ Capital နဲ့ Decision Rules တွေကို တစ်နေရာတည်းက စီမံဖို့ Business တစ်ခု စတင်ထည့်ပါ။</p>
                    </div>
                    @if($user->isAdmin() || $user->isStudent())
                        <a href="{{ route('workspaces.create') }}" class="pbr-business-btn">Business အသစ်ထည့်ရန်</a>
                    @endif
                </div>
            @else
                <div class="pbr-premium-business-grid">
                    @foreach($businesses as $business)
                        @php
                            $workspace = $business['workspace'];
                            $metrics = $business['metrics'];
                            $status = $business['status'];
                            $nextAction = $business['next_action'];
                            $isNew = $workspace->business_stage === 'new';
                        @endphp

                        <article class="pbr-premium-business-card status-{{ $status['key'] }}">
                            <div class="pbr-premium-business-card-top">
                                <div class="pbr-premium-business-identity">
                                    <span class="pbr-premium-stage">{{ $isNew ? 'NEW BUSINESS PLAN' : 'OPERATING BUSINESS' }}</span>
                                    <h3>{{ $workspace->business_name ?: $workspace->name }}</h3>
                                    <p>
                                        {{ $workspace->currency_code ?? 'THB' }}
                                        <span>•</span>
                                        Partners {{ $metrics['partner_count'] }}
                                        <span>•</span>
                                        {{ $metrics['active_area_count'] }} Active Areas
                                    </p>
                                </div>

                                <span class="pbr-premium-business-status status-{{ $status['key'] }}">
                                    <small>{{ $status['label_en'] }}</small>
                                    <strong>{{ $status['label_mm'] }}</strong>
                                </span>
                            </div>

                            <div class="pbr-premium-business-stats">
                                <div>
                                    <span>Funding Gap</span>

                                    @if($metrics['capital_data_available'] ?? false)
                                        <strong>
                                            {{ $workspace->currency_code ?? 'THB' }}
                                            {{ number_format((float) $metrics['funding_gap'], 0) }}
                                        </strong>
                                    @else
                                        <strong>—</strong>
                                    @endif
                                </div>
                                <div>
                                    <span>Active Rules</span>
                                    <strong>{{ $metrics['active_rule_count'] }}</strong>
                                </div>
                                <div>
                                    <span>Pending Review</span>
                                    <strong>{{ $metrics['working_change_count'] }}</strong>
                                </div>
                                <div>
                                    <span>Business Records</span>
                                    <strong>{{ $metrics['operating_record_count'] }}</strong>
                                </div>
                            </div>

                            <div class="pbr-premium-next-action status-{{ $status['key'] }}">
                                <span>NEXT ACTION</span>

                                @if($status['key'] === 'stable')
                                    <strong>လက်ရှိ အရေးပေါ် Action မရှိပါ</strong>
                                    <p>{{ $status['detail_mm'] }}</p>
                                @elseif($nextAction)
                                    <strong>{{ $nextAction['title_mm'] }}</strong>
                                    <p>{{ $nextAction['detail_mm'] ?? $status['detail_mm'] }}</p>
                                    <a href="{{ $nextAction['url'] }}">{{ $nextAction['action_mm'] }}</a>
                                @else
                                    <strong>{{ $status['label_mm'] }}</strong>
                                    <p>{{ $status['detail_mm'] }}</p>
                                @endif
                            </div>

                            <div class="pbr-premium-business-card-actions">
                                <a href="{{ route('workspaces.show', $workspace) }}" class="pbr-business-btn secondary">Overview</a>
                                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-business-btn">Open Business OS →</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="pbr-premium-dashboard-footer-actions">
            <div>
                <span class="pbr-business-eyebrow">PARTNER INTELLIGENCE</span>
                <h2>Partner Dynamics</h2>
                <p>Working Style၊ Decision Style နဲ့ Role Fit ကို Operating Decisions တွေနဲ့ ချိတ်ဆက်စီမံနိုင်ပါတယ်။</p>
            </div>
            <a href="{{ route('partner-dynamics.index') }}" class="pbr-business-btn secondary">Partner Dynamics ဖွင့်ရန် →</a>
        </section>
    </div>
</section>
@endsection
