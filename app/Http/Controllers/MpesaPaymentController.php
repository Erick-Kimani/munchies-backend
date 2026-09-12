<?php

namespace App\Http\Controllers;

use App\Exceptions\Mpesa\MpesaException;
use App\Models\Payment;
use App\Services\Mpesa\StkPushService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MpesaPaymentController extends Controller
{
    // Daraja's own "request cancelled by user" ResultCode — the one case
    // we report as 'cancelled' rather than a generic 'failed', since the
    // customer explicitly dismissed the prompt rather than anything
    // actually going wrong.
    private const RESULT_CODE_CANCELLED_BY_USER = 1032;

    public function __construct(private StkPushService $stkPush)
    {
    }

    // Requires auth:sanctum — starts the listing-fee STK Push for the
    // logged-in user. The amount is never taken from the request; it
    // always comes from server config, so a client can't ask to be
    // charged less (or nothing) for the fee.
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        try {
            $phone = StkPushService::normalizeMsisdn($validated['phone']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $amount = (int) config('services.mpesa.listing_fee');
        $user = $request->user();

        $payment = Payment::create([
            'user_id' => $user->id,
            'purpose' => Payment::PURPOSE_PROPERTY_LISTING_FEE,
            'amount' => $amount,
            'phone' => $phone,
            'status' => Payment::STATUS_PENDING,
        ]);

        try {
            $result = $this->stkPush->initiate(
                phone: $phone,
                amount: $amount,
                accountReference: 'TAWI-' . $payment->id,
                transactionDesc: 'Tawi Properties listing fee',
            );
        } catch (MpesaException $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'result_desc' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        $payment->update([
            'merchant_request_id' => $result['merchant_request_id'],
            'checkout_request_id' => $result['checkout_request_id'],
        ]);

        return response()->json([
            'message' => $result['customer_message'],
            'checkout_request_id' => $result['checkout_request_id'],
            'amount' => $amount,
        ], 201);
    }

    // PUBLIC — Safaricom posts here once the customer responds to the STK
    // prompt (or it times out). No auth is possible on this route since
    // Daraja is the caller, not a logged-in browser — see the README note
    // on why this still can't be used to fabricate a completed payment:
    // it only ever updates a payment row that already exists, and the
    // routes that consume a payment always re-check status === completed
    // and the owning user_id.
    //
    // Always responds 200, even when the payment can't be matched — a
    // non-200 tells Safaricom's side to keep retrying, which wouldn't
    // change the outcome here.
    public function callback(Request $request)
    {
        $callback = $request->input('Body.stkCallback', []);

        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = $callback['ResultCode'] ?? null;
        $resultDesc = $callback['ResultDesc'] ?? '';

        if (!$checkoutRequestId || $resultCode === null) {
            Log::warning('M-Pesa callback missing expected fields.', ['body' => $request->all()]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $payment = Payment::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$payment) {
            Log::warning('M-Pesa callback for unknown CheckoutRequestID.', ['checkout_request_id' => $checkoutRequestId]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $metadata = $this->flattenCallbackMetadata($callback['CallbackMetadata']['Item'] ?? []);

        $this->applyResult($payment, (int) $resultCode, (string) $resultDesc, $metadata);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    // Requires auth:sanctum — lets the frontend poll while the customer
    // is completing the prompt on their phone. Scoped to the requesting
    // user's own payment: a checkout_request_id belonging to someone
    // else returns 404, not their payment's status.
    public function status(Request $request, string $checkoutRequestId)
    {
        $payment = Payment::where('checkout_request_id', $checkoutRequestId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        // If the callback hasn't arrived yet, ask Daraja directly rather
        // than leaving the frontend to poll a status that may never
        // change — the assignment brief this project's C++ counterpart
        // is built from calls out sandbox callbacks as sometimes
        // unreliable, and the same is true here. Give the callback a
        // couple of seconds' head start before falling back to this, so
        // we're not racing it on every poll.
        if ($payment->status === Payment::STATUS_PENDING && $payment->updated_at->diffInSeconds(now()) >= 3) {
            try {
                $result = $this->stkPush->query($payment->checkout_request_id);
                if (!$result['pending']) {
                    $this->applyResult($payment, $result['result_code'], $result['result_desc']);
                }
            } catch (MpesaException $e) {
                // A failed status *check* isn't a failed *payment* — leave
                // the payment as pending and let the next poll try again.
                Log::info('M-Pesa status query failed, will retry on next poll.', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status' => $payment->status,
            'result_desc' => $payment->result_desc,
            'mpesa_receipt_number' => $payment->mpesa_receipt_number,
        ]);
    }

    // Shared by callback() and the status() fallback query, so the
    // "what does this result code mean for our payment record" logic
    // exists in exactly one place.
    private function applyResult(Payment $payment, int $resultCode, string $resultDesc, ?array $metadata = null): void
    {
        if ($resultCode === 0) {
            $payment->status = Payment::STATUS_COMPLETED;
            if ($metadata) {
                $payment->mpesa_receipt_number = $metadata['MpesaReceiptNumber'] ?? $payment->mpesa_receipt_number;
                if (!empty($metadata['TransactionDate'])) {
                    $payment->transaction_date = Carbon::createFromFormat('YmdHis', (string) $metadata['TransactionDate']);
                }
            }
        } else {
            $payment->status = $resultCode === self::RESULT_CODE_CANCELLED_BY_USER
                ? Payment::STATUS_CANCELLED
                : Payment::STATUS_FAILED;
        }

        $payment->result_code = $resultCode;
        $payment->result_desc = $resultDesc;
        $payment->save();
    }

    // Daraja's CallbackMetadata.Item arrives as a list of {Name, Value}
    // pairs rather than a plain object — flatten it into
    // ['Amount' => ..., 'MpesaReceiptNumber' => ..., ...] once here
    // instead of re-walking the array wherever a field is needed.
    private function flattenCallbackMetadata(array $items): array
    {
        $flat = [];
        foreach ($items as $item) {
            if (isset($item['Name'])) {
                $flat[$item['Name']] = $item['Value'] ?? null;
            }
        }
        return $flat;
    }
}