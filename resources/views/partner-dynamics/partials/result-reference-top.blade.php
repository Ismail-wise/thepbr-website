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
        ->take(4)
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

    $visibleStrengths =
        $strengths->take(5);

    $extraStrengths =
        $strengths->skip(5);

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

    $examples =
        $profileContent['examples']
        ?? [];

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

                    {{
                        $profileContent['title_mm']
                        ?? $primaryVisual['badge_mm']
                    }}

                </div>

                <p class="pd-ref-description">
                    {{
                        $profileContent['who_you_are']
                        ?? $primary['description']
                    }}
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

                    </div>

                    <div
                        class="pd-ref-score-icon secondary"
                        style="
                            --pd-secondary-profile-color:
                            {{ $secondaryVisual['primary'] }};
                        "
                    >

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

                @foreach(
                    $visibleStrengths
                    as $strength
                )

                    <div>

                        <span>✓</span>

                        <p>
                            {{ $strength }}
                        </p>

                    </div>

                @endforeach

            </div>


            @if($extraStrengths->isNotEmpty())

                <details class="pd-ref-inline-details">

                    <summary>
                        View all strengths
                    </summary>

                    <ul>

                        @foreach(
                            $extraStrengths
                            as $strength
                        )

                            <li>
                                {{ $strength }}
                            </li>

                        @endforeach

                    </ul>

                </details>

            @endif


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


        {{-- PARTNERSHIP GUIDANCE --}}

        <aside class="pd-ref-card pd-ref-guidance">


            <div class="pd-ref-guidance-item">

                <div class="pd-ref-guidance-icon">
                    ✦
                </div>

                <div>

                    <strong>
                        သင့်အတွက် သင့်တော်တဲ့ Partner
                    </strong>

                    <p>
                        {{
                            $profileContent[
                                'best_partner_principle'
                            ]
                            ?? ''
                        }}
                    </p>

                </div>

            </div>


            <div class="pd-ref-guidance-item">

                <div class="pd-ref-guidance-icon">
                    ↔
                </div>

                <div>

                    <strong>
                        Working With You
                    </strong>

                    <p>
                        {{
                            $profileContent[
                                'working_with_you'
                            ]
                            ?? ''
                        }}
                    </p>

                </div>

            </div>


            <div class="pd-ref-guidance-item">

                <div class="pd-ref-guidance-icon">
                    !
                </div>

                <div>

                    <strong>
                        Watch-out Area
                    </strong>

                    <p>
                        {{
                            $profileContent[
                                'blind_spot_intro'
                            ]
                            ?? ''
                        }}
                    </p>

                    @if(! empty($blindSpots))

                        <details class="pd-ref-guidance-details" name="pd-guidance">

                            <summary>
                                See Blind Spots
                            </summary>

                            <ul>

                                @foreach(
                                    $blindSpots
                                    as $spot
                                )

                                    <li>
                                        {{ $spot }}
                                    </li>

                                @endforeach

                            </ul>

                        </details>

                    @endif

                </div>

            </div>


            <div class="pd-ref-guidance-item">

                <div class="pd-ref-guidance-icon">
                    ⚡
                </div>

                <div>

                    <strong>
                        Under Pressure & Conflict
                    </strong>

                    @if(! empty($underStress))

                        <p>
                            {{
                                implode(
                                    ' · ',
                                    array_slice(
                                        $underStress,
                                        0,
                                        2
                                    )
                                )
                            }}
                        </p>

                    @endif


                    <details class="pd-ref-guidance-details" name="pd-guidance">

                        <summary>
                            View Guidance
                        </summary>

                        @if(! empty($underStress))

                            <ul>

                                @foreach(
                                    $underStress
                                    as $stress
                                )

                                    <li>
                                        {{ $stress }}
                                    </li>

                                @endforeach

                            </ul>

                        @endif


                        @if(
                            ! empty(
                                $profileContent[
                                    'conflict_style'
                                ]
                            )
                        )

                            <p>
                                <strong>
                                    Conflict Style
                                </strong>
                                <br>

                                {{
                                    $profileContent[
                                        'conflict_style'
                                    ]
                                }}
                            </p>

                        @endif


                        @if(
                            ! empty(
                                $profileContent[
                                    'stress_question'
                                ]
                            )
                        )

                            <blockquote>
                                “{{
                                    $profileContent[
                                        'stress_question'
                                    ]
                                }}”
                            </blockquote>

                        @endif

                    </details>

                </div>

            </div>

        </aside>

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

        <details class="pd-ref-examples">

            <summary>
                People commonly associated with similar traits
            </summary>

            <div class="pd-ref-example-body">

                <div class="pd-ref-example-chips">

                    @foreach(
                        $examples
                        as $example
                    )

                        <span>
                            {{ $example }}
                        </span>

                    @endforeach

                </div>

                <p>
                    {{
                        $contentProfiles[
                            'example_disclaimer'
                        ]
                        ?? ''
                    }}
                </p>

            </div>

        </details>

    @endif

</div>
