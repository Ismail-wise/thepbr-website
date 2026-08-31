@extends('layouts.student-portal')

@section('title', 'My Partner Dynamics Result')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('css/partner-dynamics-result-reference.css') }}?v={{ filemtime(public_path('css/partner-dynamics-result-reference.css')) }}"
>


@php

    $profiles = [
        'visionary' => [
            'name' => 'Visionary',
            'mm' => 'အနာဂတ်အခွင့်အလမ်းနဲ့ Direction ကို မြင်တတ်သူ',
            'description' => 'အနာဂတ်အခွင့်အလမ်းတွေကို မြင်နိုင်ပြီး Business Direction အသစ်တွေ ဖော်ထုတ်ရာမှာ သဘာဝကျကျ အားသာတတ်ပါတယ်။',
        ],

        'builder' => [
            'name' => 'Builder',
            'mm' => 'Idea ကို Action ပြောင်းတတ်သူ',
            'description' => 'Idea ကို လက်တွေ့အကောင်အထည်ဖော်ပြီး momentum ဖန်တီးရာမှာ အားသာတတ်ပါတယ်။',
        ],

        'connector' => [
            'name' => 'Connector',
            'mm' => 'လူတွေနဲ့ Relationship တည်ဆောက်တတ်သူ',
            'description' => 'People, customers နဲ့ partners ကြား communication နဲ့ relationship ကို သဘာဝကျကျတည်ဆောက်တတ်ပါတယ်။',
        ],

        'analyst' => [
            'name' => 'Analyst',
            'mm' => 'Data နဲ့ အချက်အလက်ကို အခြေခံတတ်သူ',
            'description' => 'Numbers, evidence နဲ့ financial impact ကို စစ်ဆေးပြီး ဆုံးဖြတ်ရာမှာ အားသာတတ်ပါတယ်။',
        ],

        'operator' => [
            'name' => 'Operator',
            'mm' => 'System နဲ့ Execution ကို တည်ငြိမ်စေသူ',
            'description' => 'Process, responsibility နဲ့ day-to-day execution ကို တည်ငြိမ်အောင် စီမံရာမှာ အားသာတတ်ပါတယ်။',
        ],

        'guardian' => [
            'name' => 'Guardian',
            'mm' => 'Risk နဲ့ Control ကို သေချာစောင့်ကြည့်သူ',
            'description' => 'Risk, structure နဲ့ downside တွေကို စစ်ဆေးပြီး business ကို ကာကွယ်ရာမှာ အားသာတတ်ပါတယ်။',
        ],

        'negotiator' => [
            'name' => 'Negotiator',
            'mm' => 'Decision နဲ့ Conflict ကို ဖြေရှင်းတတ်သူ',
            'description' => 'မတူညီတဲ့အမြင်တွေကြားမှာ discussion, negotiation နဲ့ decision clarity ဖန်တီးရာမှာ အားသာတတ်ပါတယ်။',
        ],

        'optimizer' => [
            'name' => 'Optimizer',
            'mm' => 'ရှိပြီးသားအရာကို ပိုကောင်းအောင်လုပ်တတ်သူ',
            'description' => 'System နဲ့ workflow တွေကို ပြန်လည်တိုးတက်အောင်လုပ်ပြီး အပြောင်းအလဲနဲ့ လိုက်လျောညီထွေဖြစ်တတ်ပါတယ်။',
        ],
    ];


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


    $primary = $profiles[$assessment->primary_profile]
        ?? [
            'name' => ucfirst($assessment->primary_profile),
            'mm' => '',
            'description' => '',
        ];

    $secondary = $profiles[$assessment->secondary_profile]
        ?? [
            'name' => ucfirst($assessment->secondary_profile),
            'mm' => '',
            'description' => '',
        ];


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
            'badge_mm' => '',
            'illustration' => '',
        ];

    $secondaryVisual = $visualThemes[$assessment->secondary_profile]
        ?? [
            'primary' => '#64748B',
            'secondary' => '#94A3B8',
            'soft' => '#F8FAFC',
            'light' => '#E2E8F0',
            'badge_mm' => '',
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
