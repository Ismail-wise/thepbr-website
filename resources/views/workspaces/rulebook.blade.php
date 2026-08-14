@extends('layouts.student-portal')

@section(
    'title',
    'Business Rulebook — '
    .$rulebook['business_name']
)

@section('content')
@php
    $currency =
        $rulebook['currency_code'];

    $formatRuleValue =
        static function (
            $value,
            string $format = 'text'
        ) use ($currency) {
            if (
                $value === null
                || $value === ''
            ) {
                return '—';
            }

            return match ($format) {
                'money' =>
                    $currency.' '
                    .number_format(
                        (float) $value,
                        2
                    ),

                'percent' =>
                    number_format(
                        (float) $value,
                        2
                    ).'%',

                'units' =>
                    number_format(
                        (float) $value,
                        2
                    ).' Units',

                'days' =>
                    number_format(
                        (float) $value,
                        0
                    ).' Days',

                'months' =>
                    number_format(
                        (float) $value,
                        2
                    ).' Months',

                'number' =>
                    is_numeric($value)
                        ? number_format(
                            (float) $value,
                            2
                        )
                        : (string) $value,

                default =>
                    (string) $value,
            };
        };
@endphp

<section
    class="pbr-rulebook-page"
    data-pbr-rulebook
>
    <div class="portal-wrap rulebook-wrap">
        <nav
            class="rulebook-breadcrumb no-print"
            aria-label="Breadcrumb"
        >
            <a
                href="{{
                    route(
                        'workspaces.show',
                        $workspace
                    )
                }}"
            >
                {{
                    $rulebook[
                        'business_name'
                    ]
                }}
            </a>

            <span>›</span>
            <span>Business Rulebook</span>
        </nav>

        <header class="rulebook-hero">
            <div>
                <span class="rulebook-kicker">
                    PBR BUSINESS RULEBOOK
                </span>

                <h1>
                    {{
                        $rulebook[
                            'business_name'
                        ]
                    }}
                </h1>

                <p>
                    Approved Current Rules နဲ့
                    Active Operating Records ကိုသာ
                    စုစည်းထားတဲ့ လက်ရှိ Business
                    Operating Rulebook ဖြစ်ပါတယ်။
                    Working Draft နဲ့ private scenario
                    တွေ ဒီ document ထဲ မပါပါဘူး။
                </p>

                <div class="rulebook-meta">
                    <span>
                        {{
                            $rulebook[
                                'business_stage'
                            ]
                            === 'existing'
                                ? 'Operating Business'
                                : 'New Business'
                        }}
                    </span>

                    <span>
                        {{
                            $rulebook[
                                'currency_code'
                            ]
                        }}
                    </span>

                    <span>
                        Generated
                        {{
                            \Carbon\Carbon::parse(
                                $rulebook[
                                    'generated_at'
                                ]
                            )->format(
                                'd M Y, H:i'
                            )
                        }}
                    </span>

                    @unless(
                        $rulebook[
                            'can_manage'
                        ]
                    )
                        <span>
                            Partner Read-Only
                        </span>
                    @endunless
                </div>
            </div>

            <div class="rulebook-actions no-print">
                <a
                    href="{{
                        route(
                            'workspaces.tools.index',
                            $workspace
                        )
                    }}"
                >
                    Open Operating System
                </a>

                <button
                    type="button"
                    onclick="window.print()"
                >
                    Print / Save PDF
                </button>
            </div>
        </header>

        <section class="rulebook-scope">
            <strong>
                Approved-only source of truth
            </strong>

            <p>
                ဒီ Rulebook က agreed domain
                snapshots နဲ့ approved active
                operating records ကိုပဲဖတ်ပါတယ်။
                Draft၊ Working Change၊ proposed
                transfer သို့မဟုတ် unapproved
                scenario ကို current business truth
                အဖြစ် မပြပါဘူး။
            </p>
        </section>

        <section class="rulebook-kpis">
            <article>
                <span>OPERATING AREAS</span>
                <strong>
                    {{
                        $rulebook[
                            'metrics'
                        ][
                            'area_count'
                        ]
                    }}
                </strong>
                <small>
                    10 Operating Areas
                </small>
            </article>

            <article>
                <span>CONFIGURED AREAS</span>
                <strong>
                    {{
                        $rulebook[
                            'metrics'
                        ][
                            'configured_area_count'
                        ]
                    }}
                </strong>
                <small>
                    Approved data / history
                </small>
            </article>

            <article>
                <span>CURRENT RULES</span>
                <strong>
                    {{
                        $rulebook[
                            'metrics'
                        ][
                            'current_rule_count'
                        ]
                    }}
                </strong>
                <small>
                    Active revisions
                </small>
            </article>

            <article>
                <span>OPERATING RECORDS</span>
                <strong>
                    {{
                        $rulebook[
                            'metrics'
                        ][
                            'operating_record_count'
                        ]
                    }}
                </strong>
                <small>
                    Approved history
                </small>
            </article>
        </section>

        <main class="rulebook-sections">
            @foreach(
                $rulebook['sections']
                as $section
            )
                <section
                    class="rulebook-area"
                    data-rulebook-area="{{
                        $section[
                            'chapter_number'
                        ]
                    }}"
                >
                    <header class="rulebook-area-head">
                        <div>
                            <span>
                                OPERATING AREA
                                {{
                                    $section[
                                        'chapter_number'
                                    ]
                                }}
                            </span>

                            <h2>
                                {{
                                    $section[
                                        'name_mm'
                                    ]
                                }}
                            </h2>

                            <p>
                                {{
                                    $section[
                                        'name_en'
                                    ]
                                }}
                            </p>
                        </div>

                        <div>
                            @if(
                                $section[
                                    'configured'
                                ]
                            )
                                <span class="rulebook-active">
                                    ✓ Approved State
                                </span>

                                @if(
                                    $section[
                                        'revision'
                                    ]
                                )
                                    <small>
                                        Domain Revision
                                        {{
                                            $section[
                                                'revision'
                                            ]
                                        }}
                                    </small>
                                @endif
                            @else
                                <span class="rulebook-setup">
                                    Setup Required
                                </span>
                            @endif
                        </div>
                    </header>

                    @if(
                        !empty(
                            $section[
                                'summary_items'
                            ]
                        )
                    )
                        <div class="rulebook-summary">
                            @foreach(
                                $section[
                                    'summary_items'
                                ]
                                as $item
                            )
                                <div>
                                    <span>
                                        {{
                                            $item[
                                                'label'
                                            ]
                                        }}
                                    </span>

                                    <strong>
                                        @if(
                                            is_bool(
                                                $item[
                                                    'value'
                                                ]
                                            )
                                        )
                                            {{
                                                $item[
                                                    'value'
                                                ]
                                                    ? 'Yes'
                                                    : 'No'
                                            }}
                                        @elseif(
                                            $item[
                                                'value'
                                            ]
                                            === null
                                            || $item[
                                                'value'
                                            ]
                                            === ''
                                        )
                                            —
                                        @else
                                            {{
                                                $item[
                                                    'value'
                                                ]
                                            }}
                                        @endif
                                    </strong>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(
                        !empty(
                            $section[
                                'current_rules'
                            ]
                        )
                    )
                        <div class="rulebook-subsection">
                            <div class="rulebook-subhead">
                                <span>
                                    CURRENT APPROVED RULES
                                </span>

                                <strong>
                                    {{
                                        count(
                                            $section[
                                                'current_rules'
                                            ]
                                        )
                                    }}
                                    Active
                                </strong>
                            </div>

                            <div class="rulebook-rule-grid">
                                @foreach(
                                    $section[
                                        'current_rules'
                                    ]
                                    as $rule
                                )
                                    <article class="rulebook-rule">
                                        <div class="rulebook-rule-head">
                                            <div>
                                                <strong>
                                                    {{
                                                        $rule[
                                                            'title_mm'
                                                        ]
                                                        ?: $rule[
                                                            'title_en'
                                                        ]
                                                    }}
                                                </strong>

                                                <small>
                                                    {{
                                                        $rule[
                                                            'title_en'
                                                        ]
                                                    }}
                                                </small>
                                            </div>

                                            @if(
                                                $rule[
                                                    'revision'
                                                ]
                                            )
                                                <span>
                                                    Rev
                                                    {{
                                                        $rule[
                                                            'revision'
                                                        ]
                                                    }}
                                                </span>
                                            @endif
                                        </div>

                                        @if(
                                            !empty(
                                                $rule[
                                                    'headline'
                                                ]
                                            )
                                        )
                                            <div class="rulebook-result">
                                                <span>
                                                    {{
                                                        $rule[
                                                            'headline'
                                                        ][
                                                            'label'
                                                        ]
                                                        ?? 'Current Result'
                                                    }}
                                                </span>

                                                <strong>
                                                    {{
                                                        $formatRuleValue(
                                                            $rule[
                                                                'headline'
                                                            ][
                                                                'value'
                                                            ]
                                                            ?? null,
                                                            $rule[
                                                                'headline'
                                                            ][
                                                                'format'
                                                            ]
                                                            ?? 'text'
                                                        )
                                                    }}
                                                </strong>
                                            </div>
                                        @endif

                                        @if(
                                            !empty(
                                                $rule[
                                                    'warnings'
                                                ]
                                            )
                                        )
                                            <details>
                                                <summary>
                                                    Approved Review Notes
                                                    ({{
                                                        count(
                                                            $rule[
                                                                'warnings'
                                                            ]
                                                        )
                                                    }})
                                                </summary>

                                                <ul>
                                                    @foreach(
                                                        $rule[
                                                            'warnings'
                                                        ]
                                                        as $warning
                                                    )
                                                        <li>
                                                            {{
                                                                $warning
                                                            }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(
                        !empty(
                            $section[
                                'records'
                            ]
                        )
                    )
                        <div class="rulebook-subsection">
                            <div class="rulebook-subhead">
                                <span>
                                    APPROVED OPERATING HISTORY
                                </span>

                                <strong>
                                    {{
                                        count(
                                            $section[
                                                'records'
                                            ]
                                        )
                                    }}
                                    Records
                                </strong>
                            </div>

                            <div class="rulebook-records">
                                @foreach(
                                    $section[
                                        'records'
                                    ]
                                    as $record
                                )
                                    <article class="rulebook-record">
                                        <div>
                                            <strong>
                                                {{
                                                    $record[
                                                        'title'
                                                    ]
                                                    ?: (
                                                        $record[
                                                            'tool_title'
                                                        ]
                                                        ?: str(
                                                            $record[
                                                                'record_type'
                                                            ]
                                                        )
                                                            ->replace(
                                                                '_',
                                                                ' '
                                                            )
                                                            ->title()
                                                    )
                                                }}
                                            </strong>

                                            <small>
                                                {{
                                                    $record[
                                                        'record_date'
                                                    ]
                                                    ?? (
                                                        $record[
                                                            'effective_at'
                                                        ]
                                                        ? \Carbon\Carbon::parse(
                                                            $record[
                                                                'effective_at'
                                                            ]
                                                        )->format(
                                                            'd M Y'
                                                        )
                                                        : 'Approved Record'
                                                    )
                                                }}
                                            </small>
                                        </div>

                                        <span>
                                            {{
                                                str(
                                                    $record[
                                                        'record_type'
                                                    ]
                                                )
                                                    ->replace(
                                                        '_',
                                                        ' '
                                                    )
                                                    ->title()
                                            }}
                                        </span>

                                        @php
                                            $recordScalars =
                                                collect(
                                                    $record[
                                                        'data'
                                                    ]
                                                    ?? []
                                                )
                                                    ->filter(
                                                        fn ($value) =>
                                                            is_scalar(
                                                                $value
                                                            )
                                                            || $value
                                                            === null
                                                    )
                                                    ->take(8);
                                        @endphp

                                        @if(
                                            $recordScalars
                                                ->isNotEmpty()
                                        )
                                            <div class="rulebook-record-data">
                                                @foreach(
                                                    $recordScalars
                                                    as $key => $value
                                                )
                                                    <div>
                                                        <span>
                                                            {{
                                                                str(
                                                                    (string) $key
                                                                )
                                                                    ->replace(
                                                                        '_',
                                                                        ' '
                                                                    )
                                                                    ->title()
                                                            }}
                                                        </span>

                                                        <strong>
                                                            {{
                                                                $value
                                                                === null
                                                                || $value
                                                                === ''
                                                                    ? '—'
                                                                    : (
                                                                        is_bool(
                                                                            $value
                                                                        )
                                                                            ? (
                                                                                $value
                                                                                    ? 'Yes'
                                                                                    : 'No'
                                                                            )
                                                                            : $value
                                                                    )
                                                            }}
                                                        </strong>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @unless(
                        $section['configured']
                    )
                        <div class="rulebook-empty">
                            Approved Current Rule သို့မဟုတ်
                            Approved Operating Record
                            မရှိသေးပါ။
                        </div>
                    @endunless
                </section>
            @endforeach
        </main>

        <footer class="rulebook-footer">
            <strong>
                PBR Operating Note
            </strong>

            <p>
                ဒီ Rulebook က business planning,
                operating governance နဲ့ internal
                decision support အတွက်ဖြစ်ပါတယ်။
                Legal, tax, accounting, insurance
                သို့မဟုတ် certified valuation advice
                ကို အစားမထိုးပါ။
            </p>
        </footer>
    </div>
</section>
@endsection
