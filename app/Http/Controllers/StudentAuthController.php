<?php

namespace App\Http\Controllers;

use App\Models\StudentAccessCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentAuthController extends Controller
{
    public function showRegister(): View
    {
        return view('student.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'access_code' => ['required', 'string', 'max:40'],
        ]);

        $fingerprint = StudentAccessCode::fingerprint($validated['access_code']);

        $user = DB::transaction(function () use ($validated, $fingerprint): User {
            $accessCode = StudentAccessCode::query()
                ->where('code_hash', $fingerprint)
                ->lockForUpdate()
                ->first();

            if (! $accessCode || ! $accessCode->isUsable()) {
                throw ValidationException::withMessages([
                    'access_code' => 'This access code is invalid, expired, disabled, or already used.',
                ]);
            }

            $classSession = $accessCode->classSession()
                ->lockForUpdate()
                ->first();

            if ($classSession && $classSession->capacity > 0 && $classSession->enrolled >= $classSession->capacity) {
                throw ValidationException::withMessages([
                    'access_code' => 'This class batch is already full. Please contact the PBR team.',
                ]);
            }

            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
                'password' => $validated['password'],
                'role' => 'student',
                'class_session_id' => $accessCode->class_session_id,
                'account_status' => 'active',
                'portal_access_expires_at' => null,
            ]);

            $accessCode->update([
                'status' => 'used',
                'used_by_user_id' => $user->id,
                'used_at' => now(),
            ]);

            if ($classSession) {
                $classSession->increment('enrolled');
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('student.dashboard')
            ->with('success', 'Your student account has been created successfully.');
    }

    public function showLogin(): View
    {
        return view('student.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $credentials = [
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        if (! Auth::attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'The email address or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! $user || ! $user->isStudent() || ! $user->hasActivePortalAccess()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account does not currently have access to the Student Portal.',
            ]);
        }

        return redirect()->intended(route('student.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('student.login')
            ->with('success', 'You have been logged out safely.');
    }
}
