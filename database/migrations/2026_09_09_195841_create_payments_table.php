<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // nullOnDelete rather than cascade — a payment is a financial
            // record. If the user account is later deleted we keep the
            // row (for reconciliation) but drop the association.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What this payment is for. Kept as a plain string rather than
            // an enum column so adding a new purpose later is just a new
            // constant, not a migration. See Payment::PURPOSE_* below —
            // this is always set server-side from those constants, never
            // taken directly from client input.
            $table->string('purpose');

            // Amount actually sent to Daraja. Always sourced from server
            // config (see config/services.php 'mpesa.listing_fee'), never
            // trusted from the request — a client could otherwise ask us
            // to charge itself KES 1 for a listing fee.
            $table->decimal('amount', 10, 2);

            // Normalized MSISDN the STK prompt was sent to, e.g. 2547XXXXXXXX.
            $table->string('phone');

            // Correlation IDs Daraja returns from the initiate call.
            // checkout_request_id is what the callback references, and
            // what the frontend polls on — so it's unique once set.
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();

            // pending -> completed | failed | cancelled.
            // 'cancelled' covers the case where the customer dismisses the
            // STK prompt on their phone rather than entering a PIN — Daraja
            // reports that as a specific ResultCode, not a generic failure.
            $table->string('status')->default('pending');

            $table->unsignedInteger('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->timestamp('transaction_date')->nullable();

            // Set the moment this payment is consumed by whatever it paid
            // for (see PropertySubmissionController::store). A completed
            // payment can only ever be consumed once — this is what
            // enforces that, alongside a row lock at consumption time.
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};