<div class="pbr-calculator-panel-head">
    <span class="portal-kicker">
        Existing Business
    </span>

    <p>
        လက်ရှိလုပ်ငန်းမှာရှိနေတဲ့ resources နဲ့
        liabilities တွေကို ကိုယ်တိုင်ထည့်ပြီး
        Net Capital Position ကိုကြည့်ပါ။
    </p>
</div>

@include(
    'workspaces.tools.chapter-one.partials.category-builder',
    [
        'field' => 'resources',
        'title' => 'Business Resources / Assets',
        'help' => 'Cash, inventory, equipment, receivables or any other business resources.',
        'categories' => $input['resources'] ?? [],
    ]
)

<div class="pbr-tool-divider"></div>

@include(
    'workspaces.tools.chapter-one.partials.category-builder',
    [
        'field' => 'liabilities',
        'title' => 'Current Liabilities',
        'help' => 'Loans, unpaid bills, supplier balances or other current obligations.',
        'categories' => $input['liabilities'] ?? [],
    ]
)
