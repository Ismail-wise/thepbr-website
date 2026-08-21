@extends('layouts.site')

@section('title', 'Classes — thePBR')
@section('description', 'လာမည့် သင်တန်းအချိန်စာရင်းနှင့် ယခုအထိ ပြုလုပ်ခဲ့သည့် သင်တန်းများ မှတ်တမ်း။')

@push('schema')
{{-- Course + scheduled instances. Only real, visible upcoming sessions are
     emitted: advertising a class that is not actually scheduled would be a
     misrepresentation, and Google penalises unmatched structured data. --}}
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Course',
    'name' => 'thePBR — Partnership Business Rules',
    'description' => 'မိတ်ဖက်လုပ်ငန်း စတင်ခြင်း၊ ပြန်လည်ဖွဲ့စည်းခြင်းအတွက် SME လုပ်ငန်းရှင်များအတွက် သင်တန်း။',
    'inLanguage' => 'my',
    'provider' => [
        '@type' => 'Organization',
        'name' => 'thePBR',
        'url' => url('/'),
    ],
    'hasCourseInstance' => $upcoming->map(fn ($session) => array_filter([
        '@type' => 'CourseInstance',
        'courseMode' => 'onsite',
        'startDate' => optional($session->starts_on)->toDateString(),
        'location' => $session->location ? [
            '@type' => 'Place',
            'name' => $session->location,
        ] : null,
    ]))->values()->all() ?: null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')

@php
  $mm = fn ($n) => \App\Models\ClassSession::mmNumber($n);
  $icons = [
    'cal'   => '<svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'pin'   => '<svg viewBox="0 0 24 24"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    'tag'   => '<svg viewBox="0 0 24 24"><path d="M20 12l-8 8-8-8 8-8h8z"/><circle cx="15.5" cy="8.5" r="1.2"/></svg>',
  ];
@endphp

<div class="phead phead-stats">
  <div class="wrap">
    <div class="crumb">
      <a href="{{ route('home') }}">Home</a><i>/</i>Resources<i>/</i><span>Classes</span>
    </div>
    <div class="eyebrow">Classes</div>
    <h1>မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ သင်တန်းများ</h1>
    <p>လာမည့် သင်တန်းအချိန်စာရင်းနှင့် ယခုအထိ ပြုလုပ်ခဲ့သည့် သင်တန်းများ မှတ်တမ်း ဖြစ်ပါသည်။</p>

    @if($stats['sessions'] > 0)
      <div class="stats">
        <div class="stat"><b>{{ $mm($stats['sessions']) }} ကြိမ်</b><span>ပြုလုပ်ခဲ့သည့် သင်တန်း</span></div>
        <div class="stat"><b>{{ $mm($stats['students']) }} ဦး</b><span>သင်တန်းသား စုစုပေါင်း</span></div>
        <div class="stat"><b>{{ $mm($stats['locations']) }} နေရာ</b><span>ပြုလုပ်ခဲ့သည့် နေရာ</span></div>
      </div>
    @endif
  </div>
</div>

{{-- ===================== UPCOMING ===================== --}}
<section>
  <div class="wrap">
    <div class="head-row">
      <div>
        <div class="eyebrow">Upcoming</div>
        <h2>လာမည့် သင်တန်းများ</h2>
      </div>
      <p>သင်တန်းအသစ်များ ဖွင့်လှစ်သည့်အခါ အချိန်စာရင်းနှင့် စာရင်းသွင်းရန် အချက်အလက်များကို ဤစာမျက်နှာတွင် ဖော်ပြပေးပါမည်။</p>
    </div>

    @forelse($upcoming as $class)
      @if($loop->first)<div class="ups">@endif

      <div class="up">
        <div class="when">
          <div class="mo">{{ $class->mm_month }}</div>
          <div class="d">{{ $class->mm_day }}</div>
          <div class="yr">{{ $class->starts_on->year }}</div>
        </div>

        <div class="info">
          <div class="mode">{{ $class->mode_label }}</div>
          <h3>{{ $class->title }}</h3>
          <div class="facts">
            <div>{!! $icons['cal'] !!}{{ $class->mm_duration }}</div>
            @if($class->time_note)<div>{!! $icons['clock'] !!}{{ $class->time_note }}</div>@endif
            <div>{!! $icons['pin'] !!}{{ $class->location }}</div>
            @if($class->fee)<div>{!! $icons['tag'] !!}{{ $class->fee }}</div>@endif
          </div>
        </div>

        <div class="act">
          @if($class->capacity > 0)
            @if($class->is_full)
              <div class="seats full">စာရင်း <b>ပြည့်သွားပါပြီ</b></div>
            @else
              <div class="seats">
                ကျန်ရှိ <b>{{ $mm($class->seats_left) }} ဦး</b> / {{ $mm($class->capacity) }} ဦး
                <div class="meter">
                  <i class="{{ $class->seats_left <= 8 ? 'hot' : '' }}" style="width:{{ $class->filled_percent }}%"></i>
                </div>
              </div>
            @endif
          @endif

          @unless($class->is_full)
            <a href="{{ route('register') }}" class="btn sm">
              အခမဲ့ အကောင့်ဖွင့်ရန်
            </a>
          @endunless
        </div>
      </div>

      @if($loop->last)</div>@endif
    @empty
      <div class="empty">
        <b>လာမည့် သင်တန်း အချိန်စာရင်း မကြေညာရသေးပါ</b>
        <p>သင်တန်းအသစ် ဖွင့်သည့်အခါ အချိန်စာရင်းကို ဒီနေရာတွင် ဖော်ပြပေးပါမည်။ လက်ရှိတွင် ဆောင်းပါးများကို အခမဲ့ ဖတ်ရှုနိုင်ပါသည်။</p>
        <div class="acts">
          <a href="{{ route('articles.index') }}" class="btn ghost">ဆောင်းပါးများ ဖတ်ရန်</a>
        </div>
      </div>
    @endforelse
  </div>
</section>

{{-- ===================== PAST ARCHIVE ===================== --}}
@if($pastByYear->isNotEmpty())
<section class="sec-sage">
  <div class="wrap">
    <div class="head-row">
      <div>
        <div class="eyebrow">Archive</div>
        <h2>ပြီးခဲ့သည့် သင်တန်းများ မှတ်တမ်း</h2>
      </div>
    </div>

    @foreach($pastByYear as $year => $rows)
      <div class="yr-block">
        <div class="yr-label">{{ $year }}</div>
        @foreach($rows as $class)
          <div class="row">
            <div class="dt">{{ $class->mm_range }}</div>
            <div class="ttl">{{ $class->title }}</div>
            <div class="loc">{!! $icons['pin'] !!}{{ $class->location }}</div>
            <div class="cnt">
              @if($class->enrolled > 0)<b>{{ $mm($class->enrolled) }}</b> ဦး@endif
            </div>
          </div>
        @endforeach
      </div>
    @endforeach
  </div>
</section>
@endif

{{-- ===================== CTA ===================== --}}
<div class="cta on-dark">
  <div class="wrap">
    <div class="eyebrow" style="justify-content:center">For past students</div>
    <h2>သင်တန်း တက်ရောက်ပြီးသူများအတွက်</h2>
    <p>သင်တန်းတွင် သုံးခဲ့သည့် စာချုပ်ပုံစံများနှင့် အထောက်အကူ ပစ္စည်းများကို Student Portal တွင် ရယူနိုင်ပါသည်။ အကောင့်ဖွင့်စဉ် ဆရာပေးထားသော Code ကို ထည့်သွင်းပါ။</p>
    <div class="acts">
      <a href="{{ route('student.register') }}" class="btn light">Student Registration</a>
      <a href="{{ route('login') }}" class="btn outline-light">Log In</a>
    </div>
  </div>
</div>

@endsection
