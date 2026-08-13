@extends('layouts.student-portal')

@section('title', 'My Businesses')

@section('content')
@php
    $managedBusinesses = $businesses->filter(fn (array $business) => $business['can_manage']);
    $partnerBusinesses = $businesses->reject(fn (array $business) => $business['can_manage']);
@endphp

<section class="pbr-workspaces-page">
    <div class="portal-wrap pbr-workspaces-wrap">
        <header class="pbr-workspaces-hero">
            <div class="pbr-workspaces-hero-copy">
                <span class="pbr-workspaces-eyebrow">BUSINESS PORTFOLIO</span>
                <h1>My Businesses</h1>
                <p>
                    Partnership Business တစ်ခုချင်းစီရဲ့ လက်ရှိအခြေအနေ၊ Funding၊ Active Rules၊ Review လိုအပ်ချက်နဲ့
                    Partner Access ကို တစ်နေရာတည်းက ကြည့်ပြီး သက်ဆိုင်ရာ Business Workspace ကို တိုက်ရိုက်ဖွင့်နိုင်ပါတယ်။
                </p>
            </div>

            @if($canCreateBusiness)
                <a class="pbr-workspaces-primary-action" href="{{ route('workspaces.create') }}">
                    <span>＋</span>
                    Business အသစ်ထည့်ရန်
                </a>
            @endif
        </header>

        <section class="pbr-workspaces-summary" aria-label="Business portfolio summary">
            <article>
                <span>Businesses</span>
                <strong>{{ $portfolioSummary['business_count'] }}</strong>
                <small>ဝင်ရောက်အသုံးပြုနိုင်သော Workspace</small>
            </article>
            <article>
                <span>Managed by You</span>
                <strong>{{ $portfolioSummary['owned_count'] }}</strong>
                <small>Owner/Admin အဖြစ် စီမံနိုင်သည်</small>
            </article>
            <article class="{{ $portfolioSummary['needs_attention_count'] > 0 ? 'attention' : '' }}">
                <span>Needs Attention</span>
                <strong>{{ $portfolioSummary['needs_attention_count'] }}</strong>
                <small>Action သို့မဟုတ် Review လိုအပ်သည်</small>
            </article>
            <article>
                <span>Partner Access</span>
                <strong>{{ $portfolioSummary['partner_access_count'] }}</strong>
                <small>Partner အဖြစ် ဝင်ရောက်ကြည့်နိုင်သည်</small>
            </article>
        </section>

        <section class="pbr-workspaces-section">
            <div class="pbr-workspaces-section-head">
                <div>
                    <span class="pbr-workspaces-eyebrow">MANAGED BUSINESSES</span>
                    <h2>သင် စီမံနေသော Business များ</h2>
                    <p>အရေးကြီးဆုံး Business ကို အရင်မြင်နိုင်အောင် Action → Review → Setup → Stable အလိုက် စီထားပါတယ်။</p>
                </div>
                @if($portfolioSummary['setup_required_count'] > 0)
                    <div class="pbr-workspaces-setup-note">
                        <strong>{{ $portfolioSummary['setup_required_count'] }}</strong>
                        <span>Setup Required</span>
                    </div>
                @endif
            </div>

            @if($managedBusinesses->isEmpty())
                <div class="pbr-workspaces-empty">
                    <div class="pbr-workspaces-empty-icon">＋</div>
                    <div>
                        <strong>ကိုယ်တိုင် စီမံနေသော Business မရှိသေးပါ</strong>
                        <p>Partnership Business တစ်ခုဖန်တီးပြီး actual operating data နဲ့ စတင်စီမံနိုင်ပါတယ်။</p>
                    </div>
                    @if($canCreateBusiness)
                        <a href="{{ route('workspaces.create') }}">Business တည်ဆောက်ရန် →</a>
                    @endif
                </div>
            @else
                <div class="pbr-workspaces-grid">
                    @foreach($managedBusinesses as $business)
                        @php
                            $workspace = $business['workspace'];
                            $metrics = $business['metrics'];
                            $status = $business['status'];
                            $nextAction = $business['next_action'];
                            $currency = $workspace->currency_code ?? 'THB';
                        @endphp

                        <article class="pbr-workspace-card status-{{ $status['key'] }}">
                            <div class="pbr-workspace-card-head">
                                <div>
                                    <div class="pbr-workspace-card-meta">
                                        <span class="access">OWNER</span>
                                        <span>{{ $workspace->business_stage === 'existing' ? 'Operating Business' : 'New Business Plan' }}</span>
                                    </div>
                                    <h3>{{ $workspace->business_name ?: $workspace->name }}</h3>
                                    <p>{{ $currency }} · Partner Profiles {{ $metrics['partner_count'] }} · Active Areas {{ $metrics['active_area_count'] }}/10</p>
                                </div>

                                <div class="pbr-workspace-status status-{{ $status['key'] }}">
                                    <small>{{ $status['label_en'] }}</small>
                                    <strong>{{ $status['label_mm'] }}</strong>
                                </div>
                            </div>

                            <div class="pbr-workspace-kpis">
                                <div>
                                    <span>Funding Gap</span>
                                    <strong>{{ $currency }} {{ number_format((float) $metrics['funding_gap'], 0) }}</strong>
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

                            <div class="pbr-workspace-next-action">
                                <span>NEXT ACTION</span>
                                @if($nextAction)
                                    <strong>{{ $nextAction['title_mm'] }}</strong>
                                    <p>{{ $nextAction['detail_mm'] }}</p>
                                    <a href="{{ $nextAction['url'] }}">{{ $nextAction['action_mm'] }}</a>
                                @else
                                    <strong>လက်ရှိ အရေးပေါ် Action မရှိပါ</strong>
                                    <p>Active Rules နဲ့ Business Records ကို ဆက်လက်စောင့်ကြည့်ပြီး လိုအပ်သလို Update လုပ်နိုင်ပါတယ်။</p>
                                @endif
                            </div>

                            <div class="pbr-workspace-card-actions">
                                <a class="primary" href="{{ route('workspaces.show', $workspace) }}">Open Business</a>
                                <a href="{{ route('workspaces.tools.index', $workspace) }}">Operating System</a>
                                <a href="{{ route('workspaces.edit', $workspace) }}">Settings</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if($partnerBusinesses->isNotEmpty())
            <section class="pbr-workspaces-section partner-access-section">
                <div class="pbr-workspaces-section-head">
                    <div>
                        <span class="pbr-workspaces-eyebrow">PARTNER ACCESS</span>
                        <h2>Partner အဖြစ် ဝင်ရောက်ထားသော Business များ</h2>
                        <p>Owner က အတည်ပြုထားတဲ့ Active Business Rules နဲ့ shared operating information ကို permission-safe view နဲ့ကြည့်နိုင်ပါတယ်။</p>
                    </div>
                </div>

                <div class="pbr-workspaces-grid partner-grid">
                    @foreach($partnerBusinesses as $business)
                        @php
                            $workspace = $business['workspace'];
                            $metrics = $business['metrics'];
                            $status = $business['status'];
                            $currency = $workspace->currency_code ?? 'THB';
                        @endphp

                        <article class="pbr-workspace-card partner-card status-{{ $status['key'] }}">
                            <div class="pbr-workspace-card-head">
                                <div>
                                    <div class="pbr-workspace-card-meta">
                                        <span class="partner-access">PARTNER ACCESS</span>
                                        <span>{{ $workspace->business_stage === 'existing' ? 'Operating Business' : 'New Business Plan' }}</span>
                                    </div>
                                    <h3>{{ $workspace->business_name ?: $workspace->name }}</h3>
                                    <p>Owner: {{ $workspace->owner?->name ?? '—' }} · {{ $currency }}</p>
                                </div>
                                <div class="pbr-workspace-status status-{{ $status['key'] }}">
                                    <small>{{ $status['label_en'] }}</small>
                                    <strong>{{ $status['label_mm'] }}</strong>
                                </div>
                            </div>

                            <div class="pbr-workspace-kpis compact">
                                <div><span>Active Areas</span><strong>{{ $metrics['active_area_count'] }}/10</strong></div>
                                <div><span>Active Rules</span><strong>{{ $metrics['active_rule_count'] }}</strong></div>
                                <div><span>Business Records</span><strong>{{ $metrics['operating_record_count'] }}</strong></div>
                            </div>

                            <div class="pbr-workspace-card-actions">
                                <a class="primary" href="{{ route('workspaces.show', $workspace) }}">Open Business</a>
                                <a href="{{ route('workspaces.tools.index', $workspace) }}">View Active Rules</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="pbr-workspaces-invitation">
            <details>
                <summary>
                    <div>
                        <span class="pbr-workspaces-eyebrow">JOIN A BUSINESS</span>
                        <strong>Partner Invitation Link ရှိပါသလား?</strong>
                        <small>ဖိတ်ကြားထားတဲ့ Business Workspace နဲ့ account ကို ချိတ်ဆက်ရန်</small>
                    </div>
                    <span class="pbr-workspaces-details-icon">＋</span>
                </summary>
                <div class="pbr-workspaces-invitation-body">
                    <p>Owner ဆီကရထားတဲ့ Invitation Link ကို ထည့်ပါ။ Link ကိုစစ်ပြီးမှ သင့် account နဲ့ Business Workspace ကို ချိတ်ဆက်ပေးပါမယ်။</p>
                    <form method="POST" action="{{ route('workspace-invitations.connect') }}">
                        @csrf
                        <label for="invitation_link">Invitation Link</label>
                        <div class="pbr-workspaces-invitation-row">
                            <input id="invitation_link" name="invitation_link" type="text" value="{{ old('invitation_link') }}" placeholder="https://thepbr.io/workspace-invitations/..." required>
                            <button type="submit">Link ကို စစ်ပြီး ချိတ်ဆက်ရန်</button>
                        </div>
                        @error('invitation_link')<small class="pbr-workspaces-error">{{ $message }}</small>@enderror
                    </form>
                </div>
            </details>
        </section>
    </div>
</section>
@endsection
