<section class="c2-card" id="c2-lifecycle">
    <h2>Review · Approval · Delivery · Publication</h2>
    @if($artifact['state'] === 'proposed')
        <p>Persisted proposal ကို ပြန်စစ်ပြီး approved current register အဖြစ် ထုတ်ပြန်ပါမည်။</p>
        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.publish', ['workspace'=>$workspace,'artifactId'=>$aid]) }}">
            @csrf
            <button type="submit">Approve and Publish Register</button>
        </form>
    @elseif($artifact['state'] === 'working_draft')
        @foreach($items as $lifecycleItem)
            @php
                $itemParams = ['workspace'=>$workspace,'artifactId'=>$aid,'itemId'=>$lifecycleItem['id']];
                $itemStatus = $lifecycleItem['status'];
                $closeOptions = [];
                foreach ([
                    'rejected'=>['proposed','reviewed'],
                    'cancelled'=>['proposed','reviewed','approved','delivered'],
                    'defaulted'=>['approved','delivered'],
                ] as $target=>$from) {
                    if (in_array($itemStatus, $from, true)) { $closeOptions[] = $target; }
                }
            @endphp
            <details class="c2-card">
                <summary>{{ $lifecycleItem['description'] ?? $lifecycleItem['id'] }} · {{ $itemStatus }}</summary>
                @if($itemStatus === 'proposed')
                    <form method="POST" action="{{ route('workspaces.tools.v2.contribution.review', $itemParams) }}">
                        @csrf
                        <input type="hidden" name="edit_version" value="{{ $version }}">
                        <label>Reviewed Value
                            <input type="text" name="reviewed_value" value="{{ $lifecycleItem['proposed_value'] ?? '' }}" required>
                        </label>
                        <button type="submit">Review Contribution</button>
                    </form>
                @endif
                @if(in_array($itemStatus, ['reviewed','delivered'], true))
                    <p>Designated approver တစ်ဦးစီသည် ကိုယ်ပိုင် account ဖြင့် approval page ကို ဖွင့်ပြီး အတည်ပြုရပါမည်။</p>
                    <a href="{{ route('workspaces.tools.v2.contribution.approval.show', $itemParams) }}">Open Item Approval Page</a>
                    <label>Approval page link
                        <input type="text" readonly value="{{ route('workspaces.tools.v2.contribution.approval.show', $itemParams) }}">
                    </label>
                @endif
                @if($itemStatus === 'approved')
                    <form method="POST" action="{{ route('workspaces.tools.v2.contribution.deliver', $itemParams) }}">
                        @csrf
                        <input type="hidden" name="edit_version" value="{{ $version }}">
                        <p>Delivery မှတ်တမ်းတင်ပြီးနောက် acceptance approvals အသစ် ပြန်ယူရပါမည်။</p>
                        <div class="c2-grid">
                        @foreach(['accepted_value','committed_date','due_date','delivered_date'] as $field)
                            <label>{{ ucwords(str_replace('_',' ',$field)) }}
                                <input type="{{ str_ends_with($field,'_date') ? 'date' : 'text' }}" name="delivery[{{ $field }}]" value="{{ $lifecycleItem[$field] ?? '' }}" required>
                            </label>
                        @endforeach
                        @if($lifecycleItem['contribution_type'] === 'cash')
                            <label>Amount Received<input type="text" name="delivery[amount_received]" value="{{ $lifecycleItem['amount_received'] ?? '' }}" required></label>
                            <label>Payment Date<input type="date" name="delivery[payment_date]" value="{{ $lifecycleItem['payment_date'] ?? '' }}" required></label>
                        @endif
                        </div>
                        <p>Accepted Value ကို decimal places 2 လုံးဖြင့် ရေးပါ။ ဥပမာ 100.00</p>
                        <label>Delivery Extent<select name="delivery[delivery_extent]" required>
                            <option value="full">Full</option><option value="partial">Partial</option>
                        </select></label>
                        <label>Accepted Scope — partial delivery အတွက်<textarea name="delivery[accepted_scope]"></textarea></label>
                        <label>Adjustment Basis — partial delivery အတွက်<textarea name="delivery[adjustment_basis]"></textarea></label>
                        <label>Delivery Evidence Reference<input name="evidence_reference" required></label>
                        <button type="submit">Record Delivery</button>
                    </form>
                @endif
                @if($closeOptions !== [])
                    <details>
                        <summary>Close Contribution Item</summary>
                        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.close', $itemParams) }}">
                            @csrf
                            <input type="hidden" name="edit_version" value="{{ $version }}">
                            <label>Outcome<select name="target_status" required>
                                @foreach($closeOptions as $target)<option value="{{ $target }}">{{ $target }}</option>@endforeach
                            </select></label>
                            <label>Reason<textarea name="reason" required></textarea></label>
                            <label>Evidence Reference<input name="evidence_reference" required></label>
                            <button type="submit">Record Closure</button>
                        </form>
                    </details>
                @endif
            </details>
        @endforeach
        <p>Items အားလုံး accepted သို့မဟုတ် closed outcome ဖြစ်ပြီးမှ register ကို submit လုပ်နိုင်ပါသည်။</p>
        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.submit', ['workspace'=>$workspace,'artifactId'=>$aid]) }}">
            @csrf
            <input type="hidden" name="edit_version" value="{{ $version }}">
            <button type="submit">Submit Contribution Register</button>
        </form>
    @endif
</section>
