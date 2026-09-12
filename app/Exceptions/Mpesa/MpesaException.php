<?php

namespace App\Exceptions\Mpesa;

use Exception;

/**
 * Common base for every failure that can happen while talking to Daraja.
 *
 * Callers that don't care about the distinction (auth vs. network vs.
 * rejected request) can catch this one type and still get a useful
 * message. Callers that do care can catch the specific subclass instead.
 * Always catch by reference (`catch (MpesaException $e)`) so the derived
 * type and its data survive.
 */
class MpesaException extends Exception
{
}