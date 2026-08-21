@extends('layouts.site')

@section('title', $video->title . ' — thePBR')
@section('description', $video->excerpt)
@section('og_type', 'video.other')
@section('og_image', $video->thumbnail_url)
@section('published_time', optional($video->published_at)->toIso8601String())

@push('schema')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'VideoObject',
    'name' => $video->title,
    'description' => $video->excerpt,
    'thumbnailUrl' => $video->thumbnail_url,
    'uploadDate' => optional($video->published_at)->toIso8601String(),
    'embedUrl' => $video->embed_url,
    'inLanguage' => 'my',
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'thePBR',
        'logo' => ['@type' => 'ImageObject', 'url' => asset('images/pbr-logo.png')],
    ],
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')

<div class="phead">
  <div class="wrap">
    <div class="crumb">
      <a href="{{ route('home') }}">Home</a><i>/</i><a href="{{ route('videos.index') }}">Videos</a><i>/</i><span>{{ $video->category }}</span>
    </div>
    <h1>{{ $video->title }}</h1>
    <div class="meta">
      @if($video->category){{ $video->category }}<i>·</i>@endif{{ $video->burmese_date }}
    </div>
  </div>
</div>

<section>
  <div class="wrap read">

    {{--
      Click-to-play facade, not an iframe on load.

      A YouTube iframe pulls roughly a megabyte of scripts before the viewer
      has decided to watch anything. On Myanmar mobile data that is a real
      cost, and most visitors to a listing page never press play. The poster
      image is a few tens of kilobytes; the iframe is only inserted once the
      viewer asks for it.

      It also keeps the privacy policy honest — no YouTube cookies are set
      until the viewer actively chooses to play.
    --}}
    <div class="vplayer" data-embed="{{ $video->embed_url }}">
      <button type="button" class="vplay" aria-label="ဗီဒီယို ဖွင့်ရန် — {{ $video->title }}">
        <img src="{{ $video->thumbnail_url }}" alt="" decoding="async">
        <span class="playmark playmark-lg" aria-hidden="true"></span>
      </button>
    </div>

    @if($video->excerpt)
      <p class="lede">{{ $video->excerpt }}</p>
    @endif

    <p>
      <a href="{{ $video->watch_url }}" target="_blank" rel="noopener noreferrer">
        YouTube တွင် ကြည့်ရန် ↗
      </a>
    </p>

  </div>
</section>

@if($related->isNotEmpty())
<section class="list-sec">
  <div class="wrap">
    <h2>ဆက်စပ် ဗီဒီယိုများ</h2>
    <div class="grid">
      @foreach($related as $item)
        <a href="{{ route('videos.show', $item->slug) }}" class="card">
          <div class="thumb vthumb">
            <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
            <span class="playmark" aria-hidden="true"></span>
          </div>
          <div class="body">
            @if($item->category)<div class="tag">{{ $item->category }}</div>@endif
            <h3>{{ $item->title }}</h3>
            <div class="meta">{{ $item->burmese_date }}</div>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@push('scripts')
<script>
(() => {
  const holder = document.querySelector('.vplayer');
  if (!holder) return;

  holder.querySelector('.vplay')?.addEventListener('click', () => {
    const frame = document.createElement('iframe');
    // autoplay=1 because the click WAS the play intent; loading the iframe
    // and making the viewer press play a second time is the usual mistake
    // with this pattern.
    frame.src = holder.dataset.embed + '&autoplay=1';
    frame.title = @json($video->title);
    frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
    frame.allowFullscreen = true;
    frame.loading = 'lazy';
    holder.replaceChildren(frame);
    frame.focus();
  });
})();
</script>
@endpush

@endsection
