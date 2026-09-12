<?php

namespace App\Exceptions\Mpesa;

/**
 * Thrown when a request reached Daraja but was rejected (validation,
 * bad shortcode/passkey, etc.) or Daraja's response couldn't be
 * understood. Distinct from MpesaAuthException (token problems) and from
 * a plain network failure (Laravel's own ConnectionException, which
 * StkPushService catches and re-wraps as this type too — see below).
 */
class MpesaRequestException extends MpesaException
{
}