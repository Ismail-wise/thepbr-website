@extends('layouts.student-portal')

@section('title', 'My Partner Dynamics Result')

@section('content')

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

@endphp


<section class="pd-result-section">

    <div class="portal-wrap">

        <a href="{{ route('partner-dynamics.index') }}"
           class="pd-back-home">
            ← Partner Dynamics
        </a>


        <div class="pd-result-hero">

            <div class="pd-result-main">

                <span class="portal-kicker">
                    Your Partner Dynamics Result
                </span>

                <p class="pd-result-eyebrow">
                    Your Primary Operating Style
                </p>

                <h1>{{ $primary['name'] }}</h1>

                <h2>{{ $primary['mm'] }}</h2>

                <p class="pd-result-description">
                    {{ $primary['description'] }}
                </p>


                @if($assessment->is_blended)

                    <div class="pd-blended-box">

                        <span>Blended Operating Style</span>

                        <strong>
                            {{ $primary['name'] }}
                            +
                            {{ $secondary['name'] }}
                        </strong>

                        <p>
                            သင့် Primary နဲ့ Secondary Profile score
                            အရမ်းနီးကပ်နေတာကြောင့် သင်ဟာ profile တစ်ခုတည်းနဲ့
                            သတ်မှတ်လို့မရတဲ့ blended style ဖြစ်ပါတယ်။
                        </p>

                    </div>

                @endif

            </div>


            <aside class="pd-score-card">

                <span>Primary</span>

                <strong>
                    {{ number_format($assessment->primary_score, 1) }}
                </strong>

                <small>{{ $primary['name'] }}</small>

                <div class="pd-score-divider"></div>

                <span>Secondary</span>

                <strong class="secondary-score">
                    {{ number_format($assessment->secondary_score, 1) }}
                </strong>

                <small>{{ $secondary['name'] }}</small>

            </aside>

        </div>


        <div class="pd-result-grid">

            <section class="pd-result-panel">

                <div class="pd-panel-heading">
                    <div>
                        <span class="portal-kicker">
                            Your Dimension Map
                        </span>

                        <h2>Partnership Operating Dimensions</h2>
                    </div>
                </div>


                <div class="pd-dimension-list">

                    @foreach($assessment->dimension_scores as $key => $score)

                        <div class="pd-dimension-row">

                            <div class="pd-dimension-heading">
                                <span>
                                    {{ $dimensions[$key] ?? ucfirst($key) }}
                                </span>

                                <strong>
                                    {{ number_format($score, 0) }}
                                </strong>
                            </div>

                            <div class="pd-dimension-track">
                                <span style="width: {{ max(0, min(100, $score)) }}%">
                                </span>
                            </div>

                        </div>

                    @endforeach

                </div>

            </section>


            <aside class="pd-result-panel pd-secondary-panel">

                <span class="portal-kicker">
                    Secondary Style
                </span>

                <h2>{{ $secondary['name'] }}</h2>

                <h3>{{ $secondary['mm'] }}</h3>

                <p>
                    {{ $secondary['description'] }}
                </p>


                <div class="pd-confidence">

                    <span>Result Confidence</span>

                    <strong>
                        {{ ucfirst($assessment->result_confidence) }}
                    </strong>

                    <p>
                        @if($assessment->result_confidence === 'strong')
                            သင့် answers တွေမှာ pattern consistency
                            ကောင်းကောင်းရှိပါတယ်။
                        @else
                            သင့် responses မှာ operating style နှစ်မျိုး
                            ရောနှောနေနိုင်ပါတယ်။ ဒါကို weakness လို့
                            မယူဆပါဘူး။
                        @endif
                    </p>

                </div>

            </aside>

        </div>


        <section class="pd-next-stage">

            <div>

                <span class="portal-kicker">
                    What's Next?
                </span>

                <h2>Partner နဲ့ Alignment ကို ဆက်ကြည့်မယ်</h2>

                <p>
                    Partner တစ်ယောက်ချင်းစီ Assessment ပြီးသွားတဲ့အခါ
                    Shared Strengths, Important Differences,
                    Shared Blind Spots နဲ့ Role Suggestions တွေကို
                    Partnership Workspace ထဲမှာ နှိုင်းယှဉ်နိုင်မှာပါ။
                </p>

            </div>

            <div class="pd-result-actions">

                <a href="{{ route('workspaces.index') }}"
                   class="pd-primary-button pd-button-link">
                    My Workspace →
                </a>

                <form method="POST"
                      action="{{ route('partner-dynamics.retake') }}">
                    @csrf

                    <button type="submit"
                            class="pd-secondary-button">
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
