@php
    $capitalSteps = collect($capitalWorkflow['steps'] ?? []);
    $capitalSummary = $capitalWorkflow['current_rule']['summary'] ?? [];

    $capitalStepCount = (int) ($capitalWorkflow['step_count'] ?? 0);
    $capitalApprovedCount = (int) ($capitalWorkflow['approved_count'] ?? 0);
    $capitalWorkingCount = (int) ($capitalWorkflow['working_count'] ?? 0);

    $capitalProgress = $capitalStepCount > 0
        ? min(100, round(($capitalApprovedCount / $capitalStepCount) * 100))
        : 0;

    $capitalNextStep = $capitalWorkflow['next_step'] ?? null;
    $capitalCanManage = (bool) ($capitalWorkflow['can_manage'] ?? false);

    $capitalRequired = (float) ($capitalSummary['capital_required'] ?? 0);
    $capitalSecured = (float) ($capitalSummary['capital_secured'] ?? 0);
    $capitalGap = (float) ($capitalSummary['funding_gap'] ?? 0);
    $capitalCoverage = (float) ($capitalSummary['funding_coverage_percentage'] ?? 0);

    $capitalPrimaryValue = $workspace->business_stage === 'new'
        ? (float) ($capitalSummary['startup_capital'] ?? 0)
        : (float) ($capitalSummary['current_net_capital_position'] ?? 0);

    $capitalPrimaryMm = $workspace->business_stage === 'new'
        ? 'စတင်ရန်လိုသော မတည်ငွေ'
        : 'လက်ရှိ အသားတင်မတည်ငွေ';

    $capitalPrimaryEn = $workspace->business_stage === 'new'
        ? 'Startup Capital'
        : 'Net Capital Position';

    $capitalCurrentRevision = $capitalWorkflow['current_rule']['revision'] ?? null;

    $purposeByTool = [
        'startup_capital_planner' =>
            'လုပ်ငန်းစဖို့လိုမယ့် ကုန်ကျစရိတ်၊ Funding နဲ့ Due Date ကို စီစဉ်ပါ။',
        'current_capital_position' =>
            'ရှိပြီးသား Resources နဲ့ Liabilities ကို စုစည်းပြီး လက်ရှိ Capital Position ကိုသတ်မှတ်ပါ။',
        'working_capital_calculator' =>
            'နေ့စဉ်လုပ်ငန်းလည်ပတ်ဖို့လိုမယ့် Working Capital ကိုတွက်ပါ။',
        'contingency_fund_calculator' =>
            'မမျှော်လင့်ထားတဲ့အခြေအနေတွေအတွက် Reserve Buffer ကိုသတ်မှတ်ပါ။',
        'partner_contribution_matrix' =>
            'Partner တစ်ယောက်ချင်းစီရဲ့ Capital Contribution ကို သီးခြားမှတ်တမ်းတင်ပါ။',
        'funding_gap_calculator' =>
            'လိုအပ်တဲ့ Capital နဲ့ ရရှိထားတဲ့ Funding ကြား Gap ကိုစစ်ပါ။',
        'capital_allocation_chart' =>
            'ရရှိထားတဲ့ Capital ကို ဘယ်နေရာတွေမှာ အသုံးပြုမလဲ ခွဲဝေသတ်မှတ်ပါ။',
    ];

    $nextCapitalUrl = $capitalNextStep['url'] ?? null;

    if (
        $nextCapitalUrl
        && ! empty($capitalNextStep['draft_id'])
    ) {
        $nextCapitalUrl .=
            (str_contains($nextCapitalUrl, '?') ? '&' : '?')
            .'session='
            .urlencode((string) $capitalNextStep['draft_id']);
    }
@endphp

<section
    class="pbr-capital-command"
    aria-labelledby="capital-command-title"
>
    <div class="pbr-capital-command-head">
        <div>
            <span class="pbr-capital-command-kicker">
                CAPITAL & FUNDING
            </span>

            <h2 id="capital-command-title">
                မတည်ငွေနဲ့ Funding ကို အဆင့်လိုက် စီမံပါ
            </h2>

            <p>
                Calculator တစ်ခုချင်းစီ သီးသန့်သုံးတာမဟုတ်ဘဲ
                <strong>Business Data → Working Change → Review → Approved Rule</strong>
                အဖြစ် အစဉ်လိုက်ချိတ်ဆက်ထားပါတယ်။
            </p>
        </div>

        <div class="pbr-capital-command-progress-card">
            <div class="pbr-capital-command-progress-top">
                <span>Current Rule Progress</span>
                <strong>
                    {{ $capitalApprovedCount }} / {{ $capitalStepCount }}
                </strong>
            </div>

            <div
                class="pbr-capital-progress-track"
                role="progressbar"
                aria-valuenow="{{ $capitalProgress }}"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <span style="width: {{ $capitalProgress }}%"></span>
            </div>

            <small>
                {{ $capitalProgress }}% approved
                @if($capitalWorkingCount > 0)
                    · {{ $capitalWorkingCount }} Working Change
                @endif
            </small>
        </div>
    </div>

    <div class="pbr-capital-command-summary">
        <article>
            <span>{{ $capitalPrimaryMm }}</span>
            <small>{{ $capitalPrimaryEn }}</small>
            <strong>
                {{ $currency }}
                {{ number_format($capitalPrimaryValue, 2) }}
            </strong>
        </article>

        <article>
            <span>လိုအပ်သော မတည်ငွေ</span>
            <small>Capital Required</small>
            <strong>
                {{ $currency }}
                {{ number_format($capitalRequired, 2) }}
            </strong>
        </article>

        <article>
            <span>ရရှိထားသော ရင်းနှီးငွေ</span>
            <small>Capital Secured</small>
            <strong>
                {{ $currency }}
                {{ number_format($capitalSecured, 2) }}
            </strong>
        </article>

        <article class="{{ $capitalGap > 0 ? 'needs-action' : 'healthy' }}">
            <span>လိုအပ်နေသေးသော Funding</span>
            <small>Funding Gap</small>
            <strong>
                {{ $currency }}
                {{ number_format($capitalGap, 2) }}
            </strong>
        </article>

        <article>
            <span>Funding Coverage</span>
            <small>Coverage</small>
            <strong>
                {{ number_format($capitalCoverage, 1) }}%
            </strong>
        </article>
    </div>

    @if($capitalCanManage && $capitalNextStep)
        <div
            class="pbr-capital-next-action"
            data-capital-manager-action
        >
            <div>
                <span>
                    {{ $capitalNextStep['state'] === 'working'
                        ? 'REVIEW REQUIRED'
                        : 'NEXT OPERATING STEP' }}
                </span>

                <strong>
                    {{ $capitalNextStep['title_mm'] }}
                </strong>

                <small>
                    {{ $capitalNextStep['title_en'] }}
                </small>

                @if(
                    $capitalNextStep['state'] === 'working'
                    && ! empty($capitalNextStep['draft_name'])
                )
                    <p>
                        Working Change:
                        <b>{{ $capitalNextStep['draft_name'] }}</b>
                    </p>
                @else
                    <p>
                        ဒီအဆင့်ကို အရင်သတ်မှတ်ပြီးမှ နောက် Capital Data တွေကို
                        ဆက်ချိတ်တာ ပိုရှင်းပါတယ်။
                    </p>
                @endif
            </div>

            <a href="{{ $nextCapitalUrl }}">
                {{ $capitalNextStep['state'] === 'working'
                    ? 'Working Change ကို Review လုပ်ရန်'
                    : 'နောက်တစ်ဆင့် စတင်ရန်' }}
                <span>→</span>
            </a>
        </div>
    @elseif($capitalCanManage && ($capitalWorkflow['is_complete'] ?? false))
        <div class="pbr-capital-complete-state">
            <span>✓</span>
            <div>
                <strong>
                    Capital & Funding Current Rule ပြည့်စုံနေပါတယ်
                </strong>
                <p>
                    အဆင့်အားလုံး approved ဖြစ်ပြီး Review လုပ်ရန် Working Change မရှိပါ။
                    Business အခြေအနေပြောင်းလာရင် သက်ဆိုင်ရာ Rule ကို Update လုပ်နိုင်ပါတယ်။
                </p>
            </div>
        </div>
    @else
        <div
            class="pbr-capital-partner-state"
            data-capital-partner-view
        >
            <span>Approved Rules Only</span>
            <p>
                Partner View မှာ Owner/Admin အတည်ပြုပြီး လက်ရှိအသုံးပြုနေတဲ့
                Capital Rules ကိုသာ ပြထားပါတယ်။ Working Draft နဲ့ private scenario
                data မပါပါဘူး။
            </p>
        </div>
    @endif

    <div class="pbr-capital-step-list">
        @foreach($capitalSteps as $step)
            @php
                $isAvailableToPartner =
                    $capitalCanManage
                    || (bool) $step['is_approved'];

                $stateClass = $step['state'];

                if (
                    ! $capitalCanManage
                    && ! $step['is_approved']
                ) {
                    $stateClass = 'inactive';
                }

                $stateLabel = match (true) {
                    ! $capitalCanManage && ! $step['is_approved']
                        => 'Rule မရှိသေး',

                    (bool) $step['has_working_change']
                        && (bool) $step['is_approved']
                        => 'Active + Working Change',

                    $step['state'] === 'working'
                        => 'Working Draft',

                    $step['state'] === 'approved'
                        => 'Active Rule',

                    default
                        => 'Setup လိုသည်',
                };
            @endphp

            <article
                class="pbr-capital-step {{ $stateClass }}"
                data-capital-step="{{ $step['tool_key'] }}"
            >
                <div class="pbr-capital-step-number">
                    {{ str_pad((string) $step['position'], 2, '0', STR_PAD_LEFT) }}
                </div>

                <div class="pbr-capital-step-body">
                    <div class="pbr-capital-step-top">
                        <div>
                            <strong>{{ $step['title_mm'] }}</strong>
                            <small>{{ $step['title_en'] }}</small>
                        </div>

                        <span class="pbr-capital-step-state">
                            {{ $stateLabel }}
                        </span>
                    </div>

                    <p>
                        {{ $purposeByTool[$step['tool_key']] ?? '' }}
                    </p>

                    <div class="pbr-capital-step-meta">
                        @if($step['is_approved'])
                            <span>
                                ✓ Approved
                                @if($step['approved_revision'])
                                    · Rev {{ $step['approved_revision'] }}
                                @endif
                            </span>
                        @endif

                        @if(
                            $capitalCanManage
                            && $step['has_working_change']
                        )
                            <span class="working">
                                Working Change ရှိသည်
                            </span>
                        @endif
                    </div>
                </div>

                <div class="pbr-capital-step-action">
                    @if($isAvailableToPartner)
                        @php
                            $stepUrl = $step['url'];

                            if (
                                $capitalCanManage
                                && ! empty($step['draft_id'])
                            ) {
                                $stepUrl .=
                                    (str_contains($stepUrl, '?') ? '&' : '?')
                                    .'session='
                                    .urlencode((string) $step['draft_id']);
                            }
                        @endphp

                        <a href="{{ $stepUrl }}">
                            @if(!$capitalCanManage)
                                ကြည့်ရန်
                            @elseif($step['has_working_change'])
                                Review
                            @elseif($step['is_approved'])
                                Update
                            @else
                                Setup
                            @endif
                            <span>→</span>
                        </a>
                    @else
                        <span class="pbr-capital-step-locked">
                            Not active
                        </span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if($capitalCurrentRevision)
        <footer class="pbr-capital-current-rule-foot">
            <div>
                <span>APPROVED CURRENT CAPITAL RULE</span>
                <strong>Revision {{ $capitalCurrentRevision }}</strong>
            </div>

            <p>
                အပေါ်က Financial metrics တွေဟာ Working Draft မဟုတ်ဘဲ
                approved Capital snapshot ကိုပဲ source of truth အဖြစ်သုံးထားပါတယ်။
            </p>
        </footer>
    @endif
</section>
