@extends('layouts.student-portal')

@section('title', 'Create Student Account')

@section('content')
<section class="auth-section">
    <div class="auth-shell">
        <div class="auth-copy">
            <span class="portal-kicker">Student Portal</span>
            <h1>Create your PBR student account</h1>
            <p>Use the unique access code provided by the PBR team. Each code can be used only once.</p>
            <div class="auth-note">
                Already registered?
                <a href="{{ route('student.login') }}">Log in here</a>
            </div>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('student.register.store') }}" novalidate>
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

                <div class="field">
                    <label for="access_code">Student Access Code</label>
                    <input id="access_code" name="access_code" type="text" value="{{ old('access_code') }}" placeholder="PBR-B01-XXXXXX" autocomplete="off" required>
                    <small class="field-help">Enter the code exactly as provided. Spaces and hyphens are accepted.</small>
                    @error('access_code')<small class="field-error">{{ $message }}</small>@enderror
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

                <button class="portal-button" type="submit">Create Student Account</button>
            </form>
        </div>
    </div>
</section>
@endsection
