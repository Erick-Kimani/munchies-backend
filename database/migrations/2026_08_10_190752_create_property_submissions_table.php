<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_submissions', function (Blueprint $table) {
            $table->id();

            // Submitter's details — this form is public, so we are not
            // guaranteed a logged-in user. We still link one if they
            // happen to be authenticated, purely for reference.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('price_range');
            $table->string('location');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Review workflow — every submission starts pending and is
            // only ever moved by an admin, never by the submitter.
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_submissions');
    }
};