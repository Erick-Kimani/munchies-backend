<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PropertyTypeController;
use App\Http\Controllers\PropertySubmissionController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\Api\CountyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Public — password reset. Both stay unauthenticated by necessity: a user
// locked out of their account has no Sanctum token to send.
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Public — property types for frontend dropdowns (Land, Rentals, Commercial Buildings, Apartments)
Route::get('getActivePropertyTypes', [PropertyTypeController::class, 'fetchActivePropertyTypes']);

// Public — powers Buypage.vue / Rentpage.vue. Only ever returns
// status === 'featured' submissions; pending/rejected stay hidden.
Route::get('property-listings', [PropertySubmissionController::class, 'featured']);

// Public — powers every LocationDropdown (Home, Categories, Buy, Rent).
// Was previously in the auth:sanctum group below, which meant it 401'd
// for anyone not logged in — moved here so guests can load it too. The
// two mutating routes (restore/pull-down) stay admin-only, further down.
Route::get('/counties', [CountyController::class, 'index']);

// Protected Routes — any authenticated user
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', function (Request $request) { return $request->user(); });
    Route::post('property-submissions', [PropertySubmissionController::class, 'store']);
    Route::post('contact-messages', [ContactMessageController::class, 'store']);
    // Scoped to the logged-in user's own messages — must stay in this
    // group (not the admin group below) and must be registered before
    // contact-messages/{id} so 'mine' doesn't get swallowed as an id.
    Route::get('contact-messages/mine', [ContactMessageController::class, 'mine']);
});

// Protected Routes — admin only
Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminOnly::class])->group(function () {
    Route::get('user/{id}', [AuthController::class, 'getUserById']);
    Route::delete('user/{id}', [AuthController::class, 'deleteUser']);

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