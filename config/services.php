<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // Must match the frontend's VITE_GOOGLE_CLIENT_ID — we check the
        // access token's audience against this so a token minted for some
        // other Google app can't be used to log in here.
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // Daraja (Safaricom M-Pesa) — sandbox throughout development. See
    // App\Services\Mpesa\MpesaClient for how 'env' picks the base URL,
    // and the README for how to obtain each of these from the Daraja
    // developer portal.
    'mpesa' => [
        // 'sandbox' | 'production'. Only ever 'sandbox' during
        // development — see README.
        'env' => env('MPESA_ENV', 'sandbox'),

        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

        // The Paybill shortcode. In sandbox this is the test paybill
        // Daraja assigns your app (commonly 174379) — it will not
        // display "TAWI PROPERTIES" since that name is tied to a real,
        // Safaricom-registered Paybill. Swap in your own registered
        // Paybill number here once you move to production.
        'shortcode' => env('MPESA_SHORTCODE'),

        // The M-Pesa Express passkey for that shortcode, from the
        // Daraja portal / sandbox simulator.
        'passkey' => env('MPESA_PASSKEY'),

        // Must be a public HTTPS URL Safaricom's servers can reach —
        // localhost will never work. Use an ngrok (or similar) tunnel
        // in development; see README.
        'callback_url' => env('MPESA_CALLBACK_URL'),

        // Whole-number KES amount charged for submitting a property
        // listing. Sandbox accepts small amounts (e.g. 1) for testing.
        'listing_fee' => env('MPESA_LISTING_FEE', 1),
    ],

];