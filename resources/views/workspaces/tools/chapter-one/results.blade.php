@php
    $currency = $workspace->currency_code ?? 'THB';
    $emptyInstruction = match($toolKey) {
        'current_capital_position' => 'Resources နဲ့ liabilities ကိုဖြည့်ပြီး current net position ကိုစစ်ပါ။',
        'working_capital_calculator' => 'Monthly costs, receivables နဲ့ reserve period ကိုဖြည့်ပါ။',
        'contingency_fund_calculator' => 'Reserve method ကိုရွေးပြီး သက်ဆိုင်ရာ capital သို့မဟုတ် operating cost ကိုဖြည့်ပါ။',
        'partner_contribution_matrix' => 'Partner တစ်ဦးချင်းစီရဲ့ contribution items နဲ့ amounts ကိုဖြည့်ပါ။',
        'funding_gap_calculator' => 'Required capital, confirmed funding နဲ့ reserve ကိုဖြည့်ပါ။',
        'capital_allocation_chart' => 'Capital အသုံးပြုမယ့် categories နဲ့ amounts ကိုထည့်ပါ။',
        default => 'Business data ကိုဖြည့်ပြီး result ကိုစစ်ပါ။',
    };
@endphp

@if(! $result)

    <div class="pbr-empty-result">
        <span aria-hidden="true">↗</span>

        <strong>
            Result စစ်ဖို့ အဆင်သင့်ပါ
        </strong>

        <p>
            {{ $emptyInstruction }}
        </p>

        <small>
            Data ထည့်ပြီး “Result စစ်ရန်” ကိုနှိပ်ပါ။
        </small>
    </div>

@elseif($toolKey === 'current_capital_position')

    <div class="pbr-total-result">
        <span>
            Net Capital Position
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['net_capital_position'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation {{ ($result['net_capital_position'] ?? 0) >= 0 ? 'positive' : 'attention' }}"
        data-result-interpretation="current-capital"
    >
        <span>
            {{
                ($result['net_capital_position'] ?? 0) >= 0
                    ? 'POSITIVE NET POSITION'
                    : 'LIABILITIES EXCEED RESOURCES'
            }}
        </span>

        <p>
            @if(($result['net_capital_position'] ?? 0) >= 0)
                လက်ရှိ Resources က Liabilities ထက် ပိုများနေပါတယ်။
                ဒီ amount ကို Funding Requirement လို့ မယူဘဲ
                current business position အဖြစ်ပဲ အသုံးပြုပါတယ်။
            @else
                လက်ရှိ Liabilities က Resources ထက် ပိုများနေပါတယ်။
                Liquidity, obligations နဲ့ funding plan ကို
                သီးခြား review လုပ်သင့်ပါတယ်။
            @endif
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Total Resources</span>
            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['total_resources'],
                        2
                    )
                }}
            </strong>
        </div>

        <div>
            <span>Total Liabilities</span>
            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['total_liabilities'],
                        2
                    )
                }}
            </strong>
        </div>
    </div>


@elseif($toolKey === 'working_capital_calculator')

    <div class="pbr-total-result">
        <span>
            Working Capital Required
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['working_capital_required'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation"
        data-result-interpretation="working-capital"
    >
        <span>OPERATING BUFFER</span>

        <p>
            ဒီ result က specified reserve period အတွက်
            operations ဆက်လက်လုပ်ဆောင်နိုင်ဖို့ planning amount ပါ။
            Receivables ကို requirement ထဲက နုတ်ထားပြီး
            negative result မဖြစ်အောင် floor 0 သုံးထားပါတယ်။
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Monthly Operating Cost</span>
            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['monthly_operating_cost'],
                        2
                    )
                }}
            </strong>
        </div>

        <div>
            <span>Operating Reserve</span>
            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['operating_reserve'],
                        2
                    )
                }}
            </strong>
        </div>
    </div>

    <div class="pbr-largest-cost">
        <span>Reserve Period</span>

        <strong>
            {{ $result['reserve_months'] }} months
        </strong>

        <p>
            Gross need before receivables:
            {{ $currency }}
            {{
                number_format(
                    $result['gross_working_capital_need'],
                    2
                )
            }}
        </p>
    </div>


@elseif($toolKey === 'contingency_fund_calculator')

    <div class="pbr-total-result">
        <span>
            Contingency Fund
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['contingency_fund'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation"
        data-result-interpretation="contingency-fund"
    >
        <span>PLANNING BUFFER</span>

        <p>
            ဒီ amount က မမျှော်လင့်ထားတဲ့ cost အတွက် သီးသန့်ထားမယ့်
            planning reserve ဖြစ်ပါတယ်။ Base operating capital နဲ့မရောဘဲ
            owner review လုပ်ပြီးမှ final buffer အဖြစ်သတ်မှတ်ပါ။
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Base Capital</span>

            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['base_capital'],
                        2
                    )
                }}
            </strong>
        </div>

        <div>
            <span>Total With Contingency</span>

            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['total_with_contingency'],
                        2
                    )
                }}
            </strong>
        </div>
    </div>

    <div class="pbr-largest-cost">
        <span>Method</span>

        <strong>
            {{
                $result['method'] === 'months'
                    ? 'Operating Months'
                    : 'Percentage'
            }}
        </strong>
    </div>


@elseif($toolKey === 'partner_contribution_matrix')

    <div class="pbr-total-result">
        <span>
            Total Partner Contribution
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['total_contribution'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation"
        data-result-interpretation="partner-contribution"
    >
        <span>CONTRIBUTION MIX — NOT LEGAL OWNERSHIP</span>

        <p>
            Breakdown percentage က ဒီ tool ထဲထည့်ထားတဲ့ contribution value
            အချိုးကိုပဲပြပါတယ်။ Ownership %, voting rights သို့မဟုတ် legal share
            ကို အလိုအလျောက် မသတ်မှတ်ပါ။
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Partners</span>

            <strong>
                {{ $result['partner_count'] }}
            </strong>
        </div>

        <div>
            <span>Important</span>

            <strong>
                Not Ownership %
            </strong>
        </div>
    </div>

    <div class="pbr-breakdown">
        <h3>
            Contribution Breakdown
        </h3>

        @foreach($result['partners'] as $partner)

            <div class="pbr-breakdown-row">
                <div>
                    <span>
                        {{ $partner['name'] }}
                    </span>

                    <strong>
                        {{
                            number_format(
                                $partner['share_percentage'],
                                2
                            )
                        }}%
                    </strong>
                </div>

                <div class="pbr-breakdown-track" aria-hidden="true">
                    <i
                        style="width: {{
                            min(
                                100,
                                $partner[
                                    'share_percentage'
                                ]
                            )
                        }}%"
                    ></i>
                </div>

                <small>
                    {{ $currency }}
                    {{
                        number_format(
                            $partner['total'],
                            2
                        )
                    }}
                </small>
            </div>

        @endforeach
    </div>


@elseif($toolKey === 'funding_gap_calculator')

    <div class="pbr-total-result">
        <span>
            {{
                $result['funding_gap'] > 0
                    ? 'Funding Gap'
                    : (
                        $result['funding_surplus'] > 0
                            ? 'Funding Surplus'
                            : 'Capital Balanced'
                    )
            }}
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['funding_gap'] > 0
                        ? $result['funding_gap']
                        : $result['funding_surplus'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation {{ ($result['funding_gap'] ?? 0) > 0 ? 'attention' : 'positive' }}"
        data-result-interpretation="funding-gap"
    >
        <span>
            {{
                ($result['funding_gap'] ?? 0) > 0
                    ? 'FUNDING ACTION REQUIRED'
                    : 'CAPITAL REQUIREMENT COVERED'
            }}
        </span>

        <p>
            @if(($result['funding_gap'] ?? 0) > 0)
                Required capital နဲ့ reserve ကို အပြည့်အဝ cover လုပ်ဖို့
                အပေါ်မှာပြထားတဲ့ gap amount ထပ်မံလိုအပ်နေပါတယ်။ Funding source,
                timing နဲ့ owner responsibility ကို next action အဖြစ်သတ်မှတ်ပါ။
            @else
                Confirmed funding က current requirement နဲ့ reserve ကို cover
                လုပ်နိုင်ပါတယ်။ Surplus ရှိရင် contingency သို့မဟုတ် staged spending
                အတွက် သီးခြား review လုပ်ပါ။
            @endif
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Capital Required</span>

            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['capital_required'],
                        2
                    )
                }}
            </strong>
        </div>

        <div>
            <span>Capital Secured</span>

            <strong>
                {{ $currency }}
                {{
                    number_format(
                        $result['capital_secured'],
                        2
                    )
                }}
            </strong>
        </div>
    </div>

    <div class="pbr-largest-cost">
        <span>Funding Coverage</span>

        <strong>
            {{
                number_format(
                    $result['coverage_percentage'],
                    2
                )
            }}%
        </strong>
    </div>


@elseif($toolKey === 'capital_allocation_chart')

    <div class="pbr-total-result">
        <span>
            Total Capital Allocated
        </span>

        <strong>
            {{ $currency }}
            {{
                number_format(
                    $result['total_allocated'],
                    2
                )
            }}
        </strong>
    </div>

    <div
        class="pbr-result-interpretation"
        data-result-interpretation="capital-allocation"
    >
        <span>CAPITAL USE MIX</span>

        <p>
            ဒီ breakdown က planned capital ကို ဘယ်နေရာတွေမှာသုံးမလဲဆိုတာ
            နှိုင်းယှဉ်ပြပါတယ်။ Largest allocation ကို business priority,
            timing နဲ့ expected outcome တို့နဲ့ ပြန်လည်စစ်ဆေးပါ။
        </p>
    </div>

    <div class="pbr-result-stats">
        <div>
            <span>Capital Uses</span>

            <strong>
                {{ $result['allocation_count'] }}
            </strong>
        </div>

        <div>
            <span>Largest Allocation</span>

            <strong>
                {{
                    $result['largest_allocation']['name']
                    ?? '—'
                }}
            </strong>
        </div>
    </div>

    <div class="pbr-breakdown">
        <h3>
            Allocation Breakdown
        </h3>

        @foreach(
            $result['allocations']
            as $allocation
        )

            <div class="pbr-breakdown-row">
                <div>
                    <span>
                        {{ $allocation['name'] }}
                    </span>

                    <strong>
                        {{
                            number_format(
                                $allocation['percentage'],
                                2
                            )
                        }}%
                    </strong>
                </div>

                <div class="pbr-breakdown-track" aria-hidden="true">
                    <i
                        style="width: {{
                            min(
                                100,
                                $allocation['percentage']
                            )
                        }}%"
                    ></i>
                </div>

                <small>
                    {{ $currency }}
                    {{
                        number_format(
                            $allocation['amount'],
                            2
                        )
                    }}
                </small>
            </div>

        @endforeach
    </div>

@endif
