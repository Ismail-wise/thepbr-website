<div class="pbr-scenario-manager">
    <div class="pbr-scenario-manager-head">
        <div>
            <span class="portal-kicker">သိမ်းထားသော အစီအစဉ်များ · Saved Scenarios</span>
            <h3>Draft အစီအစဉ်များ</h3>
            <p style="margin:4px 0 0;font-size:11px;color:#77868c;line-height:1.8;">
                ဒီ Draft တွေက စမ်းသပ်ပြီး ပြင်ဆင်နိုင်တဲ့ working plans တွေပါ။
                Business မှာ တကယ်အသုံးပြုမယ့် rule ဖြစ်စေချင်တဲ့အခါမှ အတည်ပြုပါ။
                အတည်ပြုပြီးတဲ့ data ကိုပဲ အခြား Business Systems နဲ့ PBR AI Advisor က လက်ရှိ Business Rule အဖြစ်အသုံးပြုပါမယ်။
            </p>
        </div>

        <span class="pbr-scenario-count">
            {{ $drafts->count() }} ခု သိမ်းထားသည်
        </span>
    </div>

    @forelse($drafts as $draft)
        <div class="pbr-scenario-row">
            <div class="pbr-scenario-info">
                <strong>{{ $draft->scenario_name ?: 'အမည်မပေးရသေးသော Draft' }}</strong>
                <small>
                    နောက်ဆုံးသိမ်းထားချိန်
                    {{ $draft->last_saved_at ? $draft->last_saved_at->diffForHumans() : 'မကြာသေးမီက' }}
                </small>
            </div>

            <div class="pbr-scenario-actions">
                <a
                    class="pbr-scenario-open"
                    href="{{ url('/workspaces/'.$workspace->id.'/tools/'.$tool->slug) }}?session={{ $draft->id }}"
                >ဖွင့်ရန်</a>

                <form
                    method="POST"
                    class="pbr-scenario-rename-form"
                    action="{{ route('workspaces.tools.scenarios.rename', [$workspace, $tool->slug, $draft->id]) }}"
                >
                    @csrf
                    <input
                        type="text"
                        name="scenario_name"
                        value="{{ $draft->scenario_name }}"
                        maxlength="120"
                        required
                        aria-label="Draft အမည်"
                    >
                    <button type="submit">အမည်ပြောင်းရန်</button>
                </form>

                <form method="POST" action="{{ route('workspaces.tools.scenarios.duplicate', [$workspace, $tool->slug, $draft->id]) }}">
                    @csrf
                    <button type="submit">မိတ္တူပြုလုပ်ရန်</button>
                </form>

                <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $draft->id]) }}">
                    @csrf
                    <button type="submit">Draft Result ထုတ်ရန်</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $draft->id]) }}"
                    data-confirm-agreed
                >
                    @csrf
                    <button type="submit" style="background:#e4f5ea;color:#17643b;border-color:#b9dec7;">✓ Business Rule အဖြစ်အသုံးပြုရန်</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('workspaces.tools.scenarios.destroy', [$workspace, $tool->slug, $draft->id]) }}"
                    onsubmit="return confirm('ဒီ Draft အစီအစဉ်ကို ဖျက်မှာသေချာပါသလား?')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="danger">ဖျက်ရန်</button>
                </form>
            </div>
        </div>
    @empty
        <div class="pbr-scenario-empty">
            Draft အစီအစဉ် မရှိသေးပါ။ အပေါ်က Business data ကိုဖြည့်ပြီး “Draft သိမ်းရန်” ကိုနှိပ်ပါ။
        </div>
    @endforelse
</div>
