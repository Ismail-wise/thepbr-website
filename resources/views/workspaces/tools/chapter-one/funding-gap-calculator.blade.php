<div
    class="pbr-capital-form-experience"
    data-capital-form="funding-gap"
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">FUNDING POSITION</span>

        <h2>လိုအပ်တဲ့ Capital နဲ့ Confirmed Funding ကို တိုက်ရိုက်နှိုင်းယှဉ်ပါ</h2>

        <p>
            Total capital requirement ထဲက partner capital နဲ့ အခြား confirmed funding ကိုနုတ်ပြီး
            ဖြည့်ဆည်းရန်လိုနေတဲ့ gap သို့မဟုတ် available surplus ကိုတွက်ပေးပါတယ်။
        </p>
    </div>

    <div class="pbr-capital-formula-card">
        <span>HOW IT WORKS</span>
        <div>
            <strong>Total Capital Required</strong>
            <b>−</b>
            <strong>Partner Capital</strong>
            <b>−</b>
            <strong>Other Confirmed Funding</strong>
        </div>
        <p>Positive remainder က funding gap ဖြစ်ပြီး negative remainder က funding surplus ဖြစ်ပါတယ်။</p>
    </div>

    <div class="pbr-simple-grid pbr-simple-grid-three">

    <div class="pbr-simple-field">
        <label for="capital_required">
            Total Capital Required
        </label>

        <small>
            Partnership setup နဲ့ operations အတွက် စုစုပေါင်းလိုအပ်တဲ့ amount။
        </small>

        <div class="pbr-money-input">
            <span>
                {{ $workspace->currency_code ?? 'THB' }}
            </span>

            <input
                id="capital_required"
                name="capital_required"
                type="number"
                min="0"
                max="999999999999.99"
                step="0.01"
                inputmode="decimal"
                value="{{ old(
                    'capital_required',
                    $input['capital_required'] ?? ''
                ) }}"
                placeholder="0.00"
            >
        </div>
    </div>


    <div class="pbr-simple-field">
        <label for="partner_capital">
            Partner Capital
        </label>

        <small>
            Partner တွေက အတည်ပြုထားပြီး ထည့်ဝင်မယ့် capital amount။
        </small>

        <div class="pbr-money-input">
            <span>
                {{ $workspace->currency_code ?? 'THB' }}
            </span>

            <input
                id="partner_capital"
                name="partner_capital"
                type="number"
                min="0"
                max="999999999999.99"
                step="0.01"
                inputmode="decimal"
                value="{{ old(
                    'partner_capital',
                    $input['partner_capital'] ?? ''
                ) }}"
                placeholder="0.00"
            >
        </div>
    </div>


    <div class="pbr-simple-field">
        <label for="other_funding">
            Other Funding
        </label>

        <small>
            Loan, investor သို့မဟုတ် တကယ်အတည်ပြုထားတဲ့ အခြား funding။
        </small>

        <div class="pbr-money-input">
            <span>
                {{ $workspace->currency_code ?? 'THB' }}
            </span>

            <input
                id="other_funding"
                name="other_funding"
                type="number"
                min="0"
                max="999999999999.99"
                step="0.01"
                inputmode="decimal"
                value="{{ old(
                    'other_funding',
                    $input['other_funding'] ?? ''
                ) }}"
                placeholder="0.00"
            >
        </div>
    </div>

    </div>

    <div class="pbr-capital-definition-note">
        <strong>Confirmed funding ကိုပဲ ထည့်ပါ</strong>
        <p>
            ဆွေးနွေးနေဆဲ loan သို့မဟုတ် မသေချာသေးတဲ့ investor amount ကို confirmed funding အဖြစ်မထည့်ပါနဲ့။
            Result ကို cash planning နဲ့ funding action အတွက် review လုပ်ပါ။
        </p>
    </div>
</div>
