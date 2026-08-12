<!DOCTYPE html>
<html lang="my">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PBR Account') — thePBR</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+Myanmar:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace-invitations.css') }}">
    <link rel="stylesheet" href="{{ asset('css/partner-dynamics.css') }}?v={{ filemtime(public_path('css/partner-dynamics.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-tools.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-platform-v2.css') }}?v={{ filemtime(public_path('css/pbr-platform-v2.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-operating-system.css') }}?v={{ filemtime(public_path('css/pbr-operating-system.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-operating-dashboard.css') }}?v={{ filemtime(public_path('css/pbr-operating-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-operating-fixes.css') }}?v={{ filemtime(public_path('css/pbr-operating-fixes.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-startup-capital.css') }}?v={{ filemtime(public_path('css/pbr-startup-capital.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-startup-capital-readonly.css') }}?v={{ filemtime(public_path('css/pbr-startup-capital-readonly.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pbr-roster.css') }}?v={{ filemtime(public_path('css/pbr-roster.css')) }}">
    <script src="{{ asset('js/pbr-operating-system.js') }}?v={{ filemtime(public_path('js/pbr-operating-system.js')) }}" defer></script>
</head>
<body>
    <header class="portal-header">
        <div class="portal-wrap portal-bar">
            <a href="{{ route('home') }}" class="portal-brand">
                <img src="{{ asset('images/pbr-logo.png') }}" alt="thePBR">
                <span>the<strong>PBR</strong></span>
            </a>

            <div class="portal-nav">
                <a href="{{ route('home') }}">ပင်မစာမျက်နှာ</a>
                @auth
                    <a href="{{ route('account.dashboard') }}">ကျွန်ုပ်၏ Account</a>

                    @if(auth()->user()->isAdmin() || auth()->user()->isStudent() || auth()->user()->isPartner())
                        <a href="{{ route('partner-dynamics.index') }}">Partner Dynamics</a>
                    @endif

                    @if(auth()->user()->isStudent())
                        <a href="{{ route('student.dashboard') }}">သင်တန်း Portal</a>
                    @endif

                    <a href="{{ route('workspaces.index') }}">ကျွန်ုပ်၏ Business များ</a>

                    @if(auth()->user()->isAdmin())
                        <a href="{{ url('/admin') }}">Admin Portal</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">ထွက်ရန်</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Login ဝင်ရန်</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @if(session('success'))
            <div class="portal-wrap alert success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="portal-wrap alert error">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="portal-footer">
        <div class="portal-wrap">© {{ date('Y') }} thePBR — Partnership Business Management Platform</div>
    </footer>
</body>
</html>
