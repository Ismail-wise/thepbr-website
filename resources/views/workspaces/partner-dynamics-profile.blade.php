@extends('layouts.student-portal')

@section(
    'title',
    ($assessment->user?->name ?? 'Partner').' — Partner Dynamics'
)

@section('content')

@php

    $profileLabels = \App\Support\PartnerDynamicsProfile::labels();

    $profileDescriptions = [

        'visionary' =>
            'Future direction, opportunities နဲ့ big-picture thinking ကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'builder' =>
            'Ideas ကို action အဖြစ်ပြောင်းပြီး results နဲ့ execution ကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'connector' =>
            'People, communication နဲ့ relationships တည်ဆောက်ခြင်းကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'analyst' =>
            'Data, evidence နဲ့ financial impact ကို စစ်ဆေးပြီး ဆုံးဖြတ်တာကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'operator' =>
            'Daily execution, systems နဲ့ operational consistency ကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'guardian' =>
            'Risk, structure နဲ့ downside protection ကို အလေးထားတဲ့ operating style ဖြစ်ပါတယ်။',

        'negotiator' =>
            'Different viewpoints ကိုညှိနှိုင်းပြီး agreement နဲ့ decision clarity ရအောင်လုပ်တာကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

        'optimizer' =>
            'Existing systems ကို improve လုပ်ခြင်းနဲ့ change ကို adapt လုပ်ခြင်းကို အားသန်တဲ့ operating style ဖြစ်ပါတယ်။',

    ];

    $dimensionLabels = [
        'vision' => 'Vision & Direction',
        'execution' => 'Execution & Delivery',
        'people' => 'People & Influence',
        'analysis' => 'Analysis & Finance',
        'structure' => 'Structure & Control',
        'risk' => 'Risk & Opportunity',
        'decision' => 'Decision & Conflict',
        'adaptability' => 'Adaptability & Change',
    ];

@endphp


<section class="pd-workspace-profile-section">

    <div class="portal-wrap">

        <a
            class="pd-back-home"
            href="{{ route(
                'workspaces.partner-dynamics.show',
                $workspace
            ) }}"
        >
            ← Partnership Alignment
        </a>


        <div class="pd-workspace-profile-hero">

            <div>

                <span class="portal-kicker">
                    {{ $participantRole }}
                </span>

                <h1>
                    {{ $assessment->user?->name ?? 'Partner' }}
                </h1>

                <p>
                    ဒီ profile က {{ $workspace->name }}
                    အတွင်း Partner Dynamics discussion
                    အတွက် share ထားတဲ့ result ဖြစ်ပါတယ်။
                </p>

            </div>

            <div class="pd-workspace-profile-badge">

                <span>Primary Style</span>

                <strong>
                    {{
                        $profileLabels[
                            $assessment->primary_profile
                        ]
                        ?? ucfirst(
                            $assessment->primary_profile
                        )
                    }}
                </strong>

                <small>
                    {{ number_format(
                        $assessment->primary_score,
                        1
                    ) }}
                </small>

            </div>

        </div>


        <section class="pd-alignment-section">

            <div class="pd-panel-heading">

                <span class="portal-kicker">
                    Operating Style
                </span>

                <h2>
                    {{
                        $profileLabels[
                            $assessment->primary_profile
                        ]
                        ?? ucfirst(
                            $assessment->primary_profile
                        )
                    }}
                </h2>

                <p>
                    {{
                        $profileDescriptions[
                            $assessment->primary_profile
                        ]
                        ?? ''
                    }}
                </p>

            </div>


            <div class="pd-workspace-style-grid">

                <article>

                    <span>Primary Style</span>

                    <strong>
                        {{
                            $profileLabels[
                                $assessment->primary_profile
                            ]
                            ?? ucfirst(
                                $assessment->primary_profile
                            )
                        }}
                    </strong>

                    <small>
                        {{ number_format(
                            $assessment->primary_score,
                            1
                        ) }}
                    </small>

                </article>


                <article>

                    <span>Secondary Style</span>

                    <strong>
                        {{
                            $profileLabels[
                                $assessment->secondary_profile
                            ]
                            ?? ucfirst(
                                $assessment->secondary_profile
                            )
                        }}
                    </strong>

                    <small>
                        {{ number_format(
                            $assessment->secondary_score,
                            1
                        ) }}
                    </small>

                </article>


                <article>

                    <span>Operating Pattern</span>

                    <strong>
                        {{
                            $assessment->is_blended
                                ? 'Blended Style'
                                : 'Clear Primary Style'
                        }}
                    </strong>

                    <small>
                        Primary + Secondary
                    </small>

                </article>

            </div>

        </section>


        <section class="pd-alignment-section">

            <div class="pd-panel-heading">

                <span class="portal-kicker">
                    Dimension Map
                </span>

                <h2>
                    Partnership Operating Dimensions
                </h2>

                <p>
                    Score က good / bad ကိုဆိုလိုတာမဟုတ်ပါဘူး။
                    Partner တစ်ယောက်ရဲ့ natural operating
                    preference ကို conversation အတွက်
                    မြင်သာအောင်ပြထားတာပါ။
                </p>

            </div>


            <div class="pd-workspace-dimensions">

                @foreach($dimensionLabels as $dimension => $label)

                    @php
                        $score =
                            $assessment
                                ->dimension_scores[
                                    $dimension
                                ]
                            ?? 0;
                    @endphp

                    <div class="pd-workspace-dimension-row">

                        <div class="pd-workspace-dimension-head">

                            <span>
                                {{ $label }}
                            </span>

                            <strong>
                                {{ number_format(
                                    $score,
                                    0
                                ) }}
                            </strong>

                        </div>

                        <div class="pd-workspace-dimension-track">

                            <span
                                style="width:
                                    {{
                                        max(
                                            0,
                                            min(
                                                100,
                                                $score
                                            )
                                        )
                                    }}%"
                            ></span>

                        </div>

                    </div>

                @endforeach

            </div>

        </section>


        <div class="pd-workspace-profile-note">

            <strong>
                Shared Workspace Profile
            </strong>

            <p>
                ဒီ page မှာ Assessment answers အပြည့်အစုံကို
                မပြပါဘူး။ Partnership discussion အတွက်
                derived profile နဲ့ dimension results ကိုပဲ
                Workspace members အချင်းချင်း share ထားပါတယ်။
            </p>

        </div>

    </div>

</section>

@endsection
