@if($canManage && $operatingActions->isNotEmpty())
    @php
        $activeActionCount = $operatingActions
            ->whereIn(
                'status',
                \App\Models\WorkspaceToolAction::ACTIVE_STATUSES
            )
            ->count();

        $completedActionCount = $operatingActions
            ->where('status', 'completed')
            ->count();
    @endphp

    <section class="pbr-os-panel pbr-action-board">
        <div class="pbr-action-board-head">
            <div>
                <span class="portal-kicker">OPERATING ACTION TRACKER</span>
                <h2>အတည်ပြုထားသော Action Plan</h2>
                <p>
                    Approved Rule နဲ့ဆက်စပ်နေတဲ့ လက်တွေ့လုပ်ဆောင်မှုများကို
                    ဒီနေရာမှာ track လုပ်နိုင်ပါတယ်။
                </p>
            </div>

            <div class="pbr-action-board-counts">
                <span>
                    <strong>{{ $activeActionCount }}</strong>
                    Active
                </span>
                <span>
                    <strong>{{ $completedActionCount }}</strong>
                    Completed
                </span>
            </div>
        </div>

        <div class="pbr-action-list">
            @foreach($operatingActions as $action)
                <article
                    class="pbr-action-item priority-{{ $action->priority }} status-{{ $action->status }}"
                >
                    <div class="pbr-action-main">
                        <div class="pbr-action-badges">
                            <span class="pbr-action-priority">
                                {{ strtoupper($action->priority) }}
                            </span>

                            <span class="pbr-action-status">
                                {{ str_replace('_', ' ', strtoupper($action->status)) }}
                            </span>

                            @if($action->isOverdue())
                                <span class="pbr-action-overdue">
                                    OVERDUE
                                </span>
                            @endif
                        </div>

                        <h3>{{ $action->title }}</h3>

                        @if($action->description)
                            <p>{{ $action->description }}</p>
                        @endif

                        <div class="pbr-action-meta">
                            <span>
                                Owner:
                                <b>{{ $action->owner_name ?: 'Not assigned' }}</b>
                            </span>

                            <span>
                                Due:
                                <b>
                                    {{
                                        $action->due_date
                                            ? $action->due_date->format('d M Y')
                                            : 'No deadline'
                                    }}
                                </b>
                            </span>

                            @if($action->workspaceOutput)
                                <span>
                                    Rule Revision:
                                    <b>{{ $action->workspaceOutput->revision }}</b>
                                </span>
                            @endif
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('workspaces.tool-actions.update', [$workspace, $action]) }}"
                        class="pbr-action-status-form"
                    >
                        @csrf
                        @method('PATCH')

                        <label>
                            Update Status
                            <select name="status">
                                <option value="open" @selected($action->status === 'open')>
                                    Open
                                </option>
                                <option value="in_progress" @selected($action->status === 'in_progress')>
                                    In Progress
                                </option>
                                <option value="blocked" @selected($action->status === 'blocked')>
                                    Blocked
                                </option>
                                <option value="completed" @selected($action->status === 'completed')>
                                    Completed
                                </option>
                            </select>
                        </label>

                        <button type="submit" class="pbr-os-btn secondary">
                            Update
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endif
