<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Backs the seller "request an edit" feature. A row here is a *proposed*
// change to a property_submissions row — it never touches the live
// listing until an admin approves it (see
// PropertyEditRequestController::approve). Kept as its own table rather
// than a JSON column on property_submissions so the full history (what
// was asked, by whom, and the admin's decision/reason) survives and is
// queryable, and so "how many requests has this listing used" is a
// simple COUNT(*) rather than something read out of JSON.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_edit_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_submission_id')
                ->constrained('property_submissions')
                ->cascadeOnDelete();

            // The seller who asked for this change. Always the submission's
            // own owner — enforced in the controller, not here.
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // Only the fields a seller is allowed to touch. Every one is
            // nullable because a given request might only change some of
            // them (e.g. just the description) — NULL means "no change
            // requested to this field", which is why these mirror
            // property_submissions' own nullability rather than being
            // required. Deliberately excludes full_name/email: those are
            // identity fields tied to who submitted the listing and are
            // never editable through this flow.
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();

            // The seller's reason for the change — required on every
            // request (see PropertyEditRequestController::store). Gives
            // the admin context, and the same field doubles as the
            // "substantive reason" once a seller is out of slots and has
            // to email/request manually instead.
            $table->text('seller_note');

            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Submissions table is small enough that a straight index on
            // the FK (already added by constrained()) plus this one on
            // status covers both "all requests for this submission" and
            // "all pending requests across the app" (the admin queue)
            // without a composite index being needed yet.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_edit_requests');
    }
};