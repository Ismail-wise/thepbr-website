@php
    $matchLabels = [
        'အကောင်းဆုံး ဖြည့်ဆည်းမှု',
        'ညီမျှစွာ ဖြည့်ဆည်းမှု',
        'ထောက်ပံ့ ဖြည့်ဆည်းမှု',
    ];
@endphp

<section class="pd-fold-match" id="partner-match">

    <div class="pd-fold-match-header">

        <div>

            <span class="pd-fold-kicker">
                သင့်အတွက် PARTNER MATCH
            </span>

            <h2>
                သင့် Working Style ကို
                <span>ဟန်ချက်</span>
                လုပ်ပေးနိုင်မယ့် မိတ်ဖက် အမျိုးအစားများ
            </h2>

            <p>
                ဒီ Recommendation က Primary Profile
                တစ်ခုတည်းကို ကြည့်ထားတာမဟုတ်ဘဲ
                သင့် Dimension Scores အားလုံးကို
                အသုံးပြုပြီး complementary မိတ်ဖက် အမျိုးအစားများ
                တွေကို ရွေးပေးထားတာပါ။
            </p>

        </div>

        <div class="pd-fold-count">
            <strong>
                {{ count($partnerMatch['recommendations'] ?? []) }}
            </strong>

            <span>
                အကြံပြုထားသော<br>
                မိတ်ဖက် အမျိုးအစားများ
            </span>
        </div>

    </div>


    @if(! empty($partnerMatch['priority_needs']))

        <div class="pd-fold-priority">

            <strong>
                အဓိက ဖြည့်ဆည်းပေးနိုင်မယ့် နေရာများ
            </strong>

            <div>

                @foreach($partnerMatch['priority_needs'] as $need)

                    <span>
                        {{ $need['label'] }}
                    </span>

                @endforeach

            </div>

        </div>

    @endif


    <div class="pd-fold-list">

        @foreach(
            $partnerMatch['recommendations'] ?? []
            as $index => $recommendation
        )

            <details class="pd-fold-item" @if($index === 0) open @endif>

                <summary>

                    <div class="pd-fold-rank">
                        0{{ $index + 1 }}
                    </div>

                    <div class="pd-fold-summary-main">

                        <span>
                            {{
                                $matchLabels[$index]
                                ?? 'ဖြည့်ဆည်းနိုင်မှု'
                            }}
                        </span>

                        <h3>
                            {{ $recommendation['name'] }}
                        </h3>

                        <p>
                            {{ $recommendation['description'] }}
                        </p>

                    </div>

                    <div class="pd-fold-summary-right">

                        <small>
                            {{
                                $recommendation[
                                    'recommendation_label'
                                ] ?? ''
                            }}
                        </small>

                        <span class="pd-fold-toggle">
                            အသေးစိတ်
                        </span>

                    </div>

                </summary>


                <div class="pd-fold-body">

                    <div class="pd-fold-detail">

                        <span>
                            ဘာကြောင့် သင့်တော်နိုင်သလဲ
                        </span>

                        <strong>
                            သင့် Working Style နဲ့
                            ဘယ်လိုဖြည့်ဆည်းပေးနိုင်သလဲ?
                        </strong>

                        <p>
                            {{ $recommendation['reason'] }}
                        </p>

                    </div>


                    @if(
                        ! empty(
                            $recommendation['strengthens']
                        )
                    )

                        <div class="pd-fold-detail">

                            <span>
                                ပိုအားကောင်းလာနိုင်သည့် Areas
                            </span>

                            <strong>
                                Partner က ဖြည့်ဆည်းပေးနိုင်သည့် နေရာများ
                            </strong>

                            <div class="pd-fold-chips">

                                @foreach(
                                    $recommendation['strengthens']
                                    as $strength
                                )

                                    <small>
                                        {{ $strength['label'] }}
                                    </small>

                                @endforeach

                            </div>

                        </div>

                    @endif


                    @if(
                        ! empty(
                            $recommendation[
                                'discussion_points'
                            ]
                        )
                    )

                        <div class="pd-fold-detail">

                            <span>
                                Partner မဖြစ်ခင်
                            </span>

                            <strong>
                                ကြိုတင်ဆွေးနွေးထားသင့်သည့် အချက်များ
                            </strong>

                            <ul>

                                @foreach(
                                    $recommendation[
                                        'discussion_points'
                                    ]
                                    as $point
                                )

                                    <li>
                                        {{ $point }}
                                    </li>

                                @endforeach

                            </ul>

                        </div>

                    @endif

                </div>

            </details>

        @endforeach

    </div>


    @if(! empty($partnerMatch['note']))

        <div class="pd-fold-note">

            <strong>
                <span>i</span>
                အရေးကြီးတဲ့ မှတ်ချက်
            </strong>

            <p>
                {{ $partnerMatch['note'] }}
            </p>

        </div>

    @endif

</section>
