@extends('layouts.site')

@section('title', 'Videos — thePBR')
@section('description', 'မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ ဗီဒီယိုများ။ မည်သူမဆို အခမဲ့ ကြည့်ရှုနိုင်ပါသည်။')

@section('content')

<div class="phead">
  <div class="wrap">
    <div class="crumb">
      <a href="{{ route('home') }}">Home</a><i>/</i>Resources<i>/</i><span>Videos</span>
    </div>
    <div class="eyebrow">Videos</div>
    <h1>မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ ဗီဒီယိုများ</h1>
    <p>ဖတ်ရှုရန် အချိန်မရသူများအတွက် တိုတိုရှင်းရှင်း ရှင်းပြထားသော ဗီဒီယိုများ ဖြစ်ပါသည်။ မည်သူမဆို အခမဲ့ ကြည့်ရှုနိုင်ပါသည်။</p>
  </div>
</div>

<div class="tools">
  <div class="wrap tools-in">
    <div class="chips">
      <a href="{{ route('videos.index', request()->only('q')) }}"
         class="chip {{ $category ? '' : 'on' }}">
        All <span class="n">{{ $totalCount }}</span>
      </a>
      @foreach($categories as $name => $count)
        <a href="{{ route('videos.index', array_merge(request()->only('q'), ['category' => $name])) }}"
           class="chip {{ $category === $name ? 'on' : '' }}">
          {{ $name }} <span class="n">{{ $count }}</span>
        </a>
      @endforeach
    </div>

    <form class="search" method="GET" action="{{ route('videos.index') }}">
      @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2-4.2"/></svg>
      <input type="search" name="q" value="{{ $q }}" placeholder="ဗီဒီယို ရှာပါ…" aria-label="ရှာဖွေရန်">
    </form>
  </div>
</div>

<section class="list-sec">
  <div class="wrap">

    @if($videos->total() > 0)
      <div class="count">ဗီဒီယို {{ $videos->total() }} ခု</div>
    @endif

    @forelse($videos as $i => $video)
      @if($i === 0)<div class="grid">@endif

        <a href="{{ route('videos.show', $video->slug) }}" class="card">
          <div class="thumb vthumb">
            {{-- Thumbnails are served straight from YouTube's CDN rather than
                 copied onto our disk: they stay in sync if the channel art
                 changes, and cost us no storage or bandwidth. --}}
            <img src="{{ $video->thumbnail_url }}"
                 alt="{{ $video->title }}"
                 loading="lazy" decoding="async">
            <span class="playmark" aria-hidden="true"></span>
            @if($video->duration_minutes)
              <span class="vdur">{{ $video->duration_minutes }} မိနစ်</span>
            @endif
          </div>
          <div class="body">
            @if($video->category)<div class="tag">{{ $video->category }}</div>@endif
            <h3>{{ $video->title }}</h3>
            <div class="meta">{{ $video->burmese_date }}</div>
          </div>
        </a>

      @if($loop->last)</div>@endif
    @empty
      <div class="empty">
        <b>ရှာလို့ မတွေ့ပါ</b>
        <p>အခြား စကားလုံးဖြင့် ပြန်ရှာကြည့်ပါ။</p>
      </div>
    @endforelse

    {{ $videos->links() }}

  </div>
</section>

@endsection
