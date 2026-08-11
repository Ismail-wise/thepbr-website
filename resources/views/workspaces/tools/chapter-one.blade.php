@extends('layouts.student-portal')

@section('title', $tool->title_en)

@section('content')

<section class="pbr-tools-section">
    <div class="portal-wrap">

        <div class="pbr-tool-page-head">

            <div>
                <a
                    href="{{ route('workspaces.tools.index', $workspace) }}"
                    class="pbr-tools-back"
                >
                    ← Back to PBR Business Tools
                </a>

                <span class="portal-kicker">
                    Chapter 1 · Capital Contribution
                </span>

                <h1>{{ $tool->title_en }}</h1>

                <p>
                    {{ $tool->description }}
                </p>
            </div>

            <div class="pbr-tool-context-box">
                <span>Workspace</span>

                <strong>
                    {{ $workspace->business_name ?: $workspace->name }}
                </strong>

                <small>
                    Mode:
                    {{
                        $workspace->business_stage === 'new'
                            ? 'Planning a New Partnership'
                            : 'Managing an Existing Partnership'
                    }}
                </small>

                <small>
                    Currency:
                    {{ $workspace->currency_code ?? 'THB' }}
                </small>
            </div>

        </div>


        @if(session('status'))
            <div class="pbr-save-success">
                {{ session('status') }}
            </div>
        @endif


        <form
            id="chapter-one-tool-form"
            method="POST"
            action="{{ route(
                'workspaces.tools.chapter-one.calculate',
                [
                    $workspace,
                    $tool->slug
                ]
            ) }}"
            data-currency="{{ $workspace->currency_code ?? 'THB' }}"
        >

            @csrf

            @if($activeSession)
                <input
                    type="hidden"
                    name="tool_session_id"
                    value="{{ $activeSession->id }}"
                >
            @endif


            <div class="pbr-scenario-box">

                <div>
                    <label for="scenario_name">
                        Scenario Name
                    </label>

                    <small>
                        Save different versions of this plan.
                    </small>
                </div>

                <input
                    id="scenario_name"
                    name="scenario_name"
                    type="text"
                    maxlength="120"
                    placeholder="Example: Initial Plan"
                    value="{{ old(
                        'scenario_name',
                        $activeSession?->scenario_name ?? ''
                    ) }}"
                >

            </div>


            @if($activeSession)

                <div class="pbr-active-draft">

                    <span>
                        Editing Saved Scenario
                    </span>

                    <strong>
                        {{ $activeSession->scenario_name }}
                    </strong>

                    <small>
                        Last saved
                        {{
                            $activeSession->last_saved_at
                                ? $activeSession->last_saved_at
                                    ->diffForHumans()
                                : 'recently'
                        }}
                    </small>

                </div>

            @endif


            @if($errors->any())

                <div class="pbr-form-errors">

                    <strong>
                        Please check your entries.
                    </strong>

                    <p>
                        Amounts must be zero or positive numbers.
                    </p>

                </div>

            @endif


            <div class="pbr-calculator-layout">

                <div class="pbr-calculator-panel">

                    @include(
                        'workspaces.tools.chapter-one.'
                        .$tool->slug
                    )


                    <div class="pbr-calculator-actions">

                        <button
                            type="submit"
                            class="pbr-save-draft-button"
                            formaction="{{ route(
                                'workspaces.tools.chapter-one.save',
                                [
                                    $workspace,
                                    $tool->slug
                                ]
                            ) }}"
                            formmethod="POST"
                        >
                            Save Draft
                        </button>

                        <button
                            type="submit"
                            class="pbr-tools-primary-button"
                        >
                            Calculate
                        </button>

                    </div>

                </div>


                <aside class="pbr-calculator-results">

                    <span class="portal-kicker">
                        Result Summary
                    </span>

                    @include(
                        'workspaces.tools.chapter-one.results',
                        [
                            'toolKey' => $tool->tool_key
                        ]
                    )

                </aside>

            </div>

        </form>


        @include(
            'workspaces.tools.partials.scenario-manager'
        )

    </div>
</section>

<script src="/js/chapter-one-tools.js"></script>

@endsection
