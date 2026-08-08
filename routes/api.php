<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

 // Protected Routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminOnly::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    
    Route::get('user', function (Request $request) { return $request->user(); });
    Route::get('user/{id}', [AuthController::class, 'getUserById']);
    Route::delete('user/{id}', [AuthController::class, 'deleteUser']);

    Route::get('getAllRoles', [RoleController::class, 'fetchRoles']);
    Route::post('createRole', [RoleController::class, 'saveRole']);
    Route::get('getRole/{id}', [RoleController::class, 'fetchRole']);
    Route::put('updateRole/{id}', [RoleController::class, 'updateRole']);
    Route::delete('deleteRole/{id}', [RoleController::class, 'deleteRole']);
});
