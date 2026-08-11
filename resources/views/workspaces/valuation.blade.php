@extends('layouts.student-portal')

@section('title', 'Business Valuation')

@section('content')
@php
    $saved = $latest?->inputs ?? [];
    $result = $latest?->result ?? null;
    $value = fn ($key, $default = '') => old($key, $saved[$key] ?? $default);
    $currency = $workspace->currency_code ?? 'THB';
@endphp

<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Business Valuation Center</span>
            <h1>Business တန်ဖိုး ခန့်မှန်းခြင်း</h1>
            <p>Existing Business ရဲ့ Earnings, Assets, Cash Flow နဲ့ Risk Factors တွေကိုသုံးပြီး indicative Valuation Range တစ်ခုဖန်တီးပေးပါတယ်။</p>
            <a class="portal-button secondary" href="{{ route('workspaces.show', $workspace) }}">Back to Business</a>
        </div>

        @if($result)
            <div class="auth-card">
                <span class="portal-kicker">Latest Valuation</span>
                <h2>Estimated Business Value</h2>
                <div class="auth-note"><strong>Conservative</strong><br>{{ $currency }} {{ number_format($result['conservative'], 2) }}</div>
                <div class="auth-note"><strong>Base Case</strong><br>{{ $currency }} {{ number_format($result['base'], 2) }}</div>
                <div class="auth-note"><strong>Optimistic</strong><br>{{ $currency }} {{ number_format($result['optimistic'], 2) }}</div>
                <div class="auth-note"><strong>Data Confidence:</strong> {{ $result['confidence'] }}</div>

                <h3>Valuation Methods</h3>
                @forelse($result['methods'] as $method => $amount)
                    <div class="auth-note"><strong>{{ $method }}</strong><br>{{ $currency }} {{ number_format($amount, 2) }}</div>
                @empty
                    <div class="auth-note warning-note">Valuation Method တွက်ဖို့ Financial Data မလုံလောက်သေးပါ။</div>
                @endforelse

                @if(!empty($result['risks']))
                    <h3>Value လျော့စေနိုင်တဲ့အချက်များ</h3>
                    @foreach($result['risks'] as $item)<div class="auth-note warning-note">{{ $item }}</div>@endforeach
                @endif

                @if(!empty($result['actions']))
                    <h3>Business Value တိုးဖို့</h3>
                    @foreach($result['actions'] as $item)<div class="auth-note">✓ {{ $item }}</div>@endforeach
                @endif

                <div class="auth-note">{{ $result['note'] }}</div>
            </div>
        @endif

        <div class="auth-card">
            <span class="portal-kicker">Real Business Data</span>
            <h2>Financial Data ထည့်ပါ</h2>
            <p class="panel-copy">Multiples နဲ့ discount rate တွေဟာ default example values ပါ။ Real industry / adviser data ရလာရင် အမှန် value နဲ့ပြောင်းပါ။</p>

            <form method="POST" action="{{ route('workspaces.valuation.calculate', $workspace) }}">
                @csrf
                @php
                    $moneyFields = [
                        'annual_revenue' => 'Annual Revenue',
                        'ebitda' => 'Annual EBITDA',
                        'owner_earnings' => 'Owner Earnings / SDE',
                        'free_cash_flow' => 'Annual Free Cash Flow',
                        'total_assets' => 'Total Assets',
                        'total_liabilities' => 'Total Liabilities',
                        'cash' => 'Cash',
                        'debt' => 'Interest-Bearing Debt',
                    ];
                @endphp

                @foreach($moneyFields as $key => $label)
                    <div class="field"><label>{{ $label }} ({{ $currency }})</label><input name="{{ $key }}" type="number" step="0.01" min="0" value="{{ $value($key, 0) }}" required></div>
                @endforeach

                <div class="field"><label>EBITDA Multiple</label><input name="ebitda_multiple" type="number" step="0.1" min="0" max="50" value="{{ $value('ebitda_multiple', 3) }}" required><small class="field-help">3.0 = example placeholder</small></div>
                <div class="field"><label>Owner Earnings / SDE Multiple</label><input name="sde_multiple" type="number" step="0.1" min="0" max="50" value="{{ $value('sde_multiple', 2.5) }}" required><small class="field-help">2.5 = example placeholder</small></div>
                <div class="field"><label>Expected Annual Growth %</label><input name="growth_rate" type="number" step="0.1" min="-50" max="100" value="{{ $value('growth_rate', 5) }}" required></div>
                <div class="field"><label>Discount Rate %</label><input name="discount_rate" type="number" step="0.1" min="1" max="60" value="{{ $value('discount_rate', 15) }}" required><small class="field-help">15% = example placeholder</small></div>
                <div class="field"><label>Terminal Growth %</label><input name="terminal_growth" type="number" step="0.1" min="0" max="15" value="{{ $value('terminal_growth', 3) }}" required></div>
                <div class="field"><label>Recurring Revenue %</label><input name="recurring_revenue_pct" type="number" step="0.1" min="0" max="100" value="{{ $value('recurring_revenue_pct', 30) }}" required></div>
                <div class="field"><label>Largest Customer / Group Revenue %</label><input name="customer_concentration_pct" type="number" step="0.1" min="0" max="100" value="{{ $value('customer_concentration_pct', 20) }}" required></div>
                <div class="field"><label>Owner Dependency</label><select name="owner_dependency" required>@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" @selected((string) $value('owner_dependency', 3) === (string) $i)>{{ $i }}@if($i === 1) — Very Low @elseif($i === 5) — Very High @endif</option>@endfor</select></div>

                @if($errors->any())<div class="auth-note warning-note">Data တချို့ကို ပြန်စစ်ပါ။</div>@endif
                <button class="portal-button" type="submit">Calculate Business Value</button>
            </form>
        </div>
    </div>
</section>
@endsection
