@extends('layouts.student-portal')

@section('title', 'Create PBR Account')

@section('content')
<section class="auth-section">
    <div class="auth-shell">
        <div class="auth-copy">
            <span class="portal-kicker">Public Account</span>
            <h1>Create your PBR account</h1>
            <p>This account can use public and profile features. Student materials remain locked until the account receives student access.</p>
            <div class="auth-note">
                Already have an account?
                <a href="{{ route('login') }}">Log in here</a>
            </div>
            <div class="auth-note">
                Already received a student access code?
                <a href="{{ route('student.register') }}">Use student registration</a>
            </div>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('register.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="field">
                    <label for="phone">Phone Number <span>(Optional)</span></label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
                        <small class="field-help">At least 8 characters.</small>
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </div>

                <button class="portal-button" type="submit">Create Public Account</button>
            </form>
        </div>
    </div>
</section>
@endsection
