<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HousekeepingTaskController;
use App\Http\Controllers\Api\MaintenanceTicketController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::get('/dashboard', DashboardController::class);

    // Properties
    Route::apiResource('properties', PropertyController::class);
    Route::get('properties/{id}/statistics', [PropertyController::class, 'statistics']);

    Route::apiResource('units', UnitController::class);
    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('messages', MessageController::class);
    Route::apiResource('housekeeping-tasks', HousekeepingTaskController::class);
    Route::apiResource('maintenance-tickets', MaintenanceTicketController::class);
    Route::apiResource('owners', OwnerController::class);
    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    Route::apiResource('expenses', ExpenseController::class);
    Route::post('upload', function (Request $request) {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);
        $path = $request->file('file')->store('uploads', 'public');
        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path),
        ]);
    });
});
