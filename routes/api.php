<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardAnalyticsController;
use App\Http\Controllers\Api\DashboardExportController;
use App\Http\Controllers\Api\DashboardGroupController;
use App\Http\Controllers\Api\DashboardIspController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\DashboardUserController;
use App\Http\Controllers\Api\TrafficAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/summary', DashboardSummaryController::class);
        Route::get('/live', [DashboardAnalyticsController::class, 'live']);
        Route::get('/isps', [DashboardIspController::class, 'index']);
        Route::get('/isps/{isp}/history', [DashboardIspController::class, 'history']);
        Route::get('/isps/distribution', [DashboardAnalyticsController::class, 'ispDistribution']);
        Route::get('/isps/{isp}/health-history', [DashboardAnalyticsController::class, 'healthHistory']);
        Route::get('/users', [DashboardUserController::class, 'index']);
        Route::get('/users/{monitoredUser}/history', [DashboardUserController::class, 'history']);
        Route::get('/users/{monitoredUser}/quota-timeline', [DashboardAnalyticsController::class, 'quotaTimeline']);
        Route::get('/users/{monitoredUser}/throttling-history', [DashboardAnalyticsController::class, 'userThrottlingHistory']);
        Route::get('/users/throttling-history', [DashboardAnalyticsController::class, 'throttlingHistory']);
        Route::get('/top-users', [DashboardUserController::class, 'topUsers']);
        Route::get('/top-active-users', [DashboardAnalyticsController::class, 'topActiveUsers']);
        Route::get('/groups/usage', [DashboardGroupController::class, 'usage']);
        Route::get('/alerts', [DashboardAnalyticsController::class, 'alerts']);
        Route::get('/comparisons', [DashboardAnalyticsController::class, 'comparisons']);
        Route::get('/reports', [DashboardAnalyticsController::class, 'reports']);
        Route::get('/export/users.csv', [DashboardExportController::class, 'users']);
        Route::get('/export/top-users.csv', [DashboardExportController::class, 'topUsers']);
        Route::get('/export/isps.csv', [DashboardExportController::class, 'isps']);
        Route::get('/export/alerts.csv', [DashboardExportController::class, 'alerts']);
        Route::get('/export/throttling-history.csv', [DashboardExportController::class, 'throttlingHistory']);
        Route::get('/traffic/top-sites', [TrafficAnalyticsController::class, 'topSites']);
        Route::get('/traffic/top-apps', [TrafficAnalyticsController::class, 'topApps']);
        Route::get('/traffic/top-games', [TrafficAnalyticsController::class, 'topGames']);
        Route::get('/traffic/top-categories', [TrafficAnalyticsController::class, 'topCategories']);
        Route::get('/traffic/users/{user}/top-destinations', [TrafficAnalyticsController::class, 'userTopDestinations']);
        Route::get('/traffic/isps/{isp}/top-destinations', [TrafficAnalyticsController::class, 'ispTopDestinations']);
        Route::get('/traffic/groups/top-destinations', [TrafficAnalyticsController::class, 'groupTopDestinations']);
        Route::get('/traffic/history', [TrafficAnalyticsController::class, 'history']);
        Route::get('/traffic/overview', [TrafficAnalyticsController::class, 'overview']);
        Route::get('/traffic/export.csv', [DashboardExportController::class, 'trafficAnalytics']);
        Route::get('/print/summary', [DashboardExportController::class, 'printSummary']);
    });
});
