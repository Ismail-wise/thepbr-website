<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PBR Account') — thePBR</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&family=Noto+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/student-portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace-invitations.css') }}">
</head>
<body>
    <header class="portal-header">
        <div class="portal-wrap portal-bar">
            <a href="{{ route('home') }}" class="portal-brand">
                <img src="{{ asset('images/pbr-logo.png') }}" alt="thePBR">
                <span>the<strong>PBR</strong></span>
            </a>

            <div class="portal-nav">
                <a href="{{ route('home') }}">Public Website</a>
                @auth
                    <a href="{{ route('account.dashboard') }}">My Account</a>

                    @if(auth()->user()->isStudent())
                        <a href="{{ route('student.dashboard') }}">Student Portal</a>
                    @endif

                    @if(auth()->user()->isAdmin() || auth()->user()->ownedWorkspaces()->exists() || auth()->user()->hasAcceptedWorkspaceMembership())
                        <a href="{{ route('workspaces.index') }}">Workspaces</a>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <a href="{{ url('/admin') }}">Admin Portal</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Log Out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Log In</a>
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
        <div class="portal-wrap">© {{ date('Y') }} thePBR. Secure Account Access.</div>
    </footer>
</body>
</html>
