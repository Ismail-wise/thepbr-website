@php
    $workflowSteps = collect($capitalWorkflow['steps'] ?? []);

    $currentWorkflowStep = $workflowSteps->firstWhere(
        'tool_key',
        $tool->tool_key
    );

    $currentPosition = (int) (
        $currentWorkflowStep['position'] ?? 0
    );

    $stepCount = (int) (
        $capitalWorkflow['step_count'] ?? 0
    );

    $approvedCount = (int) (
        $capitalWorkflow['approved_count'] ?? 0
    );

    $workingCount = (int) (
        $capitalWorkflow['working_count'] ?? 0
    );

    $currentHasApproved = (bool) (
        $currentWorkflowStep['is_approved'] ?? false
    );

    $currentHasWorking = (bool) (
        $currentWorkflowStep['has_working_change'] ?? false
    );

    $currentStateLabel = match (true) {
        $currentHasApproved && $currentHasWorking =>
            'Active Rule + Working Change',

        $currentHasWorking =>
            'Working Change',

        $currentHasApproved =>
            'Active Rule',

        default =>
            'Setup Required',
    };

    $currentStateClass = match (true) {
        $currentHasWorking => 'working',
        $currentHasApproved => 'approved',
        default => 'setup',
    };

    $nextSequenceStep = $workflowSteps
        ->first(
            fn (array $step): bool =>
                (int) $step['position']
                    > $currentPosition
        );

    $nextVisibleStep = null;

    if ($canManage) {
        $nextVisibleStep = $nextSequenceStep;
    } else {
        $nextVisibleStep = $workflowSteps
            ->first(
                fn (array $step): bool =>
                    (int) $step['position']
                        > $currentPosition
                    && (bool) $step['is_approved']
            );
    }

    $nextStepUrl = $nextVisibleStep['url'] ?? null;

    if (
        $canManage
        && $nextStepUrl
        && ! empty($nextVisibleStep['draft_id'])
    ) {
        $nextStepUrl .=
            (str_contains($nextStepUrl, '?') ? '&' : '?')
            .'session='
            .urlencode(
                (string) $nextVisibleStep['draft_id']
            );
    }

    $connectionInfo = match ($tool->tool_key) {
        'startup_capital_planner' => [
            'label' => 'SOURCE DATA',
            'title' => 'နောက် Capital Steps တွေအတွက် အခြေခံ Plan',
            'body' =>
                'Approved Startup Capital နဲ့ confirmed startup funding ကို Working Capital, Contingency, Funding Position နဲ့ Allocation flow မှာ ချိတ်ဆက်အသုံးပြုနိုင်ပါတယ်။',
        ],

        'current_capital_position' => [
            'label' => 'OPERATING DIAGNOSTIC',
            'title' => 'လက်ရှိ Capital Position ကို သီးခြားထိန်းထားသည်',
            'body' =>
                'Net Capital Position က existing business ရဲ့ diagnostic ဖြစ်ပါတယ်။ Required Funding ထဲကို အလိုအလျောက် ပေါင်း/နုတ်မလုပ်ပါဘူး။',
        ],

        'working_capital_calculator' => [
            'label' => 'CONNECTED OUTPUT',
            'title' => 'Working Capital က နောက် Steps တွေဆီ ဆက်သွားမယ်',
            'body' =>
                'Approved Working Capital Required နဲ့ Monthly Operating Cost ကို Contingency, Funding Position နဲ့ နောက် Financial modules တွေက approved source အဖြစ် အသုံးပြုနိုင်ပါတယ်။',
        ],

        'contingency_fund_calculator' => [
            'label' => 'APPROVED PREFILL',
            'title' => 'Approved Capital Data က blank fields ကို ကူဖြည့်ပေးသည်',
            'body' =>
                'User က value မထည့်ရသေးတဲ့အခါ Approved Startup / Working Capital နဲ့ Monthly Operating Cost ကိုပဲ prefill source အဖြစ် အသုံးပြုပါတယ်။ Draft data ကို Current Rule အဖြစ် မယူပါဘူး။',
        ],

        'partner_contribution_matrix' => [
            'label' => 'BUSINESS INVARIANT',
            'title' => 'Contribution ကို Ownership နဲ့ မရောပါ',
            'body' =>
                'Approved Partner Capital Contributions က Funding Position ကို ကူညီပေးနိုင်ပေမယ့် Contribution Share က Ownership Share အဖြစ် အလိုအလျောက်မပြောင်းပါဘူး။',
        ],

        'funding_gap_calculator' => [
            'label' => 'APPROVED PREFILL',
            'title' => 'Capital Requirement နဲ့ Funding Sources ကို ချိတ်ထားသည်',
            'body' =>
                'Blank fields တွေကို Approved Capital Requirement, Partner Contributions နဲ့ approved funding data ကသာ ကူဖြည့်ပေးပါတယ်။ Working Draft က Current Funding Position ကို မပြောင်းပါဘူး။',
        ],

        'capital_allocation_chart' => [
            'label' => 'CONNECTED ALLOCATION',
            'title' => 'Approved Capital Needs က Allocation starting point ဖြစ်သည်',
            'body' =>
                'Approved Startup Capital, Working Capital နဲ့ Contingency Reserve ကို Allocation starting data အဖြစ် ချိတ်ထားပါတယ်။ User က Working Change အသစ်အဖြစ် ပြန်ပြင်နိုင်ပါတယ်။',
        ],

        default => [
            'label' => 'CAPITAL WORKFLOW',
            'title' => 'Approved data only',
            'body' =>
                'Capital & Funding Current Rule ကို approved outputs အပေါ်မှာပဲ တည်ဆောက်ထားပါတယ်။',
        ],
    };
@endphp

<section
    class="pbr-capital-tool-flow"
    data-capital-tool-workflow
>
    <div class="pbr-capital-tool-flow-head">
        <div>
            <a
                href="{{ route('workspaces.tools.index', $workspace) }}#capital-command-title"
                class="pbr-capital-tool-flow-back"
            >
                ← Capital Command Center
            </a>

            <div class="pbr-capital-tool-flow-position">
                <span>
                    CAPITAL WORKFLOW
                </span>

                <strong>
                    Step {{ $currentPosition }}
                    of {{ $stepCount }}
                </strong>
            </div>
        </div>

        <div
            class="pbr-capital-tool-current-state {{ $currentStateClass }}"
            data-current-capital-state
        >
            <span>Current Step</span>
            <strong>{{ $currentStateLabel }}</strong>

            @if($currentWorkflowStep['approved_revision'] ?? null)
                <small>
                    Active Revision
                    {{ $currentWorkflowStep['approved_revision'] }}
                </small>
            @endif
        </div>
    </div>

    <div
        class="pbr-capital-tool-step-track"
        aria-label="Capital workflow steps"
    >
        @foreach($workflowSteps as $workflowStep)
            @php
                $isCurrent =
                    $workflowStep['tool_key']
                    === $tool->tool_key;

                $isAccessible =
                    $canManage
                    || (bool) $workflowStep['is_approved'];

                $stepState = match (true) {
                    (bool) $workflowStep['has_working_change']
                        && $canManage
                        => 'working',

                    (bool) $workflowStep['is_approved']
                        => 'approved',

                    default
                        => 'setup',
                };

                $workflowUrl =
                    $workflowStep['url'];

                if (
                    $canManage
                    && ! empty($workflowStep['draft_id'])
                ) {
                    $workflowUrl .=
                        (
                            str_contains($workflowUrl, '?')
                                ? '&'
                                : '?'
                        )
                        .'session='
                        .urlencode(
                            (string) $workflowStep['draft_id']
                        );
                }
            @endphp

            @if($isAccessible)
                <a
                    href="{{ $workflowUrl }}"
                    class="pbr-capital-tool-step {{ $stepState }} {{ $isCurrent ? 'current' : '' }}"
                    data-tool-step="{{ $workflowStep['tool_key'] }}"
                    @if($isCurrent)
                        aria-current="step"
                    @endif
                >
                    <b>
                        {{ str_pad(
                            (string) $workflowStep['position'],
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) }}
                    </b>

                    <span>
                        {{ $workflowStep['title_en'] }}
                    </span>

                    <i>
                        @if(
                            $workflowStep['has_working_change']
                            && $canManage
                        )
                            Review
                        @elseif($workflowStep['is_approved'])
                            Active
                        @else
                            Setup
                        @endif
                    </i>
                </a>
            @else
                <div
                    class="pbr-capital-tool-step setup locked"
                    data-tool-step="{{ $workflowStep['tool_key'] }}"
                >
                    <b>
                        {{ str_pad(
                            (string) $workflowStep['position'],
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) }}
                    </b>

                    <span>
                        {{ $workflowStep['title_en'] }}
                    </span>

                    <i>Not active</i>
                </div>
            @endif
        @endforeach
    </div>

    <div class="pbr-capital-tool-connection">
        <div>
            <span>
                {{ $connectionInfo['label'] }}
            </span>

            <strong>
                {{ $connectionInfo['title'] }}
            </strong>

            <p>
                {{ $connectionInfo['body'] }}
            </p>
        </div>

        <div class="pbr-capital-tool-flow-stats">
            <div>
                <span>Active Rules</span>
                <strong>{{ $approvedCount }}/{{ $stepCount }}</strong>
            </div>

            @if($canManage)
                <div>
                    <span>Working Changes</span>
                    <strong>{{ $workingCount }}</strong>
                </div>
            @endif
        </div>
    </div>

    @if($canManage && $nextVisibleStep && $nextStepUrl)
        <div class="pbr-capital-tool-next">
            <div>
                <span>NEXT CAPITAL STEP</span>
                <strong>
                    {{ $nextVisibleStep['title_mm'] }}
                </strong>
                <small>
                    {{ $nextVisibleStep['title_en'] }}
                </small>
            </div>

            <a href="{{ $nextStepUrl }}">
                @if($nextVisibleStep['has_working_change'])
                    Working Change Review
                @elseif($nextVisibleStep['is_approved'])
                    Rule ကို Update
                @else
                    Setup စတင်ရန်
                @endif
                →
            </a>
        </div>
    @endif
</section>
