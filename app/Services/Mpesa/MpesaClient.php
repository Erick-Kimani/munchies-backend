<?php

namespace App\Services\Mpesa;

use App\Exceptions\Mpesa\MpesaAuthException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Owns Daraja's base URL and the OAuth access token used to authorize
 * every other Daraja call. Nothing else in the application should build
 * a Daraja base address itself or fetch a token directly — go through
 * this class so there is exactly one place that knows the sandbox vs.
 * production distinction and exactly one token cache.
 */
class MpesaClient
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;

    // Cache key the access token is stored under. Deliberately a single
    // shared key — this app authenticates as one Daraja app (one
    // consumer key/secret pair), so one cached token serves every
    // request until it's due for refresh.
    private const TOKEN_CACHE_KEY = 'mpesa_access_token';

    // Reuse a cached token for this fraction of its declared lifetime,
    // then treat it as due for refresh even though it hasn't technically
    // expired yet. Daraja tokens are typically valid ~3600s; a 0.9 margin
    // means we stop trusting a token at ~54 minutes, well before it can
    // expire mid-request.
    private const TOKEN_SAFETY_MARGIN = 0.9;

    public function __construct()
    {
        $env = config('services.mpesa.env', 'sandbox');
        $this->baseUrl = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $this->consumerKey = (string) config('services.mpesa.consumer_key');
        $this->consumerSecret = (string) config('services.mpesa.consumer_secret');

        if ($this->consumerKey === '' || $this->consumerSecret === '') {
            // Fail early — an app with no credentials should never get
            // far enough to make a network call and produce a confusing
            // downstream error instead.
            throw new MpesaAuthException('M-Pesa consumer key/secret are not configured.');
        }
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Returns a usable Bearer token, fetching and caching a new one only
     * when no cached token exists or the cached one is due for refresh.
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheAccessToken();
    }

    private function fetchAndCacheAccessToken(): string
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(15)
                ->get("{$this->baseUrl}/oauth/v1/generate", [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Network-level failure — Daraja never actually answered.
            Log::warning('M-Pesa OAuth request failed to connect.', ['error' => $e->getMessage()]);
            throw new MpesaAuthException('Could not reach the M-Pesa authentication service.', previous: $e);
        }

        if ($response->failed()) {
            // Daraja answered, but rejected the credentials or errored.
            // Never log the consumer secret — only the response status
            // and whatever Daraja itself sent back.
            Log::warning('M-Pesa OAuth request rejected.', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            throw new MpesaAuthException('M-Pesa authentication was rejected.');
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 0);

        if (!is_string($token) || $token === '' || $expiresIn <= 0) {
            throw new MpesaAuthException('M-Pesa authentication response was missing expected data.');
        }

        $ttlSeconds = (int) floor($expiresIn * self::TOKEN_SAFETY_MARGIN);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttlSeconds);

        return $token;
    }
}