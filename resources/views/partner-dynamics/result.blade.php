@extends('layouts.student-portal')

@section('title', 'My Partner Dynamics Result')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('css/partner-dynamics-result-reference.css') }}?v={{ filemtime(public_path('css/partner-dynamics-result-reference.css')) }}"
>


@php

    $dimensions = [
        'vision' => 'Vision & Direction',
        'execution' => 'Execution & Delivery',
        'people' => 'People & Influence',
        'analysis' => 'Analysis & Finance',
        'structure' => 'Structure & Control',
        'risk' => 'Risk & Opportunity',
        'decision' => 'Decision & Conflict',
        'adaptability' => 'Adaptability & Change',
    ];


    $primary = \App\Support\PartnerDynamicsProfile::describe(
        $assessment->primary_profile
    );

    $secondary = \App\Support\PartnerDynamicsProfile::describe(
        $assessment->secondary_profile
    );


    /*
    |--------------------------------------------------------------------------
    | Partner Dynamics Visual Theme
    |--------------------------------------------------------------------------
    |
    | Scoring logic remains inside the existing Partner Dynamics engine.
    | This config controls visual identity only.
    |
    */

    $visualThemes = config('partner_dynamics_visuals', []);

    $primaryVisual = $visualThemes[$assessment->primary_profile]
        ?? [
            'primary' => '#157F09',
            'secondary' => '#F28C28',
            'soft' => '#F1F8EE',
            'light' => '#E3F0DE',
            'illustration' => '',
        ];

    $secondaryVisual = $visualThemes[$assessment->secondary_profile]
        ?? [
            'primary' => '#64748B',
            'secondary' => '#94A3B8',
            'soft' => '#F8FAFC',
            'light' => '#E2E8F0',
            'illustration' => '',
        ];

@endphp


<section
    class="pd-result-section"
    style="
        --pd-primary: {{ $primaryVisual['primary'] }};
        --pd-secondary: {{ $primaryVisual['secondary'] }};
        --pd-soft: {{ $primaryVisual['soft'] }};
        --pd-light: {{ $primaryVisual['light'] }};
        --pd-secondary-profile: {{ $secondaryVisual['primary'] }};
    "
>

    <div class="portal-wrap">

        <a href="{{ route('partner-dynamics.index') }}"
           class="pd-back-home">
            ← Partner Dynamics
        </a>
        @include(
            'partner-dynamics.partials.result-reference-top'
        )

        <div id="partner-match"></div>




        @php

            $matchLabels = [
                'အကောင်းဆုံး ဖြည့်ဆည်းမှု',
                'ညီမျှစွာ ဖြည့်ဆည်းမှု',
                'ထောက်ပံ့ ဖြည့်ဆည်းမှု',
            ];

        @endphp

        @include(
            'partner-dynamics.partials.partner-match-folded'
        )




        <section class="pd-next-stage">

            <div>

                <span class="portal-kicker">
                    What's Next?
                </span>

                <h2>
                    Partner နဲ့ Alignment ကို ဆက်ကြည့်မယ်
                </h2>

                <p>
                    Partner တစ်ယောက်ချင်းစီ Assessment ပြီးသွားတဲ့အခါ
                    Shared Strengths, Important Differences,
                    Shared Blind Spots နဲ့ Role Suggestions တွေကို
                    Partnership Workspace ထဲမှာ နှိုင်းယှဉ်နိုင်မှာပါ။
                </p>

            </div>


            <div class="pd-result-actions">

                <a
                    href="{{ route('workspaces.index') }}"
                    class="pd-primary-button pd-button-link"
                >
                    My Workspace →
                </a>


                <form
                    method="POST"
                    action="{{ route('partner-dynamics.retake') }}"
                >

                    @csrf

                    <button
                        type="submit"
                        class="pd-secondary-button"
                    >
                        Assessment ပြန်ဖြေမယ်
                    </button>

                </form>

            </div>

        </section>


        <div class="pd-disclaimer">

            <strong>PBR Note</strong>

            <p>
                Profile တစ်ခုက တခြား profile ထက် ပိုကောင်းတယ်လို့
                မဆိုလိုပါဘူး။ Partnership ကောင်းတစ်ခုမှာ
                operating styles အမျိုးမျိုး ပေါင်းစပ်နိုင်ပါတယ်။
                ဒီ Result ကို role clarity နဲ့ partner discussion
                ပိုကောင်းစေဖို့ အသုံးပြုပါ။
            </p>

        </div>

    </div>

</section>

@endsection
