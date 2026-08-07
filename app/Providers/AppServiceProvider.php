<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('account-login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by("login:{$email}:{$ip}:minute"),
                Limit::perMinute(20)->by("login:{$ip}:global"),
            ];
        });

        RateLimiter::for('public-registration', function (Request $request): array {
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by("public-register:{$ip}:minute"),
                Limit::perHour(20)->by("public-register:{$ip}:hour"),
            ];
        });

        RateLimiter::for('student-registration', function (Request $request): array {
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by("student-register:{$ip}:minute"),
                Limit::perHour(20)->by("student-register:{$ip}:hour"),
            ];
        });

        RateLimiter::for('access-code-redemption', function (Request $request): array {
            $identity = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(5)->by("access-code:{$identity}:minute"),
                Limit::perHour(20)->by("access-code:{$identity}:hour"),
            ];
        });

        RateLimiter::for('workspace-invitations', function (Request $request): array {
            $identity = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(10)->by("workspace-invite:{$identity}:minute"),
                Limit::perHour(50)->by("workspace-invite:{$identity}:hour"),
            ];
        });

        RateLimiter::for('workspace-invitation-accept', function (Request $request): array {
            $identity = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(10)->by("workspace-accept:{$identity}:minute"),
            ];
        });
    }
}
