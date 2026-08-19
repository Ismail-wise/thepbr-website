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

<div
    class="pbr-capital-form-experience"
    data-capital-form="partner-contributions"
    data-partner-builder
    data-currency="{{ $currency }}"
    data-next-partner="{{ count($partners) + 100 }}"
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">PARTNER CAPITAL</span>

        <h2>Partner တစ်ယောက်ချင်းစီရဲ့ Capital Contribution ကို စုစည်းပါ</h2>

        <p>
            Cash, equipment, inventory နဲ့ အခြား measurable contributions တွေကို
            Partner အလိုက်ထည့်ပြီး total contribution နဲ့ relative share ကိုကြည့်နိုင်ပါတယ်။
        </p>
    </div>

    <div class="pbr-capital-form-guide">
        <article>
            <span>01</span>
            <div>
                <strong>Partner ထည့်ပါ</strong>
                <p>Business roster ထဲက တစ်ဦးချင်းနာမည်ကို အသုံးပြုပါ။</p>
            </div>
        </article>

        <article>
            <span>02</span>
            <div>
                <strong>Contribution ခွဲထည့်ပါ</strong>
                <p>Cash, equipment နဲ့ inventory ကို သီးခြား rows အဖြစ်ထားပါ။</p>
            </div>
        </article>

        <article>
            <span>03</span>
            <div>
                <strong>Total ကို Review လုပ်ပါ</strong>
                <p>Contribution share ကို ownership share နဲ့ မရောပါနဲ့။</p>
            </div>
        </article>
    </div>

    <div
        class="pbr-builder-live-summary"
        data-partner-builder-summary
        role="status"
        aria-live="polite"
    >
        <div>
            <span>Partners</span>
            <strong data-partner-count>{{ count($partners) }}</strong>
        </div>
        <div>
            <span>Current Total</span>
            <strong data-partner-grand-total>{{ $currency }} 0.00</strong>
        </div>
    </div>

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
                            aria-label="Partner {{ $partnerIndex + 1 }} name"
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-category"
                        data-remove-partner
                        aria-label="Remove partner {{ $partnerIndex + 1 }}"
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
                                aria-label="Contribution name for partner {{ $partnerIndex + 1 }}"
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
                                    aria-label="Contribution amount for partner {{ $partnerIndex + 1 }}"
                                >
                            </div>

                            <button
                                type="button"
                                class="pbr-remove-item"
                                data-remove-contribution
                                aria-label="Remove contribution for partner {{ $partnerIndex + 1 }}"
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

    <div class="pbr-capital-definition-note">
        <strong>Contribution Share ≠ Ownership Share</strong>

        <p>
            ဒီ tool က Partner တစ်ဦးချင်းစီရဲ့ capital contribution ကိုပဲပြပါတယ်။
            Ownership percentage နဲ့ voting rights ကို Chapter 2 မှာ သီးခြားဆုံးဖြတ်ရပါတယ်။
        </p>
    </div>
</div>
