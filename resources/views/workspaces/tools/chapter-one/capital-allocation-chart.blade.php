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

<div
    class="pbr-capital-form-experience pbr-allocation-builder"
    data-capital-form="capital-allocation"
    data-allocation-builder
    data-currency="{{ $currency }}"
    data-next-allocation="{{ count($allocations) + 100 }}"
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">CAPITAL ALLOCATION</span>

        <h2>Available Capital ကို ဘယ်နေရာမှာ အသုံးပြုမလဲ စီစဉ်ပါ</h2>

        <p>
            Capital uses တွေကို သီးခြားထည့်ပြီး total allocation၊ percentage breakdown
            နဲ့ concentration ကို review လုပ်နိုင်ပါတယ်။
        </p>
    </div>

    <div class="pbr-capital-form-guide">
        <article>
            <span>01</span>
            <div>
                <strong>Capital Uses ထည့်ပါ</strong>
                <p>Operations, inventory, equipment စတဲ့ use တစ်ခုချင်းခွဲပါ။</p>
            </div>
        </article>

        <article>
            <span>02</span>
            <div>
                <strong>Amounts သတ်မှတ်ပါ</strong>
                <p>အသုံးပြုမယ့် amount ကို use တစ်ခုချင်းစီအတွက် ထည့်ပါ။</p>
            </div>
        </article>

        <article>
            <span>03</span>
            <div>
                <strong>Balance ကို Review လုပ်ပါ</strong>
                <p>Allocation တစ်ခုတည်းမှာ capital များလွန်းနေသလား စစ်ပါ။</p>
            </div>
        </article>
    </div>

    <div class="pbr-quick-category-presets" aria-label="Quick add capital uses">
        <span>Quick Add</span>
        <div>
            @foreach([
                'Operations',
                'Inventory & Stock',
                'Equipment & Tools',
                'Marketing',
                'Contingency Reserve',
            ] as $capitalUse)
                <button
                    type="button"
                    data-add-allocation-preset="{{ $capitalUse }}"
                    aria-pressed="false"
                >+ {{ $capitalUse }}</button>
            @endforeach
        </div>
    </div>

    <div
        class="pbr-builder-live-summary"
        data-allocation-builder-summary
        role="status"
        aria-live="polite"
    >
        <div>
            <span>Capital Uses</span>
            <strong data-allocation-count>{{ count($allocations) }}</strong>
        </div>
        <div>
            <span>Current Total</span>
            <strong data-allocation-total>{{ $currency }} 0.00</strong>
        </div>
    </div>

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
                    aria-label="Capital use {{ $allocationIndex + 1 }} name"
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
                        aria-label="Capital use {{ $allocationIndex + 1 }} amount"
                    >
                </div>

                <button
                    type="button"
                    class="pbr-remove-item"
                    data-remove-allocation
                    aria-label="Remove capital use {{ $allocationIndex + 1 }}"
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

    <div class="pbr-capital-definition-note">
        <strong>Allocation total က funding secured နဲ့ တူမတူ စစ်ပါ</strong>
        <p>
            ဒီ chart က capital ကို ဘယ်လိုအသုံးပြုမယ်ဆိုတာပြပါတယ်။ Funding source ကို မပြသလို
            approval မရသေးတဲ့ spending authority လည်း မဖြစ်ပါဘူး။
        </p>
    </div>
</div>
