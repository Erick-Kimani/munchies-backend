<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            // The sender is always the logged-in user — never a typed
            // email — so we can trace every message back to a real
            // account without asking them to re-enter contact details.
            // There is deliberately no recipient_id: every message goes
            // to the admin implicitly, and nothing lets a seller/user
            // address another seller/user.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('message');

            // 'new' | 'read' | 'resolved' — plain string (not enum) for
            // the same SQLite-friendliness reason as property_submissions.
            $table->string('status')->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};