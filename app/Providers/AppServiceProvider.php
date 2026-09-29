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
        // Login — brute-force target. Two limits apply simultaneously:
        //
        //   1. Per (IP, email) — stops one IP hammering one account.
        //   2. Per email alone — stops the same account being hammered
        //      from many *different* IPs (credential stuffing, botnets).
        //
        // The email is lowercased before building either key. Without
        // that, "A@x.com" and "a@X.com" hashed to different throttle
        // buckets even though the DB's case-insensitive collation treats
        // them as the same account in login()'s User::where('email', ...)
        // lookup — so an attacker could bypass the limit entirely just by
        // varying the case of the email on each request.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($request->ip() . '|' . $email),
                Limit::perMinute(10)->by('email:' . $email),
            ];
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

        // Starting an STK Push is money-adjacent and hits Daraja's own
        // API on our behalf — kept tighter than an ordinary authenticated
        // write so a stuck "Pay" button or a scripted retry loop can't
        // spam Daraja or spam the customer's phone with prompts.
        RateLimiter::for('mpesa-initiate', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Daraja's own callback. Keyed by IP rather than user (there is
        // no authenticated user on this route). Generous enough to
        // tolerate Safaricom's own retries on a slow response, but still
        // capped since this route is publicly reachable by definition.
        RateLimiter::for('mpesa-callback', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}