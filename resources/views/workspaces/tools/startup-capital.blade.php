@extends('layouts.student-portal')

@section('title', 'စတင်မတည်ငွေ အစီအစဉ်')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
@endphp

<section class="pbr-tools-section">
    <div class="portal-wrap">
        <div class="pbr-tool-page-head">
            <div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-tools-back">
                    ← Business Operating System သို့ ပြန်ရန်
                </a>

                <span class="portal-kicker">မတည်ငွေနှင့် ရင်းနှီးငွေ · Capital & Funding</span>
                <h1>စတင်မတည်ငွေ အစီအစဉ်</h1>
                <p class="pbr-tool-en-subtitle">Startup Capital Plan</p>
                <p>
                    လုပ်ငန်းစတင်ဖို့ တကယ်ကုန်ကျမယ့်အရာတွေကို ကိုယ့်လုပ်ငန်းအတိုင်း အုပ်စုခွဲထည့်ပြီး
                    စုစုပေါင်း မတည်ငွေဘယ်လောက်လိုမလဲဆိုတာ စီစဉ်ပါ။ PBR က fixed cost list မသတ်မှတ်ထားပါဘူး။
                </p>
            </div>

            <div class="pbr-tool-context-box">
                <span>လက်ရှိ Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <small>Partnership အသစ် စီစဉ်နေသည်</small>
                <small>ငွေကြေး · {{ $currency }}</small>
            </div>
        </div>

        <form
            id="startup-capital-builder"
            method="POST"
            action="{{ route('workspaces.tools.startup-capital.calculate', $workspace) }}"
            data-currency="{{ $currency }}"
            data-next-category="{{ count($categories) + 100 }}"
        >
            @csrf

            @if($activeSession || request('tool_session_id'))
                <input
                    type="hidden"
                    name="tool_session_id"
                    value="{{ $activeSession?->id ?? request('tool_session_id') }}"
                >
            @endif

            @if(session('status'))
                <div class="pbr-save-success">{{ session('status') }}</div>
            @endif

            <div class="pbr-scenario-box">
                <div>
                    <label for="scenario_name">Draft အမည်</label>
                    <small>Plan မျိုးစုံစမ်းချင်ရင် မှတ်မိလွယ်တဲ့နာမည်ပေးပါ။ ဥပမာ — အခြေခံ Plan, Budget နည်း Plan</small>
                </div>
                <input
                    id="scenario_name"
                    name="scenario_name"
                    type="text"
                    maxlength="120"
                    placeholder="ဥပမာ: အခြေခံ Plan 2026"
                    value="{{ old('scenario_name', $activeSession?->scenario_name ?? request('scenario_name', '')) }}"
                >
            </div>

            @if($activeSession)
                <div class="pbr-active-draft">
                    <span>သိမ်းထားသော Draft ကို ပြင်နေသည်</span>
                    <strong>{{ $activeSession->scenario_name }}</strong>
                    <small>
                        နောက်ဆုံးသိမ်းထားသည် ·
                        {{ $activeSession->last_saved_at ? $activeSession->last_saved_at->diffForHumans() : 'မကြာသေးမီက' }}
                    </small>
                </div>
            @endif

            <div class="pbr-calculator-layout">
                <div class="pbr-calculator-panel">
                    <div class="pbr-calculator-panel-head">
                        <span class="portal-kicker">စတင်ဖို့ ကုန်ကျစရိတ်များ · Startup Costs</span>
                        <h2>ကိုယ့်လုပ်ငန်းအတွက် တကယ်လိုအပ်တာတွေကို ထည့်ပါ</h2>
                        <p>
                            အောက်မှာ ကုန်ကျစရိတ်အုပ်စု (Category) နဲ့ တစ်ခုချင်းကုန်ကျစရိတ် (Item) ကိုထည့်ပါ။
                            မလိုတဲ့အုပ်စုကိုဖယ်နိုင်ပြီး လိုသလောက်အသစ်ထည့်နိုင်ပါတယ်။
                        </p>
                    </div>

                    @if($errors->any())
                        <div class="pbr-form-errors">
                            <strong>ထည့်ထားတဲ့အချက်အလက်ကို ပြန်စစ်ပါ။</strong>
                            <p>Amount ကို 0 သို့မဟုတ် အပေါင်းကိန်းနဲ့ထည့်ပါ။ အမည်လိုအပ်တဲ့နေရာတွေ မလွတ်ထားပါနဲ့။</p>
                        </div>
                    @endif

                    <div data-categories>
                        @foreach($categories as $categoryIndex => $category)
                            @php
                                $isOthers = false;
                                $items = $category['items'] ?? [];

                                if (empty($items)) {
                                    $items = [['name' => '', 'amount' => '']];
                                }
                            @endphp

                            <section
                                class="pbr-dynamic-category {{ $isOthers ? 'pbr-others-category' : '' }}"
                                data-category
                                data-category-index="{{ $categoryIndex }}"
                            >
                                <div class="pbr-category-head">
                                    <div class="pbr-category-name-wrap">
                                        <label>ကုန်ကျစရိတ်အုပ်စု · Category</label>

                                        @if($isOthers)
                                            <input
                                                type="text"
                                                name="categories[{{ $categoryIndex }}][name]"
                                                value="Others"
                                                readonly
                                                data-category-name
                                            >
                                            <small>ဘယ်အုပ်စုထဲထည့်ရမလဲ မသေချာတဲ့ကုန်ကျစရိတ်တွေကို ဒီမှာထားနိုင်ပါတယ်။</small>
                                        @else
                                            <input
                                                type="text"
                                                name="categories[{{ $categoryIndex }}][name]"
                                                value="{{ old("categories.$categoryIndex.name", $category['name'] ?? '') }}"
                                                placeholder="ဥပမာ: ဆိုင်ပြင်ဆင်မှု၊ ပစ္စည်းဝယ်ယူမှု"
                                                maxlength="120"
                                                required
                                                data-category-name
                                            >
                                        @endif
                                    </div>

                                    @unless($isOthers)
                                        <button type="button" class="pbr-remove-category" data-remove-category>
                                            အုပ်စု ဖယ်ရန်
                                        </button>
                                    @endunless
                                </div>

                                <div class="pbr-category-items" data-items>
                                    @foreach($items as $itemIndex => $item)
                                        <div class="pbr-dynamic-item" data-item>
                                            <input
                                                type="text"
                                                name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][name]"
                                                value="{{ old("categories.$categoryIndex.items.$itemIndex.name", $item['name'] ?? '') }}"
                                                placeholder="ကုန်ကျစရိတ်အမည်"
                                                maxlength="150"
                                                data-item-name
                                            >

                                            <div class="pbr-money-input">
                                                <span>{{ $currency }}</span>
                                                <input
                                                    type="number"
                                                    name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][amount]"
                                                    value="{{ old("categories.$categoryIndex.items.$itemIndex.amount", $item['amount'] ?? '') }}"
                                                    min="0"
                                                    max="999999999999.99"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    data-item-amount
                                                >
                                            </div>

                                            <button
                                                type="button"
                                                class="pbr-remove-item"
                                                data-remove-item
                                                aria-label="ဒီကုန်ကျစရိတ်ကို ဖယ်ရန်"
                                                title="ဖယ်ရန်"
                                            >×</button>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="pbr-category-footer">
                                    <button type="button" class="pbr-add-item" data-add-item>
                                        + ကုန်ကျစရိတ်တစ်ခု ထည့်ရန်
                                    </button>

                                    <div>
                                        <span>ဒီအုပ်စု စုစုပေါင်း</span>
                                        <strong data-category-subtotal>{{ $currency }} 0.00</strong>
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <button type="button" class="pbr-add-category" data-add-category>
                        + ကုန်ကျစရိတ်အုပ်စု ထည့်ရန်
                    </button>

                    <div class="pbr-calculator-actions">
                        <button
                            type="submit"
                            class="pbr-save-draft-button"
                            formaction="{{ route('workspaces.tools.startup-capital.draft.store', $workspace) }}"
                            formmethod="POST"
                        >Draft သိမ်းရန်</button>

                        <button type="submit" class="pbr-tools-primary-button">
                            မတည်ငွေ Result စစ်ရန်
                        </button>
                    </div>
                </div>

                <aside class="pbr-calculator-results">
                    <span class="portal-kicker">မတည်ငွေ အနှစ်ချုပ် · Capital Summary</span>

                    <div class="pbr-total-result">
                        <span>စတင်ဖို့လိုအပ်သော မတည်ငွေ စုစုပေါင်း</span>
                        <strong data-live-total>
                            {{ $currency }} {{ number_format($result['total_startup_capital'] ?? 0, 2) }}
                        </strong>
                    </div>

                    <div class="pbr-result-stats">
                        <div>
                            <span>ကုန်ကျစရိတ်အုပ်စု</span>
                            <strong data-live-categories>{{ $result['category_count'] ?? count($categories) }}</strong>
                        </div>
                        <div>
                            <span>Amount ထည့်ပြီးသော Item</span>
                            <strong data-live-items>{{ $result['item_count'] ?? 0 }}</strong>
                        </div>
                    </div>

                    @if($result)
                        @if($result['largest_category'])
                            <div class="pbr-largest-cost">
                                <span>အများဆုံးကုန်ကျသော အုပ်စု</span>
                                <strong>{{ $result['largest_category']['name'] }}</strong>
                                <p>
                                    {{ $currency }} {{ number_format($result['largest_category']['subtotal'], 2) }}
                                    · {{ number_format($result['largest_category']['percentage'], 2) }}%
                                </p>
                            </div>
                        @endif

                        @if($result['largest_item'])
                            <div class="pbr-largest-cost">
                                <span>အများဆုံးကုန်ကျသော Item</span>
                                <strong>{{ $result['largest_item']['name'] }}</strong>
                                <p>
                                    {{ $result['largest_item']['category'] }}
                                    · {{ $currency }} {{ number_format($result['largest_item']['amount'], 2) }}
                                </p>
                            </div>
                        @endif

                        <div class="pbr-breakdown">
                            <h3>ကုန်ကျစရိတ် ခွဲခြမ်းချက် · Breakdown</h3>

                            @forelse($result['categories'] as $category)
                                <div class="pbr-breakdown-row">
                                    <div>
                                        <span>{{ $category['name'] }}</span>
                                        <strong>{{ number_format($category['percentage'], 2) }}%</strong>
                                    </div>

                                    <div class="pbr-breakdown-track">
                                        <i style="width: {{ min(100, $category['percentage']) }}%"></i>
                                    </div>

                                    <small>{{ $currency }} {{ number_format($category['subtotal'], 2) }}</small>
                                </div>
                            @empty
                                <p class="pbr-muted-copy">ကုန်ကျစရိတ် မထည့်ရသေးပါ။</p>
                            @endforelse
                        </div>
                    @else
                        <div class="pbr-empty-result">
                            <strong>ကိုယ့်လုပ်ငန်းရဲ့ စတင်မတည်ငွေ Plan ကို တည်ဆောက်ပါ။</strong>
                            <p>Category နဲ့ Item တွေထည့်ပြီး Amount ဖြည့်တာနဲ့ စုစုပေါင်း Result ကို ဒီဘက်မှာ live မြင်ရပါမယ်။</p>
                        </div>
                    @endif

                    <div class="pbr-tool-next-note">
                        <strong>မသေချာတဲ့ကုန်ကျစရိတ်ရှိရင်</strong>
                        <p>ဘယ် Category ထဲထည့်သင့်မှန်း မသေချာသေးရင် “Others” အုပ်စုတစ်ခုဖန်တီးပြီး ယာယီထည့်ထားနိုင်ပါတယ်။</p>
                    </div>
                </aside>
            </div>
        </form>

        @include('workspaces.tools.partials.scenario-manager')
    </div>
</section>

<script src="/js/startup-capital-planner.js"></script>
@endsection
