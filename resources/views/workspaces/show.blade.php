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

    $pendingEmailInvitations = $pendingInvitations->reject(
        fn ($invitation) => str_ends_with(
            strtolower((string) $invitation->invited_email),
            '@invite.thepbr.local'
        )
    );

    $notConfiguredCount = (int) ($metrics['not_configured_area_count'] ?? $systems
        ->filter(fn ($system) => ($system['state']['key'] ?? null) === 'not_configured')
        ->count());

    if ((float) ($metrics['funding_gap'] ?? 0) > 0) {
        $overallStatusKey = 'needs-action';
        $overallStatusLabel = 'Needs Action';
    } elseif ((int) ($metrics['working_change_count'] ?? 0) > 0) {
        $overallStatusKey = 'needs-review';
        $overallStatusLabel = 'Needs Review';
    } elseif ($notConfiguredCount > 0) {
        $overallStatusKey = 'setup-required';
        $overallStatusLabel = 'Setup Required';
    } else {
        $overallStatusKey = 'stable';
        $overallStatusLabel = 'Stable';
    }

    $priorityActions = $actions
        ->groupBy('domain')
        ->map(function ($items) {
            $action = $items->first();
            $action['open_items'] = $items->count();

            return $action;
        })
        ->values()
        ->take(4);
@endphp

<section class="pbr-overview-v2">
    <div class="ov-wrap">
        <nav class="ov-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.index') }}">My Businesses</a>
            <span>›</span>
            <span>{{ $businessName }}</span>
        </nav>

        <header class="ov-hero">
            <div>
                <span class="ov-eyebrow">BUSINESS CONTROL CENTER</span>
                <h1>{{ $businessName }}</h1>
                <p>
                    လက်ရှိ Business Position၊ Partner Profiles၊ Funding၊ Approved Rules၊ Pending Reviews နဲ့
                    Operating Areas တွေကို တစ်နေရာတည်းက စီမံပြီး နောက်တစ်ဆင့်လုပ်ရမယ့် Action ကို အမြန်ဆုံးသိနိုင်ပါတယ်။
                </p>

                <div class="ov-tags">
                    <span>{{ $workspace->business_stage === 'existing' ? 'Operating Business' : 'New Business Plan' }}</span>
                    <span>{{ $currency }}</span>
                    <span>Partner Profiles {{ (int) ($metrics['partner_count'] ?? 0) }}</span>
                    <span class="ov-status {{ $overallStatusKey }}">{{ $overallStatusLabel }}</span>
                    @unless($businessState['can_manage'])
                        <span>Partner Read-Only</span>
                    @endunless
                </div>
            </div>

            <div class="ov-hero-actions">
                @if($canUsePbrAiAdvisor)
                    <a class="ov-btn" href="{{ route('workspaces.ai-advisor.index', $workspace) }}">Ask PBR AI ✦</a>
                @endif
                <a class="ov-btn" href="{{ route('workspaces.tools.index', $workspace) }}#current-business-rules">Current Rules</a>
                <a class="ov-btn" href="{{ route('workspaces.rulebook.show', $workspace) }}">Business Rulebook</a>
                @if($canManageBusiness)
                    <a class="ov-btn" href="{{ route('workspaces.edit', $workspace) }}">Business Settings</a>
                @endif
                <a class="ov-btn primary" href="{{ route('workspaces.tools.index', $workspace) }}">Open Operating System →</a>
            </div>
        </header>

        @unless($businessState['can_manage'])
            <div class="ov-readonly">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>Owner/Admin က Activate လုပ်ထားတဲ့ Business Rules နဲ့ shared operating data ကိုသာ မြင်ရပြီး private Working Changes တွေ မမြင်ရပါဘူး။</p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        <section class="ov-position" aria-label="Current business position">
            <article class="ov-kpi {{ (float) ($metrics['funding_gap'] ?? 0) > 0 ? 'attention' : '' }}">
                <span>လိုအပ်နေသေးသော ရင်းနှီးငွေ</span>
                <small>Funding Gap</small>
                <strong>{{ $currency }} {{ number_format((float) ($metrics['funding_gap'] ?? 0), 0) }}</strong>
                <p>{{ (float) ($metrics['funding_gap'] ?? 0) > 0 ? 'Capital plan မှာ ဖြည့်ဆည်းရန်လိုနေသည်' : 'လက်ရှိ Funding Gap မရှိပါ' }}</p>
            </article>

            <article class="ov-kpi">
                <span>လက်ရှိအသုံးပြုနေသော Rules</span>
                <small>Active Rules</small>
                <strong>{{ (int) ($metrics['active_rule_count'] ?? 0) }}</strong>
                <p>Approve & Activate လုပ်ထားသော Current Rules</p>
            </article>

            <article class="ov-kpi {{ (int) ($metrics['working_change_count'] ?? 0) > 0 ? 'review' : '' }}">
                <span>ပြန်စစ်ရန်ရှိသော Changes</span>
                <small>Pending Review</small>
                <strong>{{ (int) ($metrics['working_change_count'] ?? 0) }}</strong>
                <p>Current Rule မပြောင်းခင် Review လုပ်ရန်</p>
            </article>

            <article class="ov-kpi">
                <span>Partner Profiles</span>
                <small>Operating Roster</small>
                <strong>{{ (int) ($metrics['partner_count'] ?? 0) }}</strong>
                <p>Owner + current/planned Partner Profiles</p>
            </article>

            <article class="ov-kpi">
                <span>Business Records</span>
                <small>Operating Records</small>
                <strong>{{ (int) ($metrics['operating_record_count'] ?? 0) }}</strong>
                <p>Approved decisions ကနေ ထွက်လာသော records</p>
            </article>
        </section>

        <section class="ov-section">
            <div class="ov-section-head">
                <div>
                    <span class="ov-eyebrow">PRIORITY ACTIONS</span>
                    <h2>အခု ဘာလုပ်ရမလဲ</h2>
                    <p>တူညီတဲ့ Operating Area ထဲက issue တွေကို တစ်စုတည်းပြပြီး owner က အရေးကြီးဆုံး Action ကို မြန်မြန်ရွေးနိုင်အောင် စီထားပါတယ်။</p>
                </div>
            </div>

            @if($priorityActions->isNotEmpty())
                <div class="ov-priority-grid">
                    @foreach($priorityActions as $action)
                        <a href="{{ $action['url'] }}" class="ov-priority-card {{ $action['level'] }}">
                            <span class="domain">{{ strtoupper(str_replace('_', ' ', $action['domain'])) }}</span>
                            <strong>{{ $action['title_mm'] }}</strong>
                            <p>{{ $action['detail_mm'] }}</p>
                            <footer>
                                <span>{{ $action['action_mm'] }}</span>
                                @if(($action['open_items'] ?? 1) > 1)
                                    <em>{{ $action['open_items'] }} open items</em>
                                @endif
                            </footer>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="ov-healthy">
                    <span>✓</span>
                    <div>
                        <strong>လက်ရှိ Priority Action မရှိပါ</strong>
                        <p>Funding Gap၊ Pending Review နဲ့ urgent operating issue မရှိသေးပါဘူး။</p>
                    </div>
                </div>
            @endif
        </section>

        <section class="ov-section">
            <div class="ov-section-head">
                <div>
                    <span class="ov-eyebrow">OPERATING AREAS</span>
                    <h2>Business Operating Areas 10 ခု</h2>
                    <p>Business ရဲ့ Data၊ Decision၊ Approval နဲ့ Current Rule တွေကို ပြောင်းလဲမှုတိုင်းနဲ့အတူ ဆက်လက်ထိန်းသိမ်းပြီး လက်တွေ့လုပ်ငန်းလည်ပတ်မှုကို တစ်နေရာတည်းက စီမံနိုင်တဲ့ operating systems တွေပါ။</p>
                </div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="ov-btn">View Full Operating System →</a>
            </div>

            <div class="ov-system-grid">
                @foreach($systems as $system)
                    <a href="{{ $system['url'] }}" class="ov-system-card {{ $system['state']['key'] }}">
                        <div class="ov-system-top">
                            <span>{{ $system['state']['label_mm'] }}</span>
                            <small>{{ $system['name_en'] }}</small>
                        </div>
                        <h3>{{ $system['name_mm'] }}</h3>
                        <div class="ov-system-footer">
                            <span>Active {{ $system['active_count'] }}</span>
                            @if($businessState['can_manage'])
                                <span>Review {{ $system['working_count'] }}</span>
                            @endif
                            <b>Open →</b>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="ov-section">
            <div class="ov-section-head">
                <div>
                    <span class="ov-eyebrow">BUSINESS SERVICES</span>
                    <h2>ဆုံးဖြတ်ချက်နဲ့ Business Records ကို ဆက်သုံးရန်</h2>
                    @if($canManageBusiness)
                        <p>Operating Areas ထဲက approved data ကို AI၊ Rule Register၊ Valuation နဲ့ Partner workflows တွေနဲ့ ချိတ်ဆက်အသုံးပြုနိုင်ပါတယ်။</p>
                    @else
                        <p>Approved Current Rules နဲ့ Partner Dynamics လို shared business workflows တွေကို ဒီနေရာကနေ ဆက်သုံးနိုင်ပါတယ်။</p>
                    @endif
                </div>
            </div>

            <div class="ov-tools-grid">
                @if($canUsePbrAiAdvisor)
                    <article class="ov-tool-card ai">
                        <span class="ov-eyebrow">PBR AI ADVISOR</span>
                        <h3>Business-aware AI Advisor</h3>
                        <p>Permission-safe Current Rules၊ Partner Data၊ Valuation၊ Feasibility နဲ့ Business Records ပေါ်မူတည်ပြီး မေးမြန်းနိုင်ပါတယ်။</p>
                        <a class="ov-btn primary" href="{{ route('workspaces.ai-advisor.index', $workspace) }}">Ask PBR AI ✦</a>
                    </article>
                @endif

                <article class="ov-tool-card">
                    <span class="ov-eyebrow">DOCUMENTS & RULES</span>
                    <h3>Current Business Rule Register</h3>
                    <p>Approve & Activate လုပ်ထားတဲ့ Current Rules တွေကို Operating Area အလိုက် ပြန်ကြည့်ပြီး Print / Save PDF လုပ်နိုင်ပါတယ်။</p>
                    <a class="ov-btn" href="{{ route('workspaces.tools.index', $workspace) }}#current-business-rules">Open Rule Register →</a>
                </article>

                @if($canManageBusiness)
                    <article class="ov-tool-card">
                        <span class="ov-eyebrow">PARTNERS</span>
                        <h3>Partner Roster & Roles</h3>
                        <p>Partner Profile တစ်ကြိမ်ထည့်ပြီး Capital၊ Ownership၊ Roles၊ Governance၊ Exit နဲ့ Transfer areas တွေမှာ ပြန်သုံးနိုင်ပါတယ်။</p>
                        <a class="ov-btn" href="{{ route('workspaces.partner-roster.index', $workspace) }}">Open Partner Roster →</a>
                    </article>

                    <article class="ov-tool-card">
                        <span class="ov-eyebrow">DECISION SUPPORT</span>
                        <h3>Business Feasibility</h3>
                        <p>Business အသစ်၊ Branch၊ Product ဒါမှမဟုတ် Project တစ်ခုကို လက်ရှိ Business Data နဲ့ စစ်ဆေးပြီး decision support ရယူနိုင်ပါတယ်။</p>
                        <a class="ov-btn" href="{{ route('workspaces.feasibility.show', $workspace) }}">Open Feasibility →</a>
                    </article>

                    <article class="ov-tool-card">
                        <span class="ov-eyebrow">BUSINESS VALUE</span>
                        <h3>Business Valuation</h3>
                        <p>လက်ရှိ Business Value ကို structured assumptions နဲ့တွက်ပြီး ownership၊ buyout နဲ့ transfer decisions တွေအတွက် reference အဖြစ်သုံးနိုင်ပါတယ်။</p>
                        <a class="ov-btn" href="{{ route('workspaces.valuation.show', $workspace) }}">Open Valuation →</a>
                    </article>
                @endif

                <article class="ov-tool-card">
                    <span class="ov-eyebrow">PARTNER INTELLIGENCE</span>
                    <h3>Partner Dynamics</h3>
                    <p>Working Style၊ Decision Style၊ Strengths နဲ့ Role Fit ကို operating decisions တွေနဲ့ချိတ်ပြီး partner alignment ကို ပြန်စစ်နိုင်ပါတယ်။</p>
                    <a class="ov-btn" href="{{ route('workspaces.partner-dynamics.show', $workspace) }}">Open Partner Dynamics →</a>
                </article>
            </div>
        </section>

        @if($canManageInvitations)
            <section class="ov-section" id="partner-access">
                <div class="ov-section-head">
                    <div>
                        <span class="ov-eyebrow">PARTNERS & ACCESS</span>
                        <h2>Partner Access</h2>
                        <p>Partner Profiles နဲ့ Login Access ကို သီးခြားစီထိန်းထားပါတယ်။ Accepted account ဆိုတာ system ထဲဝင်ကြည့်ခွင့်ရှိသူကိုဆိုလိုပြီး Partner Profile count နဲ့ မတူနိုင်ပါတယ်။</p>
                    </div>
                </div>

                <div class="ov-access-grid">
                    <article class="ov-panel">
                        <h3>Accepted Partner Accounts — {{ $acceptedPartners->count() }}</h3>
                        <p>ဒီ Accounts တွေက Owner/Admin သတ်မှတ်ထားတဲ့ permission-safe Business Data ကို login ဝင်ပြီး ကြည့်နိုင်ပါတယ်။</p>

                        @if($acceptedPartners->isNotEmpty())
                            <div class="ov-account-list">
                                @foreach($acceptedPartners as $partner)
                                    <div class="ov-account">
                                        <div>
                                            <strong>{{ $partner->user?->name ?: 'Partner' }}</strong>
                                            <small>{{ $partner->user?->email ?: $partner->invited_email }}</small>
                                        </div>
                                        <span>Partner</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="ov-empty">Accepted Partner Account မရှိသေးပါဘူး။ Partner Profile ရှိတာနဲ့ Login Access ရှိတာ မတူပါဘူး။</div>
                        @endif
                    </article>

                    <article class="ov-panel">
                        <h3>Invite Partner</h3>
                        <p>Email တစ်ခုကို တိုက်ရိုက်ဖိတ်နိုင်သလို single-use shareable link တစ်ခုလည်း ဖန်တီးနိုင်ပါတယ်။</p>

                        @if(session('invitation_link'))
                            <div class="ov-invite-link">
                                <strong>{{ session('shareable_invitation') ? 'Shareable invitation created' : 'Partner invitation created' }}</strong>
                                <small>ဒီ link ကို recipient ကိုပဲပို့ပါ။ Link က invitation acceptance အတွက်အသုံးပြုမှာပါ။</small>
                                <input class="ov-input" type="text" readonly value="{{ session('invitation_link') }}" onclick="this.select()">
                            </div>
                        @endif

                        <div class="ov-invite-actions">
                            <div class="ov-invite-block">
                                <strong>Invite by Email</strong>
                                <form class="ov-inline-form" method="POST" action="{{ route('workspace-invitations.store', $workspace) }}">
                                    @csrf
                                    <input class="ov-input" type="email" name="email" placeholder="partner@example.com" required>
                                    <button class="ov-btn primary" type="submit">Create Invite</button>
                                </form>
                            </div>

                            <div class="ov-invite-block">
                                <strong>Single-use Shareable Link</strong>
                                <form method="POST" action="{{ route('workspace-invitations.shareable.store', $workspace) }}">
                                    @csrf
                                    <button class="ov-btn" type="submit">Create Shareable Link →</button>
                                </form>
                            </div>
                        </div>

                        @if($pendingEmailInvitations->isNotEmpty())
                            <div class="ov-pending">
                                @foreach($pendingEmailInvitations as $invitation)
                                    <div class="ov-pending-row">
                                        <span>Pending · {{ $invitation->invited_email }}</span>
                                        <form method="POST" action="{{ route('workspace-invitations.revoke', [$workspace, $invitation]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Revoke</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                </div>
            </section>
        @endif

        <aside class="ov-note">
            <strong>Planning & Governance Note</strong>
            <p>PBR က Business Planning နဲ့ Partnership Governance အတွက် decision-support system ဖြစ်ပါတယ်။ Legal၊ tax၊ accounting သို့မဟုတ် regulated professional advice ကို အစားမထိုးပါဘူး။ အရေးကြီး agreement နဲ့ transaction တွေအတွက် qualified professional review ကို သီးခြားယူသင့်ပါတယ်။</p>
        </aside>
    </div>
</section>
@endsection