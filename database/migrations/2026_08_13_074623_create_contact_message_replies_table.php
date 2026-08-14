<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')
                ->constrained()
                ->cascadeOnDelete();
            // Whoever wrote this reply — the original user, or a staff member.
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // true  -> written by an admin/staff member (shown as "Support" in the UI)
            // false -> written by the user who owns the thread
            $table->boolean('is_admin')->default(false);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_replies');
    }
};