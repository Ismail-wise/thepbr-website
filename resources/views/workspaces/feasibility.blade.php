@extends('layouts.student-portal')

@section('title', 'Business Feasibility')

@section('content')
@php
    $saved = $latest?->inputs ?? [];
    $result = $latest?->result ?? null;
    $value = fn ($key, $default = '') => old($key, $saved[$key] ?? $default);
@endphp

<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Business Feasibility</span>
            <h1>လုပ်သင့် / မလုပ်သင့် ဆုံးဖြတ်ခြင်း</h1>
            <p>{{ $workspace->business_name ?: $workspace->name }} အတွက် Market, Finance, Operations, Partner, Risk နဲ့ Sales Readiness ကိုပေါင်းပြီး လက်ရှိအခြေအနေမှာ ဘယ်လိုဆုံးဖြတ်သင့်လဲ ပြပေးပါတယ်။</p>
            <a class="portal-button secondary" href="{{ route('workspaces.show', $workspace) }}">Back to Business</a>
        </div>

        @if($result)
            <div class="auth-card">
                <span class="portal-kicker">Latest Decision</span>
                <h2>{{ $result['decision'] }}</h2>
                <div class="auth-note"><strong>Readiness Score:</strong> {{ $result['score'] }} / 100</div>
                <div class="auth-note">{{ $result['decision_mm'] }}</div>

                <h3>Assessment Areas</h3>
                @foreach($result['dimensions'] as $name => $score)
                    <div class="auth-note"><strong>{{ $name }}</strong> — {{ $score }} / 100</div>
                @endforeach

                @if(!empty($result['blockers']))
                    <h3>အရင်ဆုံးပြင်ရမယ့် Critical Issues</h3>
                    @foreach($result['blockers'] as $item)<div class="auth-note warning-note">{{ $item }}</div>@endforeach
                @endif

                @if(!empty($result['risks']))
                    <h3>Risks</h3>
                    @foreach($result['risks'] as $item)<div class="auth-note">{{ $item }}</div>@endforeach
                @endif

                @if(!empty($result['actions']))
                    <h3>အောင်မြင်နိုင်ခြေတိုးဖို့ ဘာလုပ်ရမလဲ?</h3>
                    @foreach($result['actions'] as $item)<div class="auth-note">✓ {{ $item }}</div>@endforeach
                @endif
            </div>
        @endif

        <div class="auth-card">
            <span class="portal-kicker">Assessment Data</span>
            <h2>လက်ရှိ Data ကိုထည့်ပါ</h2>
            <p class="panel-copy">Placeholder values မဟုတ်ဘဲ Real Business Data ရလာတဲ့အခါ အမှန်တကယ် data နဲ့ထည့်ပါ။</p>

            <form method="POST" action="{{ route('workspaces.feasibility.calculate', $workspace) }}">
                @csrf
                <div class="field"><label>Project / Business Idea Name</label><input name="project_name" type="text" value="{{ $value('project_name') }}" placeholder="Example: Second Restaurant Branch"></div>
                <div class="field"><label>Estimated Startup Cost ({{ $workspace->currency_code }})</label><input name="startup_cost" type="number" step="0.01" min="0" value="{{ $value('startup_cost') }}" required></div>
                <div class="field"><label>Available Capital</label><input name="available_capital" type="number" step="0.01" min="0" value="{{ $value('available_capital') }}" required></div>
                <div class="field"><label>Expected Monthly Revenue</label><input name="monthly_expected_revenue" type="number" step="0.01" min="0" value="{{ $value('monthly_expected_revenue') }}" required></div>
                <div class="field"><label>Monthly Fixed Cost</label><input name="monthly_fixed_cost" type="number" step="0.01" min="0" value="{{ $value('monthly_fixed_cost') }}" required></div>
                <div class="field"><label>Reserve Fund — Months</label><input name="reserve_months" type="number" step="0.1" min="0" max="60" value="{{ $value('reserve_months', 3) }}" required></div>

                @php
                    $ratings = [
                        'market_demand' => 'Market Demand',
                        'customer_validation' => 'Customer Validation',
                        'competitive_advantage' => 'Competitive Advantage',
                        'team_experience' => 'Team Experience',
                        'operational_readiness' => 'Operational Readiness',
                        'partner_alignment' => 'Partner Alignment',
                        'legal_readiness' => 'Legal & Compliance Readiness',
                        'sales_readiness' => 'Sales Readiness',
                    ];
                @endphp

                @foreach($ratings as $key => $label)
                    <div class="field">
                        <label>{{ $label }}</label>
                        <select name="{{ $key }}" required>
                            @for($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((string) $value($key, 3) === (string) $i)>{{ $i }}@if($i === 1) — Very Weak @elseif($i === 3) — Moderate @elseif($i === 5) — Very Strong @endif</option>
                            @endfor
                        </select>
                    </div>
                @endforeach

                @if($errors->any())<div class="auth-note warning-note">Data တချို့ မပြည့်စုံသေးပါ။ Fields တွေကို ပြန်စစ်ပါ။</div>@endif
                <button class="portal-button" type="submit">Analyze Business Feasibility</button>
            </form>
        </div>
    </div>
</section>
@endsection
