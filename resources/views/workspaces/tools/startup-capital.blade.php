@extends('layouts.student-portal')

@section('title', 'Startup Capital Planner')

@section('content')

<section class="pbr-tools-section">
    <div class="portal-wrap">

        <div class="pbr-tool-page-head">
            <div>
                <a
                    href="{{ route('workspaces.tools.index', $workspace) }}"
                    class="pbr-tools-back"
                >
                    ← Back to PBR Business Tools
                </a>

                <span class="portal-kicker">
                    Chapter 1 · Capital Contribution
                </span>

                <h1>Startup Capital Planner</h1>

                <p>
                    ကိုယ့်လုပ်ငန်းအတွက် လိုအပ်မယ့် startup cost
                    categories နဲ့ items တွေကို ကိုယ်တိုင်ထည့်ပြီး
                    စုစုပေါင်း မတည်ငွေလိုအပ်ချက်ကို တွက်နိုင်ပါတယ်။
                </p>
            </div>

            <div class="pbr-tool-context-box">
                <span>Workspace</span>

                <strong>
                    {{ $workspace->business_name ?: $workspace->name }}
                </strong>

                <small>
                    Currency:
                    {{ $workspace->currency_code ?? 'THB' }}
                </small>
            </div>
        </div>


        <form
            id="startup-capital-builder"
            method="POST"
            action="{{ route(
                'workspaces.tools.startup-capital.calculate',
                $workspace
            ) }}"
            data-currency="{{ $workspace->currency_code ?? 'THB' }}"
            data-next-category="{{ count($categories) + 100 }}"
        >
            @csrf
            @if($activeSession)
                <input
                    type="hidden"
                    name="tool_session_id"
                    value="{{ $activeSession->id }}"
                >
            @endif

            @if(session('status'))
                <div class="pbr-save-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="pbr-scenario-box">
                <div>
                    <label for="scenario_name">
                        Scenario Name
                    </label>

                    <small>
                        Example: Initial Plan, Lower Budget,
                        Premium Setup
                    </small>
                </div>

                <input
                    id="scenario_name"
                    name="scenario_name"
                    type="text"
                    maxlength="120"
                    placeholder="Example: Initial Plan"
                    value="{{ old(
                        'scenario_name',
                        $activeSession?->scenario_name ?? ''
                    ) }}"
                >
            </div>

            @if($activeSession)
                <div class="pbr-active-draft">
                    <span>Editing Saved Scenario</span>

                    <strong>
                        {{ $activeSession->scenario_name }}
                    </strong>

                    <small>
                        Last saved
                        {{
                            $activeSession->last_saved_at
                                ? $activeSession->last_saved_at->diffForHumans()
                                : 'recently'
                        }}
                    </small>
                </div>
            @endif


            <div class="pbr-calculator-layout">

                <div class="pbr-calculator-panel">

                    <div class="pbr-calculator-panel-head">
                        <span class="portal-kicker">
                            Your Startup Costs
                        </span>

                        <h2>
                            Build Your Own Cost List
                        </h2>

                        <p>
                            Category နဲ့ Item တွေကို PBR က
                            သတ်မှတ်ပေးထားတာမဟုတ်ပါဘူး။
                            ကိုယ့်လုပ်ငန်းနဲ့ကိုက်ညီသလို
                            ကိုယ်တိုင်ထည့်နိုင်ပါတယ်။
                        </p>
                    </div>


                    @if($errors->any())
                        <div class="pbr-form-errors">
                            <strong>
                                Please check your entries.
                            </strong>

                            <p>
                                Amount တွေကို 0 သို့မဟုတ်
                                အပေါင်းကိန်းနဲ့ ထည့်ပေးပါ။
                            </p>
                        </div>
                    @endif


                    <div data-categories>

                        @foreach($categories as $categoryIndex => $category)

                            @php
                                $isOthers =
                                    strtolower(
                                        trim(
                                            (string) (
                                                $category['name']
                                                ?? ''
                                            )
                                        )
                                    ) === 'others';

                                $items =
                                    $category['items'] ?? [];

                                if (empty($items)) {
                                    $items = [
                                        [
                                            'name' => '',
                                            'amount' => '',
                                        ],
                                    ];
                                }
                            @endphp


                            <section
                                class="pbr-dynamic-category
                                    {{ $isOthers
                                        ? 'pbr-others-category'
                                        : '' }}"
                                data-category
                                data-category-index="{{ $categoryIndex }}"
                            >

                                <div class="pbr-category-head">

                                    <div class="pbr-category-name-wrap">
                                        <label>
                                            Category Name
                                        </label>

                                        @if($isOthers)

                                            <input
                                                type="text"
                                                name="categories[
                                                    {{ $categoryIndex }}
                                                ][name]"
                                                value="Others"
                                                readonly
                                                data-category-name
                                            >

                                            <small>
                                                မည်သည့် category ထဲ
                                                ထည့်ရမလဲ မသေချာတဲ့
                                                items တွေကို ဒီမှာ
                                                ထည့်နိုင်ပါတယ်။
                                            </small>

                                        @else

                                            <input
                                                type="text"
                                                name="categories[
                                                    {{ $categoryIndex }}
                                                ][name]"
                                                value="{{
                                                    old(
                                                        "categories.$categoryIndex.name",
                                                        $category['name']
                                                        ?? ''
                                                    )
                                                }}"
                                                placeholder="Type your category name"
                                                maxlength="120"
                                                required
                                                data-category-name
                                            >

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


                                <div
                                    class="pbr-category-items"
                                    data-items
                                >

                                    @foreach($items as $itemIndex => $item)

                                        <div
                                            class="pbr-dynamic-item"
                                            data-item
                                        >

                                            <input
                                                type="text"
                                                name="categories[
                                                    {{ $categoryIndex }}
                                                ][items][
                                                    {{ $itemIndex }}
                                                ][name]"
                                                value="{{
                                                    old(
                                                        "categories.$categoryIndex.items.$itemIndex.name",
                                                        $item['name']
                                                        ?? ''
                                                    )
                                                }}"
                                                placeholder="Item name"
                                                maxlength="150"
                                                data-item-name
                                            >


                                            <div class="pbr-money-input">

                                                <span>
                                                    {{
                                                        $workspace
                                                            ->currency_code
                                                        ?? 'THB'
                                                    }}
                                                </span>

                                                <input
                                                    type="number"
                                                    name="categories[
                                                        {{ $categoryIndex }}
                                                    ][items][
                                                        {{ $itemIndex }}
                                                    ][amount]"
                                                    value="{{
                                                        old(
                                                            "categories.$categoryIndex.items.$itemIndex.amount",
                                                            $item['amount']
                                                            ?? ''
                                                        )
                                                    }}"
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
                                        <span>
                                            Category Subtotal
                                        </span>

                                        <strong data-category-subtotal>
                                            {{
                                                $workspace->currency_code
                                                ?? 'THB'
                                            }}
                                            0.00
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


                    <div class="pbr-calculator-actions">

                        <button
                            type="submit"
                            class="pbr-save-draft-button"
                            formaction="{{ route(
                                'workspaces.tools.startup-capital.draft.store',
                                $workspace
                            ) }}"
                            formmethod="POST"
                        >
                            Save Draft
                        </button>

                        <button
                            type="submit"
                            class="pbr-tools-primary-button"
                        >
                            Calculate Startup Capital
                        </button>

                    </div>

                </div>


                <aside class="pbr-calculator-results">

                    <span class="portal-kicker">
                        Capital Summary
                    </span>


                    <div class="pbr-total-result">
                        <span>
                            Total Startup Capital
                        </span>

                        <strong data-live-total>
                            {{ $workspace->currency_code ?? 'THB' }}
                            {{
                                number_format(
                                    $result[
                                        'total_startup_capital'
                                    ] ?? 0,
                                    2
                                )
                            }}
                        </strong>
                    </div>


                    <div class="pbr-result-stats">

                        <div>
                            <span>Categories</span>

                            <strong data-live-categories>
                                {{
                                    $result['category_count']
                                    ?? count($categories)
                                }}
                            </strong>
                        </div>

                        <div>
                            <span>Items with Amount</span>

                            <strong data-live-items>
                                {{ $result['item_count'] ?? 0 }}
                            </strong>
                        </div>

                    </div>


                    @if($result)

                        @if($result['largest_category'])

                            <div class="pbr-largest-cost">
                                <span>
                                    Largest Category
                                </span>

                                <strong>
                                    {{
                                        $result[
                                            'largest_category'
                                        ]['name']
                                    }}
                                </strong>

                                <p>
                                    {{ $workspace->currency_code ?? 'THB' }}
                                    {{
                                        number_format(
                                            $result[
                                                'largest_category'
                                            ]['subtotal'],
                                            2
                                        )
                                    }}

                                    ·

                                    {{
                                        number_format(
                                            $result[
                                                'largest_category'
                                            ]['percentage'],
                                            2
                                        )
                                    }}%
                                </p>
                            </div>

                        @endif


                        @if($result['largest_item'])

                            <div class="pbr-largest-cost">
                                <span>
                                    Largest Individual Item
                                </span>

                                <strong>
                                    {{
                                        $result[
                                            'largest_item'
                                        ]['name']
                                    }}
                                </strong>

                                <p>
                                    {{
                                        $result[
                                            'largest_item'
                                        ]['category']
                                    }}
                                    ·
                                    {{ $workspace->currency_code ?? 'THB' }}
                                    {{
                                        number_format(
                                            $result[
                                                'largest_item'
                                            ]['amount'],
                                            2
                                        )
                                    }}
                                </p>
                            </div>

                        @endif


                        <div class="pbr-breakdown">
                            <h3>
                                Category Breakdown
                            </h3>

                            @forelse(
                                $result['categories']
                                as $category
                            )

                                <div class="pbr-breakdown-row">

                                    <div>
                                        <span>
                                            {{ $category['name'] }}
                                        </span>

                                        <strong>
                                            {{
                                                number_format(
                                                    $category[
                                                        'percentage'
                                                    ],
                                                    2
                                                )
                                            }}%
                                        </strong>
                                    </div>

                                    <div class="pbr-breakdown-track">
                                        <i
                                            style="width: {{
                                                min(
                                                    100,
                                                    $category[
                                                        'percentage'
                                                    ]
                                                )
                                            }}%"
                                        ></i>
                                    </div>

                                    <small>
                                        {{
                                            $workspace->currency_code
                                            ?? 'THB'
                                        }}
                                        {{
                                            number_format(
                                                $category[
                                                    'subtotal'
                                                ],
                                                2
                                            )
                                        }}
                                    </small>

                                </div>

                            @empty

                                <p class="pbr-muted-copy">
                                    No startup costs added yet.
                                </p>

                            @endforelse
                        </div>

                    @else

                        <div class="pbr-empty-result">
                            <strong>
                                Build your startup cost plan.
                            </strong>

                            <p>
                                Category အသစ်ထည့်ပါ၊
                                Items တွေထည့်ပါ၊
                                Amount ထည့်တာနဲ့
                                total ကို ဒီနေရာမှာ
                                live ပြပါမယ်။
                            </p>
                        </div>

                    @endif


                    <div class="pbr-tool-next-note">
                        <strong>
                            About “Others”
                        </strong>

                        <p>
                            ဘယ် Category ထဲထည့်သင့်မှန်း
                            မသေချာတဲ့ startup cost ကို
                            Others ထဲမှာ ထည့်ထားနိုင်ပါတယ်။
                        </p>
                    </div>

                </aside>

            </div>

        </form>

        @include(
            'workspaces.tools.partials.scenario-manager'
        )


    </div>
</section>

<script src="/js/startup-capital-planner.js"></script>

@endsection
