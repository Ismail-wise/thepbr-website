@extends('layouts.student-portal')

@section('title', 'Partner Dynamics')

@section('content')

@php
    $profileLabels = [
        'visionary' => 'Visionary',
        'builder' => 'Builder',
        'connector' => 'Connector',
        'analyst' => 'Analyst',
        'operator' => 'Operator',
        'guardian' => 'Guardian',
        'negotiator' => 'Negotiator',
        'optimizer' => 'Optimizer',
    ];
@endphp

<section class="pd-section">
    <div class="portal-wrap">

        <div class="pd-hero">
            <div class="pd-hero-copy">
                <span class="portal-kicker">PBR Partner Dynamics</span>

                <h1>
                    Partnership မှာ သင်က
                    <span>ဘယ်လိုလူမျိုးလဲ?</span>
                </h1>

                <p>
                    Partner Dynamics က သင့်ရဲ့ Partnership Operating Style,
                    အားသာချက်တွေ၊ ဆုံးဖြတ်ချက်ချတဲ့ပုံစံနဲ့
                    လုပ်ငန်းအတွင်း သဘာဝကျကျ တာဝန်ယူတတ်တဲ့နေရာတွေကို
                    နားလည်စေဖို့ ဖန်တီးထားတဲ့ PBR Assessment ဖြစ်ပါတယ်။
                </p>

                <div class="pd-meta-row">
                    <div>
                        <strong>40</strong>
                        <span>Questions</span>
                    </div>

                    <div>
                        <strong>8–12</strong>
                        <span>Minutes</span>
                    </div>

                    <div>
                        <strong>8</strong>
                        <span>Dimensions</span>
                    </div>
                </div>
            </div>

            <div class="pd-intro-card">

                @if(!$latestAssessment)

                    <span class="pd-card-kicker">Your Assessment</span>

                    <h2>သင့် Operating Style ကို ရှာဖွေပါ</h2>

                    <p>
                        မှန်တဲ့အဖြေ၊ မှားတဲ့အဖြေ မရှိပါဘူး။
                        လက်တွေ့အလုပ်လုပ်တဲ့အချိန်မှာ သင်ဘာလုပ်တတ်လဲဆိုတာကို
                        အမှန်ဆုံးရွေးချယ်ပေးပါ။
                    </p>

                    <form method="POST"
                          action="{{ route('partner-dynamics.start') }}">
                        @csrf

                        <button class="pd-primary-button" type="submit">
                            Assessment စတင်မယ်
                            <span>→</span>
                        </button>
                    </form>

                @elseif(!$latestAssessment->isCompleted())

                    <span class="pd-card-kicker">Assessment In Progress</span>

                    <h2>သင်ဖြေထားတာတွေ Save လုပ်ထားပါတယ်</h2>

                    <p>
                        အရင်ဖြေထားတဲ့နေရာကနေ ဆက်လက်ဖြေဆိုနိုင်ပါတယ်။
                        အစကနေ ပြန်စရာမလိုပါဘူး။
                    </p>

                    <div class="pd-saved-note">
                        <span class="pd-saved-dot"></span>
                        Progress Saved
                    </div>

                    <form method="POST"
                          action="{{ route('partner-dynamics.start') }}">
                        @csrf

                        <button class="pd-primary-button" type="submit">
                            Assessment ဆက်ဖြေမယ်
                            <span>→</span>
                        </button>
                    </form>

                @else

                    <span class="pd-card-kicker">Latest Result</span>

                    <h2>
                        {{ $profileLabels[$latestAssessment->primary_profile]
                            ?? ucfirst($latestAssessment->primary_profile) }}
                    </h2>

                    @if($latestAssessment->is_blended)
                        <div class="pd-result-badge">
                            Blended Operating Style
                        </div>
                    @endif

                    <p>
                        Primary Score:
                        <strong>
                            {{ number_format($latestAssessment->primary_score, 1) }}
                        </strong>

                        · Secondary:

                        <strong>
                            {{ $profileLabels[$latestAssessment->secondary_profile]
                                ?? ucfirst($latestAssessment->secondary_profile) }}
                        </strong>
                    </p>

                    <a class="pd-primary-button pd-button-link"
                       href="{{ route('partner-dynamics.result', $latestAssessment) }}">
                        Result ကြည့်မယ်
                        <span>→</span>
                    </a>

                @endif

            </div>
        </div>


        <div class="pd-info-grid">

            <article class="pd-info-card">
                <span>01</span>
                <h3>သင့် Strength ကို သိမယ်</h3>
                <p>
                    Partnership ထဲမှာ သင်က naturally အားသာတတ်တဲ့
                    operating areas တွေကို ရှာဖွေပေးပါတယ်။
                </p>
            </article>

            <article class="pd-info-card">
                <span>02</span>
                <h3>Partner နဲ့ Difference ကို နားလည်မယ်</h3>
                <p>
                    တစ်ယောက်နဲ့တစ်ယောက် ဘာကြောင့် အမြင်မတူနိုင်လဲဆိုတာကို
                    နောက်ပိုင်း Partnership Alignment မှာ အသုံးချနိုင်ပါတယ်။
                </p>
            </article>

            <article class="pd-info-card">
                <span>03</span>
                <h3>Role တွေကို ပိုရှင်းအောင်လုပ်မယ်</h3>
                <p>
                    ဘယ်သူက ဘယ်အပိုင်းမှာ ownership ယူသင့်လဲဆိုတာကို
                    discussion လုပ်ရာမှာ guide အဖြစ်အသုံးပြုနိုင်ပါတယ်။
                </p>
            </article>

        </div>


        <div class="pd-disclaimer">
            <strong>PBR Note</strong>

            <p>
                ဒီ Assessment က Partner တစ်ယောက်က ကောင်းတယ်၊ မကောင်းဘူး
                ဒါမှမဟုတ် Partnership လုပ်သင့်တယ်၊ မလုပ်သင့်ဘူးလို့
                ဆုံးဖြတ်ပေးတဲ့ test မဟုတ်ပါဘူး။
                ပိုကောင်းတဲ့ discussion နဲ့ role clarity ရရှိဖို့
                decision-support tool အဖြစ် အသုံးပြုထားတာပါ။
            </p>
        </div>

    </div>
</section>

@endsection
