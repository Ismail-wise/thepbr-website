@php
    $premiumToolTitleMm =
        $premiumToolTitleMm
        ?? $tool->title_mm
        ?? $tool->title_en;

    $premiumToolTitleEn =
        $premiumToolTitleEn
        ?? $tool->title_en
        ?? 'Business Tool';

    $premiumToolAreaEn =
        $premiumToolAreaEn
        ?? 'Business Operations';

    $premiumToolIndexUrl =
        $premiumToolIndexUrl
        ?? route('workspaces.tools.index', $workspace);

    $premiumToolWorkspaceTarget =
        $premiumToolWorkspaceTarget
        ?? 'tool-workspace';

    $premiumToolResultTarget =
        $premiumToolResultTarget
        ?? 'result';

    $premiumToolCanManage =
        $premiumToolCanManage
        ?? $canManage
        ?? false;

    $premiumToolHasDraft =
        $premiumToolHasDraft
        ?? (bool) ($activeSession ?? false);

    $premiumToolHasActiveRule =
        $premiumToolHasActiveRule
        ?? (bool) ($latestAgreedOutput ?? false);

    $premiumToolIsRecord =
        $premiumToolIsRecord
        ?? false;

    $premiumToolState = match (true) {
        ! $premiumToolCanManage => [
            'key' => 'readonly',
            'label' => 'Read-only View',
        ],
        $premiumToolHasDraft => [
            'key' => 'working',
            'label' => 'Working Draft',
        ],
        $premiumToolHasActiveRule => [
            'key' => 'active',
            'label' => $premiumToolIsRecord
                ? 'Approved Records'
                : 'Active Rule',
        ],
        default => [
            'key' => 'setup',
            'label' => 'Setup Required',
        ],
    };

    $premiumToolSaveState = match (true) {
        ! $premiumToolCanManage => 'Permission Safe',
        $premiumToolHasDraft => 'Draft Loaded',
        default => 'Ready',
    };
@endphp

<div
    class="pbr-premium-toolbar"
    data-pbr-premium-toolbar
    data-tool-state="{{ $premiumToolState['key'] }}"
>
    <div class="pbr-premium-toolbar-location">
        <a
            href="{{ $premiumToolIndexUrl }}"
            class="pbr-premium-toolbar-back"
            aria-label="Back to all Business OS tools"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 18l-6-6 6-6" />
            </svg>
        </a>

        <div>
            <span>
                {{ $premiumToolAreaEn }}
                <i aria-hidden="true">/</i>
                {{ $workspace->business_name ?: $workspace->name }}
            </span>

            <strong title="{{ $premiumToolTitleMm }}">
                {{ $premiumToolTitleEn }}
            </strong>
        </div>
    </div>

    <div class="pbr-premium-toolbar-status" aria-label="Tool status">
        <span class="pbr-premium-tool-state {{ $premiumToolState['key'] }}">
            <i aria-hidden="true"></i>
            {{ $premiumToolState['label'] }}
        </span>

        <span class="pbr-premium-tool-currency">
            {{ $workspace->currency_code ?? 'THB' }}
        </span>

        <span
            class="pbr-premium-save-state"
            data-pbr-save-state
            aria-live="polite"
        >
            {{ $premiumToolSaveState }}
        </span>
    </div>

    <nav class="pbr-premium-toolbar-nav" aria-label="Tool page sections">
        @if($premiumToolCanManage)
            <a
                href="#{{ $premiumToolWorkspaceTarget }}"
                data-pbr-section-link="{{ $premiumToolWorkspaceTarget }}"
            >
                Workspace
            </a>
        @endif

        <a
            href="#{{ $premiumToolResultTarget }}"
            data-pbr-section-link="{{ $premiumToolResultTarget }}"
        >
            Result
        </a>

        <a href="{{ $premiumToolIndexUrl }}">
            All Tools
        </a>
    </nav>
</div>
