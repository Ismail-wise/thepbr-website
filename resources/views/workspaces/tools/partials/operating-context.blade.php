@php
    $operatingContext = old(
        'operating_context',
        $input['operating_context'] ?? []
    );

    $operatingActionRows = old(
        'operating_actions',
        $input['operating_actions'] ?? []
    );

    $operatingActionRows = is_array($operatingActionRows)
        ? array_values($operatingActionRows)
        : [];

    if ($operatingActionRows === []) {
        $operatingActionRows = [[]];
    }

    $contextStatusOptions = [
        'planned' => 'စီစဉ်နေသည် · Planned',
        'in_progress' => 'လုပ်ဆောင်နေသည် · In Progress',
        'blocked' => 'အခက်အခဲရှိသည် · Blocked',
        'ready' => 'အတည်ပြုရန်အသင့် · Ready',
    ];

    $actionPriorityOptions = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    $actionStatusOptions = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'blocked' => 'Blocked',
        'completed' => 'Completed',
    ];
@endphp

<section class="pbr-operating-context">
    <div class="pbr-operating-context-head">
        <div>
            <span class="portal-kicker">OPERATING CONTEXT</span>
            <h3>ဆုံးဖြတ်ချက်ကို လက်တွေ့အသုံးချရန်</h3>
            <p>
                Calculation သို့မဟုတ် Plan တစ်ခုတည်းမဟုတ်ဘဲ
                ဘယ်သူတာဝန်ယူမလဲ၊ ဘယ်နေ့စမလဲ၊ ဘယ်နေ့ပြန်စစ်မလဲနဲ့
                ဘာဆက်လုပ်မလဲကို သတ်မှတ်ပါ။
            </p>
        </div>
        <span class="pbr-context-live-badge">Business Ready</span>
    </div>

    <div class="pbr-context-grid">
        <div class="pbr-context-field">
            <label for="pbrOperatingOwner">Operating Owner</label>
            <small>အဓိက တာဝန်ယူသူ</small>
            <input
                id="pbrOperatingOwner"
                type="text"
                name="operating_context[owner_name]"
                maxlength="160"
                value="{{ $operatingContext['owner_name'] ?? '' }}"
                placeholder="ဥပမာ: Si Thu Aung"
            >
        </div>

        <div class="pbr-context-field">
            <label for="pbrOperatingStatus">Operating Status</label>
            <small>လက်ရှိအခြေအနေ</small>
            <select
                id="pbrOperatingStatus"
                name="operating_context[status]"
            >
                @foreach($contextStatusOptions as $value => $label)
                    <option
                        value="{{ $value }}"
                        @selected(
                            ($operatingContext['status'] ?? 'planned')
                            === $value
                        )
                    >{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="pbr-context-field">
            <label for="pbrEffectiveDate">Effective Date</label>
            <small>စတင်အသုံးပြုမည့်နေ့</small>
            <input
                id="pbrEffectiveDate"
                type="date"
                name="operating_context[effective_date]"
                value="{{ $operatingContext['effective_date'] ?? '' }}"
            >
        </div>

        <div class="pbr-context-field">
            <label for="pbrReviewDate">Review Date</label>
            <small>ပြန်လည်စစ်ဆေးမည့်နေ့</small>
            <input
                id="pbrReviewDate"
                type="date"
                name="operating_context[review_date]"
                value="{{ $operatingContext['review_date'] ?? '' }}"
            >
        </div>

        <div class="pbr-context-field pbr-context-field-wide">
            <label for="pbrDecisionSummary">Decision / Rule Summary</label>
            <small>
                Partner အားလုံး နားလည်နိုင်မည့်
                အဓိကဆုံးဖြတ်ချက်
            </small>
            <textarea
                id="pbrDecisionSummary"
                name="operating_context[decision_summary]"
                rows="3"
                maxlength="2000"
                placeholder="ဒီ tool result ကနေ အတည်ပြုမယ့် Rule သို့မဟုတ် Decision ကို ရေးပါ"
            >{{ $operatingContext['decision_summary'] ?? '' }}</textarea>
        </div>

        <div class="pbr-context-field pbr-context-field-wide">
            <label for="pbrOperatingEvidence">Evidence / Source Reference</label>
            <small>
                Meeting minutes၊ agreement၊ invoice သို့မဟုတ်
                source data reference
            </small>
            <textarea
                id="pbrOperatingEvidence"
                name="operating_context[evidence]"
                rows="2"
                maxlength="3000"
                placeholder="ဆုံးဖြတ်ချက်အတွက် အသုံးပြုထားသည့် evidence သို့မဟုတ် reference"
            >{{ $operatingContext['evidence'] ?? '' }}</textarea>
        </div>
    </div>

    <div
        class="pbr-os-repeater pbr-operating-actions-editor"
        data-pbr-repeater="operating_actions"
        style="--pbr-cols:6"
    >
        <div class="pbr-os-field-heading">
            <div>
                <label>Operating Action Plan</label>
                <span>
                    Result ကို လက်တွေ့အလုပ်အဖြစ်
                    ပြောင်းရန်
                </span>
            </div>
            <button
                type="button"
                class="pbr-os-add-row"
                data-repeater-add
            >+ Action ထည့်ရန်</button>
        </div>

        <p class="pbr-os-help">
            Action တစ်ခုစီအတွက် တာဝန်ယူသူ၊ deadline၊ priority နဲ့
            status ကို သတ်မှတ်နိုင်ပါတယ်။
        </p>

        <div class="pbr-os-repeater-rows" data-repeater-rows>
            @foreach($operatingActionRows as $rowIndex => $row)
                <div class="pbr-os-repeater-row" data-repeater-row>
                    <span class="pbr-os-row-number">
                        {{ $rowIndex + 1 }}
                    </span>

                    <div class="pbr-os-mini-field pbr-action-title-field">
                        <label>Action</label>
                        <input
                            type="text"
                            name="operating_actions[{{ $rowIndex }}][title]"
                            maxlength="180"
                            value="{{ $row['title'] ?? '' }}"
                            placeholder="ဆက်လုပ်ရမည့်အလုပ်"
                        >
                    </div>

                    <div class="pbr-os-mini-field">
                        <label>Owner</label>
                        <input
                            type="text"
                            name="operating_actions[{{ $rowIndex }}][owner_name]"
                            maxlength="160"
                            value="{{ $row['owner_name'] ?? '' }}"
                            placeholder="တာဝန်ယူသူ"
                        >
                    </div>

                    <div class="pbr-os-mini-field">
                        <label>Due Date</label>
                        <input
                            type="date"
                            name="operating_actions[{{ $rowIndex }}][due_date]"
                            value="{{ $row['due_date'] ?? '' }}"
                        >
                    </div>

                    <div class="pbr-os-mini-field">
                        <label>Priority</label>
                        <select
                            name="operating_actions[{{ $rowIndex }}][priority]"
                        >
                            @foreach($actionPriorityOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        ($row['priority'] ?? 'normal')
                                        === $value
                                    )
                                >{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pbr-os-mini-field">
                        <label>Status</label>
                        <select
                            name="operating_actions[{{ $rowIndex }}][status]"
                        >
                            @foreach($actionStatusOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        ($row['status'] ?? 'open')
                                        === $value
                                    )
                                >{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pbr-os-mini-field pbr-action-description-field">
                        <label>Details</label>
                        <input
                            type="text"
                            name="operating_actions[{{ $rowIndex }}][description]"
                            maxlength="2000"
                            value="{{ $row['description'] ?? '' }}"
                            placeholder="လိုအပ်သော အသေးစိတ်"
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-os-remove-row"
                        data-repeater-remove
                        aria-label="Remove action"
                    >×</button>
                </div>
            @endforeach
        </div>

        <template data-repeater-template>
            <div class="pbr-os-repeater-row" data-repeater-row>
                <span class="pbr-os-row-number">__NUMBER__</span>

                <div class="pbr-os-mini-field pbr-action-title-field">
                    <label>Action</label>
                    <input
                        type="text"
                        name="operating_actions[__INDEX__][title]"
                        maxlength="180"
                        placeholder="ဆက်လုပ်ရမည့်အလုပ်"
                    >
                </div>

                <div class="pbr-os-mini-field">
                    <label>Owner</label>
                    <input
                        type="text"
                        name="operating_actions[__INDEX__][owner_name]"
                        maxlength="160"
                        placeholder="တာဝန်ယူသူ"
                    >
                </div>

                <div class="pbr-os-mini-field">
                    <label>Due Date</label>
                    <input
                        type="date"
                        name="operating_actions[__INDEX__][due_date]"
                    >
                </div>

                <div class="pbr-os-mini-field">
                    <label>Priority</label>
                    <select name="operating_actions[__INDEX__][priority]">
                        @foreach($actionPriorityOptions as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($value === 'normal')
                            >{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pbr-os-mini-field">
                    <label>Status</label>
                    <select name="operating_actions[__INDEX__][status]">
                        @foreach($actionStatusOptions as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($value === 'open')
                            >{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pbr-os-mini-field pbr-action-description-field">
                    <label>Details</label>
                    <input
                        type="text"
                        name="operating_actions[__INDEX__][description]"
                        maxlength="2000"
                        placeholder="လိုအပ်သော အသေးစိတ်"
                    >
                </div>

                <button
                    type="button"
                    class="pbr-os-remove-row"
                    data-repeater-remove
                    aria-label="Remove action"
                >×</button>
            </div>
        </template>
    </div>
</section>
