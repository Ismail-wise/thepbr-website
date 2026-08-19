@extends('layouts.student-portal')

@section('title', 'Operating Action Center')

@section('content')
@php
    $statusLabels = [
        'open' => 'စတင်ရန်',
        'in_progress' => 'လုပ်ဆောင်နေ',
        'blocked' => 'ပိတ်ဆို့နေ',
        'completed' => 'ပြီးစီး',
    ];

    $priorityLabels = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'critical' => 'Critical',
    ];
@endphp

<section class="pbr-tools-section pbr-action-center-page">
    <div class="portal-wrap">
        <header class="pbr-action-center-hero">
            <div>
                <a
                    href="{{ route('workspaces.tools.index', $workspace) }}"
                    class="pbr-action-center-back"
                >
                    ← Business Operating System
                </a>

                <span class="portal-kicker">
                    CROSS-TOOL EXECUTION
                </span>

                <h1>Operating Action Center</h1>

                <p>
                    {{ $workspace->business_name ?: $workspace->name }}
                    အတွက် Tool 64 ခုလုံးမှ အတည်ပြုထားသော
                    Business Rules တွေရဲ့ လုပ်ဆောင်ရန် Action,
                    တာဝန်ခံ၊ Priority နဲ့ Due Date ကို
                    နေ့စဉ်စီမံနိုင်ပါတယ်။
                </p>
            </div>
        </header>

        <div class="pbr-action-center-summary">
            <article>
                <small>Active Actions</small>
                <strong>{{ $summary['active'] }}</strong>
            </article>

            <article>
                <small>Not Started</small>
                <strong>{{ $summary['open'] }}</strong>
            </article>

            <article>
                <small>In Progress</small>
                <strong>{{ $summary['in_progress'] }}</strong>
            </article>

            <article class="is-blocked">
                <small>Blocked</small>
                <strong>{{ $summary['blocked'] }}</strong>
            </article>

            <article class="is-overdue">
                <small>Overdue</small>
                <strong>{{ $summary['overdue'] }}</strong>
            </article>
        </div>

        <form
            method="GET"
            action="{{ route('workspaces.tool-actions.index', $workspace) }}"
            class="pbr-action-center-filters"
        >
            <label>
                Status
                <select name="status">
                    <option value="active" @selected($filters['status'] === 'active')>
                        Active အားလုံး
                    </option>
                    <option value="open" @selected($filters['status'] === 'open')>
                        စတင်ရန်
                    </option>
                    <option value="in_progress" @selected($filters['status'] === 'in_progress')>
                        လုပ်ဆောင်နေ
                    </option>
                    <option value="blocked" @selected($filters['status'] === 'blocked')>
                        ပိတ်ဆို့နေ
                    </option>
                    <option value="completed" @selected($filters['status'] === 'completed')>
                        ပြီးစီး
                    </option>
                    <option value="all" @selected($filters['status'] === 'all')>
                        အားလုံး
                    </option>
                </select>
            </label>

            <label>
                Priority
                <select name="priority">
                    <option value="">Priority အားလုံး</option>
                    @foreach($priorityLabels as $key => $label)
                        <option
                            value="{{ $key }}"
                            @selected($filters['priority'] === $key)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                တာဝန်ခံ
                <select name="owner">
                    <option value="">Owner အားလုံး</option>
                    @foreach($owners as $ownerName)
                        <option
                            value="{{ $ownerName }}"
                            @selected($filters['owner'] === $ownerName)
                        >
                            {{ $ownerName }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Due Date
                <select name="due">
                    <option value="">Due Date အားလုံး</option>
                    <option value="overdue" @selected($filters['due'] === 'overdue')>
                        Overdue
                    </option>
                    <option value="today" @selected($filters['due'] === 'today')>
                        ယနေ့
                    </option>
                    <option value="seven_days" @selected($filters['due'] === 'seven_days')>
                        နောက် 7 ရက်
                    </option>
                    <option value="no_date" @selected($filters['due'] === 'no_date')>
                        Due Date မရှိ
                    </option>
                </select>
            </label>

            <div class="pbr-action-center-filter-buttons">
                <button type="submit">Filter</button>

                <a href="{{ route('workspaces.tool-actions.index', $workspace) }}">
                    Reset
                </a>
            </div>
        </form>

        @if($actions->isEmpty())
            <div class="pbr-action-center-empty">
                <strong>ဒီ Filter အတွက် Action မရှိသေးပါ</strong>
                <p>
                    Tool တစ်ခုကို Draft သိမ်းပြီး Rule အဖြစ်
                    အတည်ပြုတဲ့အခါ Action Plan များ ဒီနေရာကို
                    အလိုအလျောက်ရောက်လာပါမယ်။
                </p>
            </div>
        @else
            <div class="pbr-action-center-list">
                @foreach($actions as $action)
                    @php
                        $tool = $tools->get($action->chapter_tool_id);
                        $chapterNumber = $tool?->chapter?->chapter_number;
                        $toolName = $tool?->title_mm
                            ?? $tool?->name_mm
                            ?? $tool?->title_en
                            ?? $tool?->name_en
                            ?? str($tool?->tool_key ?? 'Operating Tool')
                                ->replace('_', ' ')
                                ->title();

                        $toolUrl = null;

                        if ($tool?->tool_key === 'startup_capital_planner') {
                            $toolUrl = route(
                                'workspaces.tools.startup-capital.show',
                                $workspace
                            );
                        } elseif($tool && (int) $chapterNumber === 1) {
                            $toolUrl = route(
                                'workspaces.tools.chapter-one.show',
                                [$workspace, $tool->slug]
                            );
                        } elseif($tool) {
                            $toolUrl = route(
                                'workspaces.tools.operating.show',
                                [$workspace, $tool->slug]
                            );
                        }

                        $dueDate = $action->due_date
                            ? \Illuminate\Support\Carbon::parse($action->due_date)
                            : null;

                        $isOverdue = $dueDate
                            && $dueDate->isBefore(today())
                            && $action->status !== 'completed';
                    @endphp

                    <article
                        class="pbr-action-center-item
                            {{ $action->status === 'blocked' ? 'is-blocked' : '' }}
                            {{ $isOverdue ? 'is-overdue' : '' }}
                            {{ $action->status === 'completed' ? 'is-completed' : '' }}"
                    >
                        <div>
                            <div class="pbr-action-center-meta">
                                @if($toolUrl)
                                    <a href="{{ $toolUrl }}">
                                        Chapter {{ $chapterNumber }}
                                        · {{ $toolName }}
                                    </a>
                                @else
                                    <span>{{ $toolName }}</span>
                                @endif

                                <span class="priority-{{ $action->priority }}">
                                    {{ $priorityLabels[$action->priority] ?? ucfirst($action->priority) }}
                                    Priority
                                </span>

                                @if($action->workspaceOutput)
                                    <span>
                                        Approved Rule
                                        Rev {{ $action->workspaceOutput->revision }}
                                    </span>
                                @endif
                            </div>

                            <h2>{{ $action->title }}</h2>

                            @if($action->description)
                                <p class="pbr-action-center-description">
                                    {{ $action->description }}
                                </p>
                            @endif

                            <div class="pbr-action-center-details">
                                <span>
                                    တာဝန်ခံ:
                                    <strong>
                                        {{ $action->owner_name ?: 'မသတ်မှတ်ရသေး' }}
                                    </strong>
                                </span>

                                <span class="{{ $isOverdue ? 'overdue' : '' }}">
                                    Due:
                                    <strong>
                                        {{ $dueDate?->format('d M Y') ?? 'မသတ်မှတ်ရသေး' }}
                                    </strong>
                                </span>

                                <span>
                                    Status:
                                    <strong>
                                        {{ $statusLabels[$action->status] ?? $action->status }}
                                    </strong>
                                </span>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('workspaces.tool-actions.update', [$workspace, $action]) }}"
                            class="pbr-action-center-status-form"
                        >
                            @csrf
                            @method('PATCH')

                            <select
                                name="status"
                                aria-label="Action status"
                            >
                                @foreach($statusLabels as $key => $label)
                                    <option
                                        value="{{ $key }}"
                                        @selected($action->status === $key)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            <button type="submit">
                                Update
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
