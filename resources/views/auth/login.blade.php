@extends('layouts.student-portal')

@section('title', 'Account Login')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">PBR Account</span>
            <h1>Welcome back</h1>
            <p>Use one account to access the public website, Student Portal, invited workspaces, and the Admin Portal when permitted.</p>
            <div class="auth-note">
                New to thePBR?
                <a href="{{ route('register', request()->filled('email') ? ['email' => request('email')] : []) }}">Create a public account</a>
            </div>
            <div class="auth-note">
                Have a new student access code?
                <a href="{{ route('student.register') }}">Create a student account</a>
            </div>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" autocomplete="email" required autofocus>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <label class="check-row">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Keep me logged in on this device</span>
                </label>

                <button class="portal-button" type="submit">Log In</button>
            </form>
        </div>
    </div>
</section>
@endsection
