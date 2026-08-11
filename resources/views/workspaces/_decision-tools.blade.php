<div class="auth-card">
    <span class="portal-kicker">Business Decision</span>
    <h2>Feasibility Assessment</h2>
    <p class="panel-copy">Business အသစ် သို့မဟုတ် Project အသစ်ကို လက်ရှိ Data အရ စတင်သင့် / မစတင်သင့်၊ ဘာတွေပြင်ရမလဲဆိုတာ Assessment လုပ်ပါ။</p>
    <a class="portal-button" href="{{ route('workspaces.feasibility.show', $workspace) }}">Analyze Feasibility</a>
</div>

@if($workspace->isExistingPartnership())
    <div class="auth-card">
        <span class="portal-kicker">Business Value</span>
        <h2>Valuation Center</h2>
        <p class="panel-copy">Existing Business ရဲ့ Financial Data, Cash Flow, Assets နဲ့ Risk Factors တွေကနေ indicative valuation range ကိုတွက်ပါ။</p>
        <a class="portal-button" href="{{ route('workspaces.valuation.show', $workspace) }}">Open Valuation Center</a>
    </div>
@endif
