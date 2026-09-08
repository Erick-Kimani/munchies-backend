<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set the first (and, by default, only) time this account's
            // manual-login password is chosen via POST /set-password.
            // Null means the user has never used /set-password — e.g. a
            // Google-only account that has never had a real password. Once
            // this is set, /set-password refuses further attempts and
            // points the user at /forgot-password instead, unless an admin
            // has granted a one-time override (see below).
            $table->timestamp('password_set_at')->nullable()->after('google_id');

            // Admin-granted, single-use permission to go through
            // /set-password again despite password_set_at already being
            // set — e.g. a user locked themselves out and can't complete
            // the email-based forgot-password flow. An admin flips this on
            // for that one account; it's automatically cleared the moment
            // the user successfully sets a password again.
            $table->boolean('password_set_override')->default(false)->after('password_set_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_set_at', 'password_set_override']);
        });
    }
};