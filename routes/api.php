<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardExportController;
use App\Http\Controllers\Api\DashboardGroupController;
use App\Http\Controllers\Api\DashboardIspController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\DashboardUserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/summary', DashboardSummaryController::class);
        Route::get('/isps', [DashboardIspController::class, 'index']);
        Route::get('/isps/{isp}/history', [DashboardIspController::class, 'history']);
        Route::get('/users', [DashboardUserController::class, 'index']);
        Route::get('/users/{monitoredUser}/history', [DashboardUserController::class, 'history']);
        Route::get('/top-users', [DashboardUserController::class, 'topUsers']);
        Route::get('/groups/usage', [DashboardGroupController::class, 'usage']);
        Route::get('/export/users.csv', [DashboardExportController::class, 'users']);
    });
});
