@extends('layouts.student-portal')

@section('title', 'Student Login')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Student Portal</span>
            <h1>Welcome back</h1>
            <p>Log in with the email address and password you used when registering your student account.</p>
            <div class="auth-note">
                Have an unused access code?
                <a href="{{ route('student.register') }}">Create an account</a>
            </div>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('student.login.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
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

                <button class="portal-button" type="submit">Log In to Student Portal</button>
            </form>
        </div>
    </div>
</section>
@endsection
