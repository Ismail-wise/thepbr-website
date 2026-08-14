@extends('layouts.student-portal')

@section('title', 'My Account')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">My Account</span>
            <h1>Hello, {{ $user->name }}</h1>
            <p>ဒီ Account ရဲ့ Access နဲ့ ချိတ်ဆက်ထားတဲ့ Business Workspace တွေကို အောက်ကနေ ဖွင့်နိုင်ပါတယ်။</p>
        </div>

        <div class="auth-card">
            <div class="auth-note">
                <strong>Email:</strong> {{ $user->email }}
            </div>

            @if($user->isAdmin())
                <div class="auth-note">
                    <strong>Administrator:</strong> Full system access is enabled.
                </div>
                <a class="portal-button" href="{{ url('/admin') }}">Open Admin Portal</a>
            @endif

            @if($user->isStudent())
                <div class="auth-note">
                    <strong>Business OS Access:</strong> PBR private business workspace access is active.
                </div>
                <a class="portal-button" href="{{ route('student.dashboard') }}">Open Business Operating System</a>
            @endif

            @if($user->canAccessBusinessOperatingSystem())
                <div class="auth-note">
                    <strong>My Businesses:</strong> ဒီ Account က ခွင့်ပြုထားတဲ့ Workspace တွေကိုသာ ဝင်ရောက်နိုင်ပါတယ်။
                </div>
                <a class="portal-button" href="{{ route('workspaces.index') }}">Open My Businesses</a>
            @endif

            @unless($user->isAdmin() || $user->isStudent())
                <div class="auth-note">
                    PBR Access Code ရှိရင် Account အသစ်မဖန်တီးဘဲ private Business OS access ကို activate လုပ်နိုင်ပါတယ်။
                </div>
                <a class="portal-button" href="{{ route('account.access-code.show') }}">Redeem PBR Access Code</a>
            @endunless

            @unless($user->canAccessBusinessOperatingSystem())
                <div class="auth-note">
                    ဒီ Account မှာ private Business Workspace access မရှိသေးပါ။ Public website resources ကို ဆက်လက်အသုံးပြုနိုင်ပါတယ်။
                </div>
            @endunless
        </div>
    </div>
</section>
@endsection
