@extends('layouts.student-portal')

@section('title', 'Business Feasibility')

@section('content')
@php
    $saved = $latest?->inputs ?? [];
    $result = $latest?->result ?? null;
    $value = fn ($key, $default = '') => old($key, $saved[$key] ?? $default);
    $businessName = $workspace->business_name ?: $workspace->name;

    $decisionLabels = [
        'GO' => 'လက်ရှိအခြေအနေမှာ စတင်နိုင်ပါတယ်',
        'CONDITIONAL GO' => 'လုပ်နိုင်ပါတယ်၊ အရေးကြီးအချက်အချို့ကို အရင်ပြင်ပါ',
        'HOLD / IMPROVE FIRST' => 'အခုမစသေးဘဲ အဓိကအားနည်းချက်တွေကို အရင်ပြင်ပါ',
        'NO-GO AT CURRENT CONDITIONS' => 'လက်ရှိအခြေအနေမှာ မစတင်သင့်သေးပါ',
    ];

    $dimensionLabels = [
        'Market Viability' => 'Market အလားအလာ',
        'Financial Readiness' => 'ငွေကြေး အဆင်သင့်ဖြစ်မှု',
        'Operational Readiness' => 'လုပ်ငန်းလည်ပတ်မှု အဆင်သင့်ဖြစ်မှု',
        'Partner Alignment' => 'Partner ညှိနှိုင်းမှု',
        'Risk & Compliance' => 'Risk & Compliance',
        'Sales Readiness' => 'Sales အဆင်သင့်ဖြစ်မှု',
    ];
@endphp

<div class="pbr2-page">
    <section class="pbr2-hero">
        <div class="pbr2-hero-row">
            <div>
                <span class="pbr2-eyebrow">PBR Business Feasibility</span>
                <h1>လုပ်ငန်း / Project ကို လုပ်သင့်မလုပ်သင့် စစ်ဆေးပါ</h1>
                <p>{{ $businessName }} အတွက် Market, Finance, Operations, Partner, Risk နဲ့ Sales Readiness ကို ပေါင်းစပ်စစ်ဆေးပြီး လက်ရှိအခြေအနေမှာ ဘာလုပ်သင့်လဲ ပြပေးပါတယ်။</p>
            </div>
            <a class="pbr2-btn secondary" href="{{ route('workspaces.show', $workspace) }}">Business ဆီပြန်သွားရန်</a>
        </div>
    </section>

    @if($result)
        <section class="pbr2-section">
            <div class="pbr2-result-hero">
                <div class="pbr2-score" style="--score:{{ $result['score'] }}">
                    <strong>{{ $result['score'] }}</strong>
                    <small>READINESS</small>
                </div>
                <div>
                    <span class="pbr2-badge {{ in_array($result['decision'], ['GO', 'CONDITIONAL GO']) ? '' : 'orange' }}">{{ $result['decision'] }}</span>
                    <h2>{{ $decisionLabels[$result['decision']] ?? $result['decision_mm'] }}</h2>
                    <p>{{ $result['decision_mm'] }}</p>
                    <p style="margin-top:8px;font-size:12px;"><strong>သတိပြုရန်:</strong> ဒီ Score က Business Success Probability မဟုတ်ပါ။ လက်ရှိ Data ပေါ်မူတည်တဲ့ PBR Readiness Score ဖြစ်ပါတယ်။</p>
                </div>
            </div>

            <div class="pbr2-grid">
                <div class="pbr2-panel">
                    <h3>Assessment အပိုင်းများ</h3>
                    @foreach($result['dimensions'] as $name => $score)
                        <div class="pbr2-data-row">
                            <span>{{ $dimensionLabels[$name] ?? $name }}</span>
                            <strong>{{ $score }} / 100</strong>
                        </div>
                    @endforeach
                </div>

                <div>
                    @if(!empty($result['blockers']))
                        <div class="pbr2-panel">
                            <h3>အရင်ဆုံးဖြေရှင်းရမယ့် အရေးကြီးပြဿနာများ</h3>
                            <ul class="pbr2-list danger">
                                @foreach($result['blockers'] as $item)<li>{{ $item }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($result['risks']))
                        <div class="pbr2-panel">
                            <h3>သတိထားရမယ့် Risk များ</h3>
                            <ul class="pbr2-list warning">
                                @foreach($result['risks'] as $item)<li>{{ $item }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($result['actions']))
                <div class="pbr2-panel">
                    <h3>အောင်မြင်နိုင်ဖို့ အခုဘာလုပ်ရမလဲ?</h3>
                    <ul class="pbr2-list">
                        @foreach($result['actions'] as $item)<li>✓ {{ $item }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="pbr2-section">
        @if($canManageBusiness)
            <div class="pbr2-form-shell">
                <form method="POST" action="{{ route('workspaces.feasibility.calculate', $workspace) }}">
                    @csrf

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၁ / ၃</span>
                        <h2>Business / Project အခြေခံအချက်အလက်</h2>
                        <p>ခန့်မှန်းတန်ဖိုးထက် Real Data သို့မဟုတ် ကိုယ့်မှာရှိတဲ့ အကောင်းဆုံး estimate ကိုထည့်ပါ။ Currency: {{ $workspace->currency_code }}</p>

                        <div class="pbr2-field">
                            <label>Business / Project အမည်</label>
                            <input name="project_name" type="text" value="{{ $value('project_name') }}" placeholder="ဥပမာ - Chiang Mai Branch အသစ်">
                        </div>

                        <div class="pbr2-form-grid">
                            <div class="pbr2-field">
                                <label>စတင်ရန် လိုအပ်မယ့် စုစုပေါင်းငွေ</label>
                                <input name="startup_cost" type="number" step="0.01" min="0" value="{{ $value('startup_cost') }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>လက်ရှိ ရရှိနိုင်တဲ့ Capital</label>
                                <input name="available_capital" type="number" step="0.01" min="0" value="{{ $value('available_capital') }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>တစ်လ ခန့်မှန်း Revenue</label>
                                <input name="monthly_expected_revenue" type="number" step="0.01" min="0" value="{{ $value('monthly_expected_revenue') }}" required>
                            </div>
                            <div class="pbr2-field">
                                <label>တစ်လ Fixed Cost</label>
                                <input name="monthly_fixed_cost" type="number" step="0.01" min="0" value="{{ $value('monthly_fixed_cost') }}" required>
                            </div>
                        </div>

                        <div class="pbr2-field">
                            <label>Reserve Fund က လုပ်ငန်းစရိတ် ဘယ်နှလစာ လုံလောက်ပါသလဲ?</label>
                            <input name="reserve_months" type="number" step="0.1" min="0" max="60" value="{{ $value('reserve_months', 3) }}" required>
                        </div>
                    </section>

                    @php
                        $marketRatings = [
                            'market_demand' => 'Target Customer တွေအတွက် ဒီ Business / Product ကို လိုအပ်ချက် ဘယ်လောက်ရှိပါသလဲ?',
                            'customer_validation' => 'အမှန်တကယ် Customer တွေနဲ့ Idea ကို စမ်းသပ် / Validate လုပ်ထားမှု ဘယ်လောက်ရှိပါသလဲ?',
                            'competitive_advantage' => 'ပြိုင်ဘက်တွေနဲ့ယှဉ်ရင် ကိုယ့်ရဲ့ ကွဲပြားတဲ့အားသာချက် ဘယ်လောက်ရှင်းလင်းပါသလဲ?',
                            'sales_readiness' => 'ပထမ Customer တွေရဖို့ Sales Channel နဲ့ Plan ဘယ်လောက်အဆင်သင့်ဖြစ်ပါသလဲ?',
                        ];
                        $readinessRatings = [
                            'team_experience' => 'Team မှာ ဒီလုပ်ငန်းနဲ့သက်ဆိုင်တဲ့ Experience ဘယ်လောက်ရှိပါသလဲ?',
                            'operational_readiness' => 'Supplier, Staff, Process နဲ့ Delivery ပိုင်း ဘယ်လောက်အဆင်သင့်ဖြစ်ပါသလဲ?',
                            'partner_alignment' => 'Partner Roles, Expectations နဲ့ Decision Rules ဘယ်လောက်ရှင်းလင်းပါသလဲ?',
                            'legal_readiness' => 'Registration, License, Tax နဲ့ Compliance ပိုင်း ဘယ်လောက်အဆင်သင့်ဖြစ်ပါသလဲ?',
                        ];
                    @endphp

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၂ / ၃</span>
                        <h2>Market & Customer Readiness</h2>
                        <p>၁ = အလွန်အားနည်း၊ ၅ = အလွန်ကောင်း ဆိုပြီး လက်ရှိအခြေအနေအတိုင်း ရွေးပါ။</p>

                        @foreach($marketRatings as $key => $label)
                            <div class="pbr2-rating-block">
                                <div class="pbr2-rating-title">{{ $label }}</div>
                                <div class="pbr2-rating">
                                    @for($i = 1; $i <= 5; $i++)
                                        <div class="pbr2-rating-item">
                                            <input id="{{ $key }}-{{ $i }}" type="radio" name="{{ $key }}" value="{{ $i }}" @checked((string) $value($key, 3) === (string) $i) required>
                                            <label for="{{ $key }}-{{ $i }}">{{ $i }}</label>
                                        </div>
                                    @endfor
                                </div>
                                <div class="pbr2-scale-labels"><span>အလွန်အားနည်း</span><span>အလွန်ကောင်း</span></div>
                            </div>
                        @endforeach
                    </section>

                    <section class="pbr2-form-card">
                        <span class="pbr2-eyebrow">အပိုင်း ၃ / ၃</span>
                        <h2>Team, Operations & Partner Readiness</h2>
                        <p>အဖြေကောင်းအောင် မရွေးဘဲ လက်ရှိအခြေအနေကိုမှန်မှန်ရွေးတာက Result ပိုအသုံးဝင်စေပါတယ်။</p>

                        @foreach($readinessRatings as $key => $label)
                            <div class="pbr2-rating-block">
                                <div class="pbr2-rating-title">{{ $label }}</div>
                                <div class="pbr2-rating">
                                    @for($i = 1; $i <= 5; $i++)
                                        <div class="pbr2-rating-item">
                                            <input id="{{ $key }}-{{ $i }}" type="radio" name="{{ $key }}" value="{{ $i }}" @checked((string) $value($key, 3) === (string) $i) required>
                                            <label for="{{ $key }}-{{ $i }}">{{ $i }}</label>
                                        </div>
                                    @endfor
                                </div>
                                <div class="pbr2-scale-labels"><span>အလွန်အားနည်း</span><span>အလွန်ကောင်း</span></div>
                            </div>
                        @endforeach
                    </section>

                    <button class="pbr2-btn" style="width:100%;" type="submit">Feasibility Result ထုတ်ရန်</button>
                </form>
            </div>
        @else
            <div class="pbr2-panel">
                <span class="pbr2-eyebrow">Partner Read-only Access</span>
                <h2>နောက်ဆုံး Feasibility Result ကို ကြည့်နိုင်ပါတယ်</h2>
                <p>ဒီ Assessment ကို ပြန်တွက်ခြင်းနဲ့ Business Data ပြောင်းခြင်းကို Business Owner သို့မဟုတ် Admin ကသာ လုပ်နိုင်ပါတယ်။</p>
            </div>
        @endif
    </section>
</div>
@endsection
