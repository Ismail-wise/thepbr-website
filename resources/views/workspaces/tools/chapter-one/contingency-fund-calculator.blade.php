<div
    class="pbr-capital-form-experience"
    data-capital-form="contingency-fund"
    data-contingency-tool
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">
            CAPITAL BUFFER
        </span>

        <h2>
            မမျှော်လင့်ထားတဲ့ Business Costs အတွက် Reserve သတ်မှတ်ပါ
        </h2>

        <p>
            Base capital ရဲ့ percentage အဖြစ်ဖြစ်စေ၊ လစဉ် operating cost
            ဘယ်နှလစာအဖြစ်ဖြစ်စေ contingency buffer ကို ရှင်းရှင်းလင်းလင်းတွက်နိုင်ပါတယ်။
        </p>
    </div>

    <div class="pbr-capital-form-guide pbr-capital-form-guide-two">
        <article>
            <span>01</span>
            <div>
                <strong>Method ရွေးပါ</strong>
                <p>Capital percentage သို့မဟုတ် operating months ကိုရွေးပါ။</p>
            </div>
        </article>

        <article>
            <span>02</span>
            <div>
                <strong>Reserve ကို Review လုပ်ပါ</strong>
                <p>Risk level နဲ့ cash availability ကိုကြည့်ပြီး final buffer ကိုဆုံးဖြတ်ပါ။</p>
            </div>
        </article>
    </div>

    <div class="pbr-capital-formula-card">
        <span>TWO CALCULATION OPTIONS</span>
        <div>
            <strong>Base Capital × Reserve %</strong>
            <b>or</b>
            <strong>Monthly Cost × Reserve Months</strong>
        </div>
        <p id="method-help" data-contingency-method-help aria-live="polite">
            Percentage method က base capital ပေါ်မူတည်ပြီး reserve ကိုတွက်ပေးပါတယ်။
        </p>
    </div>

    <div class="pbr-contingency-method">
        <div class="pbr-simple-field">
        <label for="method">
            Calculation Method
        </label>

        <select
            id="method"
            name="method"
            data-contingency-method
            aria-describedby="method-help"
            aria-controls="contingency-percentage-fields contingency-month-fields"
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
            id="contingency-percentage-fields"
            data-percentage-fields
        >
            <div class="pbr-reserve-presets" aria-label="Contingency percentage presets">
                @foreach([5, 10, 15, 20] as $percentage)
                    <button
                        type="button"
                        data-contingency-percentage="{{ $percentage }}"
                        aria-pressed="false"
                    >{{ $percentage }}%</button>
                @endforeach
            </div>

        <div class="pbr-simple-grid">

            <div class="pbr-simple-field">
                <label for="base_capital">
                    Base Capital
                </label>

                <small>Reserve percentage ကို သက်ရောက်စေမယ့် capital amount။</small>

                <div class="pbr-money-input">
                    <span>{{ $workspace->currency_code ?? 'THB' }}</span>

                    <input
                        id="base_capital"
                        name="base_capital"
                        type="number"
                        min="0"
                        max="999999999999.99"
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

                <small>Business risk အတွက် သီးသန့်ထားချင်တဲ့ percentage။</small>

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
            id="contingency-month-fields"
            data-month-fields
        >
            <div class="pbr-reserve-presets" aria-label="Contingency month presets">
                @foreach([1, 2, 3, 6] as $months)
                    <button
                        type="button"
                        data-contingency-months="{{ $months }}"
                        aria-pressed="false"
                    >{{ $months }} mo</button>
                @endforeach
            </div>

        <div class="pbr-simple-grid">

            <div class="pbr-simple-field">
                <label for="monthly_operating_cost">
                    Monthly Operating Cost
                </label>

                <small>လုပ်ငန်းတစ်လ ဆက်လက်လည်ပတ်ဖို့ ပုံမှန်လိုအပ်တဲ့ amount။</small>

                <div class="pbr-money-input">
                    <span>{{ $workspace->currency_code ?? 'THB' }}</span>

                    <input
                        id="monthly_operating_cost"
                        name="monthly_operating_cost"
                        type="number"
                        min="0"
                        max="999999999999.99"
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

                <small>Emergency အတွက် ကြိုတင်ထားချင်တဲ့ operating period။</small>

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

    <div class="pbr-capital-definition-note">
        <strong>PBR က reserve amount ကို အလိုအလျောက် မဆုံးဖြတ်ပါ</strong>

        <p>
            Result က planning reference ဖြစ်ပါတယ်။ Business owner က risk,
            operating needs နဲ့ available cash ကို review လုပ်ပြီး final reserve ကိုဆုံးဖြတ်ရပါတယ်။
        </p>
    </div>
</div>
