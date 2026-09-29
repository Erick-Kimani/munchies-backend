<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the initial administrator account from environment variables —
 * never from a credential committed to source control.
 *
 * Set ADMIN_EMAIL and ADMIN_PASSWORD in your .env (and optionally
 * ADMIN_NAME) before running `php artisan db:seed`. If either is missing,
 * this seeder does nothing rather than falling back to a guessable
 * default — there is no default admin account.
 *
 * If an account with ADMIN_EMAIL already exists, this seeder promotes it
 * to Administrator (if it isn't already) but NEVER touches its password.
 * Re-running `db:seed` after the first setup is always safe.
 *
 * If this project's git history ever contained a hard-coded admin
 * credential (check with `git log --all -p -- database/seeders`),
 * rotate that password and treat it as compromised.
 */
class AdminUserSeeder extends Seeder
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'Administrator');

        if (!$email || !$password) {
            $this->command?->info(
                'AdminUserSeeder: ADMIN_EMAIL / ADMIN_PASSWORD not set in .env — skipping. '
                . 'No admin account will be created automatically.'
            );

            return;
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->command?->error(sprintf(
                'AdminUserSeeder: ADMIN_PASSWORD must be at least %d characters — skipping.',
                self::MIN_PASSWORD_LENGTH
            ));

            return;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            // Never reset a password here — that was exactly the bug in
            // the seeder this replaces. Only promote the role if needed.
            if ((int) $existing->role_id !== 1) {
                $existing->update(['role_id' => 1]);
                $this->command?->info("AdminUserSeeder: promoted {$email} to Administrator.");
            } else {
                $this->command?->info("AdminUserSeeder: {$email} already exists as Administrator — nothing to do.");
            }

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => 1,
            // Treat this the same as a normal registration: the admin
            // chose this password themselves via the env var, so
            // /set-password's one-time rule should already be "used".
            'password_set_at' => now(),
        ]);

        $this->command?->info("AdminUserSeeder: created administrator {$email}.");
    }
}