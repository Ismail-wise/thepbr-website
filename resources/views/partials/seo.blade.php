{{--
    Shared social + search metadata for every public page.

    Why this exists: the site had a <title> and a description and nothing else.
    A link shared to Facebook or Viber — the two channels that matter most for
    Myanmar SME owners, and the two the homepage has share buttons for —
    rendered as a bare URL with no image, title or description.

    Pages override these by setting the section; everything falls back to
    sensible site-wide defaults so a new page is never left with nothing.

        @section('og_type', 'article')
        @section('og_image', 'storage/'.$article->cover_image)

    Note the 'storage/' prefix: cover_image holds a path relative to the
    storage disk, and every <img> on the site renders it as
    asset('storage/'.$article->cover_image). Passing the bare value here
    produced a social card pointing at an image that does not exist.
--}}
@php
    $seoTitle = trim($__env->yieldContent('title', 'thePBR — Partnership Business Rules'));
    $seoDescription = trim($__env->yieldContent(
        'description',
        'မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများကို လုပ်ငန်းရှင်များ နားလည်လွယ်အောင် သင်ကြားပေးသည့် သင်တန်း။'
    ));
    $seoType = trim($__env->yieldContent('og_type', 'website'));

    // url()->current() drops the query string on purpose: ?utm_source=... and
    // ?page=2 variants would otherwise each be treated as a separate canonical
    // page and split the ranking signal.
    $seoCanonical = trim($__env->yieldContent('canonical', url()->current()));

    $seoImageRaw = trim($__env->yieldContent('og_image', ''));

    if ($seoImageRaw === '') {
        $seoImage = asset('images/pbr-logo.png');
    } elseif (str_starts_with($seoImageRaw, 'http')) {
        $seoImage = $seoImageRaw;
    } else {
        $seoImage = asset(ltrim($seoImageRaw, '/'));
    }
@endphp

<link rel="canonical" href="{{ $seoCanonical }}">

{{-- Open Graph: Facebook, Viber, Messenger, LinkedIn --}}
<meta property="og:site_name" content="thePBR">
<meta property="og:locale" content="my_MM">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:alt" content="thePBR — Partnership Business Rules">

{{-- Twitter/X reads og:* as a fallback but needs the card type declared. --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

@hasSection('published_time')
    <meta property="article:published_time" content="@yield('published_time')">
@endif

{{-- Organization: tells Google what thePBR is, once, on every page. --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'thePBR',
    'alternateName' => 'Partnership Business Rules',
    'url' => url('/'),
    'logo' => asset('images/pbr-logo.png'),
    'description' => $seoDescription,
    'areaServed' => [
        ['@type' => 'Country', 'name' => 'Myanmar'],
        ['@type' => 'Country', 'name' => 'Thailand'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

@stack('schema')
