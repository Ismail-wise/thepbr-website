<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Operating Capital
    </span>

    <p>
        လစဉ်လုပ်ငန်းလည်ပတ်ဖို့လိုတဲ့ costs တွေကို
        ကိုယ်တိုင်ထည့်ပြီး ဘယ်နှလစာ reserve
        ထားချင်လဲဆိုတာ သတ်မှတ်နိုင်ပါတယ်။
    </p>
</div>

@include(
    'workspaces.tools.chapter-one.partials.category-builder',
    [
        'field' => 'monthly_costs',
        'title' => 'Monthly Operating Costs',
        'help' => 'Create your own monthly cost categories and items.',
        'categories' => $input['monthly_costs'] ?? [],
    ]
)

<div class="pbr-tool-divider"></div>

<div class="pbr-simple-grid">

    <div class="pbr-simple-field">
        <label for="reserve_months">
            Reserve Period
        </label>

        <small>
            How many months of operating costs should be reserved?
        </small>

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
            Additional Inventory Requirement
        </label>

        <small>
            Extra inventory or stock needed for operations.
        </small>

        <div class="pbr-money-input">
            <span>{{ $workspace->currency_code ?? 'THB' }}</span>

            <input
                id="inventory_requirement"
                name="inventory_requirement"
                type="number"
                min="0"
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
            Amount the business needs to cover soon.
        </small>

        <div class="pbr-money-input">
            <span>{{ $workspace->currency_code ?? 'THB' }}</span>

            <input
                id="short_term_payables"
                name="short_term_payables"
                type="number"
                min="0"
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
            Money expected to come into the business.
        </small>

        <div class="pbr-money-input">
            <span>{{ $workspace->currency_code ?? 'THB' }}</span>

            <input
                id="expected_receivables"
                name="expected_receivables"
                type="number"
                min="0"
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
