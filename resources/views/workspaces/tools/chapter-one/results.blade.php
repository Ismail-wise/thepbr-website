@php
    $currency = $workspace->currency_code ?? 'THB';
@endphp

@if(! $result)

    <div class="pbr-empty-result">
        <strong>
            Your result will appear here.
        </strong>

        <p>
            Data ထည့်ပြီး Calculate ကိုနှိပ်ပါ။
        </p>
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

                <div class="pbr-breakdown-track">
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

                <div class="pbr-breakdown-track">
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
