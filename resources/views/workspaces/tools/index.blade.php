@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $activeRuleCount = $agreedToolIds->count();
    $draftCount = $draftToolIds->count();

    $partnerProfiles = $workspace->partnerProfiles
        ->whereIn('status', ['active', 'planned']);
    $profileUserIds = $partnerProfiles
        ->pluck('user_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique();
    $workspaceUserIds = collect([$workspace->owner_user_id])
        ->merge($workspace->acceptedMemberships->pluck('user_id'))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique();
    $peopleCount = $partnerProfiles->count()
        + $workspaceUserIds->diff($profileUserIds)->count();

    $stageMm = $workspace->business_stage === 'new'
        ? 'Partnership အသစ် စီစဉ်နေသည်'
        : 'ရှိပြီးသား Partnership ကို စီမံနေသည်';

    $systemDefinitions = [
        1 => [
            'key' => 'capital',
            'name_mm' => 'မတည်ငွေနှင့် ရင်းနှီးငွေ',
            'name_en' => 'Capital & Funding',
            'summary_mm' => 'လုပ်ငန်းစတင်ရန်နဲ့ ဆက်လက်လည်ပတ်ရန် လိုအပ်တဲ့ မတည်ငွေ၊ လည်ပတ်ငွေ၊ Partner ထည့်ဝင်ငွေ၊ အရေးပေါ်အရန်ငွေနဲ့ လိုအပ်နေသေးတဲ့ Funding Gap ကို တစ်နေရာတည်းမှာ စီမံပါ။',
        ],
        2 => [
            'key' => 'ownership',
            'name_mm' => 'ပိုင်ဆိုင်မှုနှင့် အစုရှယ်ယာ',
            'name_en' => 'Ownership & Equity',
            'summary_mm' => 'Partner တစ်ဦးချင်းစီရဲ့ ပိုင်ဆိုင်မှုရာခိုင်နှုန်း၊ Voting Power၊ Share Value၊ Cap Table နဲ့ နောင်ဖြစ်နိုင်တဲ့ Dilution ကို သေချာသတ်မှတ်ပါ။',
        ],
        3 => [
            'key' => 'contribution',
            'name_mm' => 'Partner တာဝန်နှင့် တန်ဖိုးထည့်ဝင်မှု',
            'name_en' => 'Partner Roles & Contributions',
            'summary_mm' => 'အချိန်၊ ကျွမ်းကျင်မှု၊ တာဝန်၊ အလုပ်အားနဲ့ ငွေမဟုတ်တဲ့ တန်ဖိုးထည့်ဝင်မှုတွေကို မြင်သာအောင် စီမံပြီး Partner တစ်ဦးချင်းစီရဲ့ အခန်းကဏ္ဍကို ရှင်းလင်းပါ။',
        ],
        4 => [
            'key' => 'distribution',
            'name_mm' => 'အမြတ်၊ လစာနှင့် အရှုံး ခွဲဝေမှု',
            'name_en' => 'Profit & Distribution',
            'summary_mm' => 'လစာ၊ အမြတ်ခွဲဝေမှု၊ Retained Earnings၊ Reserve Fund နဲ့ အရှုံးကို Partner တွေအကြား ဘယ်လိုမျှဝေမလဲဆိုတာ ကြိုတင်သတ်မှတ်ပါ။',
        ],
        5 => [
            'key' => 'financial-controls',
            'name_mm' => 'ငွေကြေး ထိန်းချုပ်မှု',
            'name_en' => 'Financial Controls',
            'summary_mm' => 'Budget၊ Cash Flow၊ Expense Approval၊ Bank Authority နဲ့ ကြီးမားတဲ့ Payment တွေအတွက် အတည်ပြုမှုစည်းမျဉ်းတွေကို နေ့စဉ်အသုံးပြုနိုင်အောင် တည်ဆောက်ပါ။',
        ],
        6 => [
            'key' => 'governance',
            'name_mm' => 'အုပ်ချုပ်မှုနှင့် ဆုံးဖြတ်ချက် စနစ်',
            'name_en' => 'Governance & Decision Making',
            'summary_mm' => 'ဘယ်သူက ဘာဆုံးဖြတ်နိုင်သလဲ၊ ဘယ်ကိစ္စတွေမှာ Vote လိုသလဲ၊ Authority Level နဲ့ Deadlock ဖြစ်ရင် ဘယ်လိုဖြေရှင်းမလဲဆိုတာ ရှင်းလင်းပါ။',
        ],
        7 => [
            'key' => 'exit',
            'name_mm' => 'Partner ထွက်ခွာမှုနှင့် Buyout',
            'name_en' => 'Exit & Buyout',
            'summary_mm' => 'Partner တစ်ဦး ထွက်ခွာချင်တဲ့အခါ Buyout Value၊ Notice Period၊ Handover နဲ့ လုပ်ငန်းဆက်လက်လည်ပတ်မှုအတွက် ကြိုတင်ပြင်ဆင်ပါ။',
        ],
        8 => [
            'key' => 'continuity',
            'name_mm' => 'လုပ်ငန်းဆက်လက်မှုနှင့် အန္တရာယ်ကာကွယ်မှု',
            'name_en' => 'Continuity & Risk',
            'summary_mm' => 'သေဆုံးမှု၊ မသန်စွမ်းမှု၊ မိသားစုဆိုင်ရာ အခြေအနေ၊ Succession နဲ့ မမျှော်လင့်ထားတဲ့ ဖြစ်ရပ်တွေကြောင့် လုပ်ငန်းမရပ်တန့်အောင် ပြင်ဆင်ပါ။',
        ],
        9 => [
            'key' => 'share-transfer',
            'name_mm' => 'အစုရှယ်ယာ လွှဲပြောင်းမှု',
            'name_en' => 'Share Transfers',
            'summary_mm' => 'Share လွှဲပြောင်းခြင်း၊ ဝယ်ယူခွင့်၊ Approval၊ Valuation နဲ့ ပိုင်ဆိုင်မှုပြောင်းလဲမှုတွေကို စည်းမျဉ်းတကျ စီမံပါ။',
        ],
        10 => [
            'key' => 'disputes',
            'name_mm' => 'Partner အငြင်းပွားမှု ဖြေရှင်းရေး',
            'name_en' => 'Dispute Management',
            'summary_mm' => 'ပြဿနာကြီးမားမသွားခင် Issue Priority၊ Escalation၊ Mediation နဲ့ Resolution Process ကို ကြိုတင်တည်ဆောက်ထားပါ။',
        ],
    ];

    $capitalModuleMeta = [
        'startup_capital_planner' => [
            'title_mm' => 'စတင်မတည်ငွေ အစီအစဉ်',
            'title_en' => 'Startup Capital Plan',
            'purpose_mm' => 'လုပ်ငန်းစတင်ဖို့ တကယ်လိုအပ်မယ့် ကုန်ကျစရိတ်တွေကို စုစည်းပြီး စုစုပေါင်း မတည်ငွေလိုအပ်ချက်ကို သတ်မှတ်ပါ။',
            'action_mm' => 'မတည်ငွေ အစီအစဉ် စီမံရန် →',
        ],
        'current_capital_position' => [
            'title_mm' => 'လက်ရှိ မတည်ငွေ အခြေအနေ',
            'title_en' => 'Current Capital Position',
            'purpose_mm' => 'ရှိပြီးသားလုပ်ငန်းရဲ့ လက်ရှိ Capital၊ Partner Funding နဲ့ အသုံးပြုပြီးသား ရင်းနှီးငွေအခြေအနေကို တစ်နေရာတည်းမှာ ကြည့်ပါ။',
            'action_mm' => 'လက်ရှိ Capital ကို စစ်ဆေးရန် →',
        ],
        'working_capital_calculator' => [
            'title_mm' => 'လည်ပတ်ငွေ စီမံမှု',
            'title_en' => 'Working Capital',
            'purpose_mm' => 'နေ့စဉ်လုပ်ငန်းလည်ပတ်ဖို့ လိုအပ်တဲ့ Cash Buffer နဲ့ လုံလောက်စွာထားသင့်တဲ့ လည်ပတ်ငွေကို သတ်မှတ်ပါ။',
            'action_mm' => 'လည်ပတ်ငွေ သတ်မှတ်ရန် →',
        ],
        'contingency_fund_calculator' => [
            'title_mm' => 'အရေးပေါ်ငွေ အရန်',
            'title_en' => 'Emergency Reserve',
            'purpose_mm' => 'မမျှော်လင့်ထားတဲ့ ကုန်ကျစရိတ်နဲ့ လုပ်ငန်းအနှောင့်အယှက်တွေကို ခံနိုင်ဖို့ အနည်းဆုံး Reserve Policy ကို သတ်မှတ်ပါ။',
            'action_mm' => 'အရေးပေါ်အရန်ငွေ သတ်မှတ်ရန် →',
        ],
        'partner_contribution_matrix' => [
            'title_mm' => 'Partner မတည်ငွေ ထည့်ဝင်မှု',
            'title_en' => 'Partner Contributions',
            'purpose_mm' => 'Partner တစ်ဦးချင်းစီရဲ့ Cash၊ Asset နဲ့ အခြားတန်ဖိုးထည့်ဝင်မှုတွေကို မှတ်တမ်းတင်ပြီး မျှတမှုကို စစ်ဆေးပါ။',
            'action_mm' => 'Partner ထည့်ဝင်မှု စီမံရန် →',
        ],
        'funding_gap_calculator' => [
            'title_mm' => 'လိုအပ်ငွေ ကွာဟချက်',
            'title_en' => 'Funding Position',
            'purpose_mm' => 'လိုအပ်တဲ့ မတည်ငွေနဲ့ လက်ရှိရရှိထားတဲ့ Funding ကို နှိုင်းယှဉ်ပြီး ဘယ်လောက်လိုနေသေးလဲ အတိအကျကြည့်ပါ။',
            'action_mm' => 'Funding Gap စစ်ဆေးရန် →',
        ],
        'capital_allocation_chart' => [
            'title_mm' => 'မတည်ငွေ ခွဲဝေသုံးစွဲမှု',
            'title_en' => 'Capital Allocation',
            'purpose_mm' => 'ရရှိထားတဲ့ မတည်ငွေကို ဘယ်လုပ်ငန်းအပိုင်းတွေမှာ ဘယ်လောက်သုံးမလဲဆိုတာ ရှင်းလင်းစွာ ခွဲဝေစီမံပါ။',
            'action_mm' => 'ငွေခွဲဝေမှု စီမံရန် →',
        ],
    ];

    $businessSystems = [];

    foreach ($chapters as $chapter) {
        $number = (int) $chapter->chapter_number;
        $meta = $systemDefinitions[$number];
        $domain = $operatingDomains[$number] ?? null;

        $activeCount = $chapter->tools
            ->filter(fn ($tool) => $agreedToolIds->has((int) $tool->id))
            ->count();
        $draftModuleCount = $chapter->tools
            ->filter(fn ($tool) => $draftToolIds->has((int) $tool->id))
            ->count();

        $hasActive = $activeCount > 0 || (($domain['status'] ?? null) === 'agreed');
        $hasDraft = $draftModuleCount > 0 || (($domain['status'] ?? null) === 'draft');

        $systemState = $hasActive
            ? ['key' => 'active', 'label_mm' => 'အသုံးပြုနေသောစနစ်', 'label_en' => 'Active']
            : ($hasDraft
                ? ['key' => 'draft', 'label_mm' => 'Draft ပြင်ဆင်နေ', 'label_en' => 'In progress']
                : ['key' => 'setup', 'label_mm' => 'မသတ်မှတ်ရသေး', 'label_en' => 'Needs setup']);

        $modules = [];

        foreach ($chapter->tools as $tool) {
            $toolId = (int) $tool->id;
            $definition = $toolDefinitions[$tool->tool_key] ?? [];
            $customMeta = $number === 1
                ? ($capitalModuleMeta[$tool->tool_key] ?? [])
                : [];

            $titleMm = $customMeta['title_mm']
                ?? $definition['title_mm']
                ?? $tool->title_mm
                ?? $tool->title_en;
            $titleEn = $customMeta['title_en'] ?? $tool->title_en;
            $purposeMm = $customMeta['purpose_mm']
                ?? $definition['purpose_mm']
                ?? $tool->description;
            $actionMm = $customMeta['action_mm'] ?? 'စီမံရန် →';

            $agreedOutput = $latestAgreedOutputs->get($toolId);
            $draftSession = $latestDraftSessions->get($toolId);

            if ($agreedOutput) {
                $moduleState = [
                    'key' => 'active',
                    'label_mm' => 'အသုံးပြုနေ',
                    'label_en' => 'Active rule',
                    'detail_mm' => 'လက်ရှိ Business Rule အဖြစ် အသုံးပြုနေသည်',
                    'meta' => 'Revision '.$agreedOutput->revision.' · '.optional($agreedOutput->agreed_at)->format('d M Y'),
                ];
            } elseif ($draftSession) {
                $moduleState = [
                    'key' => 'draft',
                    'label_mm' => 'Draft ရှိနေ',
                    'label_en' => 'Draft',
                    'detail_mm' => 'အတည်ပြုရန် Draft အချက်အလက် ရှိနေသည်',
                    'meta' => 'Updated '.optional($draftSession->last_saved_at)->format('d M Y'),
                ];
            } else {
                $moduleState = [
                    'key' => 'setup',
                    'label_mm' => 'ပြင်ဆင်ရန်',
                    'label_en' => 'Not set',
                    'detail_mm' => 'ဒီအပိုင်းကို Business အတွက် မသတ်မှတ်ရသေးပါ',
                    'meta' => null,
                ];
            }

            if ($tool->tool_key === 'startup_capital_planner' && $workspace->business_stage === 'new') {
                $toolUrl = route('workspaces.tools.startup-capital.show', $workspace);
            } elseif ($number === 1) {
                $toolUrl = route('workspaces.tools.chapter-one.show', [$workspace, $tool->slug]);
            } else {
                $toolUrl = route('workspaces.tools.operating.show', [$workspace, $tool->slug]);
            }

            $modules[] = [
                'title_mm' => $titleMm,
                'title_en' => $titleEn,
                'purpose_mm' => $purposeMm,
                'action_mm' => $canManageContext ? $actionMm : 'ကြည့်ရန် →',
                'state' => $moduleState,
                'url' => $toolUrl,
            ];
        }

        $businessSystems[] = [
            'number' => $number,
            'key' => $meta['key'],
            'name_mm' => $meta['name_mm'],
            'name_en' => $meta['name_en'],
            'summary_mm' => $meta['summary_mm'],
            'state' => $systemState,
            'active_count' => $activeCount,
            'draft_count' => $draftModuleCount,
            'module_count' => count($modules),
            'modules' => $modules,
        ];
    }

    $attentionSystems = collect($businessSystems)
        ->whereIn('number', [2, 3, 4, 5, 6])
        ->filter(fn ($system) => $system['state']['key'] !== 'active')
        ->values();

    $fundingGap = (float) ($chapterOneSummary['funding_gap'] ?? 0);
    $capitalRequired = (float) ($chapterOneSummary['capital_required'] ?? 0);
    $capitalState = $businessSystems[0]['state']['key'] ?? 'setup';
@endphp

<section class="pbr-business-page">
    <div class="portal-wrap pbr-business-wrap">
        <nav class="pbr-os-breadcrumb pbr-business-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            <span>›</span>
            <span>လုပ်ငန်း စီမံခန့်ခွဲမှုစနစ်</span>
        </nav>

        <header class="pbr-business-hero">
            <div class="pbr-business-hero-copy">
                <span class="pbr-business-eyebrow">PBR BUSINESS OPERATING SYSTEM</span>
                <h1>{{ $workspace->business_name ?: $workspace->name }}</h1>
                <p class="pbr-business-hero-lead">
                    Partnership တစ်ခုလုံးရဲ့ မတည်ငွေ၊ ပိုင်ဆိုင်မှု၊ Partner တာဝန်၊ အမြတ်ခွဲဝေမှု၊
                    ငွေကြေးထိန်းချုပ်မှု၊ ဆုံးဖြတ်ချက်၊ Risk နဲ့ Exit ကို <strong>တစ်နေရာတည်းမှာ တကယ်အသုံးပြုနိုင်အောင်</strong>
                    စီမံထားတဲ့ Business Workspace ဖြစ်ပါတယ်။
                </p>

                <div class="pbr-business-tags">
                    <span>{{ $stageMm }}</span>
                    <span>{{ $currency }}</span>
                    <span>Partner {{ $peopleCount }} ဦး</span>
                </div>
            </div>

            <div class="pbr-business-hero-actions">
                <a href="{{ route('workspaces.partner-roster.index', $workspace) }}" class="pbr-business-btn secondary">
                    Partner များ စီမံရန်
                </a>
                <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}" class="pbr-business-btn">
                    PBR AI ကို မေးရန် ✦
                </a>
            </div>
        </header>

        <section class="pbr-business-metrics" aria-label="Business overview">
            <article class="pbr-business-metric {{ $capitalState === 'draft' ? 'draft' : '' }}">
                <span class="pbr-mm-label">လိုအပ်သော မတည်ငွေ</span>
                <small class="pbr-en-label">Capital Required</small>
                <strong>{{ $currency }} {{ number_format($capitalRequired, 2) }}</strong>
                <div class="pbr-metric-foot">
                    @if($capitalState === 'active')
                        <b class="active">အသုံးပြုနေသော အချက်အလက်</b>
                    @elseif($capitalState === 'draft')
                        <b class="draft">Draft အချက်အလက်</b>
                        <span>အတည်မပြုရသေး</span>
                    @else
                        <span>မသတ်မှတ်ရသေးပါ</span>
                    @endif
                </div>
            </article>

            <article class="pbr-business-metric {{ $fundingGap > 0 ? 'attention' : 'healthy' }}">
                <span class="pbr-mm-label">လိုအပ်နေသေးသော ရင်းနှီးငွေ</span>
                <small class="pbr-en-label">Funding Gap</small>
                <strong>{{ $currency }} {{ number_format($fundingGap, 2) }}</strong>
                <div class="pbr-metric-foot">
                    <b class="{{ $fundingGap > 0 ? 'warning' : 'active' }}">
                        {{ $fundingGap > 0 ? 'သတိထားစီမံရန်' : 'လုံလောက်ပါသည်' }}
                    </b>
                    @if($capitalState === 'draft')<span>Draft data</span>@endif
                </div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">Partner အရေအတွက်</span>
                <small class="pbr-en-label">Partners</small>
                <strong>{{ $peopleCount }} ဦး</strong>
                <div class="pbr-metric-foot"><span>လက်ရှိနှင့် စီစဉ်ထားသော Partner များ</span></div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">အသုံးပြုနေသော စည်းမျဉ်းများ</span>
                <small class="pbr-en-label">Active Business Rules</small>
                <strong>{{ $activeRuleCount }}</strong>
                <div class="pbr-metric-foot">
                    <span>Draft {{ $draftCount }} ခု ပြင်ဆင်နေသည်</span>
                </div>
            </article>
        </section>

        <section class="pbr-business-attention">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">BUSINESS HEALTH</span>
                    <h2>အခုအရင်ဆုံး သတိထားစီမံရမယ့်အရာများ</h2>
                    <p>မသတ်မှတ်ရသေးတာ၊ Draft အဖြစ်ကျန်နေတာ၊ ဒါမှမဟုတ် ဆုံးဖြတ်ချက်လိုနေတာတွေကို အရင်ပြပါတယ်။</p>
                </div>
            </div>

            @if($fundingGap > 0 || $attentionSystems->isNotEmpty())
                <div class="pbr-business-attention-grid">
                    @if($fundingGap > 0)
                        <a href="#system-capital" class="pbr-business-attention-card warning">
                            <span class="pbr-en-label">Capital & Funding</span>
                            <strong>ရင်းနှီးငွေ {{ $currency }} {{ number_format($fundingGap, 2) }} လိုနေသေးသည်</strong>
                            <small>Funding Plan ကို ပြန်စစ်ပြီး ရရှိနိုင်မယ့် အရင်းအမြစ်ကို သတ်မှတ်ပါ →</small>
                        </a>
                    @endif

                    @foreach($attentionSystems as $attentionSystem)
                        <a href="#system-{{ $attentionSystem['key'] }}" class="pbr-business-attention-card {{ $attentionSystem['state']['key'] }}">
                            <span class="pbr-en-label">{{ $attentionSystem['name_en'] }}</span>
                            <strong>{{ $attentionSystem['name_mm'] }}</strong>
                            <small>
                                {{ $attentionSystem['state']['key'] === 'draft'
                                    ? 'Draft ရှိနေပါတယ် — ပြန်စစ်ပြီး အသုံးပြုမယ့် Rule အဖြစ် အတည်ပြုပါ →'
                                    : 'ဒီ Business အတွက် မသတ်မှတ်ရသေးပါ — စတင်ပြင်ဆင်ရန် →' }}
                            </small>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="pbr-business-healthy-banner">
                    <span>✓</span>
                    <div>
                        <strong>အရေးပေါ်စီမံရန် မရှိသေးပါ</strong>
                        <p>လက်ရှိသတ်မှတ်ထားတဲ့ Business Rules နဲ့ Funding အခြေအနေက ပုံမှန်ဖြစ်ပါတယ်။</p>
                    </div>
                </div>
            @endif
        </section>

        <details class="pbr-business-settings">
            <summary>
                <div>
                    <span class="pbr-business-eyebrow">BUSINESS SETTINGS</span>
                    <strong>Business အခြေခံအချက်အလက်</strong>
                    <small>{{ $stageMm }} · {{ $currency }}</small>
                </div>
                <span class="pbr-business-settings-action">ပြင်ဆင်ရန် +</span>
            </summary>

            <div class="pbr-business-settings-body">
                <p>Financial calculations၊ Business Rules၊ Valuation နဲ့ PBR AI က ဒီ settings ကို default အဖြစ် အသုံးပြုပါတယ်။</p>

                @if($canManageContext)
                    <form method="POST" action="{{ route('workspaces.business-context.update', $workspace) }}">
                        @csrf
                        @method('PUT')

                        <div class="pbr-context-grid">
                            <div class="pbr-tools-field">
                                <label for="business_stage">Partnership အခြေအနေ <span>Business Stage</span></label>
                                <select id="business_stage" name="business_stage" required>
                                    @foreach($businessStages as $value => $label)
                                        <option value="{{ $value }}" @selected(old('business_stage', $workspace->business_stage) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small>Business အသစ်စတင်နေတာလား၊ ရှိပြီးသား Partnership ကို စီမံနေတာလား ရွေးပါ။</small>
                            </div>

                            <div class="pbr-tools-field">
                                <label for="currency_code">အဓိက ငွေကြေး <span>Primary Currency</span></label>
                                <select id="currency_code" name="currency_code" required>
                                    @foreach($currencies as $value => $label)
                                        <option value="{{ $value }}" @selected(old('currency_code', $workspace->currency_code) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small>Financial systems အားလုံးအတွက် default currency ဖြစ်ပါတယ်။</small>
                            </div>
                        </div>

                        <div class="pbr-context-actions">
                            <button type="submit" class="pbr-tools-primary-button">Business Settings သိမ်းရန်</button>
                        </div>
                    </form>
                @else
                    <div class="pbr-context-readonly">
                        <div><span>Partnership အခြေအနေ</span><strong>{{ $stageMm }}</strong></div>
                        <div><span>အဓိက ငွေကြေး</span><strong>{{ $workspace->currency_code ?? 'Not selected' }}</strong></div>
                    </div>
                @endif
            </div>
        </details>

        <section class="pbr-business-systems">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">BUSINESS SYSTEMS</span>
                    <h2>Partnership တစ်ခုလုံးကို တစ်နေရာတည်းက စီမံပါ</h2>
                    <p>လိုအပ်တဲ့ Business Area ကိုဖွင့်ပြီး actual company data၊ ဆုံးဖြတ်ချက်၊ Draft နဲ့ အသုံးပြုနေတဲ့ Rule တွေကို တိုက်ရိုက်စီမံနိုင်ပါတယ်။</p>
                </div>
            </div>

            <div class="pbr-business-system-list">
                @foreach($businessSystems as $businessSystem)
                    <details id="system-{{ $businessSystem['key'] }}" class="pbr-business-system" @if($businessSystem['number'] === 1) open @endif>
                        <summary>
                            <div class="pbr-business-system-title">
                                <span class="pbr-business-system-dot {{ $businessSystem['state']['key'] }}"></span>
                                <div>
                                    <h3>{{ $businessSystem['name_mm'] }}</h3>
                                    <p class="pbr-system-en-name">{{ $businessSystem['name_en'] }}</p>
                                </div>
                            </div>

                            <div class="pbr-business-system-summary-meta">
                                <div class="pbr-system-counts">
                                    @if($businessSystem['active_count'] > 0)<span class="active">အသုံးပြုနေ {{ $businessSystem['active_count'] }}</span>@endif
                                    @if($businessSystem['draft_count'] > 0)<span class="draft">Draft {{ $businessSystem['draft_count'] }}</span>@endif
                                    <span>{{ $businessSystem['module_count'] }} Modules</span>
                                </div>
                                <div class="pbr-business-system-state {{ $businessSystem['state']['key'] }}">
                                    {{ $businessSystem['state']['label_mm'] }}
                                </div>
                            </div>
                        </summary>

                        <div class="pbr-business-system-body">
                            <p class="pbr-business-system-summary">{{ $businessSystem['summary_mm'] }}</p>

                            <div class="pbr-business-module-grid">
                                @foreach($businessSystem['modules'] as $module)
                                    <article class="pbr-business-module {{ $module['state']['key'] }}">
                                        <div class="pbr-business-module-head">
                                            <div class="pbr-module-state {{ $module['state']['key'] }}">
                                                <i></i>
                                                <span>{{ $module['state']['label_mm'] }}</span>
                                            </div>
                                            @if($module['state']['meta'])
                                                <small>{{ $module['state']['meta'] }}</small>
                                            @endif
                                        </div>

                                        <h4>{{ $module['title_mm'] }}</h4>
                                        <span class="pbr-business-module-en">{{ $module['title_en'] }}</span>
                                        <p>{{ $module['purpose_mm'] }}</p>

                                        <div class="pbr-module-insight">
                                            <span>{{ $module['state']['detail_mm'] }}</span>
                                        </div>

                                        <a href="{{ $module['url'] }}">{{ $module['action_mm'] }}</a>
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
                <span class="pbr-business-eyebrow">CONNECTED INTELLIGENCE</span>
                <h2>Business Rule တွေက PBR AI ကို သင့်လုပ်ငန်းအတွက် ပိုအသုံးဝင်စေပါတယ်</h2>
                <p>အသုံးပြုနေတဲ့ Business Rules၊ Partner Data၊ Feasibility၊ Valuation နဲ့ Business Records တွေကို ဆက်စပ်ပြီး သင့် Workspace အတွက်ပဲ သီးသန့် guidance ရယူနိုင်ပါတယ်။</p>
            </div>
            <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}">PBR AI Advisor ဖွင့်ရန် ✦</a>
        </section>
    </div>
</section>
@endsection
