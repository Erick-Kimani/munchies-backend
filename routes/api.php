<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PropertyTypeController;
use App\Http\Controllers\PropertySubmissionController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\MpesaPaymentController;
use App\Http\Controllers\Api\CountyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-sensitive');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

// Public — Google sign-in/sign-up. Takes an OAuth access_token obtained
// client-side (see the frontend's GoogleAuthButton.vue), verifies it
// directly with Google, and logs the user in — registering them first if
// this is their first time signing in with this Google account.
Route::post('auth/google', [AuthController::class, 'googleAuth'])->middleware('throttle:auth-sensitive');

// Public — password reset. Both stay unauthenticated by necessity: a user
// locked out of their account has no Sanctum token to send.
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-sensitive');
Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-sensitive');

// Public — property types for frontend dropdowns (Land, Rentals, Commercial Buildings, Apartments)
Route::get('getActivePropertyTypes', [PropertyTypeController::class, 'fetchActivePropertyTypes'])->middleware('throttle:public-read');

// Public — powers Buypage.vue / Rentpage.vue. Only ever returns
// status === 'featured' submissions; pending/rejected stay hidden.
Route::get('property-listings', [PropertySubmissionController::class, 'featured'])->middleware('throttle:public-read');

// Public — powers every LocationDropdown (Home, Categories, Buy, Rent).
// Was previously in the auth:sanctum group below, which meant it 401'd
// for anyone not logged in — moved here so guests can load it too. The
// two mutating routes (restore/pull-down) stay admin-only, further down.
Route::get('/counties', [CountyController::class, 'index'])->middleware('throttle:public-read');

// PUBLIC — Safaricom's own server posts here after the customer responds
// to (or ignores/times out) an STK Push prompt. Can't require auth:sanctum
// since Daraja isn't a logged-in browser. See MpesaPaymentController::
// callback for why this is still safe: it can only update a payment row
// that was already created by an authenticated initiate() call, and every
// route that consumes a payment re-checks its status and owning user.
Route::post('mpesa/callback', [MpesaPaymentController::class, 'callback'])->middleware('throttle:mpesa-callback');

// Protected Routes — any authenticated user
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('throttle:authenticated-write');
    // Lets a user who signed up via Google (and so has no password they
    // actually know) set a real one, so manual email/password login works
    // for their account too. Requires auth:sanctum — see AuthController::
    // setPassword for why no reset code is needed here.
    Route::post('set-password', [AuthController::class, 'setPassword'])->middleware('throttle:authenticated-write');
    Route::get('user', function (Request $request) { return $request->user(); })->middleware('throttle:authenticated-read');
    Route::post('property-submissions', [PropertySubmissionController::class, 'store'])->middleware('throttle:authenticated-write');
    Route::post('contact-messages', [ContactMessageController::class, 'store'])->middleware('throttle:authenticated-write');
    // Scoped to the logged-in user's own messages — must stay in this
    // group (not the admin group below) and must be registered before
    // contact-messages/{id} so 'mine' doesn't get swallowed as an id.
    Route::get('contact-messages/mine', [ContactMessageController::class, 'mine'])->middleware('throttle:authenticated-read');

    // Starts the listing-fee STK Push. See MpesaPaymentController::initiate
    // — amount always comes from server config, never from this request.
    Route::post('payments/mpesa/stkpush', [MpesaPaymentController::class, 'initiate'])->middleware('throttle:mpesa-initiate');
    // Polled by MpesaPaymentModal.vue while the customer completes the
    // prompt on their phone. Scoped to the caller's own payment.
    Route::get('payments/mpesa/{checkoutRequestId}/status', [MpesaPaymentController::class, 'status'])->middleware('throttle:authenticated-read');
});

// Protected Routes — admin only
Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminOnly::class])->group(function () {
    Route::get('user/{id}', [AuthController::class, 'getUserById']);
    Route::delete('user/{id}', [AuthController::class, 'deleteUser']);
    // Look up a user by email (plural 'users' prefix, so it can't collide
    // with the singular 'user/{id}' routes above).
    Route::get('users/find', [AuthController::class, 'findUserByEmail']);
    // Grants one locked-out user a single further attempt at
    // POST /set-password — see AuthController::grantSetPasswordAccess.
    Route::post('user/{id}/grant-set-password-access', [AuthController::class, 'grantSetPasswordAccess']);

    Route::get('getAllRoles', [RoleController::class, 'fetchRoles']);
    Route::post('createRole', [RoleController::class, 'saveRole']);
    Route::get('getRole/{id}', [RoleController::class, 'fetchRole']);
    Route::put('updateRole/{id}', [RoleController::class, 'updateRole']);
    Route::delete('deleteRole/{id}', [RoleController::class, 'deleteRole']);

    Route::get('getAllPropertyTypes', [PropertyTypeController::class, 'fetchPropertyTypes']);
    Route::post('createPropertyType', [PropertyTypeController::class, 'savePropertyType']);
    Route::get('getPropertyType/{id}', [PropertyTypeController::class, 'fetchPropertyType']);
    Route::put('updatePropertyType/{id}', [PropertyTypeController::class, 'updatePropertyType']);
    Route::delete('deletePropertyType/{id}', [PropertyTypeController::class, 'deletePropertyType']);

    Route::get('property-submissions', [PropertySubmissionController::class, 'index']);
    Route::get('property-submissions/{id}', [PropertySubmissionController::class, 'show']);
    Route::put('property-submissions/{id}/feature', [PropertySubmissionController::class, 'feature']);
    Route::put('property-submissions/{id}/unfeature', [PropertySubmissionController::class, 'unfeature']);
    Route::put('property-submissions/{id}/reject', [PropertySubmissionController::class, 'reject']);

    Route::get('contact-messages', [ContactMessageController::class, 'index']);
    Route::get('contact-messages/{id}', [ContactMessageController::class, 'show']);
    Route::put('contact-messages/{id}/read', [ContactMessageController::class, 'markRead']);
    Route::put('contact-messages/{id}/resolve', [ContactMessageController::class, 'resolve']);
    Route::put('contact-messages/{id}/reply', [ContactMessageController::class, 'reply']);

    Route::patch('/counties/{county}/restore', [CountyController::class, 'restore']);
    Route::patch('/counties/{county}/pull-down', [CountyController::class, 'pullDown']);
});