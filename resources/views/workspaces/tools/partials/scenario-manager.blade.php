<div class="pbr-scenario-manager">

    <div class="pbr-scenario-manager-head">
        <div>
            <span class="portal-kicker">Saved Scenarios</span>
            <h3>Your Plans</h3>
        </div>

        <span class="pbr-scenario-count">
            {{ $drafts->count() }} saved
        </span>
    </div>

    @forelse($drafts as $draft)

        <div class="pbr-scenario-row">

            <div class="pbr-scenario-info">
                <strong>
                    {{ $draft->scenario_name ?: 'Untitled Scenario' }}
                </strong>

                <small>
                    Last saved
                    {{
                        $draft->last_saved_at
                            ? $draft->last_saved_at->diffForHumans()
                            : 'recently'
                    }}
                </small>
            </div>

            <div class="pbr-scenario-actions">

                <a
                    class="pbr-scenario-open"
                    href="{{
                        url(
                            '/workspaces/'
                            .$workspace->id
                            .'/tools/'
                            .$tool->slug
                        )
                    }}?session={{ $draft->id }}"
                >
                    Open
                </a>

                <form
                    method="POST"
                    class="pbr-scenario-rename-form"
                    action="{{ route(
                        'workspaces.tools.scenarios.rename',
                        [
                            $workspace,
                            $tool->slug,
                            $draft->id
                        ]
                    ) }}"
                >
                    @csrf

                    <input
                        type="text"
                        name="scenario_name"
                        value="{{ $draft->scenario_name }}"
                        maxlength="120"
                        required
                        aria-label="Scenario name"
                    >

                    <button type="submit">
                        Rename
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route(
                        'workspaces.tools.scenarios.duplicate',
                        [
                            $workspace,
                            $tool->slug,
                            $draft->id
                        ]
                    ) }}"
                >
                    @csrf

                    <button type="submit">
                        Duplicate
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route(
                        'workspaces.tools.scenarios.output',
                        [
                            $workspace,
                            $tool->slug,
                            $draft->id
                        ]
                    ) }}"
                >
                    @csrf

                    <button type="submit">
                        Workspace Output
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route(
                        'workspaces.tools.scenarios.destroy',
                        [
                            $workspace,
                            $tool->slug,
                            $draft->id
                        ]
                    ) }}"
                    onsubmit="return confirm('Delete this saved scenario?')"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="danger"
                    >
                        Delete
                    </button>
                </form>

            </div>

        </div>

    @empty

        <div class="pbr-scenario-empty">
            No saved scenarios yet.
        </div>

    @endforelse

</div>
