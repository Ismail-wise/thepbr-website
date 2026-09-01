{{--
    Partner Dynamics — Profile Symbol

    Inline SVG rather than image files: each is under a kilobyte, needs no
    extra request, and inherits currentColor so one file serves all eight
    profile colours. Inside the hero score card that colour is #fff over the
    profile gradient, so no per-profile variants are needed.

    Abstract marks, not human figures. A drawn person implies an age, gender
    and ethnicity that has nothing to do with the result, on a page whose whole
    purpose is the reader recognising themselves.

    Usage:
        @include('partner-dynamics.partials.profile-symbol', [
            'symbol' => $assessment->primary_profile,
        ])

    Optional: 'size' (px, default 38).
--}}

@php
    $pdSymbolKey = $symbol ?? null;
    $pdSymbolSize = $size ?? 38;
@endphp

<svg
    class="pd-ref-symbol"
    viewBox="0 0 64 64"
    width="{{ $pdSymbolSize }}"
    height="{{ $pdSymbolSize }}"
    fill="none"
    stroke="currentColor"
    stroke-width="2.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>

    @switch($pdSymbolKey)

        {{-- An eye on a distant point. Earlier drafts drew a sun over a
             horizon with a converging path; at small sizes the overlapping
             curves read as a knot. A single clear shape survives shrinking. --}}
        @case('visionary')
            <path d="M6 32s10-16 26-16 26 16 26 16-10 16-26 16S6 32 6 32z"/>
            <circle cx="32" cy="32" r="7"/>
            @break

        {{-- Blocks assembled into a whole. The earlier version carried a
             stem above the top block, which read as an org chart rather
             than construction. --}}
        @case('builder')
            <rect x="12" y="38" width="17" height="14" rx="2"/>
            <rect x="35" y="38" width="17" height="14" rx="2"/>
            <rect x="23.5" y="18" width="17" height="14" rx="2"/>
            @break

        @case('connector')
            <circle cx="32" cy="14" r="5"/>
            <circle cx="14" cy="46" r="5"/>
            <circle cx="50" cy="46" r="5"/>
            <path d="M29 19L17 41M35 19l12 22M19 46h26"/>
            @break

        @case('analyst')
            <path d="M10 52h44"/>
            <rect x="15" y="34" width="9" height="18" rx="1.5"/>
            <rect x="28" y="24" width="9" height="28" rx="1.5"/>
            <rect x="41" y="14" width="9" height="38" rx="1.5"/>
            @break

        {{-- Control sliders. The earlier version drew two meshed gears;
             at 40px the teeth and hubs collapsed into a smudge. --}}
        @case('operator')
            <path d="M12 20 H52"/>
            <path d="M12 32 H52"/>
            <path d="M12 44 H52"/>
            <circle cx="23" cy="20" r="4.8"/>
            <circle cx="41" cy="32" r="4.8"/>
            <circle cx="28" cy="44" r="4.8"/>
            @break

        @case('guardian')
            <path d="M32 8l20 8v16c0 12-8 20-20 24-12-4-20-12-20-24V16z"/>
            <path d="M24 32l6 6 12-12"/>
            @break

        {{-- Scales. The pans hang BELOW the beam as open bowls; drawn as
             filled triangles they read as a table. --}}
        @case('negotiator')
            <path d="M32 14v34M20 50h24"/>
            <path d="M14 22h36"/>
            <path d="M14 22v6M50 22v6"/>
            <path d="M6 28h16a8 8 0 0 1-16 0z"/>
            <path d="M42 28h16a8 8 0 0 1-16 0z"/>
            @break

        {{-- A cycle raising something upward. The earlier version put a
             tick inside the loop, duplicating the guardian shield's tick
             and reading as "verified" rather than "improved". --}}
        @case('optimizer')
            <path d="M25.2 50.8 A20 20 0 1 1 38.8 50.8"/>
            <path d="M46.9 52.6 L38.8 50.8 L43.9 44.2"/>
            <path d="M32 44 V20"/>
            <path d="M25 27 L32 20 L39 27"/>
            @break

        @default
            <circle cx="32" cy="32" r="20"/>
            <circle cx="32" cy="32" r="6"/>

    @endswitch

</svg>
