@php
    $rows = old($field, $categories ?? []);

    if (! is_array($rows)) {
        $rows = [];
    }

    $currency = $workspace->currency_code ?? 'THB';
@endphp

<div
    class="pbr-shared-category-builder"
    data-category-builder
    data-field="{{ $field }}"
    data-currency="{{ $currency }}"
    data-next-index="{{ count($rows) + 100 }}"
>
    <div class="pbr-builder-heading">
        <div>
            <h3>{{ $title }}</h3>

            @if(! empty($help))
                <p>{{ $help }}</p>
            @endif
        </div>
    </div>

    <div data-categories>

        @foreach($rows as $categoryIndex => $category)

            @php
                $isOthers = false;

                $items = $category['items'] ?? [];

                if (! is_array($items) || empty($items)) {
                    $items = [
                        [
                            'name' => '',
                            'amount' => '',
                        ],
                    ];
                }
            @endphp

            <section
                class="pbr-dynamic-category {{ $isOthers ? 'pbr-others-category' : '' }}"
                data-category
                data-category-index="{{ $categoryIndex }}"
                @if($isOthers) data-locked-category @endif
            >
                <div class="pbr-category-head">

                    <div class="pbr-category-name-wrap">
                        <label>Category Name</label>

                        <input
                            type="text"
                            name="{{ $field }}[{{ $categoryIndex }}][name]"
                            value="{{ $isOthers ? 'Others' : ($category['name'] ?? '') }}"
                            placeholder="Type your category name"
                            maxlength="120"
                            @if($isOthers) readonly @endif
                        >

                        @if($isOthers)
                            <small>
                                မည်သည့် category ထဲထည့်ရမလဲ
                                မသေချာတဲ့ items တွေကို ဒီမှာထည့်နိုင်ပါတယ်။
                            </small>
                        @endif
                    </div>

                    @unless($isOthers)
                        <button
                            type="button"
                            class="pbr-remove-category"
                            data-remove-category
                        >
                            Remove Category
                        </button>
                    @endunless

                </div>

                <div class="pbr-category-items" data-items>

                    @foreach($items as $itemIndex => $item)

                        <div class="pbr-dynamic-item" data-item>

                            <input
                                type="text"
                                name="{{ $field }}[{{ $categoryIndex }}][items][{{ $itemIndex }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Item name"
                                maxlength="150"
                            >

                            <div class="pbr-money-input">
                                <span>{{ $currency }}</span>

                                <input
                                    type="number"
                                    name="{{ $field }}[{{ $categoryIndex }}][items][{{ $itemIndex }}][amount]"
                                    value="{{ $item['amount'] ?? '' }}"
                                    min="0"
                                    max="999999999999.99"
                                    step="0.01"
                                    placeholder="0.00"
                                    data-item-amount
                                >
                            </div>

                            <button
                                type="button"
                                class="pbr-remove-item"
                                data-remove-item
                                aria-label="Remove item"
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
                        data-add-item
                    >
                        + Add Item
                    </button>

                    <div>
                        <span>Category Subtotal</span>

                        <strong data-category-subtotal>
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
        data-add-category
    >
        + Add Category
    </button>

</div>
