@extends('layouts.student-portal')

@section('title', 'Partnership Alignment')

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

<section class="pd-result-section pd-alignment-page">
    <div class="portal-wrap">

        <a href="{{ route('workspaces.show', $workspace) }}"
           class="pd-back-home">
            ← {{ $workspace->name }}
        </a>

        <div class="pd-alignment-heading">
            <div>
                <span class="portal-kicker">
                    PBR Partner Dynamics
                </span>

                <h1>Partnership Alignment</h1>

                <p>
                    Partner တစ်ယောက်ချင်းစီရဲ့ operating style ကို
                    နှိုင်းယှဉ်ပြီး အတူတကွအားသာနိုင်တဲ့နေရာတွေ၊
                    သဘောထားကွာခြားနိုင်တဲ့နေရာတွေနဲ့
                    ကြိုတင်သဘောတူထားသင့်တဲ့ decision rules တွေကို
                    ရှင်းရှင်းလင်းလင်း ကြည့်နိုင်ပါတယ်။
                </p>
            </div>

            <div class="pd-alignment-status">
                <strong>{{ $completedParticipantCount }}</strong>
                <span>Completed Profiles</span>
            </div>
        </div>


        <section class="pd-alignment-section">
            <div class="pd-panel-heading">
                <span class="portal-kicker">Profiles</span>
                <h2>Partner Profiles</h2>
            </div>

            <div class="pd-profile-grid">

                @foreach($participantRows as $row)

                    <article class="pd-profile-card">

                        <div class="pd-profile-top">
                            <div>
                                <span>
                                    {{ $row['is_owner'] ? 'Workspace Owner' : 'Partner' }}
                                </span>

                                <h3>{{ $row['user']->name }}</h3>
                            </div>

                            @if($row['assessment'])
                                <span class="pd-profile-ready">
                                    Assessment Complete
                                </span>
                            @else
                                <span class="pd-profile-waiting">
                                    Waiting
                                </span>
                            @endif
                        </div>

                        @if($row['assessment'])

                            <div class="pd-profile-result">
                                <div>
                                    <span>Primary Style</span>
                                    <strong>
                                        {{ $profileLabels[$row['assessment']->primary_profile]
                                            ?? ucfirst($row['assessment']->primary_profile) }}
                                    </strong>
                                </div>

                                <div>
                                    <span>Secondary Style</span>
                                    <strong>
                                        {{ $profileLabels[$row['assessment']->secondary_profile]
                                            ?? ucfirst($row['assessment']->secondary_profile) }}
                                    </strong>
                                </div>
                            </div>

                            <a class="pd-inline-link"
                               href="{{ route(
                                    'workspaces.partner-dynamics.profile',
                                    [$workspace, $row['assessment']]
                               ) }}">
                                View Workspace Profile →
                            </a>

                        @else

                            <p class="pd-profile-message">
                                ဒီ Partner ရဲ့ Assessment မပြီးသေးပါဘူး။
                                Alignment report ထုတ်ဖို့ completed profiles
                                အနည်းဆုံး ၂ ခုလိုပါတယ်။
                            </p>

                        @endif

                    </article>

                @endforeach

            </div>
        </section>


        @if(!$report)

            <section class="pd-waiting-report">
                <span class="portal-kicker">Alignment Pending</span>

                <h2>Partner Assessment တစ်ခု ထပ်လိုပါတယ်</h2>

                <p>
                    Partnership Alignment report ထုတ်ဖို့ completed
                    Partner Dynamics Assessments အနည်းဆုံး ၂ ခုလိုပါတယ်။
                </p>
            </section>

        @else

            <div class="pd-alignment-summary-grid">

                <div>
                    <strong>
                        {{ count($report->shared_strengths ?? []) }}
                    </strong>
                    <span>Shared Strengths</span>
                </div>

                <div>
                    <strong>
                        {{ count($report->complementary_areas ?? []) }}
                    </strong>
                    <span>Complementary Areas</span>
                </div>

                <div>
                    <strong>
                        {{ count($report->important_differences ?? []) }}
                    </strong>
                    <span>Important Differences</span>
                </div>

                <div>
                    <strong>
                        {{ count($report->shared_blind_spots ?? []) }}
                    </strong>
                    <span>Shared Blind Spots</span>
                </div>

            </div>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        What Already Works
                    </span>
                    <h2>Shared Strengths</h2>
                </div>

                <div class="pd-insight-grid">

                    @forelse($report->shared_strengths ?? [] as $item)

                        <article class="pd-insight-card positive">
                            <span class="pd-insight-label">
                                {{ $item['label'] }}
                            </span>

                            <strong>
                                Average {{ number_format($item['average_score'], 0) }}
                            </strong>

                            <p>{{ $item['message'] }}</p>
                        </article>

                    @empty

                        <article class="pd-empty-insight">
                            Shared Strength threshold ကို Partner
                            အားလုံးတစ်ပြိုင်တည်း မရောက်သေးပါဘူး။
                            ဒါက problem ဖြစ်တယ်လို့ မဆိုလိုပါဘူး။
                        </article>

                    @endforelse

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        Different Strengths
                    </span>
                    <h2>Complementary Areas</h2>
                </div>

                <div class="pd-insight-grid">

                    @forelse($report->complementary_areas ?? [] as $item)

                        <article class="pd-insight-card complement">
                            <span class="pd-insight-label">
                                {{ $item['label'] }}
                            </span>

                            <strong>
                                {{ $item['stronger_participant']['name'] }}
                            </strong>

                            <p>{{ $item['message'] }}</p>
                        </article>

                    @empty

                        <article class="pd-empty-insight">
                            Current scores မှာ Complementary Area
                            threshold နဲ့ကိုက်ညီတဲ့ dimension မရှိသေးပါဘူး။
                        </article>

                    @endforelse

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        Discuss Before It Becomes Conflict
                    </span>
                    <h2>Important Differences</h2>
                </div>

                <div class="pd-difference-list">

                    @forelse($report->important_differences ?? [] as $item)

                        <article class="pd-difference-card">

                            <div class="pd-difference-head">
                                <div>
                                    <span>Dimension</span>
                                    <h3>{{ $item['label'] }}</h3>
                                </div>

                                <strong>
                                    {{ number_format($item['gap'], 0) }}
                                    <small>point gap</small>
                                </strong>
                            </div>

                            <div class="pd-partner-score-row">

                                <div>
                                    <span>
                                        {{ $item['highest_participant']['name'] }}
                                    </span>
                                    <strong>
                                        {{ number_format(
                                            $item['highest_participant']['score'],
                                            0
                                        ) }}
                                    </strong>
                                </div>

                                <div>
                                    <span>
                                        {{ $item['lowest_participant']['name'] }}
                                    </span>
                                    <strong>
                                        {{ number_format(
                                            $item['lowest_participant']['score'],
                                            0
                                        ) }}
                                    </strong>
                                </div>

                            </div>

                            <p>{{ $item['message'] }}</p>

                        </article>

                    @empty

                        <article class="pd-empty-insight">
                            Major operating difference threshold ကို
                            မတွေ့ရသေးပါဘူး။
                        </article>

                    @endforelse

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        Watch Together
                    </span>
                    <h2>Shared Blind Spots</h2>
                </div>

                <div class="pd-insight-grid">

                    @forelse($report->shared_blind_spots ?? [] as $item)

                        <article class="pd-insight-card caution">
                            <span class="pd-insight-label">
                                {{ $item['label'] }}
                            </span>

                            <p>{{ $item['message'] }}</p>
                        </article>

                    @empty

                        <article class="pd-empty-insight">
                            Current assessment မှာ Partner အားလုံး
                            တစ်ပြိုင်တည်း low-score ဖြစ်နေတဲ့
                            Shared Blind Spot မတွေ့ရသေးပါဘူး။
                        </article>

                    @endforelse

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">Role Clarity</span>
                    <h2>Role Suggestions</h2>
                </div>

                <div class="pd-role-grid">

                    @foreach($report->role_suggestions ?? [] as $role)

                        <article class="pd-role-card">

                            <span>{{ $role['name'] }}</span>

                            <h3>
                                {{ $profileLabels[$role['primary_profile']]
                                    ?? ucfirst($role['primary_profile']) }}
                            </h3>

                            <ul>
                                @foreach($role['suggestions'] as $suggestion)
                                    <li>{{ $suggestion }}</li>
                                @endforeach
                            </ul>

                            <small>{{ $role['note'] }}</small>

                        </article>

                    @endforeach

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        Partnership Rules
                    </span>
                    <h2>Decision Recommendations</h2>
                </div>

                <div class="pd-recommendation-list">

                    @foreach($report->decision_recommendations ?? [] as $index => $item)

                        <article class="pd-recommendation-card">
                            <span>
                                {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>

                            <div>
                                <h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['message'] }}</p>
                            </div>
                        </article>

                    @endforeach

                </div>

            </section>


            <section class="pd-alignment-section">

                <div class="pd-panel-heading">
                    <span class="portal-kicker">
                        Next Discussion
                    </span>
                    <h2>Discussion Priorities</h2>
                </div>

                <div class="pd-priority-list">

                    @foreach($report->discussion_priorities ?? [] as $item)

                        <article class="pd-priority-row">

                            <span class="pd-priority-level {{ strtolower($item['priority']) }}">
                                {{ $item['priority'] }}
                            </span>

                            <div>
                                <strong>{{ $item['topic'] }}</strong>
                                <p>{{ $item['reason'] }}</p>
                            </div>

                        </article>

                    @endforeach

                </div>

            </section>


            <div class="pd-disclaimer">
                <strong>PBR Note</strong>

                <p>
                    ဒီ Alignment Report က Compatibility Percentage
                    မဟုတ်ပါဘူး။ Partnership လုပ်သင့်/မလုပ်သင့်ကို
                    ဆုံးဖြတ်ပေးတာလည်း မဟုတ်ပါဘူး။
                    Partner တွေကြား role clarity, decision rules နဲ့
                    important discussions ပိုကောင်းစေဖို့
                    decision-support tool အဖြစ် အသုံးပြုထားတာပါ။
                </p>
            </div>

        @endif

    </div>
</section>

@endsection
