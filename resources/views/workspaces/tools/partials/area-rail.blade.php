{{--
    Area rail — persistent workspace context inside a tool page.

    Placement: sits directly below the premium command bar, above the
    breadcrumb. This is deliberate. pbr-premium-tool-system-v2.js runs
    simplifyOperatingWorkspace() after load, which lifts .pbr-os-sidebar out of
    the layout and nests it inside a collapsed "Working Plans & History"
    <details> panel. Anything placed in the sidebar is therefore hidden from the
    owner until they expand that panel. This region is left alone by the script.

    Deliberately simple: a name and a dot per area, nothing else. No counts, no
    percentages, no badges. The product notes record that extra checkboxes and
    tool clutter were repeatedly rejected, and the audience is non-technical
    business owners.

    Owner-only. Invited partners get read-only tool access and must not receive
    owner navigation across the whole workspace.
--}}
@php
    $railAreas = $railAreas ?? [];
@endphp

@if(!empty($railAreas) && ($canManage ?? false))
    <nav class="pbr-area-rail" aria-label="Business areas">
        <ul class="pbr-area-rail-list">
            @foreach($railAreas as $area)
                @php
                    $isCurrent = ($area['domain'] ?? null) === ($currentDomain ?? null);

                    $statusText = match($area['status_key'] ?? 'setup') {
                        'established' => 'အတည်ပြုပြီး',
                        'review' => 'ပြင်ဆင်နေဆဲ',
                        'dependency-review' => 'ပြန်စစ်ရန်',
                        'in-progress' => 'တစ်စိတ်တစ်ပိုင်း',
                        default => 'မစရသေး',
                    };
                @endphp

                <li class="pbr-area-rail-item {{ $isCurrent ? 'is-current' : '' }}">
                    <a
                        href="{{ $area['url'] }}"
                        class="pbr-area-rail-link"
                        @if($isCurrent) aria-current="page" @endif
                        title="{{ $area['name_mm'] }} — {{ $statusText }}"
                    >
                        <span
                            class="pbr-area-rail-dot is-{{ $area['status_key'] ?? 'setup' }}"
                            aria-hidden="true"
                        ></span>

                        <span class="pbr-area-rail-name">{{ $area['name_mm'] }}</span>

                        {{-- Colour alone must not carry the state: this text is
                             read by screen readers and shown in the tooltip. --}}
                        <span class="pbr-area-rail-sr">{{ $statusText }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
