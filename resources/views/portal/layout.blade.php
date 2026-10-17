<!doctype html>
<html lang="my">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Partner Portal') — thePBR</title>
    <link rel="icon" href="{{ asset('images/pbr-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/partner-portal.css') }}?v=20261017">
    <script defer src="{{ asset('js/partner-portal.js') }}?v=20261017"></script>
</head>
<body>
<a class="skip-link" href="#main">အဓိကအကြောင်းအရာသို့</a>
<div class="portal-shell">
    <header class="portal-topbar">
        <a class="logo" href="{{ route('home') }}"><span class="logo-mark"><img src="{{ asset('images/pbr-logo.png') }}" alt="thePBR"></span><span><span class="logo-name">thePBR<span style="color:#8ba380">.</span></span><span class="logo-sub" style="display:block">PARTNER PORTAL</span></span></a>
        <div class="top-actions">
            @if(auth()->user()->hasActivePortalAccess() && !request()->routeIs('portal.home'))
                <a class="btn ghost sm" href="{{ route('portal.home') }}">@include('portal.icon', ['icon'=>'back']) Apps</a>
            @endif
            <span class="portal-person">{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" aria-label="Log Out">@include('portal.icon', ['icon'=>'logout'])</button></form>
        </div>
    </header>
    <main id="main" class="portal-main" tabindex="-1">
        @if(session('success'))<div class="portal-feedback" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="portal-feedback error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
