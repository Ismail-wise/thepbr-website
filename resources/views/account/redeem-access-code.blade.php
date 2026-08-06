@extends('layouts.student-portal')

@section('title', 'Redeem Student Access Code')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Student Access</span>
            <h1>Upgrade your existing account</h1>
            <p>Enter the unused Student Access Code provided by the PBR team. Your current account will be upgraded without creating a duplicate account.</p>
            <div class="auth-note">
                Signed in as <strong>{{ $user->email }}</strong>
            </div>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('account.access-code.redeem') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="access_code">Student Access Code</label>
                    <input id="access_code" name="access_code" type="text" value="{{ old('access_code') }}" placeholder="PBR-B01-XXXXXX" autocomplete="off" required autofocus>
                    <small class="field-help">Enter the code exactly as provided. Spaces and hyphens are accepted.</small>
                    @error('access_code')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <button class="portal-button" type="submit">Upgrade to Student Access</button>
            </form>

            <div class="auth-note">
                <a href="{{ route('account.dashboard') }}">Return to My Account</a>
            </div>
        </div>
    </div>
</section>
@endsection
