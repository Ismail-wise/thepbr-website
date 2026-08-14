<div
    class="pbr-capital-form-experience"
    data-capital-form="current-position"
>
    <div class="pbr-calculator-panel-head">
        <span class="portal-kicker">
            EXISTING BUSINESS · CAPITAL POSITION
        </span>

        <h2>
            လုပ်ငန်းရဲ့ လက်ရှိ Capital Position ကို တိတိကျကျကြည့်ပါ
        </h2>

        <p>
            လက်ရှိလုပ်ငန်းမှာရှိနေတဲ့
            <strong>Business Resources</strong> နဲ့
            <strong>Liabilities</strong> ကို ထည့်ပါ။
            System က Net Capital Position ကိုတွက်ပေးပါမယ်။
        </p>
    </div>

    <div class="pbr-capital-form-guide">
        <article>
            <span>01</span>
            <div>
                <strong>Resources ထည့်ပါ</strong>
                <p>
                    Cash, inventory, receivables, equipment
                    နဲ့ လုပ်ငန်းပိုင် တန်ဖိုးရှိတဲ့ resources တွေ။
                </p>
            </div>
        </article>

        <article>
            <span>02</span>
            <div>
                <strong>Liabilities ထည့်ပါ</strong>
                <p>
                    Supplier balances, short-term loans,
                    unpaid bills နဲ့ လက်ရှိ obligations တွေ။
                </p>
            </div>
        </article>

        <article>
            <span>03</span>
            <div>
                <strong>Net Position စစ်ပါ</strong>
                <p>
                    Resources − Liabilities ကို
                    business diagnostic အဖြစ်ကြည့်ပါ။
                </p>
            </div>
        </article>
    </div>

    <div class="pbr-capital-definition-note">
        <strong>ဒီ Tool က ဘာကိုမလုပ်ပါသလဲ?</strong>
        <p>
            Net Capital Position ကို Funding Requirement အဖြစ်
            အလိုအလျောက် မယူပါဘူး။
            Existing business ရဲ့ လက်ရှိ financial position ကို
            diagnostic အဖြစ်ပဲ ထိန်းထားပါတယ်။
        </p>
    </div>

    @include(
        'workspaces.tools.chapter-one.partials.category-builder',
        [
            'field' => 'resources',
            'title' => 'လုပ်ငန်းရဲ့ Resources / Assets',
            'help' =>
                'Cash, inventory, receivables, equipment သို့မဟုတ် အခြား business resources တွေကို category အလိုက်ထည့်ပါ။',
            'categories' => $input['resources'] ?? [],
            'quickCategories' => [
                'Cash & Bank',
                'Inventory / Stock',
                'Accounts Receivable',
                'Equipment & Tools',
            ],
        ]
    )

    <div class="pbr-tool-divider"></div>

    @include(
        'workspaces.tools.chapter-one.partials.category-builder',
        [
            'field' => 'liabilities',
            'title' => 'လက်ရှိ Liabilities / Obligations',
            'help' =>
                'Loans, supplier balances, unpaid bills နဲ့ လက်ရှိပေးရန်ရှိတာတွေကို category အလိုက်ထည့်ပါ။',
            'categories' => $input['liabilities'] ?? [],
            'quickCategories' => [
                'Supplier Payables',
                'Short-term Loans',
                'Unpaid Expenses',
                'Other Current Liabilities',
            ],
        ]
    )
</div>
