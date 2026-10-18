<!doctype html>
<html lang="my">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $__env->yieldContent('title', 'Partner Portal') }} — thePBR</title>
    <link rel="icon" href="{{ asset('images/pbr-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/partner-portal.css') }}?v=20261017">
    <script defer src="{{ asset('js/partner-portal.js') }}?v=20261019"></script>
    @include('partials.premium-assets')
</head>
<body class="pbr-portal">
<a class="skip-link" href="#main">အဓိကအကြောင်းအရာသို့</a>
<div class="portal-shell">
    <header class="portal-topbar">
        <a class="logo" href="{{ route('home') }}" aria-label="thePBR — Public Website"><span class="logo-mark"><img src="{{ asset('images/pbr-logo.png') }}" alt=""></span><span><span class="logo-name">thePBR<span class="pbr-logo-dot">.</span></span><span class="logo-sub" style="display:block">PARTNER PORTAL</span></span></a>
        <div class="top-actions">
            @if(auth()->user()->hasActivePortalAccess() && !request()->routeIs('portal.home'))
                <a class="btn ghost sm" href="{{ route('portal.home') }}">@include('portal.icon', ['icon'=>'back']) Apps</a>
            @endif
            <span class="pbr-user-chip"><span class="pbr-user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="portal-person">{{ auth()->user()->name }}</span></span>
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
