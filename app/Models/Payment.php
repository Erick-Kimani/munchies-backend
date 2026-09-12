<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    // What a payment can be for. A plain class-constant list rather than
    // a DB-backed enum table — add a new one here when a second paid
    // feature shows up. Controllers set this from these constants only;
    // it is never taken directly from client input (see
    // MpesaPaymentController::initiate).
    public const PURPOSE_PROPERTY_LISTING_FEE = 'property_listing_fee';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'purpose',
        'amount',
        'phone',
        'merchant_request_id',
        'checkout_request_id',
        'status',
        'result_code',
        'result_desc',
        'mpesa_receipt_number',
        'transaction_date',
        'consumed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function propertySubmission()
    {
        return $this->hasOne(PropertySubmission::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}