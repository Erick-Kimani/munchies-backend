<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('getAllRoles', [RoleController::class, 'fetchRoles']);
Route::post('createRole', [RoleController::class, 'saveRole']);
Route::get('getRole/{id}', [RoleController::class, 'fetchRole']);
Route::put('updateRole/{id}', [RoleController::class, 'updateRole']);
Route::delete('deleteRole/{id}', [RoleController::class, 'deleteRole']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
