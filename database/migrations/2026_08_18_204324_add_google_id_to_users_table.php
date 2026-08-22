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
            // Google's stable per-account identifier ("sub" claim). Nullable
            // + unique so it's only ever set for accounts that have gone
            // through Google sign-in, and never shared between two users.
            $table->string('google_id')->nullable()->unique()->after('email');

            // Optional avatar URL Google gives us on first sign-in. Purely
            // cosmetic — nothing depends on it being set.
            $table->string('avatar')->nullable()->after('google_id');
        });

        // Note: we deliberately do NOT relax `password` to nullable here —
        // that would need doctrine/dbal (not installed) to alter the column
        // on some drivers. Instead, accounts created via Google get a
        // random, never-revealed password hash (see AuthController::
        // googleAuth), so the existing NOT NULL constraint is satisfied
        // without a schema change or an extra dependency.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};