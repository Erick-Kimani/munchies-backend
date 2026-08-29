<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Login — brute-force target. Keyed by IP + email so an attacker
        // can't dodge the limit just by rotating IPs against one victim
        // account, and one IP can't hammer many accounts unchecked.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip() . '|' . $request->input('email'));
        });

        // Other unauthenticated auth flows — register, Google sign-in,
        // forgot/reset password. Account-creation and account-takeover
        // risk, and a common target for credential-stuffing bots.
        // Keyed by IP since there's no reliable user identity yet.
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Authenticated write actions — logout, submitting a property,
        // sending a contact message. Keyed by user ID (stable identity,
        // unlike IP) so one logged-in user can't spam these forms.
        RateLimiter::for('authenticated-write', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Authenticated reads — fetching own profile, own messages.
        // Cheap, low-risk, so a generous limit that mainly guards
        // against runaway frontend bugs or scripted abuse.
        RateLimiter::for('authenticated-read', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Public reads — property types, featured listings, counties.
        // No auth at all, so keyed by IP. Generous, but still capped
        // to blunt scraping/DoS against endpoints anyone can hit.
        RateLimiter::for('public-read', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}