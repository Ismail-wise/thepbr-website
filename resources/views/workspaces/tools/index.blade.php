@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $activeRuleCount = $agreedToolIds->count();
    $draftCount = $draftToolIds->count();
    $profileCount = $workspace->partnerProfiles
        ->whereIn('status', ['active', 'planned'])
        ->count();
    $peopleCount = $profileCount > 0
        ? $profileCount
        : collect([$workspace->owner_user_id])
            ->merge($workspace->acceptedMemberships->pluck('user_id'))
            ->filter()
            ->unique()
            ->count();

    $systems = [
        1 => [
            'key' => 'capital',
            'name' => 'Capital & Funding',
            'mm' => 'မတည်ငွေနှင့် ရင်းနှီးငွေ စီမံခန့်ခွဲမှု',
            'summary' => 'Plan required capital, working cash, partner funding, reserves and funding gaps.',
        ],
        2 => [
            'key' => 'ownership',
            'name' => 'Ownership & Equity',
            'mm' => 'ပိုင်ဆိုင်မှုနှင့် အစုရှယ်ယာ စနစ်',
            'summary' => 'Manage ownership percentages, cap table, voting power, share value and dilution.',
        ],
        3 => [
            'key' => 'contribution',
            'name' => 'Partner Roles & Contributions',
            'mm' => 'Partner တာဝန်နှင့် တန်ဖိုးထည့်ဝင်မှု',
            'summary' => 'Make partner responsibilities, time, expertise, non-cash value and vesting visible.',
        ],
        4 => [
            'key' => 'distribution',
            'name' => 'Profit & Distribution',
            'mm' => 'အမြတ်၊ လစာနှင့် အရှုံး ခွဲဝေမှု',
            'summary' => 'Set profit sharing, salaries, retained earnings, reserves and loss-sharing rules.',
        ],
        5 => [
            'key' => 'financial-controls',
            'name' => 'Financial Controls',
            'mm' => 'ငွေကြေး ထိန်းချုပ်မှု',
            'summary' => 'Run budgets, cash flow, expense approvals, banking authority and payment controls.',
        ],
        6 => [
            'key' => 'governance',
            'name' => 'Governance & Decision Making',
            'mm' => 'အုပ်ချုပ်မှုနှင့် ဆုံးဖြတ်ချက် စနစ်',
            'summary' => 'Define authority, decision rights, voting, meetings and deadlock handling.',
        ],
        7 => [
            'key' => 'exit',
            'name' => 'Exit & Buyout',
            'mm' => 'Partner ထွက်ခွာမှုနှင့် Buyout',
            'summary' => 'Prepare buyout value, notice periods, exit timelines, handover and continuity.',
        ],
        8 => [
            'key' => 'continuity',
            'name' => 'Continuity & Risk',
            'mm' => 'လုပ်ငန်းဆက်လက်မှုနှင့် Risk',
            'summary' => 'Prepare for death, disability, spouse issues, succession and business continuity.',
        ],
        9 => [
            'key' => 'share-transfer',
            'name' => 'Share Transfers',
            'mm' => 'အစုရှယ်ယာ လွှဲပြောင်းမှု',
            'summary' => 'Control transfer approvals, restrictions, valuation and ownership changes.',
        ],
        10 => [
            'key' => 'disputes',
            'name' => 'Dispute Management',
            'mm' => 'Partner အငြင်းပွားမှု စီမံခန့်ခွဲခြင်း',
            'summary' => 'Set escalation, mediation, issue priority and resolution procedures before conflict grows.',
        ],
    ];

    $statusForSystem = static function ($chapter, $domain, $agreedToolIds, $draftToolIds): array {
        $hasActive = $chapter->tools->contains(
            fn ($tool) => $agreedToolIds->has((int) $tool->id)
        ) || (($domain['status'] ?? null) === 'agreed');

        $hasDraft = $chapter->tools->contains(
            fn ($tool) => $draftToolIds->has((int) $tool->id)
        ) || (($domain['status'] ?? null) === 'draft');

        if ($hasActive) {
            return ['key' => 'active', 'label' => 'Active'];
        }

        if ($hasDraft) {
            return ['key' => 'draft', 'label' => 'Draft in Progress'];
        }

        return ['key' => 'setup', 'label' => 'Needs Setup'];
    };

    $systemStates = [];
    foreach ($chapters as $chapter) {
        $number = (int) $chapter->chapter_number;
        $systemStates[$number] = $statusForSystem(
            $chapter,
            $operatingDomains[$number] ?? null,
            $agreedToolIds,
            $draftToolIds
        );
    }

    $fundingGap = (float) ($chapterOneSummary['funding_gap'] ?? 0);
    $capitalRequired = (float) ($chapterOneSummary['capital_required'] ?? 0);
@endphp

<section class="pbr-business-page">
    <div class="portal-wrap pbr-business-wrap">
        <nav class="pbr-os-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            <span>›</span>
            <span>Business Operating System</span>
        </nav>

        <header class="pbr-business-hero">
            <div class="pbr-business-hero-copy">
                <span class="pbr-business-eyebrow">PBR Business Operating System</span>
                <h1>{{ $workspace->business_name ?: $workspace->name }}</h1>
                <p>
                    Partnership ရဲ့ capital, ownership, partner responsibilities, profit distribution,
                    financial controls, governance, exit, continuity, share transfers နဲ့ disputes ကို
                    တစ်နေရာတည်းမှာ အမှန်တကယ် စီမံနိုင်တဲ့ business workspace ဖြစ်ပါတယ်။
                </p>
                <div class="pbr-business-tags">
                    <span>{{ $businessStages[$workspace->business_stage] ?? 'Stage not set' }}</span>
                    <span>{{ $currency }}</span>
                    <span>{{ $peopleCount }} {{ $peopleCount === 1 ? 'Person' : 'People' }}</span>
                </div>
            </div>

            <div class="pbr-business-hero-actions">
                <a href="{{ route('workspaces.partner-roster.index', $workspace) }}" class="pbr-business-btn secondary">Manage Partners</a>
                <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}" class="pbr-business-btn">Ask PBR AI</a>
            </div>
        </header>

        <section class="pbr-business-metrics" aria-label="Business overview">
            <article class="pbr-business-metric">
                <span>Capital Required</span>
                <strong>{{ $currency }} {{ number_format($capitalRequired, 2) }}</strong>
                <small>Current capital plan</small>
            </article>
            <article class="pbr-business-metric {{ $fundingGap > 0 ? 'attention' : 'healthy' }}">
                <span>Funding Gap</span>
                <strong>{{ $currency }} {{ number_format($fundingGap, 2) }}</strong>
                <small>{{ $fundingGap > 0 ? 'Needs attention' : 'Funding requirement covered' }}</small>
            </article>
            <article class="pbr-business-metric">
                <span>Partners</span>
                <strong>{{ $peopleCount }}</strong>
                <small>Current and planned partner profiles</small>
            </article>
            <article class="pbr-business-metric">
                <span>Active Business Rules</span>
                <strong>{{ $activeRuleCount }}</strong>
                <small>{{ $draftCount }} draft{{ $draftCount === 1 ? '' : 's' }} in progress</small>
            </article>
        </section>

        <section class="pbr-business-attention">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">Business Health</span>
                    <h2>Needs Attention</h2>
                    <p>ဒီ Business မှာ မသတ်မှတ်ရသေးတာ၊ draft ဖြစ်နေတာ၊ ဒါမှမဟုတ် action လိုနေတာတွေကို အရင်ပြပါတယ်။</p>
                </div>
            </div>

            <div class="pbr-business-attention-grid">
                <a href="#system-capital" class="pbr-business-attention-card {{ $fundingGap > 0 ? 'warning' : 'ok' }}">
                    <span>Capital & Funding</span>
                    <strong>{{ $fundingGap > 0 ? $currency.' '.number_format($fundingGap, 2).' gap' : 'Funding covered' }}</strong>
                    <small>{{ $fundingGap > 0 ? 'Review funding plan' : 'No immediate funding gap' }}</small>
                </a>

                @foreach([2 => 'Ownership Structure', 3 => 'Partner Responsibilities', 4 => 'Profit Distribution', 5 => 'Financial Controls', 6 => 'Governance'] as $systemNumber => $label)
                    @php($state = $systemStates[$systemNumber] ?? ['key' => 'setup', 'label' => 'Needs Setup'])
                    <a href="#system-{{ $systems[$systemNumber]['key'] }}" class="pbr-business-attention-card {{ $state['key'] }}">
                        <span>{{ $label }}</span>
                        <strong>{{ $state['label'] }}</strong>
                        <small>{{ $state['key'] === 'active' ? 'Current business rule available' : ($state['key'] === 'draft' ? 'Review and activate when ready' : 'Set this up for the business') }}</small>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="pbr-context-card pbr-business-context-card">
            <div class="pbr-context-header">
                <div>
                    <span class="pbr-business-eyebrow">Business Settings</span>
                    <h2>Default business context</h2>
                    <p>Financial calculations, business rules, Valuation နဲ့ PBR AI က ဒီ settings ကို အသုံးပြုပါတယ်။</p>
                </div>
                <span class="{{ $workspace->hasBusinessContext() ? 'pbr-ready-badge' : 'pbr-setup-badge' }}">
                    {{ $workspace->hasBusinessContext() ? 'Configured' : 'Setup Required' }}
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
                            <small>Business အသစ်စတင်နေတာလား၊ ရှိပြီးသား partnership ကို စီမံနေတာလား ရွေးပါ။</small>
                        </div>
                        <div class="pbr-tools-field">
                            <label for="currency_code">Primary Currency</label>
                            <select id="currency_code" name="currency_code" required>
                                @foreach($currencies as $value => $label)
                                    <option value="{{ $value }}" @selected(old('currency_code', $workspace->currency_code) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small>Financial systems အားလုံးအတွက် default currency ဖြစ်ပါတယ်။</small>
                        </div>
                    </div>
                    <div class="pbr-context-actions">
                        <button type="submit" class="pbr-tools-primary-button">Save Business Settings</button>
                    </div>
                </form>
            @else
                <div class="pbr-context-readonly">
                    <div><span>Partnership Stage</span><strong>{{ $businessStages[$workspace->business_stage] ?? 'Not selected' }}</strong></div>
                    <div><span>Primary Currency</span><strong>{{ $workspace->currency_code ?? 'Not selected' }}</strong></div>
                </div>
            @endif
        </section>

        <section class="pbr-business-systems">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">Business Systems</span>
                    <h2>Run the partnership from one place</h2>
                    <p>လိုအပ်တဲ့ business area ကိုဖွင့်ပြီး actual company data, decisions, scenarios နဲ့ active rules ကို စီမံပါ။</p>
                </div>
            </div>

            <div class="pbr-business-system-list">
                @foreach($chapters as $chapter)
                    @php
                        $systemNumber = (int) $chapter->chapter_number;
                        $system = $systems[$systemNumber];
                        $state = $systemStates[$systemNumber];
                    @endphp

                    <details id="system-{{ $system['key'] }}" class="pbr-business-system" @if($systemNumber === 1) open @endif>
                        <summary>
                            <div class="pbr-business-system-title">
                                <span class="pbr-business-system-dot {{ $state['key'] }}"></span>
                                <div>
                                    <h3>{{ $system['name'] }}</h3>
                                    <p>{{ $system['mm'] }}</p>
                                </div>
                            </div>
                            <div class="pbr-business-system-state {{ $state['key'] }}">{{ $state['label'] }}</div>
                        </summary>

                        <div class="pbr-business-system-body">
                            <p class="pbr-business-system-summary">{{ $system['summary'] }}</p>

                            <div class="pbr-business-capability-grid">
                                @foreach($chapter->tools as $tool)
                                    @php
                                        $definition = $toolDefinitions[$tool->tool_key] ?? null;
                                        $titleMm = $definition['title_mm'] ?? $tool->title_mm ?? $tool->title_en;
                                        $purpose = $definition['purpose_mm'] ?? $tool->description;
                                        $isActive = $agreedToolIds->has((int) $tool->id);
                                        $hasDraft = $draftToolIds->has((int) $tool->id);
                                        $toolState = $isActive
                                            ? ['key' => 'active', 'label' => 'Active Rule']
                                            : ($hasDraft
                                                ? ['key' => 'draft', 'label' => 'Draft']
                                                : ['key' => 'setup', 'label' => 'Needs Setup']);
                                    @endphp

                                    <article class="pbr-business-capability {{ $toolState['key'] }}">
                                        <div class="pbr-business-capability-top">
                                            <span>{{ ucfirst($tool->tool_type) }}</span>
                                            <b class="{{ $toolState['key'] }}">{{ $toolState['label'] }}</b>
                                        </div>
                                        <h4>{{ $titleMm }}</h4>
                                        @if($titleMm !== $tool->title_en)
                                            <small class="pbr-business-capability-en">{{ $tool->title_en }}</small>
                                        @endif
                                        <p>{{ $purpose }}</p>

                                        @if($tool->tool_key === 'startup_capital_planner' && $workspace->business_stage === 'new')
                                            <a href="{{ route('workspaces.tools.startup-capital.show', $workspace) }}">{{ $canManageContext ? 'Manage →' : 'View →' }}</a>
                                        @elseif($systemNumber === 1)
                                            <a href="{{ route('workspaces.tools.chapter-one.show', [$workspace, $tool->slug]) }}">{{ $canManageContext ? 'Manage →' : 'View →' }}</a>
                                        @else
                                            <a href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug]) }}">{{ $canManageContext ? 'Manage →' : 'View →' }}</a>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="pbr-business-ai-bridge">
            <div>
                <span class="pbr-business-eyebrow">Connected Intelligence</span>
                <h2>Your business rules make PBR AI more useful</h2>
                <p>Active business rules, partner data, Feasibility, Valuation နဲ့ business records တွေကို ပေါင်းပြီး workspace-specific guidance ရယူနိုင်ပါတယ်။</p>
            </div>
            <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}">Open PBR AI Advisor →</a>
        </section>
    </div>
</section>
@endsection
