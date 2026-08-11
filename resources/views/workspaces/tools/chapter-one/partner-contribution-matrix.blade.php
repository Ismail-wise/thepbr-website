@php
    $partners = old(
        'partners',
        $input['partners'] ?? []
    );

    if (! is_array($partners) || empty($partners)) {
        $partners = [
            [
                'name' => '',
                'contributions' => [
                    [
                        'name' => '',
                        'amount' => '',
                    ],
                ],
            ],
        ];
    }

    $currency = $workspace->currency_code ?? 'THB';
@endphp

<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Partner Capital
    </span>

    <p>
        Partner တစ်ယောက်ချင်းစီက ထည့်ဝင်မယ့်
        Cash, Equipment, Inventory သို့မဟုတ်
        အခြား contribution တွေကို ကိုယ်တိုင်ထည့်နိုင်ပါတယ်။
    </p>
</div>

<div
    data-partner-builder
    data-currency="{{ $currency }}"
    data-next-partner="{{ count($partners) + 100 }}"
>
    <div data-partners>

        @foreach($partners as $partnerIndex => $partner)

            @php
                $contributions =
                    $partner['contributions'] ?? [];

                if (
                    ! is_array($contributions)
                    || empty($contributions)
                ) {
                    $contributions = [
                        [
                            'name' => '',
                            'amount' => '',
                        ],
                    ];
                }
            @endphp

            <section
                class="pbr-partner-card"
                data-partner
                data-partner-index="{{ $partnerIndex }}"
            >
                <div class="pbr-category-head">

                    <div class="pbr-category-name-wrap">
                        <label>
                            Partner Name
                        </label>

                        <input
                            type="text"
                            name="partners[{{ $partnerIndex }}][name]"
                            value="{{ $partner['name'] ?? '' }}"
                            placeholder="Partner name"
                            maxlength="120"
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-category"
                        data-remove-partner
                    >
                        Remove Partner
                    </button>

                </div>

                <div data-contributions>

                    @foreach(
                        $contributions
                        as $contributionIndex => $contribution
                    )

                        <div
                            class="pbr-dynamic-item"
                            data-contribution
                        >
                            <input
                                type="text"
                                name="partners[{{ $partnerIndex }}][contributions][{{ $contributionIndex }}][name]"
                                value="{{ $contribution['name'] ?? '' }}"
                                placeholder="Contribution name"
                                maxlength="150"
                            >

                            <div class="pbr-money-input">
                                <span>{{ $currency }}</span>

                                <input
                                    type="number"
                                    name="partners[{{ $partnerIndex }}][contributions][{{ $contributionIndex }}][amount]"
                                    value="{{ $contribution['amount'] ?? '' }}"
                                    min="0"
                                    max="999999999999.99"
                                    step="0.01"
                                    placeholder="0.00"
                                    data-contribution-amount
                                >
                            </div>

                            <button
                                type="button"
                                class="pbr-remove-item"
                                data-remove-contribution
                            >
                                ×
                            </button>
                        </div>

                    @endforeach

                </div>

                <div class="pbr-category-footer">

                    <button
                        type="button"
                        class="pbr-add-item"
                        data-add-contribution
                    >
                        + Add Contribution
                    </button>

                    <div>
                        <span>
                            Partner Total
                        </span>

                        <strong data-partner-total>
                            {{ $currency }} 0.00
                        </strong>
                    </div>

                </div>
            </section>

        @endforeach

    </div>

    <button
        type="button"
        class="pbr-add-category"
        data-add-partner
    >
        + Add Partner
    </button>
</div>

<div class="pbr-tool-next-note">
    <strong>
        Contribution Share ≠ Ownership Share
    </strong>

    <p>
        ဒီ tool က ဘယ် Partner က Capital ဘယ်လောက်
        ထည့်ဝင်ထားလဲကိုပဲ ပြပါတယ်။
        Ownership Percentage ကို Chapter 2 မှာ
        သီးခြားဆုံးဖြတ်ပါမယ်။
    </p>
</div>
