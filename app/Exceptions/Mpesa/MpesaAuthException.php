<?php

namespace App\Exceptions\Mpesa;

/**
 * Thrown when we can't obtain or use an OAuth access token from Daraja —
 * bad consumer key/secret, or the token endpoint itself is unreachable.
 * Never carries the consumer secret or a full access token in its message.
 */
class MpesaAuthException extends MpesaException
{
}