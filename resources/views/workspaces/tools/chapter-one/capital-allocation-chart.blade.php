@php
    $allocations = old(
        'allocations',
        $input['allocations'] ?? []
    );

    if (
        ! is_array($allocations)
        || empty($allocations)
    ) {
        $allocations = [
            [
                'name' => '',
                'amount' => '',
            ],
        ];
    }

    $currency = $workspace->currency_code ?? 'THB';
@endphp

<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Capital Overview
    </span>

    <p>
        Capital ကို ဘယ်နေရာတွေမှာ အသုံးပြုမလဲဆိုတာ
        allocation items တွေထည့်ပြီး visual breakdown
        ကြည့်နိုင်ပါတယ်။
    </p>
</div>

<div
    class="pbr-allocation-builder"
    data-allocation-builder
    data-currency="{{ $currency }}"
    data-next-allocation="{{ count($allocations) + 100 }}"
>
    <div data-allocations>

        @foreach(
            $allocations
            as $allocationIndex => $allocation
        )

            <div
                class="pbr-dynamic-item"
                data-allocation
            >
                <input
                    type="text"
                    name="allocations[{{ $allocationIndex }}][name]"
                    value="{{ $allocation['name'] ?? '' }}"
                    placeholder="Capital use"
                    maxlength="150"
                >

                <div class="pbr-money-input">
                    <span>{{ $currency }}</span>

                    <input
                        type="number"
                        name="allocations[{{ $allocationIndex }}][amount]"
                        value="{{ $allocation['amount'] ?? '' }}"
                        min="0"
                        max="999999999999.99"
                        step="0.01"
                        placeholder="0.00"
                        data-allocation-amount
                    >
                </div>

                <button
                    type="button"
                    class="pbr-remove-item"
                    data-remove-allocation
                >
                    ×
                </button>
            </div>

        @endforeach

    </div>

    <button
        type="button"
        class="pbr-add-category"
        data-add-allocation
    >
        + Add Capital Use
    </button>
</div>
