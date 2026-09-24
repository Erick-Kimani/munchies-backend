<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();

            // nullOnDelete, same reasoning as payments: an acceptance is
            // evidence. If the account is deleted we keep the fact that
            // someone accepted version X on date Y — with the personal
            // data (ip, user agent) cleared separately by whatever
            // handles erasure requests — rather than losing the record.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 'general' | 'seller' — see config/terms.php. Plain string,
            // not an enum column, so adding an audience later is a config
            // change rather than a migration.
            $table->string('audience', 32);

            // The version string of the document actually agreed to, e.g.
            // '2026-09-16'. This is the whole reason the table exists: a
            // bare "accepted: true" tells you nothing once the wording
            // changes.
            $table->string('version', 40);

            // Where the acceptance happened: 'registration',
            // 'property_submission', 're_acceptance'. Set server-side.
            $table->string('context', 40)->nullable();

            // Set when the acceptance authorized a specific listing, so a
            // seller declaration can be traced to the property it was
            // made about. nullOnDelete: losing the submission shouldn't
            // erase the declaration that went with it.
            $table->foreignId('property_submission_id')
                ->nullable()
                ->constrained('property_submissions')
                ->nullOnDelete();

            // Captured for the evidential value of the record. Both are
            // personal data under the DPA 2019 — don't surface them in any
            // public or seller-facing response.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('accepted_at');
            $table->timestamps();

            // "What is this user's latest acceptance for this audience?"
            // is the only lookup this table gets on a hot path.
            $table->index(['user_id', 'audience', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};