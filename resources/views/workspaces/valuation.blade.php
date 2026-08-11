@extends('layouts.student-portal')

@section('title', 'Business Valuation')

@section('content')
@php
    $saved = $latest?->inputs ?? [];
    $result = $latest?->result ?? null;
    $value = fn ($key, $default = '') => old($key, $saved[$key] ?? $default);
    $currency = $workspace->currency_code ?? 'THB';
    $businessName = $workspace->business_name ?: $workspace->name;
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

                <button class="pbr2-btn" style="width:100%;margin-top:18px;" type="submit">Business Valuation တွက်ရန်</button>
            </form>
        </div>
    </section>
</div>
@endsection
