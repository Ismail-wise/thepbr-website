@extends('layouts.student-portal')

@section('title', 'စတင်မတည်ငွေ အစီအစဉ်')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $total = (float) ($result['total_startup_capital'] ?? 0);
    $essential = (float) ($result['essential_total'] ?? $total);
    $optional = (float) ($result['optional_total'] ?? 0);
    $funded = (float) ($result['funded_total'] ?? 0);
    $gap = (float) ($result['funding_gap'] ?? max(0, $total - $funded));
    $due30 = (float) ($result['due_30_days_outstanding'] ?? 0);
    $fundedPercent = $total > 0 ? min(100, ($funded / $total) * 100) : 0;
@endphp

<section class="pbr-tools-section pbr-capital-plan-page">
    <div class="portal-wrap pbr-capital-plan-wrap">
        <header class="pbr-capital-plan-hero">
            <div class="pbr-capital-plan-hero-copy">
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-tools-back pbr-capital-back">
                    ← Capital & Funding သို့ ပြန်ရန်
                </a>
                <span class="portal-kicker">CAPITAL & FUNDING</span>
                <h1>စတင်မတည်ငွေ အစီအစဉ်</h1>
                <p class="pbr-tool-en-subtitle">Startup Capital Plan</p>
                <p class="pbr-capital-plan-lead">
                    Owner/Admin အတည်ပြုပြီး <strong>လက်ရှိအသုံးပြုနေသော Startup Capital Plan</strong> ကို ဒီနေရာမှာကြည့်နိုင်ပါတယ်။
                    Draft နဲ့ private scenario တွေကို Partner account မှာ မပြပါဘူး။
                </p>
            </div>

            <div class="pbr-capital-business-card">
                <small>လက်ရှိ Business</small>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <span>{{ $currency }} · Partner ကြည့်ရှုရန်သာ</span>
                @if($latestAgreedOutput)
                    <b>✓ Active Rule · Revision {{ $latestAgreedOutput->revision }}</b>
                @endif
            </div>
        </header>

        @if($result)
            <div class="pbr-capital-workspace-grid pbr-capital-readonly-grid">
                <main class="pbr-capital-planner">
                    <section class="pbr-capital-costs-section">
                        <div class="pbr-capital-section-heading">
                            <div>
                                <span class="portal-kicker">ACTIVE COST PLAN</span>
                                <h2>လက်ရှိအတည်ပြုထားသော ကုန်ကျစရိတ် Plan</h2>
                                <p>Category တစ်ခုချင်းစီရဲ့ လိုအပ်ငွေ၊ Funding ရထားမှုနဲ့ outstanding ကို စုစည်းပြထားပါတယ်။</p>
                            </div>
                            @if($latestAgreedOutput)
                                <span class="pbr-capital-readonly-status">အသုံးပြုနေ · Rev {{ $latestAgreedOutput->revision }}</span>
                            @endif
                        </div>

                        <div class="pbr-capital-readonly-categories">
                            @forelse($result['categories'] ?? [] as $category)
                                @php
                                    $categoryTotal = (float) ($category['subtotal'] ?? 0);
                                    $categoryFunded = (float) ($category['funded'] ?? 0);
                                    $categoryOutstanding = (float) ($category['outstanding'] ?? max(0, $categoryTotal - $categoryFunded));
                                @endphp
                                <article class="pbr-capital-readonly-category">
                                    <div class="pbr-capital-readonly-category-head">
                                        <div>
                                            <strong>{{ $category['name'] ?? 'ကုန်ကျစရိတ်အုပ်စု' }}</strong>
                                            <small>{{ $category['item_count'] ?? count($category['items'] ?? []) }} items · {{ number_format((float) ($category['percentage'] ?? 0), 1) }}%</small>
                                        </div>
                                        <div>
                                            <span>{{ $currency }} {{ number_format($categoryTotal, 2) }}</span>
                                            <b class="{{ $categoryOutstanding > 0 ? 'warning' : 'ready' }}">
                                                {{ $categoryOutstanding > 0 ? $currency.' '.number_format($categoryOutstanding, 2).' လိုနေ' : 'Funding ပြည့်' }}
                                            </b>
                                        </div>
                                    </div>

                                    @if(!empty($category['items']))
                                        <div class="pbr-capital-readonly-items">
                                            @foreach($category['items'] as $item)
                                                @php
                                                    $planned = (float) ($item['planned_cost'] ?? $item['amount'] ?? 0);
                                                    $itemFunded = (float) ($item['funded_amount'] ?? 0);
                                                    $outstanding = (float) ($item['outstanding'] ?? max(0, $planned - $itemFunded));
                                                @endphp
                                                <div>
                                                    <div>
                                                        <strong>{{ $item['name'] ?? 'Item' }}</strong>
                                                        <small>
                                                            {{ ($item['priority'] ?? 'essential') === 'optional' ? 'Optional' : 'မဖြစ်မနေလို' }}
                                                            @if(($item['frequency'] ?? 'one_time') === 'monthly')
                                                                · လစဉ် × {{ $item['reserve_months'] ?? 3 }} လ
                                                            @endif
                                                            @if(!empty($item['due_date'])) · Due {{ $item['due_date'] }} @endif
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <span>{{ $currency }} {{ number_format($planned, 2) }}</span>
                                                        <small>{{ $outstanding > 0 ? 'Outstanding '.$currency.' '.number_format($outstanding, 2) : '✓ Funded' }}</small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="pbr-capital-empty">
                                    <strong>ကုန်ကျစရိတ်အသေးစိတ် မရှိသေးပါ</strong>
                                    <p>လက်ရှိ Active Plan မှာ Category detail မပါသေးပါ။</p>
                                </div>
                            @endforelse
                        </div>
                    </section>
                </main>

                <aside class="pbr-capital-summary-column">
                    <section class="pbr-capital-summary-card">
                        <div class="pbr-capital-summary-head">
                            <div>
                                <span class="portal-kicker">CURRENT CAPITAL POSITION</span>
                                <h2>အတည်ပြုထားသော Plan</h2>
                            </div>
                            <span class="pbr-capital-active-badge">Active</span>
                        </div>

                        <div class="pbr-capital-total">
                            <span>စုစုပေါင်း Startup Capital</span>
                            <strong>{{ $currency }} {{ number_format($total, 2) }}</strong>
                            <div class="pbr-capital-funding-progress"><i style="width: {{ min(100, $fundedPercent) }}%"></i></div>
                            <small><b>{{ number_format($fundedPercent, 0) }}%</b> Funding ရရှိထားသည်</small>
                        </div>

                        <div class="pbr-capital-metric-grid">
                            <div><span>မဖြစ်မနေလို</span><strong>{{ $currency }} {{ number_format($essential, 2) }}</strong></div>
                            <div><span>Optional</span><strong>{{ $currency }} {{ number_format($optional, 2) }}</strong></div>
                            <div class="funded"><span>Funding ရပြီး</span><strong>{{ $currency }} {{ number_format($funded, 2) }}</strong></div>
                            <div class="gap"><span>လိုနေသေး</span><strong>{{ $currency }} {{ number_format($gap, 2) }}</strong></div>
                            <div><span>30 ရက်အတွင်းလို</span><strong>{{ $currency }} {{ number_format($due30, 2) }}</strong></div>
                            <div><span>လစဉ် Commitment</span><strong>{{ $currency }} {{ number_format((float) ($result['monthly_recurring_cost'] ?? 0), 2) }}</strong></div>
                        </div>

                        <div class="pbr-capital-live-alert {{ $gap > 0 ? 'warning' : 'healthy' }}">
                            @if($gap > 0)
                                <strong>Funding Gap ရှိနေသည်</strong>
                                <p>{{ $currency }} {{ number_format($gap, 2) }} လိုနေသေးပါတယ်။ Owner/Admin က Funding Plan ကိုဆက်စီမံနိုင်ပါတယ်။</p>
                            @else
                                <strong>လက်ရှိ Plan Funding ပြည့်စုံနေသည်</strong>
                                <p>အတည်ပြုထားတဲ့ Startup Capital Plan အရ Funding Gap မရှိပါ။</p>
                            @endif
                        </div>
                    </section>

                    @if($latestAgreedOutput)
                        <section class="pbr-capital-help-card">
                            <span class="portal-kicker">ACTIVE REVISION</span>
                            <h3>Revision {{ $latestAgreedOutput->revision }}</h3>
                            <p class="pbr-capital-readonly-revision">{{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }} မှာ အတည်ပြုထားပါတယ်။ ဒီ data ကို Capital & Funding system နဲ့ PBR AI context မှာ အသုံးပြုနိုင်ပါတယ်။</p>
                        </section>
                    @endif
                </aside>
            </div>
        @else
            <section class="pbr-capital-no-active-plan">
                <div>◎</div>
                <h2>အသုံးပြုနေသော Startup Capital Plan မရှိသေးပါ</h2>
                <p>Owner/Admin က Draft Plan တစ်ခုကို Business Rule အဖြစ်အတည်ပြုလာတဲ့အခါ ဒီနေရာမှာမြင်ရပါမယ်။</p>
            </section>
        @endif

        <div class="pbr-os-legal-note pbr-capital-legal-note">
            <strong>သတိပြုရန်</strong>
            <p>ဒီ Capital Plan က business planning အတွက်ဖြစ်ပြီး accounting, tax, financing သို့မဟုတ် legal advice ကို အစားမထိုးပါ။</p>
        </div>
    </div>
</section>
@endsection
