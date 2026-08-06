<!DOCTYPE html>
<html lang="my">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'thePBR — Partnership Business Rules')</title>
<meta name="description" content="@yield('description', 'မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများကို လုပ်ငန်းရှင်များ နားလည်လွယ်အောင် သင်ကြားပေးသည့် သင်တန်း။')">
<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&family=Noto+Serif+Myanmar:wght@500;600&family=Noto+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
@stack('head')
</head>
<body>

@stack('before')

@php
  $currentUser = auth()->user();

  if ($currentUser?->isStudent()) {
      $portalUrl = route('student.dashboard');
      $portalLabel = 'My Workspace';
  } elseif ($currentUser?->isAdmin()) {
      $portalUrl = url('/admin');
      $portalLabel = 'Admin Panel';
  } else {
      $portalUrl = route('student.login');
      $portalLabel = 'Student Portal';
  }
@endphp

<header>
  <div class="wrap bar">
    <a href="{{ route('home') }}" class="mark">
      <img src="{{ asset('images/pbr-logo.png') }}" alt="">
      <span>the<i>PBR</i></span>
    </a>

    <nav class="desk">
      <a href="{{ route('home') }}" class="item {{ request()->routeIs('home') ? 'on' : '' }}">Home</a>

      <div class="dd {{ request()->routeIs('articles.*','classes') ? 'on' : '' }}" id="dd">
        <button class="item" id="ddBtn" aria-expanded="false" aria-haspopup="true">
          Resources <i class="caret"></i>
        </button>
        <div class="menu" role="menu">
          <a href="{{ route('articles.index') }}" class="{{ request()->routeIs('articles.*') ? 'on' : '' }}" role="menuitem">
            Articles<small>ဆောင်းပါးများ</small>
          </a>
          <a href="{{ route('classes') }}" class="{{ request()->routeIs('classes') ? 'on' : '' }}" role="menuitem">Classes<small>သင်တန်းများ</small></a>
        </div>
      </div>

      <a href="#" class="item">About the Class</a>
      <a href="#" class="item">Contact Us</a>
    </nav>

    <a href="{{ $portalUrl }}" class="btn">{{ $portalLabel }}</a>

    <button class="burger" id="burger" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>

  <div class="mob wrap" id="mob">
    <a href="{{ route('home') }}">Home</a>
    <a href="{{ route('articles.index') }}">Resources</a>
    <a href="{{ route('articles.index') }}" class="sub">— Articles</a>
    <a href="{{ route('classes') }}" class="sub">— Classes</a>
    <a href="#">About the Class</a>
    <a href="#">Contact Us</a>
    <a href="{{ $portalUrl }}" class="btn" style="width:100%;margin-top:14px">{{ $portalLabel }}</a>
  </div>
</header>

@yield('content')

<footer>
  <div class="wrap">
    <div class="fgrid">
      <div>
        <div class="fmark">
          <img src="{{ asset('images/pbr-logo.png') }}" alt="">the<i>PBR</i>
        </div>
        <p style="max-width:34ch;margin-top:12px">
          မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများကို လုပ်ငန်းရှင်များ နားလည်လွယ်အောင် သင်ကြားပေးသည့် သင်တန်း။
        </p>
      </div>
      <div>
        <h5>Explore</h5>
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('articles.index') }}">Articles</a>
        <a href="{{ route('classes') }}">Classes</a>
      </div>
      <div>
        <h5>Class</h5>
        <a href="#">About the Class</a>
        <a href="#">Contact Us</a>
        <a href="{{ route('student.register') }}">Student Registration</a>
        <a href="{{ route('student.login') }}">Student Portal</a>
      </div>
      <div>
        <h5>Contact</h5>
        <p>hello@thepbr.io</p>
        <a href="#">Facebook Page</a>
        <a href="#">Viber</a>
      </div>
    </div>
    <div class="fbot">
      <span>© {{ date('Y') }} thePBR. All rights reserved.</span>
      <span>Privacy · Terms</span>
    </div>
  </div>
</footer>

<script>
const dd = document.getElementById('dd'), ddBtn = document.getElementById('ddBtn');
const setDD = o => { dd.dataset.open = o; ddBtn.setAttribute('aria-expanded', o); };
ddBtn.addEventListener('click', e => { e.stopPropagation(); setDD(dd.dataset.open !== 'true'); });
document.addEventListener('click', () => setDD(false));
document.addEventListener('keydown', e => { if (e.key === 'Escape') setDD(false); });
dd.addEventListener('mouseenter', () => setDD(true));
dd.addEventListener('mouseleave', () => setDD(false));

const burger = document.getElementById('burger'), mob = document.getElementById('mob');
burger.addEventListener('click', () => burger.setAttribute('aria-expanded', mob.classList.toggle('open')));
</script>

@stack('scripts')
</body>
</html>
