@extends('layouts.site')

@section('title', 'Articles — thePBR')
@section('description', 'မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ ဆောင်းပါးများ။ မည်သူမဆို အခမဲ့ ဖတ်ရှုနိုင်ပါသည်။')

@section('content')

<div class="phead">
  <div class="wrap">
    <div class="crumb">
      <a href="{{ route('home') }}">Home</a><i>/</i>Resources<i>/</i><span>Articles</span>
    </div>
    <div class="eyebrow">Articles</div>
    <h1>မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ ဆောင်းပါးများ</h1>
    <p>လုပ်ငန်းရှင်များ အမှန်တကယ် ကြုံတွေ့ရသည့် အခြေအနေများကို အခြေခံပြီး ရေးသားထားသော ဆောင်းပါးများ ဖြစ်ပါသည်။ မည်သူမဆို အခမဲ့ ဖတ်ရှုနိုင်ပါသည်။</p>
  </div>
</div>

<div class="tools">
  <div class="wrap tools-in">
    <div class="chips">
      <a href="{{ route('articles.index', request()->only('q')) }}"
         class="chip {{ $category ? '' : 'on' }}">
        All <span class="n">{{ $totalCount }}</span>
      </a>
      @foreach($categories as $name => $count)
        <a href="{{ route('articles.index', array_merge(request()->only('q'), ['category' => $name])) }}"
           class="chip {{ $category === $name ? 'on' : '' }}">
          {{ $name }} <span class="n">{{ $count }}</span>
        </a>
      @endforeach
    </div>

    <form class="search" method="GET" action="{{ route('articles.index') }}">
      @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2-4.2"/></svg>
      <input type="search" name="q" value="{{ $q }}" placeholder="ဆောင်းပါး ရှာပါ…" aria-label="ရှာဖွေရန်">
    </form>
  </div>
</div>

<section class="list-sec">
  <div class="wrap">

    @if($articles->total() > 0)
      <div class="count">ဆောင်းပါး {{ $articles->total() }} ပုဒ်</div>
    @endif

    @forelse($articles as $i => $article)

      {{-- the newest article gets the wide treatment, but only on an unfiltered first page --}}
      @if($showFeature && $i === 0)
        <a href="{{ route('articles.show', $article->slug) }}" class="feature">
          <div class="thumb">
            @if($article->cover_image)
              <img src="{{ asset('storage/' . $article->cover_image) }}" alt="{{ $article->title }}" loading="lazy" decoding="async">
            @endif
          </div>
          <div class="body">
            <div class="newest">Newest</div>
            <h2>{{ $article->title }}</h2>
            <p class="dek">{{ $article->excerpt }}</p>
            <div class="meta">
              {{ $article->category }}<i>·</i>{{ $article->burmese_date }}
            </div>
          </div>
        </a>
        <div class="grid">
      @else
        @if($i === ($showFeature ? 1 : 0))<div class="grid">@endif

        <a href="{{ route('articles.show', $article->slug) }}" class="card">
          <div class="thumb">
            @if($article->cover_image)
              <img src="{{ asset('storage/' . $article->cover_image) }}" alt="{{ $article->title }}" loading="lazy" decoding="async">
            @endif
          </div>
          <div class="body">
            <div class="tag">{{ $article->category }}</div>
            <h3>{{ $article->title }}</h3>
            <div class="meta">{{ $article->burmese_date }}</div>
          </div>
        </a>

        @if($loop->last)</div>@endif
      @endif

    @empty
      <div class="empty">
        <b>ရှာလို့ မတွေ့ပါ</b>
        <p>အခြား စကားလုံးဖြင့် ပြန်ရှာကြည့်ပါ၊ သို့မဟုတ် အမျိုးအစား စစ်ထုတ်မှုကို ဖြုတ်ကြည့်ပါ။</p>
      </div>
    @endforelse

    @if($articles->hasPages())
      <div class="pager">
        @if($articles->onFirstPage())
          <span class="off">←</span>
        @else
          <a href="{{ $articles->previousPageUrl() }}">←</a>
        @endif

        @foreach(range(1, $articles->lastPage()) as $page)
          @if($page === $articles->currentPage())
            <span class="on">{{ $page }}</span>
          @else
            <a href="{{ $articles->url($page) }}">{{ $page }}</a>
          @endif
        @endforeach

        @if($articles->hasMorePages())
          <a href="{{ $articles->nextPageUrl() }}">→</a>
        @else
          <span class="off">→</span>
        @endif
      </div>
    @endif

  </div>
</section>

@endsection
