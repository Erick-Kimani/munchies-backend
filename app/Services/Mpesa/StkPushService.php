<?php

namespace App\Services\Mpesa;

use App\Exceptions\Mpesa\MpesaRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * M-Pesa Express (STK Push) against a Paybill shortcode — initiate a
 * payment prompt on the customer's phone, and query its outcome
 * afterwards. Reuses MpesaClient for the base URL and access token;
 * does not build a second path to Daraja.
 */
class StkPushService
{
    // Daraja's own well-documented quirk: a query made while the customer
    // still hasn't responded to the prompt sometimes comes back with this
    // ResultCode and a ResultDesc saying "still processing" — NOT actually
    // a failure. Every mature Daraja client library special-cases this
    // exact code for the same reason. Treated identically to the
    // "500.001.1001" HTTP-level pending signal below.
    private const RESULT_CODE_STILL_PROCESSING = 4999;

    private MpesaClient $client;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;

    public function __construct(MpesaClient $client)
    {
        $this->client = $client;
        $this->shortcode = (string) config('services.mpesa.shortcode');
        $this->passkey = (string) config('services.mpesa.passkey');
        $this->callbackUrl = (string) config('services.mpesa.callback_url');

        if ($this->shortcode === '' || $this->passkey === '' || $this->callbackUrl === '') {
            throw new InvalidArgumentException(
                'M-Pesa shortcode, passkey, and callback URL must all be configured.'
            );
        }
    }

    /**
     * Normalizes a Kenyan phone number to Daraja's expected MSISDN
     * format: 2547XXXXXXXX / 2541XXXXXXXX (12 digits, no '+', no
     * leading 0). Throws InvalidArgumentException for anything that
     * doesn't look like a Kenyan mobile number — this is local input
     * validation, deliberately rejected before any network call.
     */
    public static function normalizeMsisdn(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        } elseif (str_starts_with($digits, '7') || str_starts_with($digits, '1')) {
            if (strlen($digits) === 9) {
                $digits = '254' . $digits;
            }
        }

        if (!preg_match('/^254(7|1)\d{8}$/', $digits)) {
            throw new InvalidArgumentException('Enter a valid Safaricom phone number, e.g. 0712345678.');
        }

        return $digits;
    }

    private function timestamp(): string
    {
        return now()->format('YmdHis');
    }

    private function password(string $timestamp): string
    {
        return base64_encode($this->shortcode . $this->passkey . $timestamp);
    }

    /**
     * Sends the STK Push prompt. $phone must already be normalized
     * (see normalizeMsisdn). $amount is a whole-number KES amount —
     * Daraja's sandbox rejects decimals. $accountReference is shown to
     * the customer and capped at 12 characters by Daraja.
     *
     * Returns ['merchant_request_id' => ..., 'checkout_request_id' => ...,
     * 'customer_message' => ...] on success. Throws MpesaRequestException
     * on any rejection or network failure.
     */
    public function initiate(string $phone, int $amount, string $accountReference, string $transactionDesc): array
    {
        $timestamp = $this->timestamp();

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $this->password($timestamp),
            'Timestamp' => $timestamp,
            // Paybill, not Till — CustomerBuyGoodsOnline is for till
            // numbers and would post under a different account model.
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => substr($accountReference, 0, 12),
            'TransactionDesc' => substr($transactionDesc, 0, 100),
        ];

        try {
            $response = Http::withToken($this->client->getAccessToken())
                ->timeout(20)
                ->post("{$this->client->baseUrl()}/mpesa/stkpush/v1/processrequest", $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('M-Pesa STK Push request failed to connect.', ['error' => $e->getMessage()]);
            throw new MpesaRequestException('Could not reach M-Pesa. Please try again.', previous: $e);
        }

        $body = $response->json();

        if ($response->failed() || !is_array($body) || (string) ($body['ResponseCode'] ?? '') !== '0') {
            Log::warning('M-Pesa STK Push request rejected.', [
                'status' => $response->status(),
                'body' => $body ?? $response->body(),
            ]);
            $desc = is_array($body) ? ($body['errorMessage'] ?? $body['ResponseDescription'] ?? null) : null;
            throw new MpesaRequestException($desc ?: 'M-Pesa rejected the payment request.');
        }

        if (!isset($body['CheckoutRequestID'], $body['MerchantRequestID'])) {
            throw new MpesaRequestException('M-Pesa response was missing expected data.');
        }

        return [
            'merchant_request_id' => $body['MerchantRequestID'],
            'checkout_request_id' => $body['CheckoutRequestID'],
            'customer_message' => $body['CustomerMessage'] ?? 'Check your phone to complete the payment.',
        ];
    }

    /**
     * Queries the outcome of a previously initiated STK Push.
     *
     * Returns:
     *   ['pending' => true] if Daraja hasn't settled it yet — either via
     *     the HTTP-level "still processing" errorCode, or via
     *     ResultCode 4999 (Daraja's undocumented but well-known
     *     "still processing" code, sometimes returned in place of a real
     *     terminal result while the customer hasn't responded yet). Both
     *     are normal, expected outcomes while the customer is still
     *     entering their PIN — not errors, and not failures.
     *   ['pending' => false, 'result_code' => int, 'result_desc' => string]
     *     once Daraja has a final answer. result_code 0 means success;
     *     anything else means the customer cancelled, timed out, or it
     *     was otherwise not completed.
     *
     * Throws MpesaRequestException for a genuine failure to reach or
     * understand Daraja — distinct from "pending", which is not an error.
     */
    public function query(string $checkoutRequestId): array
    {
        $timestamp = $this->timestamp();

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $this->password($timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        try {
            $response = Http::withToken($this->client->getAccessToken())
                ->timeout(20)
                ->post("{$this->client->baseUrl()}/mpesa/stkpushquery/v1/query", $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('M-Pesa STK Push query failed to connect.', ['error' => $e->getMessage()]);
            throw new MpesaRequestException('Could not reach M-Pesa to check payment status.', previous: $e);
        }

        $body = $response->json();

        // Daraja reports "still processing" as an HTTP error with this
        // specific errorCode, not as a normal pending result — this is
        // the one errorCode this method treats as non-fatal.
        if (is_array($body) && ($body['errorCode'] ?? null) === '500.001.1001') {
            return ['pending' => true];
        }

        if ($response->failed() || !is_array($body)) {
            Log::warning('M-Pesa STK Push query rejected.', [
                'status' => $response->status(),
                'body' => $body ?? $response->body(),
            ]);
            throw new MpesaRequestException('Could not understand M-Pesa\'s payment status response.');
        }

        if (!array_key_exists('ResultCode', $body)) {
            return ['pending' => true];
        }

        $resultCode = (int) $body['ResultCode'];

        if ($resultCode === self::RESULT_CODE_STILL_PROCESSING) {
            return ['pending' => true];
        }

        return [
            'pending' => false,
            'result_code' => $resultCode,
            'result_desc' => (string) ($body['ResultDesc'] ?? ''),
        ];
    }
}