<div
    class="pbr-capital-form-experience"
    data-capital-form="working-capital"
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">
            OPERATING CAPITAL
        </span>

        <h2>
            လုပ်ငန်းဆက်လက်လည်ပတ်ဖို့ လိုမယ့် Working Capital ကိုတွက်ပါ
        </h2>

        <p>
            လစဉ် operating costs, reserve period,
            inventory, payables နဲ့ receivables တွေကို
            တစ်နေရာတည်းမှာ ထည့်ပြီး
            လိုအပ်မယ့် Working Capital ကိုသတ်မှတ်နိုင်ပါတယ်။
        </p>
    </div>

    <div class="pbr-capital-formula-card">
        <span>HOW IT WORKS</span>

        <div>
            <strong>Monthly Costs × Reserve Months</strong>
            <b>+</b>
            <strong>Inventory</strong>
            <b>+</b>
            <strong>Payables</strong>
            <b>−</b>
            <strong>Receivables</strong>
        </div>

        <p>
            Result က business လည်ပတ်မှုကို ဆက်ထိန်းထားဖို့
            planning amount ဖြစ်ပါတယ်။
        </p>
    </div>

    @include(
        'workspaces.tools.chapter-one.partials.category-builder',
        [
            'field' => 'monthly_costs',
            'title' => 'လစဉ် Operating Costs',
            'help' =>
                'Payroll, rent, utilities, marketing, logistics နဲ့ recurring operating costs တွေကို ထည့်ပါ။',
            'categories' => $input['monthly_costs'] ?? [],
            'quickCategories' => [
                'Payroll',
                'Rent & Premises',
                'Utilities',
                'Marketing',
                'Transport & Logistics',
            ],
        ]
    )

    <div class="pbr-tool-divider"></div>

    <section class="pbr-working-capital-variables">
        <div class="pbr-section-heading">
            <span>OPERATING VARIABLES</span>
            <h3>Monthly costs အပြင် ထည့်စဉ်းစားရမယ့် Amounts</h3>
            <p>
                မရှိတဲ့ item ကို 0 သို့မဟုတ် blank ထားနိုင်ပါတယ်။
            </p>
        </div>

        <div class="pbr-simple-grid">
            <div class="pbr-simple-field pbr-reserve-field">
                <label for="reserve_months">
                    Reserve Period
                </label>

                <small>
                    Operating costs ဘယ်နှလစာ
                    cash buffer ထားချင်ပါသလဲ?
                </small>

                <div class="pbr-reserve-presets">
                    @foreach([1, 3, 6, 12] as $months)
                        <button
                            type="button"
                            data-reserve-months="{{ $months }}"
                        >
                            {{ $months }} mo
                        </button>
                    @endforeach
                </div>

                <div class="pbr-number-suffix">
                    <input
                        id="reserve_months"
                        name="reserve_months"
                        type="number"
                        min="0"
                        max="24"
                        step="0.5"
                        value="{{ old(
                            'reserve_months',
                            $input['reserve_months'] ?? ''
                        ) }}"
                        placeholder="3"
                    >

                    <span>months</span>
                </div>
            </div>

            <div class="pbr-simple-field">
                <label for="inventory_requirement">
                    Additional Inventory
                </label>

                <small>
                    Operations ဆက်လုပ်ဖို့
                    ထပ်ဝယ်ရမယ့် stock / inventory။
                </small>

                <div class="pbr-money-input">
                    <span>
                        {{ $workspace->currency_code ?? 'THB' }}
                    </span>

                    <input
                        id="inventory_requirement"
                        name="inventory_requirement"
                        type="number"
                        min="0"
                        max="999999999999.99"
                        step="0.01"
                        value="{{ old(
                            'inventory_requirement',
                            $input['inventory_requirement'] ?? ''
                        ) }}"
                        placeholder="0.00"
                    >
                </div>
            </div>

            <div class="pbr-simple-field">
                <label for="short_term_payables">
                    Short-term Payables
                </label>

                <small>
                    မကြာခင် ပေးချေရမယ့်
                    supplier / short-term obligations။
                </small>

                <div class="pbr-money-input">
                    <span>
                        {{ $workspace->currency_code ?? 'THB' }}
                    </span>

                    <input
                        id="short_term_payables"
                        name="short_term_payables"
                        type="number"
                        min="0"
                        max="999999999999.99"
                        step="0.01"
                        value="{{ old(
                            'short_term_payables',
                            $input['short_term_payables'] ?? ''
                        ) }}"
                        placeholder="0.00"
                    >
                </div>
            </div>

            <div class="pbr-simple-field">
                <label for="expected_receivables">
                    Expected Receivables
                </label>

                <small>
                    မကြာခင် လက်ခံရရှိဖို့
                    reasonable expectation ရှိတဲ့ money။
                </small>

                <div class="pbr-money-input">
                    <span>
                        {{ $workspace->currency_code ?? 'THB' }}
                    </span>

                    <input
                        id="expected_receivables"
                        name="expected_receivables"
                        type="number"
                        min="0"
                        max="999999999999.99"
                        step="0.01"
                        value="{{ old(
                            'expected_receivables',
                            $input['expected_receivables'] ?? ''
                        ) }}"
                        placeholder="0.00"
                    >
                </div>
            </div>
        </div>
    </section>
</div>
