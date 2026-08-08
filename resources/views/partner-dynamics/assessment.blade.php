@extends('layouts.student-portal')

@section('title', 'Partner Dynamics Assessment')

@section('content')

@php
    $progress = $step * 20;

    $scaleLabels = [
        1 => ['mm' => 'လုံးဝ မသဘောတူ', 'en' => 'Strongly Disagree'],
        2 => ['mm' => 'မသဘောတူ', 'en' => 'Disagree'],
        3 => ['mm' => 'အလယ်အလတ်', 'en' => 'Neutral'],
        4 => ['mm' => 'သဘောတူ', 'en' => 'Agree'],
        5 => ['mm' => 'အပြည့်အဝ သဘောတူ', 'en' => 'Strongly Agree'],
    ];
@endphp

<section class="pd-assessment-section">
    <div class="pd-assessment-wrap">

        <header class="pd-assessment-header">

            <div>
                <a href="{{ route('partner-dynamics.index') }}"
                   class="pd-back-home">
                    ← Partner Dynamics
                </a>

                <span class="portal-kicker">
                    Assessment · Step {{ $step }} of 5
                </span>

                @if($step <= 4)
                    <h1>သင့်အလုပ်လုပ်ပုံကို ရွေးချယ်ပါ</h1>

                    <p>
                        လက်တွေ့လုပ်ငန်းခွင်မှာ သင်ဘယ်လိုပြုမူတတ်လဲဆိုတာကို
                        အနီးစပ်ဆုံးရွေးပါ။
                    </p>
                @else
                    <h1>Business Situations</h1>

                    <p>
                        အောက်ပါအခြေအနေတွေ ဖြစ်လာရင်
                        သင်အရင်ဆုံးလုပ်ဖြစ်မယ့်အရာကို ရွေးပါ။
                    </p>
                @endif
            </div>

            <div class="pd-step-number">
                {{ $step }}
                <small>/ 5</small>
            </div>

        </header>


        <div class="pd-progress-shell">
            <div class="pd-progress-info">
                <span>Progress</span>
                <strong>{{ $progress }}%</strong>
            </div>

            <div class="pd-progress-track">
                <span style="width: {{ $progress }}%"></span>
            </div>
        </div>


        <form method="POST"
              action="{{ route(
                    'partner-dynamics.assessment.save',
                    [$assessment, $step]
              ) }}">

            @csrf
            @method('PUT')


            <div class="pd-question-list">

                @foreach($questions as $number => $question)

                    @php
                        $questionText = is_array($question)
                            ? (
                                $question['text']
                                ?? $question['question']
                                ?? $question['label']
                                ?? ''
                            )
                            : $question;

                        $selectedAnswer = old(
                            "answers.$number",
                            $answers[$number] ?? null
                        );
                    @endphp


                    <article class="pd-question-card">

                        <div class="pd-question-number">
                            Q{{ $number }}
                        </div>

                        <h2>{{ $questionText }}</h2>


                        @if($step <= 4)

                            <div class="pd-scale-options">

                                @foreach($scaleLabels as $value => $label)

                                    <label class="pd-scale-option">

                                        <input
                                            type="radio"
                                            name="answers[{{ $number }}]"
                                            value="{{ $value }}"
                                            {{ (string)$selectedAnswer === (string)$value ? 'checked' : '' }}
                                            required
                                        >

                                        <span class="pd-radio-ui">
                                            <b>{{ $value }}</b>

                                            <span>
                                                {{ $label['mm'] }}
                                                <small>{{ $label['en'] }}</small>
                                            </span>
                                        </span>

                                    </label>

                                @endforeach

                            </div>

                        @else

                            @php
                                $optionSet = is_array($question)
                                    ? (
                                        $question['options']
                                        ?? $question['choices']
                                        ?? []
                                    )
                                    : [];
                            @endphp

                            <div class="pd-scenario-options">

                                @foreach($optionSet as $letter => $option)

                                    @php
                                        $optionText = is_array($option)
                                            ? (
                                                $option['text']
                                                ?? $option['label']
                                                ?? $option['answer']
                                                ?? ''
                                            )
                                            : $option;
                                    @endphp

                                    <label class="pd-scenario-option">

                                        <input
                                            type="radio"
                                            name="answers[{{ $number }}]"
                                            value="{{ $letter }}"
                                            {{ (string)$selectedAnswer === (string)$letter ? 'checked' : '' }}
                                            required
                                        >

                                        <span class="pd-scenario-ui">
                                            <b>{{ $letter }}</b>
                                            <span>{{ $optionText }}</span>
                                        </span>

                                    </label>

                                @endforeach

                            </div>

                        @endif


                        @error("answers.$number")
                            <p class="pd-question-error">
                                ဒီမေးခွန်းကို ဖြေပေးပါ။
                            </p>
                        @enderror

                    </article>

                @endforeach

            </div>


            <div class="pd-form-footer">

                @if($step > 1)

                    <a class="pd-secondary-button"
                       href="{{ route(
                            'partner-dynamics.assessment.step',
                            [$assessment, $step - 1]
                       ) }}">
                        ← Previous
                    </a>

                @else

                    <a class="pd-secondary-button"
                       href="{{ route('partner-dynamics.index') }}">
                        ← Back
                    </a>

                @endif


                <button class="pd-primary-button pd-next-button"
                        type="submit">

                    @if($step < 5)
                        Save & Continue →
                    @else
                        Result ထုတ်မယ် →
                    @endif

                </button>

            </div>

        </form>


        <p class="pd-save-message">
            ဖြေထားတဲ့အဖြေတွေကို Step တစ်ခုပြီးတိုင်း
            automatically save လုပ်ထားပါတယ်။
        </p>

    </div>
</section>

@endsection
