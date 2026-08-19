@extends('layouts.student-portal')

@section('title', 'My Account')

@section('content')
@php
    $acceptedPartnerAccess = $user->workspaceMemberships
        ->where('member_role', 'partner')
        ->where('invitation_status', 'accepted');
    $hasAuthoritativeEnrollment = $user->studentEnrollments->isNotEmpty();
    $isStudent = $user->isStudent();
    $isAdmin = $user->isAdmin();
    $isPartner = $acceptedPartnerAccess->isNotEmpty();
    $accountType = match (true) {
        $isAdmin && $isStudent => 'Administrator + Active Student',
        $isAdmin => 'Administrator',
        $isStudent => 'Active Student',
        $isPartner => 'Public Account + Invited Partner',
        default => 'Public Account',
    };
    $studentAccessSource = match (true) {
        ! $isStudent => 'Not active',
        $hasAuthoritativeEnrollment => 'Active · Authoritative entitlement',
        default => 'Active · Legacy compatibility',
    };
@endphp
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

            <section class="account-access-summary" aria-labelledby="account-access-heading">
                <div>
                    <span class="portal-kicker">Access Summary</span>
                    <h2 id="account-access-heading">Your Account Type & Access</h2>
                </div>
                <div class="account-access-grid">
                    <article class="account-access-card">
                        <span>Account Type</span>
                        <strong>{{ $accountType }}</strong>
                    </article>
                    <article class="account-access-card">
                        <span>Student Access</span>
                        <strong>{{ $studentAccessSource }}</strong>
                    </article>
                    <article class="account-access-card">
                        <span>PBR AI</span>
                        <strong>{{ $isStudent ? 'Available' : 'Not available · Students only' }}</strong>
                    </article>
                    <article class="account-access-card">
                        <span>Invited Partner Access</span>
                        <strong>{{ $acceptedPartnerAccess->count() }} {{ \Illuminate\Support\Str::plural('workspace', $acceptedPartnerAccess->count()) }}</strong>
                    </article>
                    <article class="account-access-card">
                        <span>Admin Access</span>
                        <strong>{{ $isAdmin ? 'Enabled' : 'Not enabled' }}</strong>
                    </article>
                </div>
                <p class="account-access-help">
                    Partner invitation က Account Type ကို မပြောင်းပါ။ PBR AI နဲ့ 64 operating tools ကို Active Student account တွေသာ အသုံးပြုနိုင်ပါတယ်။
                </p>
            </section>

            @if($isAdmin)
                <div class="auth-note">
                    <strong>Administrator:</strong> Full system access is enabled.
                </div>
                <a class="portal-button" href="{{ url('/admin') }}">Open Admin Portal</a>
            @endif

            @if($isStudent)
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

            @unless($isAdmin || $isStudent)
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
