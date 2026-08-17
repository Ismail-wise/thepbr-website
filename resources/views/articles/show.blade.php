@extends('layouts.site')

@section('title', $article->title . ' — thePBR')
@section('description', $article->excerpt)

@section('content')

<div class="prog" id="prog"></div>

<article>
  <div class="ahead">
    <div class="read">
      <div class="crumb">
        <a href="{{ route('home') }}">Home</a><i>/</i><a href="{{ route('articles.index') }}">Articles</a><i>/</i><span>{{ $article->category }}</span>
      </div>
      <div class="cat">{{ $article->category }}</div>
      <h1>{{ $article->title }}</h1>

      <div class="byline">
        <div class="who">
          <div class="av"></div>
          <b>စည်သူအောင်</b>
        </div>
        <span class="dot">·</span>
        <span>{{ $article->burmese_date }}</span>

        <div class="share">
          <button data-s="fb" aria-label="Facebook တွင် မျှဝေရန်" title="Facebook">
            <svg viewBox="0 0 24 24"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>
          </button>
          <button data-s="vb" aria-label="Viber တွင် မျှဝေရန်" title="Viber">
            <svg viewBox="0 0 24 24"><path d="M12 3c5 0 8 3 8 7.3 0 4.4-3 7.4-8 7.4-.8 0-1.6-.1-2.3-.2L6 20v-3c-1.9-1.4-3-3.5-3-6.7C3 6 6 3 12 3z"/></svg>
          </button>
          <button data-s="cp" aria-label="Link ကူးယူရန်" title="Copy link">
            <svg viewBox="0 0 24 24"><path d="M10.6 13.4a3 3 0 0 0 4.2 0l2.8-2.8a3 3 0 1 0-4.2-4.2l-1 1 1.4 1.4 1-1a1 1 0 0 1 1.4 1.4l-2.8 2.8a1 1 0 0 1-1.4 0zm2.8-2.8a3 3 0 0 0-4.2 0l-2.8 2.8a3 3 0 1 0 4.2 4.2l1-1-1.4-1.4-1 1a1 1 0 0 1-1.4-1.4l2.8-2.8a1 1 0 0 1 1.4 0z"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  @if($article->cover_image)
    <div class="cover">
      <img src="{{ asset('storage/' . $article->cover_image) }}" alt="">
    </div>
  @endif

  <div class="post">
    <div class="read">
      {{-- one blank line in the admin textarea becomes one paragraph here --}}
      @foreach($article->paragraphs as $i => $paragraph)
        <p class="{{ $i === 0 ? 'lede' : '' }}">{{ $paragraph }}</p>
      @endforeach
    </div>
  </div>

  <div class="endrule">
    <div class="line"></div>
    <div class="dot"><span>◆</span></div>
  </div>

  <div class="nudge">
    <div class="box">
      <h3>သင်တန်းသားများအတွက် Resources များ</h3>
      <p>သင်တန်းတွင် သုံးခဲ့သည့် စာချုပ်ပုံစံများ၊ စစ်ဆေးရန်စာရင်းများနှင့် တွက်ချက်ပုံစံများကို Student Portal တွင် ပြန်လည် ရယူနိုင်ပါသည်။ သင်တန်းတက်ရောက်ပြီးသူများသည် အကောင့်ဖွင့်စဉ် ဆရာပေးထားသော Code ကို ထည့်သွင်းပါ။</p>
      <div class="acts">
        <a href="{{ route('student.register') }}" class="btn">Student Registration</a>
        <a href="{{ route('about') }}" class="btn ghost">About the Class</a>
      </div>
    </div>
  </div>
</article>

@if($related->isNotEmpty())
<div class="rel">
  <div class="wrap">
    <div class="head">
      <div>
        <div class="eyebrow">Keep reading</div>
        <h2>ဆက်စပ် ဆောင်းပါးများ</h2>
      </div>
      <a href="{{ route('articles.index') }}" class="btn ghost">View all articles</a>
    </div>
    <div class="grid">
      @foreach($related as $item)
        <a href="{{ route('articles.show', $item->slug) }}" class="card">
          <div class="thumb">
            @if($item->cover_image)
              <img src="{{ asset('storage/' . $item->cover_image) }}" alt="">
            @endif
          </div>
          <div class="body">
            <div class="cat">{{ $item->category }}</div>
            <h3>{{ $item->title }}</h3>
            <div class="meta">{{ $item->burmese_date }}</div>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</div>
@endif

<div class="toast" id="toast"></div>

@push('scripts')
<script>
const art = document.querySelector('article'), prog = document.getElementById('prog');
addEventListener('scroll', () => {
  const top = art.offsetTop, h = art.offsetHeight - innerHeight;
  prog.style.width = Math.min(100, Math.max(0, ((scrollY - top) / h) * 100)) + '%';
}, { passive: true });

const toastEl = document.getElementById('toast');
let tt;
function toast(m){
  toastEl.textContent = m; toastEl.classList.add('on');
  clearTimeout(tt); tt = setTimeout(() => toastEl.classList.remove('on'), 1900);
}
document.querySelectorAll('.share button').forEach(b => b.addEventListener('click', async () => {
  const u = location.href, t = document.title;
  if (b.dataset.s === 'fb') open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(u), '_blank', 'width=620,height=520');
  if (b.dataset.s === 'vb') location.href = 'viber://forward?text=' + encodeURIComponent(t + ' ' + u);
  if (b.dataset.s === 'cp') {
    try { await navigator.clipboard.writeText(u); toast('Link ကူးယူပြီးပါပြီ'); }
    catch { toast('ကူးယူလို့ မရပါ'); }
  }
}));
</script>
@endpush

@endsection
