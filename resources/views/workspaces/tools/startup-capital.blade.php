@extends('layouts.student-portal')

@section('title', 'စတင်မတည်ငွေ အစီအစဉ်')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $total = (float) ($result['total_startup_capital'] ?? 0);
    $essential = (float) ($result['essential_total'] ?? 0);
    $optional = (float) ($result['optional_total'] ?? 0);
    $funded = (float) ($result['funded_total'] ?? 0);
    $gap = (float) ($result['funding_gap'] ?? max(0, $total - $funded));
    $due30 = (float) ($result['due_30_days_outstanding'] ?? 0);
    $monthlyRecurring = (float) ($result['monthly_recurring_cost'] ?? 0);
    $fundedPercent = (float) ($result['funded_percentage'] ?? ($total > 0 ? min(100, ($funded / $total) * 100) : 0));
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
                    စတင်မယ့်လုပ်ငန်းအတွက် <strong>ဘာတွေကုန်မလဲ၊ ဘာတွေမဖြစ်မနေလိုမလဲ၊ Funding ဘယ်လောက်ရထားပြီလဲ၊ ဘယ်နေ့မတိုင်ခင် ငွေလိုမလဲ</strong>
                    ဆိုတာ တစ်နေရာတည်းမှာ စီမံပါ။
                </p>
                <div class="pbr-capital-plan-chips">
                    <span>Partnership အသစ်</span>
                    <span>{{ $currency }}</span>
                    <span>{{ $activeSession ? 'Draft ကို ပြင်နေသည်' : 'Plan အသစ်' }}</span>
                </div>
            </div>

            <div class="pbr-capital-business-card">
                <small>လက်ရှိ Business</small>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <span>Startup planning workspace</span>
                @if($latestAgreedOutput)
                    <b>✓ Active Rule · Rev {{ $latestAgreedOutput->revision }}</b>
                @endif
            </div>
        </header>

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

            @if($errors->any())
                <div class="pbr-form-errors pbr-capital-errors">
                    <strong>အချက်အလက်တချို့ကို ပြန်စစ်ပေးပါ။</strong>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <section class="pbr-capital-plan-toolbar">
                <div class="pbr-capital-draft-name">
                    <label for="scenario_name">Plan အမည်</label>
                    <input
                        id="scenario_name"
                        name="scenario_name"
                        type="text"
                        maxlength="120"
                        placeholder="ဥပမာ: Base Launch Plan 2026"
                        value="{{ old('scenario_name', $activeSession?->scenario_name ?? request('scenario_name', '')) }}"
                    >
                    <small>Draft သိမ်းချင်မှ အမည်လိုပါတယ်။ Result စစ်ဖို့တော့ မလိုပါဘူး။</small>
                </div>

                <div class="pbr-capital-toolbar-actions">
                    @if($activeSession)
                        <span class="pbr-capital-saved-state">
                            <i></i>
                            {{ $activeSession->scenario_name }}
                        </span>
                    @endif
                    <button
                        type="submit"
                        class="pbr-save-draft-button"
                        formaction="{{ route('workspaces.tools.startup-capital.draft.store', $workspace) }}"
                        formmethod="POST"
                    >Draft သိမ်းရန်</button>
                    <button type="submit" class="pbr-tools-primary-button">Plan Result စစ်ရန်</button>
                </div>
            </section>

            <div class="pbr-capital-workspace-grid">
                <main class="pbr-capital-planner">
                    <section class="pbr-capital-quick-start">
                        <div>
                            <span class="portal-kicker">QUICK START</span>
                            <h2>အသုံးများတဲ့ ကုန်ကျစရိတ်အုပ်စုကို တစ်ချက်နဲ့ထည့်ပါ</h2>
                            <p>လိုတာကိုပဲရွေးပါ။ နောက်မှ ကိုယ်ပိုင်အုပ်စုနဲ့ Item တွေထပ်ထည့်နိုင်ပါတယ်။</p>
                        </div>
                        <div class="pbr-capital-template-chips" aria-label="Quick add cost categories">
                            @foreach([
                                'ဆိုင် / နေရာ' => 'Premises',
                                'စက်ပစ္စည်း' => 'Equipment',
                                'လိုင်စင် / Legal' => 'Licenses & Legal',
                                'အစပိုင်း Stock' => 'Initial Inventory',
                                'Marketing' => 'Marketing & Launch',
                                'Technology' => 'Technology',
                                'ဝန်ထမ်း / Training' => 'Staffing & Training',
                                'လည်ပတ်ငွေ Buffer' => 'Working Cash Buffer',
                            ] as $mm => $en)
                                <button type="button" data-add-template="{{ $mm }}" title="{{ $en }}">+ {{ $mm }}</button>
                            @endforeach
                        </div>
                    </section>

                    <section class="pbr-capital-costs-section">
                        <div class="pbr-capital-section-heading">
                            <div>
                                <span class="portal-kicker">COST PLAN</span>
                                <h2>ကုန်ကျစရိတ် Plan</h2>
                                <p>Amount တင်မကဘဲ Funding နဲ့ Due Date ပါထည့်ထားရင် PBR က shortfall ကိုပိုကောင်းကောင်းပြနိုင်ပါတယ်။</p>
                            </div>
                            <button type="button" class="pbr-capital-add-category" data-add-category>+ ကိုယ်ပိုင်အုပ်စု</button>
                        </div>

                        <div class="pbr-capital-empty {{ count($categories) > 0 ? 'is-hidden' : '' }}" data-capital-empty>
                            <div>＋</div>
                            <strong>ကုန်ကျစရိတ် မထည့်ရသေးပါ</strong>
                            <p>အပေါ်က Quick Start ကိုရွေးပါ၊ ဒါမှမဟုတ် ကိုယ်ပိုင်အုပ်စုတစ်ခုထည့်ပါ။</p>
                        </div>

                        <div class="pbr-capital-category-list" data-categories>
                            @foreach($categories as $categoryIndex => $category)
                                @php
                                    $items = $category['items'] ?? [];
                                    if (empty($items)) {
                                        $items = [['name' => '', 'amount' => '']];
                                    }
                                @endphp

                                <section class="pbr-capital-category" data-category data-category-index="{{ $categoryIndex }}">
                                    <header class="pbr-capital-category-head">
                                        <div class="pbr-capital-category-name">
                                            <span>ကုန်ကျစရိတ်အုပ်စု</span>
                                            <input
                                                type="text"
                                                name="categories[{{ $categoryIndex }}][name]"
                                                value="{{ old("categories.$categoryIndex.name", $category['name'] ?? '') }}"
                                                placeholder="ဥပမာ: ဆိုင်ပြင်ဆင်မှု"
                                                maxlength="120"
                                                required
                                                data-category-name
                                            >
                                        </div>
                                        <div class="pbr-capital-category-meta">
                                            <div>
                                                <small>Planned</small>
                                                <strong data-category-subtotal>{{ $currency }} 0.00</strong>
                                            </div>
                                            <div>
                                                <small>Funded</small>
                                                <strong data-category-funded>{{ $currency }} 0.00</strong>
                                            </div>
                                            <button type="button" data-remove-category aria-label="ဒီအုပ်စုကို ဖယ်ရန်">×</button>
                                        </div>
                                    </header>

                                    <div class="pbr-capital-items" data-items>
                                        @foreach($items as $itemIndex => $item)
                                            @php
                                                $priority = old("categories.$categoryIndex.items.$itemIndex.priority", $item['priority'] ?? 'essential');
                                                $frequency = old("categories.$categoryIndex.items.$itemIndex.frequency", $item['frequency'] ?? 'one_time');
                                                $reserveMonths = old("categories.$categoryIndex.items.$itemIndex.reserve_months", $item['reserve_months'] ?? 3);
                                            @endphp
                                            <article class="pbr-capital-item" data-item>
                                                <div class="pbr-capital-item-main">
                                                    <div class="pbr-capital-field pbr-capital-item-name">
                                                        <label>ကုန်ကျစရိတ်</label>
                                                        <input
                                                            type="text"
                                                            name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][name]"
                                                            value="{{ old("categories.$categoryIndex.items.$itemIndex.name", $item['name'] ?? '') }}"
                                                            placeholder="ဥပမာ: ဆိုင် Deposit"
                                                            maxlength="150"
                                                            data-item-name
                                                        >
                                                    </div>

                                                    <div class="pbr-capital-field pbr-capital-amount-field">
                                                        <label>Amount</label>
                                                        <div class="pbr-capital-money-input">
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
                                                    </div>

                                                    <div class="pbr-capital-field pbr-capital-priority-field">
                                                        <label>အရေးပါမှု</label>
                                                        <select name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][priority]" data-item-priority>
                                                            <option value="essential" @selected($priority === 'essential')>မဖြစ်မနေလို</option>
                                                            <option value="optional" @selected($priority === 'optional')>Optional</option>
                                                        </select>
                                                    </div>

                                                    <button type="button" class="pbr-capital-remove-item" data-remove-item aria-label="ဒီကုန်ကျစရိတ်ကို ဖယ်ရန်">×</button>
                                                </div>

                                                <div class="pbr-capital-item-live-line">
                                                    <span data-item-plan-label>Plan ထဲတွင် {{ $currency }} 0.00</span>
                                                    <span data-item-funding-label>Funding မထည့်ရသေး</span>
                                                </div>

                                                <details class="pbr-capital-item-details">
                                                    <summary>Funding, timing နဲ့ အသေးစိတ် ထည့်ရန် <span>＋</span></summary>
                                                    <div class="pbr-capital-detail-grid">
                                                        <div class="pbr-capital-field">
                                                            <label>ကုန်ကျစရိတ်ပုံစံ</label>
                                                            <select name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][frequency]" data-item-frequency>
                                                                <option value="one_time" @selected($frequency === 'one_time')>တစ်ကြိမ်တည်း</option>
                                                                <option value="monthly" @selected($frequency === 'monthly')>လစဉ်</option>
                                                            </select>
                                                        </div>

                                                        <div class="pbr-capital-field pbr-capital-months-field" data-months-field @if($frequency !== 'monthly') hidden @endif>
                                                            <label>ဘယ်နှလ အရန်ထားမလဲ</label>
                                                            <input
                                                                type="number"
                                                                name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][reserve_months]"
                                                                value="{{ $reserveMonths }}"
                                                                min="1"
                                                                max="24"
                                                                step="1"
                                                                data-item-months
                                                            >
                                                        </div>

                                                        <div class="pbr-capital-field">
                                                            <label>ရရှိထားပြီး Funding</label>
                                                            <div class="pbr-capital-money-input">
                                                                <span>{{ $currency }}</span>
                                                                <input
                                                                    type="number"
                                                                    name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][funded_amount]"
                                                                    value="{{ old("categories.$categoryIndex.items.$itemIndex.funded_amount", $item['funded_amount'] ?? '') }}"
                                                                    min="0"
                                                                    max="999999999999.99"
                                                                    step="0.01"
                                                                    placeholder="0.00"
                                                                    data-item-funded
                                                                >
                                                            </div>
                                                        </div>

                                                        <div class="pbr-capital-field">
                                                            <label>Funding Source</label>
                                                            <input
                                                                type="text"
                                                                name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][funding_source]"
                                                                value="{{ old("categories.$categoryIndex.items.$itemIndex.funding_source", $item['funding_source'] ?? '') }}"
                                                                maxlength="150"
                                                                placeholder="ဥပမာ: Partner A, Bank Loan"
                                                            >
                                                        </div>

                                                        <div class="pbr-capital-field">
                                                            <label>လိုအပ်မည့်ရက် · Due Date</label>
                                                            <input
                                                                type="date"
                                                                name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][due_date]"
                                                                value="{{ old("categories.$categoryIndex.items.$itemIndex.due_date", $item['due_date'] ?? '') }}"
                                                                data-item-due
                                                            >
                                                        </div>

                                                        <div class="pbr-capital-field pbr-capital-note-field">
                                                            <label>မှတ်ချက်</label>
                                                            <input
                                                                type="text"
                                                                name="categories[{{ $categoryIndex }}][items][{{ $itemIndex }}][note]"
                                                                value="{{ old("categories.$categoryIndex.items.$itemIndex.note", $item['note'] ?? '') }}"
                                                                maxlength="500"
                                                                placeholder="လိုအပ်ရင် မှတ်ချက်တိုတိုထည့်ပါ"
                                                            >
                                                        </div>
                                                    </div>
                                                </details>
                                            </article>
                                        @endforeach
                                    </div>

                                    <footer class="pbr-capital-category-footer">
                                        <button type="button" data-add-item>+ ကုန်ကျစရိတ်ထည့်ရန်</button>
                                        <span>Item <b data-category-item-count>{{ count($items) }}</b> ခု</span>
                                    </footer>
                                </section>
                            @endforeach
                        </div>
                    </section>
                </main>

                <aside class="pbr-capital-summary-column">
                    <section class="pbr-capital-summary-card">
                        <div class="pbr-capital-summary-head">
                            <div>
                                <span class="portal-kicker">LIVE CAPITAL POSITION</span>
                                <h2>Plan အနှစ်ချုပ်</h2>
                            </div>
                            <span class="pbr-capital-draft-badge">Draft</span>
                        </div>

                        <div class="pbr-capital-total">
                            <span>စုစုပေါင်း Startup Capital</span>
                            <strong data-live-total>{{ $currency }} {{ number_format($total, 2) }}</strong>
                            <div class="pbr-capital-funding-progress" aria-label="Funding progress">
                                <i data-funding-progress style="width: {{ min(100, $fundedPercent) }}%"></i>
                            </div>
                            <small><b data-live-funded-percent>{{ number_format($fundedPercent, 0) }}%</b> Funding ရရှိထားသည်</small>
                        </div>

                        <div class="pbr-capital-metric-grid">
                            <div><span>မဖြစ်မနေလို</span><strong data-live-essential>{{ $currency }} {{ number_format($essential, 2) }}</strong></div>
                            <div><span>Optional</span><strong data-live-optional>{{ $currency }} {{ number_format($optional, 2) }}</strong></div>
                            <div class="funded"><span>Funding ရပြီး</span><strong data-live-funded>{{ $currency }} {{ number_format($funded, 2) }}</strong></div>
                            <div class="gap"><span>လိုနေသေး</span><strong data-live-gap>{{ $currency }} {{ number_format($gap, 2) }}</strong></div>
                            <div><span>30 ရက်အတွင်းလို</span><strong data-live-due30>{{ $currency }} {{ number_format($due30, 2) }}</strong></div>
                            <div><span>လစဉ် Commitment</span><strong data-live-monthly>{{ $currency }} {{ number_format($monthlyRecurring, 2) }}</strong></div>
                        </div>

                        <div class="pbr-capital-live-alert {{ $gap > 0 ? 'warning' : ($total > 0 ? 'healthy' : 'neutral') }}" data-live-alert>
                            @if(($result['overdue_outstanding'] ?? 0) > 0)
                                <strong>Due Date ကျော်နေသော Funding လိုအပ်ချက်ရှိသည်</strong>
                                <p>{{ $currency }} {{ number_format((float) $result['overdue_outstanding'], 2) }} ကို အမြန်ပြန်စစ်ပါ။</p>
                            @elseif($due30 > 0)
                                <strong>နောက် 30 ရက်အတွင်း Funding လိုမည်</strong>
                                <p>{{ $currency }} {{ number_format($due30, 2) }} outstanding ရှိနေပါတယ်။</p>
                            @elseif($gap > 0)
                                <strong>Funding Gap ရှိနေသည်</strong>
                                <p>{{ $currency }} {{ number_format($gap, 2) }} ထပ်မံရှာဖွေ/သတ်မှတ်ရန် လိုပါတယ်။</p>
                            @elseif($total > 0)
                                <strong>Plan Funding ပြည့်စုံနေသည်</strong>
                                <p>လက်ရှိထည့်ထားတဲ့ Plan အရ Funding Gap မရှိပါ။</p>
                            @else
                                <strong>Plan စတင်တည်ဆောက်ပါ</strong>
                                <p>ကုန်ကျစရိတ်ထည့်တာနဲ့ ဒီနေရာမှာ Funding Position ကို live မြင်ရပါမယ်။</p>
                            @endif
                        </div>

                        <div class="pbr-capital-summary-actions">
                            <button
                                type="submit"
                                class="pbr-save-draft-button"
                                formaction="{{ route('workspaces.tools.startup-capital.draft.store', $workspace) }}"
                                formmethod="POST"
                            >Draft သိမ်းရန်</button>
                            <button type="submit" class="pbr-tools-primary-button">Result ပြန်စစ်ရန်</button>
                        </div>
                    </section>

                    @if($scenarioComparisons->isNotEmpty())
                        <section class="pbr-capital-comparison-card">
                            <div class="pbr-capital-comparison-head">
                                <div>
                                    <span class="portal-kicker">PLAN COMPARISON</span>
                                    <h3>သိမ်းထားသော Plan များ</h3>
                                </div>
                                <span>{{ $scenarioComparisons->count() }} Draft</span>
                            </div>

                            <div class="pbr-capital-comparison-list">
                                @foreach($scenarioComparisons->take(4) as $plan)
                                    <a
                                        href="{{ route('workspaces.tools.startup-capital.show', ['workspace' => $workspace, 'session' => $plan['id']]) }}"
                                        class="{{ $plan['is_active'] ? 'active' : '' }}"
                                    >
                                        <div>
                                            <strong>{{ $plan['name'] }}</strong>
                                            <small>{{ $plan['updated_at']?->diffForHumans() }}</small>
                                        </div>
                                        <div>
                                            <span>{{ $currency }} {{ number_format($plan['total'], 0) }}</span>
                                            <b class="{{ $plan['gap'] > 0 ? 'warning' : 'ready' }}">
                                                {{ $plan['gap'] > 0 ? 'Gap '.$currency.' '.number_format($plan['gap'], 0) : 'Funding Ready' }}
                                            </b>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="pbr-capital-help-card">
                        <span class="portal-kicker">HOW TO USE</span>
                        <h3>အရင်ဆုံး ဘာထည့်သင့်လဲ?</h3>
                        <ol>
                            <li>မဖြစ်မနေလိုတဲ့ startup costs ကိုအရင်ထည့်ပါ။</li>
                            <li>Funding ရပြီးသားငွေကို item တစ်ခုချင်းစီမှာဖြည့်ပါ။</li>
                            <li>Due Date ရှိတာတွေကို ရက်စွဲထည့်ပါ။</li>
                            <li>Plan ကို Draft သိမ်းပြီး နောက် Plan နဲ့နှိုင်းယှဉ်ပါ။</li>
                        </ol>
                    </section>
                </aside>
            </div>
        </form>

        <div id="saved-plans" class="pbr-capital-saved-plans">
            @include('workspaces.tools.partials.scenario-manager')
        </div>
    </div>
</section>

<script src="/js/startup-capital-planner.js?v={{ filemtime(public_path('js/startup-capital-planner.js')) }}"></script>
@endsection
