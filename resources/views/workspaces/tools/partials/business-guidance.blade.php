<section
    class="pbr-business-guidance {{ $businessGuidance['status_key'] }}"
    data-pbr-business-guidance
    data-guidance-status="{{ $businessGuidance['status_key'] }}"
>
    <div class="pbr-guidance-top">
        <div>
            <span class="pbr-guidance-kicker">
                REAL BUSINESS USE
            </span>

            <strong>
                {{ $businessGuidance['status_label'] }}
            </strong>

            <p>
                {{ $businessGuidance['next_action_mm'] }}
            </p>
        </div>

        @if(!empty($businessGuidance['headline']))
            <div class="pbr-guidance-signal">
                <span>
                    CURRENT RESULT SIGNAL
                </span>

                <strong>
                    {{
                        $formatValue(
                            $businessGuidance[
                                'headline'
                            ]['value'] ?? null,
                            $businessGuidance[
                                'headline'
                            ]['format'] ?? 'text'
                        )
                    }}
                </strong>

                <small>
                    {{
                        $businessGuidance[
                            'headline'
                        ]['label'] ?? 'Result'
                    }}
                </small>
            </div>
        @endif
    </div>

    <div class="pbr-guidance-grid">
        <article>
            <span>WHAT HAPPENS AFTER APPROVAL</span>
            <p>
                {{
                    $businessGuidance[
                        'approval_effect_mm'
                    ]
                }}
            </p>
        </article>

        <article>
            <span>CONNECTED BUSINESS EFFECT</span>
            <p>
                {{
                    $businessGuidance[
                        'connection_effect_mm'
                    ]
                }}
            </p>

            @if(
                !empty(
                    $businessGuidance[
                        'downstream_domains'
                    ]
                )
            )
                <div class="pbr-guidance-tags">
                    @foreach(
                        $businessGuidance[
                            'downstream_domains'
                        ]
                        as $domain
                    )
                        <i>
                            {{ $domain['name'] }}
                        </i>
                    @endforeach
                </div>
            @endif
        </article>

        <article>
            <span>DECISION QUESTIONS</span>

            <ul>
                @foreach(
                    $businessGuidance[
                        'business_questions'
                    ]
                    as $question
                )
                    <li>
                        {{ $question }}
                    </li>
                @endforeach
            </ul>
        </article>
    </div>

    @if(
        !empty($businessGuidance['source_names'])
        || !empty($businessGuidance['advisory_names'])
    )
        <div class="pbr-guidance-sources">
            @if(
                !empty(
                    $businessGuidance[
                        'source_names'
                    ]
                )
            )
                <div>
                    <span>APPROVED DATA SOURCES</span>

                    <strong>
                        {{
                            implode(
                                ' · ',
                                $businessGuidance[
                                    'source_names'
                                ]
                            )
                        }}
                    </strong>
                </div>
            @endif

            @if(
                !empty(
                    $businessGuidance[
                        'advisory_names'
                    ]
                )
            )
                <div>
                    <span>ADVISORY SOURCES</span>

                    <strong>
                        {{
                            implode(
                                ' · ',
                                $businessGuidance[
                                    'advisory_names'
                                ]
                            )
                        }}
                    </strong>
                </div>
            @endif
        </div>
    @endif

    <details class="pbr-guidance-guardrails">
        <summary>
            Data & Approval Guardrails
        </summary>

        <ul>
            @foreach(
                $businessGuidance['guardrails']
                as $guardrail
            )
                <li>
                    {{ $guardrail }}
                </li>
            @endforeach
        </ul>
    </details>
</section>
