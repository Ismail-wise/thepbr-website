<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Capital Requirement
    </span>

    <p>
        လုပ်ငန်းအတွက်လိုအပ်တဲ့ Total Capital နဲ့
        ရရှိထားတဲ့ Partner Capital / Other Funding ကို
        နှိုင်းယှဉ်ပြီး Gap သို့မဟုတ် Surplus ကိုတွက်ပါ။
    </p>
</div>

<div class="pbr-simple-grid">

    <div class="pbr-simple-field">
        <label for="capital_required">
            Total Capital Required
        </label>

        <small>
            Total amount the partnership needs.
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
                step="0.01"
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
            Capital committed by the partners.
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
                step="0.01"
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
            Loans, investors or other confirmed funding.
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
                step="0.01"
                value="{{ old(
                    'other_funding',
                    $input['other_funding'] ?? ''
                ) }}"
                placeholder="0.00"
            >
        </div>
    </div>

</div>
