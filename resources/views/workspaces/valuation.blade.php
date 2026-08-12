@extends('layouts.student-portal')

@section('title', 'Business Valuation')

@section('content')
@php
    $saved = $latest?->inputs ?? [];
    $result = $latest?->result ?? null;
    $value = fn ($key, $default = '') => old($key, $saved[$key] ?? $default);
    $currency = $workspace->currency_code ?? 'THB';
    $businessName = $workspace->business_name ?: $workspace->name;
    $ownership = $result['ownership'] ?? null;
    $confidenceMm = ['HIGH' => 'Data ယုံကြည်မှု မြင့်', 'MEDIUM' => 'Data ယုံကြည်မှု အလယ်အလတ်', 'LOW' => 'Data ယုံကြည်မှု နည်း'];
    $methodMm = [
        'EBITDA Multiple' => 'EBITDA Multiple Method',
        'Owner Earnings / SDE' => 'Owner Earnings / SDE Method',
        'Asset Based' => 'ပိုင်ဆိုင်မှုအခြေပြု Asset Method',
        'Discounted Cash Flow' => 'Discounted Cash Flow (DCF)',
    ];
@endphp

<div class="pbr2-page">
    <section class="pbr2-hero">
        <div class="pbr2-hero-row">
            <div>
                <span class="pbr2-eyebrow">PBR Business Valuation Center</span>
                <h1>{{ $businessName }} ရဲ့ Business Value ကို ခန့်မှန်းပါ</h1>
                <p>Earnings, Cash Flow, Assets နဲ့ Business Risk Factors တွေကို သုံးပြီး Method အမျိုးမျိုးကနေ Valuation Range တစ်ခုကို စနစ်တကျ တွက်ပေးပါတယ်။</p>
            </div>
            <a class="pbr2-btn secondary" href="{{ route('workspaces.show', $workspace) }}">Business ဆီပြန်သွားရန်</a>
        </div>
    </section>

    @if($result)
        <section class="pbr2-section">
            <div class="pbr2-section-head">
                <div>
                    <span class="pbr2-eyebrow">Latest Valuation</span>
                    <h2>ခန့်မှန်း Business Value Range</h2>
                    <p>တစ်ခုတည်းသော exact price မဟုတ်ဘဲ Scenario အလိုက် Range နဲ့ကြည့်တာ ပိုသင့်တော်ပါတယ်။</p>
                </div>
                <span class="pbr2-badge">{{ $confidenceMm[$result['confidence']] ?? $result['confidence'] }}</span>
            </div>

            <div class="pbr2-result-grid">
                <div class="pbr2-value-card">
                    <span>သတိထားတွက်ထားသောတန်ဖိုး • Conservative</span>
                    <strong>{{ $currency }} {{ number_format($result['conservative'], 2) }}</strong>
                </div>
                <div class="pbr2-value-card" style="border-color:#9fc8a7;background:#f4faf4;">
                    <span>အခြေခံခန့်မှန်းတန်ဖိုး • Base Estimate</span>
                    <strong>{{ $currency }} {{ number_format($result['base'], 2) }}</strong>
                </div>
                <div class="pbr2-value-card">
                    <span>အကောင်းဘက်ခန့်မှန်းတန်ဖိုး • Optimistic</span>
                    <strong>{{ $currency }} {{ number_format($result['optimistic'], 2) }}</strong>
                </div>
            </div>

            @if($ownership)
                <div class="pbr2-panel" style="margin-top:20px;padding:22px;">
                    <div class="pbr2-section-head">
                        <div>
                            <span class="pbr2-eyebrow">Share / Ownership Value</span>
                            <h2>Estimated Price per Share / Ownership Unit</h2>
                            <p>စုစုပေါင်း <strong>{{ number_format($ownership['total_units']) }}</strong> Share / Ownership Units အပေါ်မူတည်ပြီး တစ် Unit ရဲ့ ခန့်မှန်းတန်ဖိုးကို ပြထားပါတယ်။</p>
                        </div>
                        <span class="pbr2-badge gray">{{ number_format($ownership['total_units']) }} Units</span>
                    </div>

                    <div class="pbr2-value-card" style="margin:18px 0;padding:28px;border-color:#91c49a;background:linear-gradient(135deg,#f8fcf8,#eef8f0);">
                        <span style="font-size:12px;font-weight:750;color:var(--pbr2-green);">အဓိက ခန့်မှန်းတန်ဖိုး • Base Estimate</span>
                        <strong style="display:block;font-size:clamp(32px,5vw,46px);line-height:1.15;margin:8px 0 4px;">{{ $currency }} {{ number_format($ownership['base_per_unit'], 2) }}</strong>
                        <span style="font-size:12px;">တစ် Share / Ownership Unit</span>
                    </div>

                    <div class="pbr2-grid">
                        <div class="pbr2-value-card">
                            <span>အနိမ့်ဘက် ခန့်မှန်းတန်ဖိုး • Conservative</span>
                            <strong>{{ $currency }} {{ number_format($ownership['conservative_per_unit'], 2) }}</strong>
                            <span style="margin-top:5px;">တစ် Unit</span>
                        </div>
                        <div class="pbr2-value-card">
                            <span>အမြင့်ဘက် ခန့်မှန်းတန်ဖိုး • Optimistic</span>
                            <strong>{{ $currency }} {{ number_format($ownership['optimistic_per_unit'], 2) }}</strong>
                            <span style="margin-top:5px;">တစ် Unit</span>
                        </div>
                    </div>

                    <div class="pbr2-panel" style="margin-top:18px;background:#fafafa;">
                        <h3>1% Ownership ရဲ့ ခန့်မှန်းတန်ဖိုး</h3>
                        <div class="pbr2-data-row">
                            <span>Conservative</span>
                            <strong>{{ $currency }} {{ number_format($ownership['conservative_one_percent'], 2) }}</strong>
                        </div>
                        <div class="pbr2-data-row">
                            <span>Base Estimate</span>
                            <strong>{{ $currency }} {{ number_format($ownership['base_one_percent'], 2) }}</strong>
                        </div>
                        <div class="pbr2-data-row">
                            <span>Optimistic</span>
                            <strong>{{ $currency }} {{ number_format($ownership['optimistic_one_percent'], 2) }}</strong>
                        </div>
                    </div>

                    <p style="margin:16px 2px 0;color:var(--pbr2-muted);font-size:12px;line-height:1.65;">
                        <strong>သတိပြုရန်:</strong> ဒီ Price per Share / Unit က PBR Valuation Estimate ကို Total Ownership Units နဲ့ ခွဲတွက်ထားတဲ့ indicative value ဖြစ်ပါတယ်။ Legal share price, actual sale price သို့မဟုတ် certified valuation မဟုတ်ပါ။
                    </p>
                </div>

                <div
                    id="partner-stake-calculator"
                    class="pbr2-panel"
                    style="margin-top:20px;"
                    data-currency="{{ $currency }}"
                    data-total-units="{{ $ownership['total_units'] }}"
                    data-conservative="{{ $result['conservative'] }}"
                    data-base="{{ $result['base'] }}"
                    data-optimistic="{{ $result['optimistic'] }}"
                >
                    <span class="pbr2-eyebrow">Partner Stake Calculator</span>
                    <h2>Partner တစ်ယောက်ရဲ့ Ownership Value ကိုတွက်ပါ</h2>
                    <p>Ownership % သို့မဟုတ် Share / Unit အရေအတွက် တစ်ခုခုထည့်ပါ။ နှစ်ခုက အလိုအလျောက်ချိတ်ဆက်တွက်ပေးပါမယ်။</p>

                    <div class="pbr2-form-grid">
                        <div class="pbr2-field">
                            <label for="stake_percent">Partner Ownership %</label>
                            <input id="stake_percent" type="number" min="0" max="100" step="0.01" value="25">
                        </div>
                        <div class="pbr2-field">
                            <label for="stake_units">Partner Share / Ownership Units</label>
                            <input id="stake_units" type="number" min="0" max="{{ $ownership['total_units'] }}" step="0.01" value="{{ $ownership['total_units'] * .25 }}">
                        </div>
                    </div>

                    <div class="pbr2-result-grid" style="margin-top:18px;">
                        <div class="pbr2-value-card">
                            <span>Conservative Stake Value</span>
                            <strong id="stake_conservative">—</strong>
                        </div>
                        <div class="pbr2-value-card" style="border-color:#9fc8a7;background:#f4faf4;">
                            <span>Base Stake Value</span>
                            <strong id="stake_base">—</strong>
                        </div>
                        <div class="pbr2-value-card">
                            <span>Optimistic Stake Value</span>
                            <strong id="stake_optimistic">—</strong>
                        </div>
                    </div>

                    <p style="margin:16px 0 0;color:var(--pbr2-muted);font-size:12px;">
                        ဒီတန်ဖိုးက PBR Valuation Estimate ပေါ်မူတည်တဲ့ indicative ownership value ဖြစ်ပြီး legal share price, market transaction price သို့မဟုတ် certified valuation မဟုတ်ပါ။
                    </p>
                </div>
            @else
                <div class="pbr2-panel" style="margin-top:20px;">
                    <span class="pbr2-eyebrow">Ownership Value အသစ်</span>
                    <h3>Share / Ownership Value ကိုရဖို့ Valuation ပြန်တွက်ပါ</h3>
                    <p>ဒီ Result က အရင် version မှာတွက်ထားတာဖြစ်လို့ Total Share / Ownership Units မပါသေးပါဘူး။ အောက်က Form မှာ Units ထည့်ပြီး Valuation ပြန်တွက်လိုက်ရင် Per Share Value နဲ့ Partner Stake Calculator ပေါ်လာပါမယ်။</p>
                </div>
            @endif

            <div class="pbr2-grid">
                <div class="pbr2-panel">
                    <h3>Valuation Method အလိုက် ရလဒ်</h3>
                    @forelse($result['methods'] as $method => $amount)
                        <div class="pbr2-data-row">
                            <span>{{ $methodMm[$method] ?? $method }}</span>
                            <strong>{{ $currency }} {{ number_format($amount, 2) }}</strong>
                        </div>
                    @empty
                        <p>Method တွက်ဖို့ Financial Data မလုံလောက်သေးပါ။</p>
                    @endforelse
                </div>

                <div>
                    @if(!empty($result['risks']))
                        <div class="pbr2-panel">
                            <h3>Business Value လျော့စေနိုင်တဲ့အချက်များ</h3>
                            <ul class="pbr2-list warning">
                                @foreach($result['risks'] as $item)<li>{{ $item }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($result['actions']))
                <div class="pbr2-panel">
                    <h3>Business Value တိုးဖို့ ဘာလုပ်နိုင်မလဲ?</h3>
                    <ul class="pbr2-list">
                        @foreach($result['actions'] as $item)<li>✓ {{ $item }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="pbr2-panel">
                <p style="margin:0;color:var(--pbr2-muted);font-size:12px;">{{ $result['note'] }}</p>
            </div>
        </section>
    @endif

    <section class="pbr2-section">
        @if($canManageBusiness)
            <div class="pbr2-form-shell">
                <form method="POST" action="{{ route('workspaces.valuation.calculate', $workspace) }}">
                    @csrf

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၁</span>
                        <h2>အဓိက Financial Data</h2>
                        <p>အကောင်းဆုံးက Financial Statements / Management Accounts ထဲက နောက်ဆုံး 12 months Data ကိုသုံးပါ။ Currency: {{ $currency }}</p>

                        <div class="pbr2-form-grid">
                            <div class="pbr2-field">
                                <label>တစ်နှစ် Revenue</label>
                                <input name="annual_revenue" type="number" step="0.01" min="0" value="{{ $value('annual_revenue', 0) }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>တစ်နှစ် EBITDA</label>
                                <input name="ebitda" type="number" step="0.01" min="0" value="{{ $value('ebitda', 0) }}" required>
                                <small class="pbr2-help">Interest, Tax, Depreciation, Amortization မနုတ်ခင် Earnings</small>
                            </div>
                            <div class="pbr2-field">
                                <label>Owner Earnings / SDE</label>
                                <input name="owner_earnings" type="number" step="0.01" min="0" value="{{ $value('owner_earnings', 0) }}" required>
                                <small class="pbr2-help">SME Owner အတွက် normalized earnings ကိုသုံးနိုင်ပါတယ်။</small>
                            </div>
                            <div class="pbr2-field">
                                <label>တစ်နှစ် Free Cash Flow</label>
                                <input name="free_cash_flow" type="number" step="0.01" min="0" value="{{ $value('free_cash_flow', 0) }}" required>
                            </div>
                        </div>
                    </section>

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၂</span>
                        <h2>Assets, Liabilities & Debt</h2>
                        <p>Balance Sheet ထဲက လက်ရှိတန်ဖိုးတွေနဲ့ နီးစပ်အောင်ထည့်ပါ။</p>

                        <div class="pbr2-form-grid">
                            <div class="pbr2-field">
                                <label>Total Assets</label>
                                <input name="total_assets" type="number" step="0.01" min="0" value="{{ $value('total_assets', 0) }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>Total Liabilities</label>
                                <input name="total_liabilities" type="number" step="0.01" min="0" value="{{ $value('total_liabilities', 0) }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>Cash</label>
                                <input name="cash" type="number" step="0.01" min="0" value="{{ $value('cash', 0) }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>Interest-Bearing Debt</label>
                                <input name="debt" type="number" step="0.01" min="0" value="{{ $value('debt', 0) }}" required>
                            </div>
                        </div>
                    </section>

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၃</span>
                        <h2>Business Quality & Risk</h2>
                        <p>ဝင်ငွေရဲ့ တည်ငြိမ်မှုနဲ့ Owner အပေါ်မှီခိုမှုတွေက Business Value ကို တိုက်ရိုက်သက်ရောက်နိုင်ပါတယ်။</p>

                        <div class="pbr2-form-grid">
                            <div class="pbr2-field">
                                <label>Recurring Revenue %</label>
                                <input name="recurring_revenue_pct" type="number" step="0.1" min="0" max="100" value="{{ $value('recurring_revenue_pct', 30) }}" required>
                                <small class="pbr2-help">ထပ်ခါတလဲလဲ / Contract / Subscription Revenue ရာခိုင်နှုန်း</small>
                            </div>
                            <div class="pbr2-field">
                                <label>အကြီးဆုံး Customer / Group က Revenue ရဲ့ ဘယ်နှ % လဲ?</label>
                                <input name="customer_concentration_pct" type="number" step="0.1" min="0" max="100" value="{{ $value('customer_concentration_pct', 20) }}" required>
                            </div>
                        </div>

                        <div class="pbr2-field">
                            <label>Owner မရှိရင် Business လည်ပတ်နိုင်မှု</label>
                            <select name="owner_dependency" required>
                                <option value="1" @selected((string) $value('owner_dependency', 3) === '1')>1 — Owner အပေါ် မှီခိုမှု အလွန်နည်း</option>
                                <option value="2" @selected((string) $value('owner_dependency', 3) === '2')>2 — မှီခိုမှု နည်း</option>
                                <option value="3" @selected((string) $value('owner_dependency', 3) === '3')>3 — အလယ်အလတ်</option>
                                <option value="4" @selected((string) $value('owner_dependency', 3) === '4')>4 — မှီခိုမှု မြင့်</option>
                                <option value="5" @selected((string) $value('owner_dependency', 3) === '5')>5 — Owner အပေါ် အလွန်မှီခို</option>
                            </select>
                        </div>
                    </section>

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၄</span>
                        <h2>Share / Ownership Structure</h2>
                        <p>Official Share ရှိရင် Total Shares ကိုထည့်ပါ။ Partnership တစ်ခုမှာ official shares မရှိရင် Ownership Units အဖြစ်သတ်မှတ်ပြီး အသုံးပြုနိုင်ပါတယ်။</p>

                        <div class="pbr2-field">
                            <label>Total Share / Ownership Units</label>
                            <input name="total_ownership_units" type="number" step="1" min="1" max="1000000000" value="{{ $value('total_ownership_units', 100) }}" required>
                            <small class="pbr2-help">Official shares မရှိရင် 100 Units သုံးနိုင်ပါတယ် — အဲဒီအခါ 1 Unit = 1% Ownership ဖြစ်ပါတယ်။ Official shares ရှိရင် အမှန်တကယ် Total Shares ကိုထည့်ပါ။</small>
                        </div>
                    </section>

                    <details class="pbr2-details">
                        <summary>Advanced Valuation Assumptions ကို ကြည့်ရန်</summary>
                        <div class="pbr2-details-body">
                            <p style="margin-top:0;color:var(--pbr2-muted);font-size:13px;">ဒီ Values တွေက default example assumptions ပါ။ Real Industry Multiple သို့မဟုတ် Adviser Data ရှိရင် အစားထိုးပါ။</p>

                            <div class="pbr2-form-grid">
                                <div class="pbr2-field">
                                    <label>EBITDA Multiple</label>
                                    <input name="ebitda_multiple" type="number" step="0.1" min="0" max="50" value="{{ $value('ebitda_multiple', 3) }}" required>
                                    <small class="pbr2-help">Default example: 3.0x</small>
                                </div>
                                <div class="pbr2-field">
                                    <label>Owner Earnings / SDE Multiple</label>
                                    <input name="sde_multiple" type="number" step="0.1" min="0" max="50" value="{{ $value('sde_multiple', 2.5) }}" required>
                                    <small class="pbr2-help">Default example: 2.5x</small>
                                </div>
                                <div class="pbr2-field">
                                    <label>Expected Annual Growth %</label>
                                    <input name="growth_rate" type="number" step="0.1" min="-50" max="100" value="{{ $value('growth_rate', 5) }}" required>
                                </div>
                                <div class="pbr2-field">
                                    <label>Discount Rate %</label>
                                    <input name="discount_rate" type="number" step="0.1" min="1" max="60" value="{{ $value('discount_rate', 15) }}" required>
                                    <small class="pbr2-help">Default example: 15%</small>
                                </div>
                                <div class="pbr2-field">
                                    <label>Terminal Growth %</label>
                                    <input name="terminal_growth" type="number" step="0.1" min="0" max="15" value="{{ $value('terminal_growth', 3) }}" required>
                                </div>
                            </div>
                        </div>
                    </details>

                    <button class="pbr2-btn" style="width:100%;margin-top:18px;" type="submit">Business Valuation + Ownership Value တွက်ရန်</button>
                </form>
            </div>
        @else
            <div class="pbr2-panel">
                <span class="pbr2-eyebrow">Partner Read-only Access</span>
                <h2>နောက်ဆုံး Valuation Result ကို ကြည့်နိုင်ပါတယ်</h2>
                <p>Financial Data ပြောင်းခြင်းနဲ့ Valuation ပြန်တွက်ခြင်းကို Business Owner သို့မဟုတ် Admin ကသာ လုပ်နိုင်ပါတယ်။</p>
            </div>
        @endif
    </section>
</div>

@if($ownership)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calculator = document.getElementById('partner-stake-calculator');
    if (!calculator) return;

    const percentInput = document.getElementById('stake_percent');
    const unitsInput = document.getElementById('stake_units');
    const totalUnits = Number(calculator.dataset.totalUnits || 0);
    const conservative = Number(calculator.dataset.conservative || 0);
    const base = Number(calculator.dataset.base || 0);
    const optimistic = Number(calculator.dataset.optimistic || 0);
    const currency = calculator.dataset.currency || '';

    const money = (amount) => currency + ' ' + new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);

    const render = (percent) => {
        const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
        const ratio = safePercent / 100;
        document.getElementById('stake_conservative').textContent = money(conservative * ratio);
        document.getElementById('stake_base').textContent = money(base * ratio);
        document.getElementById('stake_optimistic').textContent = money(optimistic * ratio);
    };

    percentInput.addEventListener('input', function () {
        const percent = Math.max(0, Math.min(100, Number(this.value) || 0));
        unitsInput.value = totalUnits > 0 ? (totalUnits * percent / 100).toFixed(2) : 0;
        render(percent);
    });

    unitsInput.addEventListener('input', function () {
        const units = Math.max(0, Math.min(totalUnits, Number(this.value) || 0));
        const percent = totalUnits > 0 ? (units / totalUnits) * 100 : 0;
        percentInput.value = percent.toFixed(2);
        render(percent);
    });

    render(percentInput.value);
});
</script>
@endif
@endsection