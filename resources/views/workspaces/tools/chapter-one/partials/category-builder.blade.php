@php
    $rows = old($field, $categories ?? []);

    if (! is_array($rows)) {
        $rows = [];
    }

    $currency = $workspace->currency_code ?? 'THB';

    $quickCategories = $quickCategories ?? [];

    if (! is_array($quickCategories)) {
        $quickCategories = [];
    }
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

    @if(! empty($quickCategories))
        <div
            class="pbr-quick-category-presets"
            aria-label="Quick add categories"
        >
            <span>Quick Add</span>

            <div>
                @foreach($quickCategories as $quickCategory)
                    <button
                        type="button"
                        data-add-category-preset="{{ $quickCategory }}"
                        aria-pressed="false"
                    >
                        + {{ $quickCategory }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

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
                        <label for="{{ $field }}_category_{{ $categoryIndex }}">Category Name</label>

                        <input
                            id="{{ $field }}_category_{{ $categoryIndex }}"
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
                            aria-label="Remove {{ $category['name'] ?? 'category' }}"
                        >
                            Remove Category
                        </button>
                    @endunless

                </div>

                <div class="pbr-category-items" data-items>

                    @foreach($items as $itemIndex => $item)

                        <div class="pbr-dynamic-item" data-item>

                            <input
                                id="{{ $field }}_item_{{ $categoryIndex }}_{{ $itemIndex }}_name"
                                type="text"
                                name="{{ $field }}[{{ $categoryIndex }}][items][{{ $itemIndex }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Item name"
                                maxlength="150"
                                aria-label="Item name in {{ $category['name'] ?? 'category' }}"
                            >

                            <div class="pbr-money-input">
                                <span>{{ $currency }}</span>

                                <input
                                    id="{{ $field }}_item_{{ $categoryIndex }}_{{ $itemIndex }}_amount"
                                    type="number"
                                    name="{{ $field }}[{{ $categoryIndex }}][items][{{ $itemIndex }}][amount]"
                                    value="{{ $item['amount'] ?? '' }}"
                                    min="0"
                                    max="999999999999.99"
                                    step="0.01"
                                    placeholder="0.00"
                                    data-item-amount
                                    aria-label="Item amount in {{ $category['name'] ?? 'category' }}"
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

                        <strong data-category-subtotal aria-live="polite">
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
