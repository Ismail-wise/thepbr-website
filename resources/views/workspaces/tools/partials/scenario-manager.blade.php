<div class="pbr-scenario-manager">
    <div class="pbr-scenario-manager-head">
        <div>
            <span class="portal-kicker">Saved Scenarios</span>
            <h3>Draft Plans</h3>
            <p style="margin:4px 0 0;font-size:11px;color:#77868c;line-height:1.6;">Draft ကိုစမ်းသပ်နိုင်ပါတယ်။ နောက် Chapters နဲ့ AI Advisor ကို official data အဖြစ်ပို့ချင်မှ Agreed Business Rule အဖြစ် approve လုပ်ပါ။</p>
        </div>

        <span class="pbr-scenario-count">
            {{ $drafts->count() }} saved
        </span>
    </div>

    @forelse($drafts as $draft)
        <div class="pbr-scenario-row">
            <div class="pbr-scenario-info">
                <strong>{{ $draft->scenario_name ?: 'Untitled Scenario' }}</strong>
                <small>
                    Last saved
                    {{ $draft->last_saved_at ? $draft->last_saved_at->diffForHumans() : 'recently' }}
                </small>
            </div>

            <div class="pbr-scenario-actions">
                <a
                    class="pbr-scenario-open"
                    href="{{ url('/workspaces/'.$workspace->id.'/tools/'.$tool->slug) }}?session={{ $draft->id }}"
                >Open</a>

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
                        aria-label="Scenario name"
                    >
                    <button type="submit">Rename</button>
                </form>

                <form method="POST" action="{{ route('workspaces.tools.scenarios.duplicate', [$workspace, $tool->slug, $draft->id]) }}">
                    @csrf
                    <button type="submit">Duplicate</button>
                </form>

                <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $draft->id]) }}">
                    @csrf
                    <button type="submit">Draft Output</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $draft->id]) }}"
                    data-confirm-agreed
                >
                    @csrf
                    <button type="submit" style="background:#e4f5ea;color:#17643b;border-color:#b9dec7;">✓ Approve Rule</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('workspaces.tools.scenarios.destroy', [$workspace, $tool->slug, $draft->id]) }}"
                    onsubmit="return confirm('ဒီ saved scenario ကိုဖျက်မလား?')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="pbr-scenario-empty">
            Draft scenario မရှိသေးပါ။ Tool ကိုတွက်ပြီး Save Draft လုပ်ပါ။
        </div>
    @endforelse
</div>
