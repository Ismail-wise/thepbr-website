<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt([
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ], (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'The email address or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! $user || ! $user->hasActiveAccount()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact the PBR team.',
            ]);
        }

        return redirect()->intended($this->destinationFor($user));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()->create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'password' => $validated['password'],
            'is_admin' => false,
            'role' => 'public',
            'class_session_id' => null,
            'account_status' => 'active',
            'portal_access_expires_at' => null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->intended(route('account.dashboard'))
            ->with('success', 'Your PBR account has been created successfully.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out safely.');
    }

    private function destinationFor(User $user): string
    {
        if ($user->isAdmin()) {
            return route('account.dashboard');
        }

        if ($user->isStudent()) {
            return route('student.dashboard');
        }

        if ($user->hasAcceptedPartnerWorkspaceMembership()) {
            return route('workspaces.index');
        }

        return route('account.dashboard');
    }
}
