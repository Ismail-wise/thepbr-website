<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Capital Buffer
    </span>

    <p>
        Unexpected costs အတွက် reserve fund ကို
        percentage နည်းလမ်း သို့မဟုတ်
        operating-month method နဲ့တွက်နိုင်ပါတယ်။
    </p>
</div>

<div
    class="pbr-contingency-method"
    data-contingency-tool
>
    <div class="pbr-simple-field">
        <label for="method">
            Calculation Method
        </label>

        <select
            id="method"
            name="method"
            data-contingency-method
        >
            <option
                value="percentage"
                @selected(
                    ($input['method'] ?? 'percentage')
                    === 'percentage'
                )
            >
                Percentage of Base Capital
            </option>

            <option
                value="months"
                @selected(
                    ($input['method'] ?? '')
                    === 'months'
                )
            >
                Operating Months
            </option>
        </select>
    </div>


    <div
        class="pbr-contingency-section"
        data-percentage-fields
    >
        <div class="pbr-simple-grid">

            <div class="pbr-simple-field">
                <label for="base_capital">
                    Base Capital
                </label>

                <div class="pbr-money-input">
                    <span>{{ $workspace->currency_code ?? 'THB' }}</span>

                    <input
                        id="base_capital"
                        name="base_capital"
                        type="number"
                        min="0"
                        step="0.01"
                        value="{{ old(
                            'base_capital',
                            $input['base_capital'] ?? ''
                        ) }}"
                        placeholder="0.00"
                    >
                </div>
            </div>

            <div class="pbr-simple-field">
                <label for="percentage">
                    Contingency Percentage
                </label>

                <div class="pbr-number-suffix">
                    <input
                        id="percentage"
                        name="percentage"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value="{{ old(
                            'percentage',
                            $input['percentage'] ?? ''
                        ) }}"
                        placeholder="10"
                    >

                    <span>%</span>
                </div>
            </div>

        </div>
    </div>


    <div
        class="pbr-contingency-section"
        data-month-fields
    >
        <div class="pbr-simple-grid">

            <div class="pbr-simple-field">
                <label for="monthly_operating_cost">
                    Monthly Operating Cost
                </label>

                <div class="pbr-money-input">
                    <span>{{ $workspace->currency_code ?? 'THB' }}</span>

                    <input
                        id="monthly_operating_cost"
                        name="monthly_operating_cost"
                        type="number"
                        min="0"
                        step="0.01"
                        value="{{ old(
                            'monthly_operating_cost',
                            $input['monthly_operating_cost'] ?? ''
                        ) }}"
                        placeholder="0.00"
                    >
                </div>
            </div>

            <div class="pbr-simple-field">
                <label for="months">
                    Reserve Months
                </label>

                <div class="pbr-number-suffix">
                    <input
                        id="months"
                        name="months"
                        type="number"
                        min="0"
                        max="24"
                        step="0.5"
                        value="{{ old(
                            'months',
                            $input['months'] ?? ''
                        ) }}"
                        placeholder="2"
                    >

                    <span>months</span>
                </div>
            </div>

        </div>
    </div>

</div>

<div class="pbr-tool-next-note">
    <strong>
        PBR does not choose the reserve for you.
    </strong>

    <p>
        Business owner က ကိုယ့်လုပ်ငန်းရဲ့
        risk နဲ့ operating needs အရ
        percentage သို့မဟုတ် months ကို
        ကိုယ်တိုင်ဆုံးဖြတ်ပါတယ်။
    </p>
</div>
