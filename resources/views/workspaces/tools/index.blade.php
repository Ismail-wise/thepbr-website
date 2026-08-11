@extends('layouts.student-portal')

@section('title', 'PBR Business Tools')

@section('content')

<section class="pbr-tools-section">
    <div class="portal-wrap">

        <div class="pbr-tools-hero">

            <div class="pbr-tools-hero-copy">

                <a
                    href="{{ route('workspaces.show', $workspace) }}"
                    class="pbr-tools-back"
                >
                    ← Back to Workspace
                </a>

                <span class="portal-kicker">
                    PBR Business System
                </span>

                <h1>
                    Partnership Business Tools
                </h1>

                <p>
                    Partnership စတင်ဖို့၊ ရှိပြီးသား Partnership ကို
                    control လုပ်ဖို့နဲ့ partners တွေကြား
                    financial, ownership, governance,
                    exit နဲ့ dispute rules တွေကို
                    practical tools တွေနဲ့ တည်ဆောက်ပါ။
                </p>

            </div>

            <div class="pbr-workspace-badge">

                <span>Current Workspace</span>

                <strong>
                    {{ $workspace->business_name ?: $workspace->name }}
                </strong>

            </div>

        </div>


        <section class="pbr-context-card">

            <div class="pbr-context-header">

                <div>

                    <span class="portal-kicker">
                        Business Context
                    </span>

                    <h2>
                        Partnership Settings
                    </h2>

                    <p>
                        ဒီ settings ကို Chapter tools တွေအားလုံးမှာ
                        default အနေနဲ့ ပြန်အသုံးပြုပါမယ်။
                    </p>

                </div>


                @if($workspace->hasBusinessContext())

                    <span class="pbr-ready-badge">
                        Ready
                    </span>

                @else

                    <span class="pbr-setup-badge">
                        Setup Required
                    </span>

                @endif

            </div>


            @if($canManageContext)

                <form
                    method="POST"
                    action="{{ route(
                        'workspaces.business-context.update',
                        $workspace
                    ) }}"
                >

                    @csrf
                    @method('PUT')

                    <div class="pbr-context-grid">

                        <div class="pbr-tools-field">

                            <label for="business_stage">
                                Partnership Stage
                            </label>

                            <select
                                id="business_stage"
                                name="business_stage"
                                required
                            >

                                <option value="">
                                    Select your current stage
                                </option>

                                @foreach(
                                    $businessStages as $value => $label
                                )

                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            old(
                                                'business_stage',
                                                $workspace->business_stage
                                            ) === $value
                                        )
                                    >
                                        {{ $label }}
                                    </option>

                                @endforeach

                            </select>

                            <small>
                                Partnership အသစ်စတင်ဖို့လား၊
                                ရှိပြီးသား Partnership ကို
                                manage လုပ်ဖို့လား ရွေးပါ။
                            </small>

                        </div>


                        <div class="pbr-tools-field">

                            <label for="currency_code">
                                Primary Currency
                            </label>

                            <select
                                id="currency_code"
                                name="currency_code"
                                required
                            >

                                <option value="">
                                    Select your currency
                                </option>

                                @foreach(
                                    $currencies as $value => $label
                                )

                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            old(
                                                'currency_code',
                                                $workspace->currency_code
                                            ) === $value
                                        )
                                    >
                                        {{ $label }}
                                    </option>

                                @endforeach

                            </select>

                            <small>
                                Financial calculators နဲ့ charts တွေအတွက်
                                default currency ဖြစ်ပါတယ်။
                            </small>

                        </div>

                    </div>


                    <div class="pbr-context-actions">

                        <button
                            type="submit"
                            class="pbr-tools-primary-button"
                        >
                            Save Partnership Settings
                        </button>

                    </div>

                </form>

            @else

                <div class="pbr-context-readonly">

                    <div>
                        <span>Partnership Stage</span>

                        <strong>
                            {{
                                $businessStages[
                                    $workspace->business_stage
                                ] ?? 'Not selected'
                            }}
                        </strong>
                    </div>


                    <div>
                        <span>Primary Currency</span>

                        <strong>
                            {{
                                $workspace->currency_code
                                ?? 'Not selected'
                            }}
                        </strong>
                    </div>

                </div>

                <p class="pbr-owner-note">
                    Workspace Owner က Partnership Settings ကို
                    manage လုပ်နိုင်ပါတယ်။
                </p>

            @endif

        </section>


        @php
            $capitalCurrency =
                $workspace->currency_code ?? 'THB';

            $chapterOneOutputs =
                $chapterOneSummary['outputs'] ?? [];

            $hasChapterOneOutputs =
                count($chapterOneOutputs) > 0;

            $capitalGap =
                $chapterOneSummary['funding_gap'] ?? 0;

            $capitalSurplus =
                $chapterOneSummary['funding_surplus'] ?? 0;
        @endphp


        <section class="pbr-capital-overview">

            <div class="pbr-capital-overview-head">

                <div>
                    <span class="portal-kicker">
                        Chapter 1 · Capital Overview
                    </span>

                    <h2>
                        Partnership Capital Position
                    </h2>

                    <p>
                        Saved scenarios ထဲက Workspace Output
                        အဖြစ်ရွေးထားတဲ့ data တွေကိုပဲ
                        ဒီ Overview မှာ ချိတ်ဆက်အသုံးပြုထားပါတယ်။
                    </p>
                </div>

                @if($hasChapterOneOutputs)

                    <span class="pbr-ready-badge">
                        Connected
                    </span>

                @else

                    <span class="pbr-setup-badge">
                        No Outputs Yet
                    </span>

                @endif

            </div>


            <div class="pbr-capital-main-grid">

                <article class="pbr-capital-main-card">

                    <span>
                        Total Capital Required
                    </span>

                    <strong>
                        {{ $capitalCurrency }}
                        {{
                            number_format(
                                $chapterOneSummary[
                                    'capital_required'
                                ] ?? 0,
                                2
                            )
                        }}
                    </strong>

                    <small>
                        Startup + Working Capital +
                        Contingency Reserve
                    </small>

                </article>


                <article class="pbr-capital-main-card">

                    <span>
                        Partner Capital Committed
                    </span>

                    <strong>
                        {{ $capitalCurrency }}
                        {{
                            number_format(
                                $chapterOneSummary[
                                    'partner_capital'
                                ] ?? 0,
                                2
                            )
                        }}
                    </strong>

                    <small>
                        From Partner Contribution Matrix
                    </small>

                </article>


                <article class="pbr-capital-main-card">

                    <span>
                        Other Funding
                    </span>

                    <strong>
                        {{ $capitalCurrency }}
                        {{
                            number_format(
                                $chapterOneSummary[
                                    'other_funding'
                                ] ?? 0,
                                2
                            )
                        }}
                    </strong>

                    <small>
                        Loans or other confirmed funding
                    </small>

                </article>


                <article
                    class="pbr-capital-main-card
                    {{ $capitalGap > 0
                        ? 'pbr-capital-gap'
                        : 'pbr-capital-covered'
                    }}"
                >

                    <span>
                        {{
                            $capitalGap > 0
                                ? 'Funding Gap'
                                : (
                                    $capitalSurplus > 0
                                        ? 'Funding Surplus'
                                        : 'Funding Position'
                                )
                        }}
                    </span>

                    <strong>
                        {{ $capitalCurrency }}
                        {{
                            number_format(
                                $capitalGap > 0
                                    ? $capitalGap
                                    : $capitalSurplus,
                                2
                            )
                        }}
                    </strong>

                    <small>
                        {{
                            $capitalGap > 0
                                ? 'Additional funding still required'
                                : (
                                    $capitalSurplus > 0
                                        ? 'Capital exceeds current requirement'
                                        : 'Current requirement and funding are balanced'
                                )
                        }}
                    </small>

                </article>

            </div>


            <div class="pbr-capital-breakdown-head">

                <div>
                    <h3>
                        Capital Requirement Breakdown
                    </h3>

                    <p>
                        Tool တစ်ခုချင်းစီက selected
                        Workspace Output ကိုစုပြထားပါတယ်။
                    </p>
                </div>

            </div>


            <div class="pbr-capital-breakdown-grid">

                @if($workspace->business_stage === 'new')

                    <article class="pbr-capital-source-card">

                        <div>
                            <span>
                                Startup Capital
                            </span>

                            <strong>
                                {{ $capitalCurrency }}
                                {{
                                    number_format(
                                        $chapterOneSummary[
                                            'startup_capital'
                                        ] ?? 0,
                                        2
                                    )
                                }}
                            </strong>
                        </div>

                        @if(
                            isset(
                                $chapterOneOutputs[
                                    'startup_capital_planner'
                                ]
                            )
                        )

                            <span class="pbr-output-connected">
                                Workspace Output
                            </span>

                        @else

                            <span class="pbr-output-missing">
                                Select a Scenario
                            </span>

                        @endif

                    </article>

                @endif


                @if($workspace->business_stage === 'existing')

                    <article class="pbr-capital-source-card">

                        <div>
                            <span>
                                Current Net Capital Position
                            </span>

                            <strong>
                                {{ $capitalCurrency }}
                                {{
                                    number_format(
                                        $chapterOneSummary[
                                            'current_net_capital_position'
                                        ] ?? 0,
                                        2
                                    )
                                }}
                            </strong>
                        </div>

                        @if(
                            isset(
                                $chapterOneOutputs[
                                    'current_capital_position'
                                ]
                            )
                        )

                            <span class="pbr-output-connected">
                                Workspace Output
                            </span>

                        @else

                            <span class="pbr-output-missing">
                                Select a Scenario
                            </span>

                        @endif

                    </article>

                @endif


                <article class="pbr-capital-source-card">

                    <div>
                        <span>
                            Working Capital
                        </span>

                        <strong>
                            {{ $capitalCurrency }}
                            {{
                                number_format(
                                    $chapterOneSummary[
                                        'working_capital'
                                    ] ?? 0,
                                    2
                                )
                            }}
                        </strong>
                    </div>

                    @if(
                        isset(
                            $chapterOneOutputs[
                                'working_capital_calculator'
                            ]
                        )
                    )

                        <span class="pbr-output-connected">
                            Workspace Output
                        </span>

                    @else

                        <span class="pbr-output-missing">
                            Select a Scenario
                        </span>

                    @endif

                </article>


                <article class="pbr-capital-source-card">

                    <div>
                        <span>
                            Contingency Reserve
                        </span>

                        <strong>
                            {{ $capitalCurrency }}
                            {{
                                number_format(
                                    $chapterOneSummary[
                                        'contingency_fund'
                                    ] ?? 0,
                                    2
                                )
                            }}
                        </strong>
                    </div>

                    @if(
                        isset(
                            $chapterOneOutputs[
                                'contingency_fund_calculator'
                            ]
                        )
                    )

                        <span class="pbr-output-connected">
                            Workspace Output
                        </span>

                    @else

                        <span class="pbr-output-missing">
                            Select a Scenario
                        </span>

                    @endif

                </article>


                <article class="pbr-capital-source-card">

                    <div>
                        <span>
                            Partner Contributions
                        </span>

                        <strong>
                            {{ $capitalCurrency }}
                            {{
                                number_format(
                                    $chapterOneSummary[
                                        'partner_capital'
                                    ] ?? 0,
                                    2
                                )
                            }}
                        </strong>
                    </div>

                    @if(
                        isset(
                            $chapterOneOutputs[
                                'partner_contribution_matrix'
                            ]
                        )
                    )

                        <span class="pbr-output-connected">
                            Workspace Output
                        </span>

                    @else

                        <span class="pbr-output-missing">
                            Select a Scenario
                        </span>

                    @endif

                </article>

            </div>


            @unless($hasChapterOneOutputs)

                <div class="pbr-capital-empty-note">

                    <strong>
                        Start by creating a scenario.
                    </strong>

                    <p>
                        Tool တစ်ခုမှာ Scenario ကို Save Draft
                        လုပ်ပြီးနောက် Workspace Output
                        အဖြစ်ရွေးလိုက်ရင် ဒီ Overview ထဲမှာ
                        automatically ချိတ်ဆက်ပေးပါမယ်။
                    </p>

                </div>

            @endunless

        </section>


        <div class="pbr-tools-stats">

            <div class="pbr-stat-card">

                <strong>
                    {{ $chapters->count() }}
                </strong>

                <span>
                    Business Chapters
                </span>

            </div>


            <div class="pbr-stat-card">

                <strong>
                    {{
                        $chapters->sum(
                            fn ($chapter) =>
                                $chapter->tools->count()
                        )
                    }}
                </strong>

                <span>
                    Practical Tools
                </span>

            </div>


            <div class="pbr-stat-card">

                <strong>
                    {{
                        $workspace
                            ->acceptedMemberships
                            ->count() + 1
                    }}
                </strong>

                <span>
                    Workspace Partners
                </span>

            </div>

        </div>


        <div class="pbr-system-heading">

            <span class="portal-kicker">
                10-Chapter Business System
            </span>

            <h2>
                Build, Control & Protect Your Partnership
            </h2>

            <p>
                Calculator, planner, simulator, matrix,
                dashboard နဲ့ tracker တွေကို
                Chapter တစ်ခုချင်းစီအလိုက် အသုံးပြုနိုင်ပါတယ်။
            </p>

        </div>


        <div class="pbr-chapter-list">

            @foreach($chapters as $chapter)

                <details
                    class="pbr-chapter-card"
                    @if($chapter->chapter_number === 1)
                        open
                    @endif
                >

                    <summary>

                        <div class="pbr-chapter-number">

                            {{
                                str_pad(
                                    (string) $chapter->chapter_number,
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                )
                            }}

                        </div>


                        <div class="pbr-chapter-title">

                            <span>
                                {{
                                    str_replace(
                                        '_',
                                        ' ',
                                        strtoupper($chapter->phase)
                                    )
                                }}
                            </span>

                            <h3>
                                {{ $chapter->title_en }}
                            </h3>

                            <p>
                                {{ $chapter->title_mm }}
                            </p>

                        </div>


                        <div class="pbr-tool-count">

                            {{ $chapter->tools->count() }}
                            Tools

                        </div>

                    </summary>


                    <div class="pbr-chapter-body">

                        <p class="pbr-chapter-description">
                            {{ $chapter->description }}
                        </p>


                        <div class="pbr-tool-grid">

                            @foreach($chapter->tools as $tool)

                                <article class="pbr-tool-card">

                                    <div class="pbr-tool-top">

                                        <span class="pbr-tool-type">
                                            {{
                                                ucfirst(
                                                    $tool->tool_type
                                                )
                                            }}
                                        </span>


                                        @if(
                                            $chapter->chapter_number === 1
                                        )

                                            <span class="pbr-tool-status ready">
                                                Ready
                                            </span>

                                        @else

                                            <span class="pbr-tool-status">
                                                Planned
                                            </span>

                                        @endif

                                    </div>


                                    <h4>
                                        {{ $tool->title_en }}
                                    </h4>


                                    <div class="pbr-stage-tags">

                                        @if(
                                            $tool->supports_new_business
                                        )

                                            <span>
                                                New Partnership
                                            </span>

                                        @endif


                                        @if(
                                            $tool->supports_existing_business
                                        )

                                            <span>
                                                Existing Business
                                            </span>

                                        @endif

                                    </div>


                                    @if(
                                        $tool->tool_key
                                        === 'startup_capital_planner'
                                        && $workspace->business_stage === 'new'
                                    )

                                        <p>
                                            Plan one-time startup costs
                                            and calculate the estimated
                                            startup capital requirement.
                                        </p>

                                        <a
                                            class="pbr-open-tool"
                                            href="{{ route(
                                                'workspaces.tools.startup-capital.show',
                                                $workspace
                                            ) }}"
                                        >
                                            Open Tool →
                                        </a>

                                    @elseif(
                                        $chapter->chapter_number === 1
                                    )

                                        <p>
                                        Open this Chapter 1 capital
                                        tool to calculate, compare,
                                        and save different scenarios.
                                    </p>

                                    <a
                                        class="pbr-open-tool"
                                        href="{{ route(
                                            'workspaces.tools.chapter-one.show',
                                            [
                                                $workspace,
                                                $tool->slug
                                            ]
                                        ) }}"
                                    >
                                        Open Tool →
                                    </a>

                                    @else

                                        <p>
                                            Planned tool in the
                                            PBR Business System.
                                        </p>

                                    @endif

                                </article>

                            @endforeach

                        </div>

                    </div>

                </details>

            @endforeach

        </div>

    </div>
</section>

@endsection
