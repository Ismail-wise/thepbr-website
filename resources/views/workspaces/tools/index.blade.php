@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';

    $stageMm = $workspace->business_stage === 'new'
        ? 'Partnership Business အသစ်'
        : 'ရှိပြီးသား Partnership Business';

    $health = $dashboard['health'];
    $capitalDashboard = $dashboard['capital'];
    $rulebook = $dashboard['rulebook'];

    $formatMoney = static function ($value) use ($currency) {
        if ($value === null) {
            return '—';
        }

        return $currency.' '.number_format(
            (float) $value,
            2
        );
    };
@endphp

<section
    class="pbr-dashboard-v2-page"
    data-pbr-dashboard-v2
>
    <div class="portal-wrap pbr-dashboard-v2-wrap">

        <nav
            class="pbr-dashboard-v2-breadcrumb"
            aria-label="Breadcrumb"
        >
            <a href="{{ route('workspaces.show', $workspace) }}">
                {{ $workspace->business_name ?: $workspace->name }}
            </a>

            <span>›</span>

            <span>Business Operating System</span>
        </nav>

        <header class="pbr-v2-card pbr-v2-hero">
            <div>
                <span class="pbr-v2-eyebrow">
                    PBR BUSINESS OPERATING SYSTEM
                </span>

                <h1>
                    {{ $workspace->business_name ?: $workspace->name }}
                </h1>

                <p>
                    Partnership ရဲ့ လက်ရှိ Business Rules,
                    Working Drafts, Partner responsibilities နဲ့
                    အရေးကြီးဆုံး next actions တွေကို
                    တစ်နေရာတည်းကနေ စီမံပါ။
                </p>

                <div class="pbr-v2-tags">
                    <span>{{ $stageMm }}</span>
                    <span>{{ $currency }}</span>
                    <span>
                        Partner {{ $businessState['metrics']['partner_count'] }} ဦး
                    </span>

                    @unless($dashboard['can_manage'])
                        <span>Partner Read-Only</span>
                    @endunless
                </div>
            </div>

            <div class="pbr-v2-hero-actions">
                <a
                    href="{{ route('workspaces.rulebook.show', $workspace) }}"
                    class="pbr-v2-btn"
                >
                    Business Rulebook
                </a>

                <a
                    href="{{ route('workspaces.partner-roster.index', $workspace) }}"
                    class="pbr-v2-btn"
                >
                    Partner များ
                </a>

                @if($canUsePbrAiAdvisor)
                    <a
                        href="{{ route('workspaces.ai-advisor.index', $workspace) }}"
                        class="pbr-v2-btn primary"
                    >
                        PBR AI ကို မေးရန် ✦
                    </a>
                @endif
            </div>
        </header>

        @unless($dashboard['can_manage'])
            <section class="pbr-v2-readonly">
                <div>
                    <strong>Partner Read-Only View</strong>

                    <p>
                        အတည်ပြုထားသော Current Rules နဲ့
                        shared operating information ကိုသာ
                        ပြထားပါတယ်။ Owner ရဲ့ Working Drafts
                        နဲ့ private scenario data မပါပါဘူး။
                    </p>
                </div>

                <span>Read-only</span>
            </section>
        @endunless

        @if($dashboard['can_manage'])
            @if(!empty($dashboard['primary_action']))
                <a
                    href="{{ $dashboard['primary_action']['url'] }}"
                    class="pbr-v2-card pbr-v2-next"
                >
                    <div>
                        <span class="pbr-v2-eyebrow">
                            NEXT BUSINESS ACTION
                        </span>

                        <h2>
                            {{ $dashboard['primary_action']['title_mm'] }}
                        </h2>

                        <p>
                            {{ $dashboard['primary_action']['detail_mm'] }}
                        </p>
                    </div>

                    <span class="pbr-v2-btn primary">
                        {{ $dashboard['primary_action']['action_mm'] }}
                    </span>
                </a>
            @else
                <section class="pbr-v2-card pbr-v2-next-empty">
                    <span class="pbr-v2-eyebrow">
                        NEXT BUSINESS ACTION
                    </span>

                    <h2>အရေးပေါ် Review လုပ်ရန် မရှိပါ</h2>

                    <p>
                        လက်ရှိ approved business state ကို
                        Rulebook မှာ ဆက်လက်ကြည့်ရှုနိုင်ပါတယ်။
                    </p>
                </section>
            @endif
        @endif

        <section class="pbr-v2-card pbr-v2-health">
            <div class="pbr-v2-section-head">
                <div>
                    <span class="pbr-v2-eyebrow">
                        BUSINESS HEALTH
                    </span>

                    <h2>Business Setup အခြေအနေ</h2>

                    <p>
                        Tool အရေအတွက်မဟုတ်ဘဲ
                        Operating Area တစ်ခုချင်းစီရဲ့
                        လက်တွေ့ setup အခြေအနေကို ပြထားပါတယ်။
                    </p>
                </div>
            </div>

            <div class="pbr-v2-health-grid">
                <article class="pbr-v2-health-item">
                    <small>Areas Started</small>

                    <strong>
                        {{ $health['started_area_count'] }}
                        /
                        {{ $health['total_area_count'] }}
                    </strong>

                    <span>စတင်လုပ်ဆောင်ထားသော Areas</span>
                </article>

                <article
                    class="pbr-v2-health-item
                        {{ $health['working_change_count'] > 0 ? 'attention' : '' }}"
                >
                    <small>Working Changes</small>

                    <strong>
                        {{ $health['working_change_count'] }}
                    </strong>

                    <span>Approve မလုပ်ရသေးသော Drafts</span>
                </article>

                <article
                    class="pbr-v2-health-item
                        {{ $health['review_area_count'] > 0 ? 'attention' : '' }}"
                >
                    <small>Needs Review</small>

                    <strong>
                        {{ $health['review_area_count'] }}
                    </strong>

                    <span>ပြန်လည်စစ်ဆေးရန် Areas</span>
                </article>

                <article class="pbr-v2-health-item">
                    <small>Approved Rules</small>

                    <strong>
                        {{ $health['active_rule_count'] }}
                    </strong>

                    <span>လက်ရှိအသုံးပြုနေသော Rules</span>
                </article>

                <article class="pbr-v2-health-item secondary">
                    <small>Approved Areas</small>

                    <strong>
                        {{ $health['approved_area_count'] }}
                        /
                        {{ $health['total_area_count'] }}
                    </strong>

                    <span>Rule setup အပြည့်ပြီးသော Areas</span>
                </article>
            </div>


            <div
                class="pbr-v2-progress"
                aria-label="Business setup progress"
            >
                <div
                    style="width:
                        {{
                            min(
                                100,
                                max(
                                    0,
                                    (int) $health['setup_progress_percent']
                                )
                            )
                        }}%"
                ></div>
            </div>
        </section>

        <section
            class="pbr-v2-card pbr-v2-journey"
            data-pbr-business-journey
        >
            <div class="pbr-v2-section-head">
                <div>
                    <span class="pbr-v2-eyebrow">
                        BUSINESS JOURNEY
                    </span>

                    <h2>
                        Build → Operate → Protect
                    </h2>

                    <p>
                        Partnership တစ်ခုကို
                        10 Chapters လို့မမြင်ဘဲ
                        တည်ဆောက်ခြင်း၊ လည်ပတ်ခြင်းနဲ့
                        ကာကွယ်ခြင်းဆိုတဲ့
                        business journey အဖြစ် စီမံပါ။
                    </p>
                </div>
            </div>

            <div class="pbr-v2-phase-stack">
                @foreach($dashboard['phases'] as $phase)
                    <section
                        class="pbr-v2-phase"
                        data-pbr-phase="{{ $phase['key'] }}"
                    >
                        <header class="pbr-v2-phase-head">
                            <div>
                                <h3>{{ $phase['title_mm'] }}</h3>

                                <small>
                                    {{ $phase['title_en'] }}
                                </small>
                            </div>

                            <p>
                                {{ $phase['description_mm'] }}
                            </p>
                        </header>

                        <div class="pbr-v2-area-grid">
                            @foreach($phase['areas'] as $area)
                                <a
                                    id="system-{{ $area['slug'] }}"
                                    href="{{ $area['url'] }}"
                                    class="
                                        pbr-v2-area
                                        {{ $area['status_key'] }}
                                    "
                                    data-journey-step="{{ $area['domain'] }}"
                                >
                                    <div class="pbr-v2-area-top">
                                        <span class="pbr-v2-area-number">
                                            {{
                                                str_pad(
                                                    (string) $area['number'],
                                                    2,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}
                                        </span>

                                        <span class="pbr-v2-status">
                                            {{ $area['status_mm'] }}
                                        </span>
                                    </div>

                                    <h4>
                                        {{ $area['name_mm'] }}
                                    </h4>

                                    <small>
                                        {{ $area['name_en'] }}
                                    </small>

                                    <div class="pbr-v2-area-progress">
                                        <div class="pbr-v2-area-progress-row">
                                            <span>
                                                Setup
                                                {{ $area['configured_rule_count'] }}
                                                /
                                                {{ $area['rule_count'] }}
                                            </span>

                                            <span>
                                                Approved
                                                {{ $area['approved_rule_count'] }}
                                            </span>
                                        </div>

                                        <div class="pbr-v2-area-progress-bar">
                                            <div
                                                style="
                                                    width:
                                                    {{
                                                        min(
                                                            100,
                                                            max(
                                                                0,
                                                                (int) $area['setup_percent']
                                                            )
                                                        )
                                                    }}%
                                                "
                                            ></div>
                                        </div>

                                        @if(
                                            $dashboard['can_manage']
                                            && !empty($area['next_module'])
                                        )
                                            @php
                                                $nextBusinessAction =
                                                    match (true) {
                                                        $area['status_key'] === 'draft'
                                                            => 'Working Draft ကို ပြန်စစ်ရန်',

                                                        $area['status_key'] === 'needs-review'
                                                            => 'ပြောင်းလဲမှုသက်ရောက်ချက် ပြန်စစ်ရန်',

                                                        $area['domain'] === 'capital'
                                                            => $workspace->business_stage === 'new'
                                                                ? 'Startup Capital ကို စီစဉ်ရန်'
                                                                : 'လက်ရှိ Capital Position သတ်မှတ်ရန်',

                                                        $area['domain'] === 'ownership'
                                                            => 'Ownership နှင့် Voting Rights သတ်မှတ်ရန်',

                                                        $area['domain'] === 'contribution'
                                                            => 'Partner တာဝန်နှင့် Contribution သတ်မှတ်ရန်',

                                                        $area['domain'] === 'distribution'
                                                            => 'Profit နှင့် Distribution Rules သတ်မှတ်ရန်',

                                                        $area['domain'] === 'financial_controls'
                                                            => 'Financial Controls သတ်မှတ်ရန်',

                                                        $area['domain'] === 'governance'
                                                            => 'Decision Rules သတ်မှတ်ရန်',

                                                        $area['domain'] === 'exit'
                                                            => 'Exit နှင့် Buyout Plan သတ်မှတ်ရန်',

                                                        $area['domain'] === 'continuity'
                                                            => 'Business Continuity Plan သတ်မှတ်ရန်',

                                                        $area['domain'] === 'share_transfer'
                                                            => 'Share Transfer Rules သတ်မှတ်ရန်',

                                                        $area['domain'] === 'dispute_resolution'
                                                            => 'Conflict Resolution Process သတ်မှတ်ရန်',

                                                        default
                                                            => 'နောက်တစ်ဆင့် ဆက်လုပ်ရန်',
                                                    };
                                            @endphp

                                            <div class="pbr-v2-area-next">
                                                <span>Next</span>
                                                <strong>
                                                    {{ $nextBusinessAction }}
                                                </strong>
                                                <b>→</b>
                                            </div>
                                        @elseif(!$dashboard['can_manage'])
                                            <div class="pbr-v2-area-next">
                                                Approved state ကြည့်ရန် →
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="pbr-v2-card pbr-v2-capital">
            <div class="pbr-v2-section-head">
                <div>
                    <span class="pbr-v2-eyebrow">
                        CURRENT BUSINESS POSITION
                    </span>

                    <h2>Capital Position</h2>

                    <p>
                        Approved capital data မရှိသေးရင်
                        0.00 လို့ မပြပါဘူး။
                        “Not Set” နဲ့ actual zero ကို
                        သီးခြားခွဲထားပါတယ်။
                    </p>
                </div>

                <span
                    class="
                        pbr-v2-capital-state
                        {{
                            $capitalDashboard['source'] === 'active'
                                ? 'active'
                                : (
                                    $capitalDashboard['source'] === 'working'
                                        ? 'working'
                                        : ''
                                )
                        }}
                    "
                >
                    {{ $capitalDashboard['source_label'] }}
                </span>
            </div>

            <div class="pbr-v2-capital-grid">
                <article class="pbr-v2-capital-metric">
                    <span>Capital Required</span>

                    <strong>
                        {{
                            $formatMoney(
                                $capitalDashboard['capital_required']
                            )
                        }}
                    </strong>
                </article>

                <article class="pbr-v2-capital-metric">
                    <span>Capital Secured</span>

                    <strong>
                        {{
                            $formatMoney(
                                $capitalDashboard['capital_secured']
                            )
                        }}
                    </strong>
                </article>

                <article class="pbr-v2-capital-metric">
                    <span>Funding Gap</span>

                    <strong>
                        {{
                            $formatMoney(
                                $capitalDashboard['funding_gap']
                            )
                        }}
                    </strong>
                </article>
            </div>
        </section>

        @if(
            $dashboard['can_manage']
            && $dashboard['priority_actions']->isNotEmpty()
        )
            <section class="pbr-v2-card pbr-v2-priority">
                <div class="pbr-v2-section-head">
                    <div>
                        <span class="pbr-v2-eyebrow">
                            PRIORITY ACTIONS
                        </span>

                        <h2>နောက်ထပ် လုပ်သင့်သောအချက်များ</h2>

                        <p>
                            Dashboard မှာ
                            အရေးကြီးဆုံး 3 ခုထက်ပိုမပြပါဘူး။
                            အလုပ်မစရသေးတာတိုင်းကို
                            urgent task လို့ မယူပါဘူး။
                        </p>
                    </div>
                </div>

                <div class="pbr-v2-action-grid">
                    @foreach($dashboard['priority_actions'] as $action)
                        <a
                            href="{{ $action['url'] }}"
                            class="
                                pbr-v2-action
                                {{ $action['level'] ?? 'normal' }}
                            "
                        >
                            <strong>
                                {{ $action['title_mm'] }}
                            </strong>

                            <p>
                                {{ $action['detail_mm'] }}
                            </p>

                            <span>
                                {{ $action['action_mm'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="pbr-v2-bottom-grid">
            <section class="pbr-v2-card pbr-v2-rulebook">
                <span class="pbr-v2-eyebrow">
                    BUSINESS RULEBOOK
                </span>

                <h2>Partnership ရဲ့ Official Rules</h2>

                <p>
                    Working Draft,
                    Approved Current Rule နဲ့
                    Operating Record ကို
                    မရောဘဲ သီးခြားထိန်းသိမ်းထားပါတယ်။
                </p>

                <div class="pbr-v2-rulebook-metrics">
                    <div>
                        <span>Approved Rules</span>

                        <strong>
                            {{ $rulebook['active_rule_count'] }}
                        </strong>
                    </div>

                    <div>
                        <span>Working Changes</span>

                        <strong>
                            {{ $rulebook['working_change_count'] }}
                        </strong>
                    </div>

                    <div>
                        <span>Operating Records</span>

                        <strong>
                            {{ $rulebook['operating_record_count'] }}
                        </strong>
                    </div>
                </div>

                <a
                    href="{{ $rulebook['url'] }}"
                    class="pbr-v2-btn primary"
                >
                    Business Rulebook ကို ဖွင့်ရန် →
                </a>
            </section>

            <section class="pbr-v2-card pbr-v2-settings">
                <span class="pbr-v2-eyebrow">
                    BUSINESS SETTINGS
                </span>

                <h2>Workspace Settings</h2>

                <p>
                    Business Stage:
                    <strong>{{ $stageMm }}</strong>
                    <br>
                    Currency:
                    <strong>{{ $currency }}</strong>
                </p>

                @if($canManageContext)
                    <a
                        href="{{ route('workspaces.edit', $workspace) }}"
                        class="pbr-v2-btn"
                    >
                        Settings ပြင်ရန် →
                    </a>
                @endif
            </section>
        </div>

    </div>
</section>
@endsection
