@php

    /*
    |--------------------------------------------------------------------------
    | Partner Dynamics — Rich Profile Content
    |--------------------------------------------------------------------------
    | The assessment/scoring engine is untouched.
    | This view only presents richer business-partnership guidance.
    */

    $contentProfiles = config('partner_dynamics_content', []);

    $profileContent =
        $contentProfiles[$assessment->primary_profile]
        ?? [];

    $secondaryContent =
        $contentProfiles[$assessment->secondary_profile]
        ?? [];

    $dimensionRows = collect(
        $assessment->dimension_scores ?? []
    )
        ->map(function ($score, $key) use ($dimensions) {
            return [
                'key' => $key,
                'label' =>
                    $dimensions[$key]
                    ?? ucfirst($key),
                'score' => (float) $score,
            ];
        })
        ->sortByDesc('score')
        ->values();

    $illustrationFile =
        $primaryVisual['illustration']
        ?? null;

    $illustrationRelativePath =
        $illustrationFile
            ? 'images/partner-dynamics/profiles/'
                .$illustrationFile
            : null;

    $illustrationAsset =
        $illustrationRelativePath
        && file_exists(
            public_path($illustrationRelativePath)
        )
            ? asset($illustrationRelativePath)
            : null;

    $bestPartnerName =
        data_get(
            $partnerMatch ?? [],
            'recommendations.0.name',
            $secondary['name']
        );

    $strengths = collect(
        $profileContent['core_strengths']
        ?? []
    );

    $blindSpots =
        $profileContent['blind_spots']
        ?? [];

    $underStress =
        $profileContent['under_stress']
        ?? [];

    $roles =
        $profileContent['best_roles']
        ?? [];

    $workingEnvironment =
        $profileContent['working_environment']
        ?? [];

    $developmentSkills =
        $profileContent['development_skills']
        ?? [];

    $examples = \App\Support\PartnerDynamicsExample::resolve(
        $profileContent['examples'] ?? []
    );

@endphp


<div class="pd-ref-result">

    {{-- =====================================================
         HERO
         ===================================================== --}}

    <div class="pd-ref-hero-row">

        <section class="pd-ref-hero-main">

            <div class="pd-ref-hero-copy">

                <span class="pd-ref-kicker">
                    Your Partner Dynamics Result
                </span>

                <p class="pd-ref-eyebrow">
                    Your Primary Operating Style
                </p>

                <h1>
                    {{ $primary['name'] }}
                </h1>

                <div class="pd-ref-profile-badge">

                    <span>✦</span>

                    {{ $primary['title_mm'] }}

                </div>

                <p class="pd-ref-description">
                    {{ $primary['description'] }}
                </p>


                @if($assessment->is_blended)

                    <div class="pd-ref-blended">

                        <strong>

                            {{ $primary['name'] }}

                            <span>+</span>

                            <em
                                style="
                                    color:
                                    {{ $secondaryVisual['primary'] }};
                                "
                            >
                                {{ $secondary['name'] }}
                            </em>

                        </strong>

                        <small>
                            Blended Operating Style
                        </small>

                        @if(
                            ! empty(
                                $secondaryContent[
                                    'secondary_influence'
                                ]
                            )
                        )

                            <p class="pd-ref-secondary-influence">
                                {{
                                    $secondaryContent[
                                        'secondary_influence'
                                    ]
                                }}
                            </p>

                        @endif

                    </div>

                @endif

            </div>


            <div class="pd-ref-hero-art">

                @if($illustrationAsset)

                    <img
                        src="{{ $illustrationAsset }}"
                        alt="{{ $primary['name'] }} Partner Dynamics illustration"
                    >

                @else

                    <div class="pd-ref-art-placeholder">

                        <span>✦</span>

                        <strong>
                            {{ $primary['name'] }}
                        </strong>

                    </div>

                @endif

            </div>

        </section>


        {{-- SCORE CARD --}}

        <aside class="pd-ref-score-card">

            <div class="pd-ref-score-block">

                <span class="pd-ref-score-label">
                    Primary
                </span>

                <div class="pd-ref-score-line">

                    <div>

                        <strong>
                            {{
                                number_format(
                                    $assessment->primary_score,
                                    1
                                )
                            }}
                        </strong>

                        <small>
                            {{ $primary['name'] }}
                        </small>

                        @if(! empty($primary['title_mm']))

                            <span class="pd-ref-score-mm">
                                {{ $primary['title_mm'] }}
                            </span>

                        @endif

                    </div>

                    <div class="pd-ref-score-icon">

                        @include(
                            'partner-dynamics.partials.profile-symbol',
                            ['symbol' => $assessment->primary_profile]
                        )

                    </div>

                </div>

            </div>


            <div class="pd-ref-score-divider"></div>


            <div class="pd-ref-score-block">

                <span class="pd-ref-score-label">
                    Secondary
                </span>

                <div class="pd-ref-score-line">

                    <div>

                        <strong class="secondary">
                            {{
                                number_format(
                                    $assessment->secondary_score,
                                    1
                                )
                            }}
                        </strong>

                        <small>
                            {{ $secondary['name'] }}
                        </small>

                        @if(! empty($secondary['title_mm']))

                            <span class="pd-ref-score-mm">
                                {{ $secondary['title_mm'] }}
                            </span>

                        @endif

                    </div>

                    <div class="pd-ref-score-icon secondary">

                        @include(
                            'partner-dynamics.partials.profile-symbol',
                            ['symbol' => $assessment->secondary_profile]
                        )

                    </div>

                </div>

            </div>

        </aside>

    </div>


    {{-- =====================================================
         MAIN INFORMATION GRID
         ===================================================== --}}

    <div class="pd-ref-content-grid">


        {{-- DIMENSION MAP --}}

        <section class="pd-ref-card pd-ref-dimensions">

            <span class="pd-ref-card-kicker">
                Your Dimension Map
            </span>

            <h2>
                Partnership Operating Dimensions
            </h2>


            <div class="pd-ref-dimension-list">

                @foreach($dimensionRows as $row)

                    <div class="pd-ref-dimension">

                        <div class="pd-ref-dimension-icon">
                            {{ $loop->iteration }}
                        </div>

                        <div class="pd-ref-dimension-content">

                            <div class="pd-ref-dimension-head">

                                <span>
                                    {{ $row['label'] }}
                                </span>

                                <strong>
                                    {{
                                        number_format(
                                            $row['score'],
                                            0
                                        )
                                    }}

                                    <small>
                                        / 100
                                    </small>
                                </strong>

                            </div>

                            <div class="pd-ref-dimension-track">

                                <span
                                    style="
                                        width:
                                        {{
                                            max(
                                                0,
                                                min(
                                                    100,
                                                    $row['score']
                                                )
                                            )
                                        }}%;
                                    "
                                ></span>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>


            <div class="pd-ref-card-note">

                <span>i</span>

                <div>
                    ဒီ scores တွေက Personality ကို
                    good / bad သတ်မှတ်တာမဟုတ်ဘဲ
                    သင့် operating tendencies ကို
                    နားလည်ဖို့ အသုံးပြုတာပါ။
                </div>

            </div>

        </section>


        {{-- STRENGTHS + ROLES --}}

        <section class="pd-ref-card pd-ref-strengths">

            <span class="pd-ref-card-kicker">
                {{ $primary['name'] }} Strengths
            </span>


            <div class="pd-ref-strength-list">

                @foreach($strengths as $strength)

                    <div>

                        <span>✓</span>

                        <p>
                            {{ $strength }}
                        </p>

                    </div>

                @endforeach

            </div>





            <div class="pd-ref-role-divider"></div>


            <span class="pd-ref-card-kicker pd-ref-role-kicker">
                Best Business Roles
            </span>


            <div class="pd-ref-role-grid">

                @foreach(
                    array_slice($roles, 0, 4)
                    as $role
                )

                    <div class="pd-ref-role">

                        <span>
                            {{ $loop->iteration }}
                        </span>

                        <small>
                            {{ $role }}
                        </small>

                    </div>

                @endforeach

            </div>


            @if(! empty($workingEnvironment))

                <div class="pd-ref-environment">

                    <strong>
                        Works Best When
                    </strong>

                    <div>

                        @foreach(
                            $workingEnvironment
                            as $environment
                        )

                            <span>
                                {{ $environment }}
                            </span>

                        @endforeach

                    </div>

                </div>

            @endif

        </section>

    </div>


    {{-- =====================================================
         SECOND ROW — STRENGTHS' COUNTERPART AND GUIDANCE
         ===================================================== --}}

    <div class="pd-ref-content-grid pd-ref-content-grid-b">


        {{-- BLIND SPOTS --}}

        <section class="pd-ref-card pd-ref-blindspots">

            <span class="pd-ref-card-kicker">
                Blind Spots
            </span>

            <h2>
                မမြင်မိတတ်တဲ့ အားနည်းချက်
            </h2>

            @if(! empty($profileContent['blind_spot_intro']))

                <p class="pd-ref-blindspot-intro">
                    {{ $profileContent['blind_spot_intro'] }}
                </p>

            @endif

            @if(! empty($blindSpots))

                <ul class="pd-ref-blindspot-list">

                    @foreach($blindSpots as $spot)

                        <li>
                            <span>!</span>
                            <p>{{ $spot }}</p>
                        </li>

                    @endforeach

                </ul>

            @endif

        </section>


        {{-- WORKING WITH YOU --}}

        <section class="pd-ref-card pd-ref-section">

            <span class="pd-ref-card-kicker">
                Working With You
            </span>

            <h2>
                သင်နဲ့ အလုပ်လုပ်သူများ သိထားသင့်တာ
            </h2>

            <p class="pd-ref-section-lead">
                {{ $profileContent['working_with_you'] ?? '' }}
            </p>

        </section>

    </div>


    {{-- =====================================================
         THIRD ROW — UNDER STRESS AND CONFLICT STYLE
         ===================================================== --}}

    <div class="pd-ref-content-grid pd-ref-content-grid-c">


        {{-- UNDER STRESS --}}

        <section class="pd-ref-card pd-ref-section">

            <span class="pd-ref-card-kicker">
                Under Stress
            </span>

            <h2>
                ဖိအားအောက်မှာ ဘယ်လိုပြောင်းလဲတတ်လဲ
            </h2>

            @if(! empty($underStress))

                <ul class="pd-ref-marker-list">

                    @foreach($underStress as $stress)

                        <li>
                            <span>⚡</span>
                            <p>{{ $stress }}</p>
                        </li>

                    @endforeach

                </ul>

            @endif

        </section>


        {{-- CONFLICT STYLE --}}

        <section class="pd-ref-card pd-ref-section">

            <span class="pd-ref-card-kicker">
                Conflict Style
            </span>

            <h2>
                သဘောထားကွဲလွဲတဲ့အခါ
            </h2>

            @if(! empty($profileContent['conflict_style']))

                <p class="pd-ref-section-lead">
                    {{ $profileContent['conflict_style'] }}
                </p>

            @endif

            @if(! empty($profileContent['stress_question']))

                <blockquote class="pd-ref-section-quote">
                    “{{ $profileContent['stress_question'] }}”
                </blockquote>

            @endif

        </section>

    </div>


    {{-- =====================================================
         DEVELOPMENT ADVICE
         ===================================================== --}}

    <section class="pd-ref-development">

        <div class="pd-ref-development-icon">
            ↗
        </div>

        <div>

            <strong>
                {{
                    $profileContent[
                        'growth_tagline'
                    ]
                    ?? $primary['name']
                        .' Development Advice'
                }}
            </strong>

            <p>
                {{
                    $profileContent[
                        'growth_advice'
                    ]
                    ?? ''
                }}
            </p>


            @if(! empty($developmentSkills))

                <div class="pd-ref-development-skills">

                    @foreach(
                        $developmentSkills
                        as $skill
                    )

                        <span>
                            {{ $skill }}
                        </span>

                    @endforeach

                </div>

            @endif

        </div>

        <a href="#partner-match">
            View Partner Match
        </a>

    </section>


    {{-- =====================================================
         OPTIONAL EXAMPLES
         ===================================================== --}}

    @if(! empty($examples))

        <section class="pd-ref-examples">

            <h2 class="pd-ref-examples-title">
                ဤလုပ်ဆောင်ပုံနဲ့ ဆင်တူသူများ
            </h2>

            <div class="pd-ref-example-grid">

                @foreach($examples as $example)

                    <div class="pd-ref-example">

                        <span
                            class="pd-ref-example-avatar"
                            aria-hidden="true"
                        >
                            {{ $example['initials'] }}
                        </span>

                        <div class="pd-ref-example-text">

                            <strong>
                                {{ $example['name'] }}
                            </strong>

                            @if(! empty($example['note']))

                                <span>
                                    {{ $example['note'] }}
                                </span>

                            @endif

                        </div>

                    </div>

                @endforeach

            </div>

            <p class="pd-ref-example-note">
                {{
                    $contentProfiles['example_disclaimer']
                    ?? ''
                }}
            </p>

        </section>

    @endif

</div>
