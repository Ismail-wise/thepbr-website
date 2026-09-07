@extends('layouts.student-portal')

@section('title', 'Capital Action Plan')

@section('content')

@php
    $hasDecisionRecords =
        is_array($decisionRecords)
        && $decisionRecords !== [];

    $priorityLabels = [
        'low' => 'LOW',
        'normal' => 'NORMAL',
        'high' => 'HIGH',
        'critical' => 'CRITICAL',
    ];
@endphp

<section
    class="pbr-os-page pbr-premium-tool-page pbr-premium-capital-tool-page"
    data-pbr-premium-tool
    data-pbr-tool-chapter="1"
>
    <div class="pbr-os-wrap portal-wrap">
        <section class="pbr-os-panel">
            <div class="pbr-os-panel-head">
                <div>
                    <span class="portal-kicker">
                        ACT / CAPITAL ACTION PLAN
                    </span>

                    <h2>
                        Recorded Decision Execution
                    </h2>

                    <p>
                        Capital Decision Record ကို
                        execution items အဖြစ်ခွဲပြီး
                        Owner, Priority, Due Date,
                        Status နဲ့ provenance ကို
                        track လုပ်နိုင်ပါတယ်။
                    </p>
                </div>

                <span class="pbr-os-session-badge">
                    {{ count($actions) }}
                    {{ count($actions) === 1 ? 'Action' : 'Actions' }}
                </span>
            </div>

            <div class="pbr-os-approval-actions">
                <a
                    class="pbr-os-btn"
                    href="{{ route(
                        'workspaces.tools.v2.capital.show',
                        $workspace
                    ) }}"
                >
                    Back to Capital Plan Authority
                </a>
            </div>
        </section>

        @if(session('status'))
            <div class="pbr-os-insight note">
                <div class="pbr-os-insight-icon">
                    ✓
                </div>

                <div>
                    <h3>Action Plan Updated</h3>
                    <p>{{ session('status') }}</p>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="pbr-os-insight warning">
                <div class="pbr-os-insight-icon">
                    !
                </div>

                <div>
                    <h3>
                        Action Plan request ကို
                        complete မလုပ်နိုင်ပါ
                    </h3>

                    <ul>
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section
            class="pbr-os-panel"
            id="v2-capital-action-create"
        >
            <div class="pbr-os-panel-head">
                <div>
                    <span class="portal-kicker">
                        CREATE ACTION
                    </span>

                    <h2>
                        Recorded Decision → Action Item
                    </h2>

                    <p>
                        Action အသစ်ဟာ always OPEN state နဲ့
                        စပြီး immutable Decision Record နဲ့
                        approved Capital Plan revision ကို
                        provenance အဖြစ် pin လုပ်ပါတယ်။
                    </p>
                </div>
            </div>

            @if($hasDecisionRecords)
                <form
                    method="POST"
                    action="{{ route(
                        'workspaces.tools.v2.capital.action-plan.store',
                        $workspace
                    ) }}"
                >
                    @csrf

                    <div class="pbr-os-fields">
                        <div class="pbr-os-field pbr-os-field-wide">
                            <label>
                                Capital Decision Record
                            </label>

                            <select
                                name="decision_record_id"
                                required
                            >
                                @foreach($decisionRecords as $record)
                                    <option
                                        value="{{ $record['id'] }}"
                                        @selected(
                                            (string) old(
                                                'decision_record_id',
                                                $decisionRecords[0]['id'] ?? ''
                                            )
                                            === (string) $record['id']
                                        )
                                    >
                                        {{ $record['title'] }}
                                        · Revision
                                        {{ $record['source']['revision'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pbr-os-field pbr-os-field-wide">
                            <label>Action Title</label>

                            <input
                                type="text"
                                name="title"
                                maxlength="255"
                                value="{{ old('title') }}"
                                required
                            >
                        </div>

                        <div class="pbr-os-field pbr-os-field-wide">
                            <label>Description</label>

                            <textarea
                                name="description"
                                maxlength="4000"
                            >{{ old('description') }}</textarea>
                        </div>

                        <div class="pbr-os-field">
                            <label>Owner</label>

                            <input
                                type="text"
                                name="owner_name"
                                maxlength="255"
                                value="{{ old('owner_name') }}"
                            >
                        </div>

                        <div class="pbr-os-field">
                            <label>Priority</label>

                            <select
                                name="priority"
                                required
                            >
                                @foreach($priorities as $priority)
                                    <option
                                        value="{{ $priority }}"
                                        @selected(
                                            old(
                                                'priority',
                                                'normal'
                                            ) === $priority
                                        )
                                    >
                                        {{ $priorityLabels[$priority] ?? strtoupper($priority) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pbr-os-field">
                            <label>Due Date</label>

                            <input
                                type="date"
                                name="due_date"
                                value="{{ old('due_date') }}"
                            >
                        </div>
                    </div>

                    <div class="pbr-os-approval-actions">
                        <button
                            type="submit"
                            class="pbr-os-btn approve"
                        >
                            Create Action Item
                        </button>
                    </div>
                </form>
            @else
                <div class="pbr-os-insight note">
                    <div class="pbr-os-insight-icon">
                        i
                    </div>

                    <div>
                        <h3>
                            Record Decision first
                        </h3>

                        <p>
                            Approved Capital Plan ကို
                            Capital Decision Record အဖြစ်
                            immutable record လုပ်ပြီးမှ
                            Action Item ဖန်တီးနိုင်ပါတယ်။
                        </p>
                    </div>
                </div>
            @endif
        </section>

        <section
            class="pbr-os-panel"
            id="v2-capital-action-items"
        >
            <div class="pbr-os-panel-head">
                <div>
                    <span class="portal-kicker">
                        EXECUTION BOARD
                    </span>

                    <h2>
                        Capital Action Items
                    </h2>

                    <p>
                        Newest-first execution list ဖြစ်ပါတယ်။
                        Transition choices ကို frozen F5
                        lifecycle matrix ကနေဖတ်ထားပါတယ်။
                    </p>
                </div>

                <span class="pbr-os-session-badge">
                    {{ count($actions) }}
                    {{ count($actions) === 1 ? 'Item' : 'Items' }}
                </span>
            </div>

            @if($actions !== [])
                <div class="pbr-os-result-table-wrap">
                    <div class="pbr-os-table-head">
                        <h3>
                            Recorded Decision Execution
                        </h3>

                        <span>
                            Newest action first
                        </span>
                    </div>

                    <div class="pbr-os-table-scroll">
                        <table class="pbr-os-result-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Owner</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Decision Record</th>
                                    <th>Plan Revision</th>
                                    <th>Updated</th>
                                    <th>Transition</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($actions as $action)
                                    @php
                                        $nextStates =
                                            $actionTransitions[
                                                $action['status']
                                            ] ?? [];

                                        $source =
                                            $action['sources'][0]
                                            ?? null;
                                    @endphp

                                    <tr>
                                        <td>
                                            <strong>
                                                {{ $action['title'] }}
                                            </strong>

                                            @if($action['description'])
                                                <div>
                                                    {{ $action['description'] }}
                                                </div>
                                            @endif
                                        </td>

                                        <td>
                                            {{ $action['owner_name'] ?: 'Not assigned' }}
                                        </td>

                                        <td>
                                            <strong>
                                                {{ strtoupper($action['priority']) }}
                                            </strong>
                                        </td>

                                        <td>
                                            <strong>
                                                {{ str_replace(
                                                    '_',
                                                    ' ',
                                                    strtoupper($action['status'])
                                                ) }}
                                            </strong>
                                        </td>

                                        <td>
                                            {{ $action['due_date'] ?: 'No deadline' }}
                                        </td>

                                        <td>
                                            Decision Record #{{ $action['producer']['operating_record_id'] }}
                                        </td>

                                        <td>
                                            @if($source)
                                                Revision {{ $source['revision'] }}
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <td>
                                            {{ $action['updated_at'] }}
                                        </td>

                                        <td>
                                            @if($nextStates !== [])
                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'workspaces.tools.v2.capital.action-plan.transition',
                                                        [
                                                            'workspace' => $workspace,
                                                            'actionId' => $action['id'],
                                                        ]
                                                    ) }}"
                                                >
                                                    @csrf
                                                    @method('PATCH')

                                                    <select
                                                        name="to_state"
                                                        required
                                                    >
                                                        @foreach($nextStates as $nextState)
                                                            <option
                                                                value="{{ $nextState }}"
                                                            >
                                                                {{ str_replace(
                                                                    '_',
                                                                    ' ',
                                                                    strtoupper($nextState)
                                                                ) }}
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    <button
                                                        type="submit"
                                                        class="pbr-os-btn"
                                                    >
                                                        Update Status
                                                    </button>
                                                </form>
                                            @else
                                                Terminal
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="pbr-os-insight note">
                    <div class="pbr-os-insight-icon">
                        i
                    </div>

                    <div>
                        <h3>
                            Action items မရှိသေးပါ
                        </h3>

                        <p>
                            Recorded decision ရှိရင်
                            အပေါ်က form ကနေ execution item
                            စတင်ဖန်တီးနိုင်ပါတယ်။
                        </p>
                    </div>
                </div>
            @endif

            <div class="pbr-os-legal-note">
                <strong>
                    ACT Authority Boundary
                </strong>

                <p>
                    Action status က OPEN → execution lifecycle
                    အတွင်းမှာသာ ရွေ့နိုင်ပါတယ်။
                    COMPLETED နဲ့ SUPERSEDED က terminal states
                    ဖြစ်ပြီး Action changes က approved Capital Plan
                    သို့မဟုတ် immutable Decision Record ကို
                    ပြန်မရေးပါ။
                </p>
            </div>
        </section>
    </div>
</section>

@endsection
