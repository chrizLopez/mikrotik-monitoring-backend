<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;

class DashboardAnalyticsController extends Controller
{
    public function live(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->live($request->limit())]);
    }

    public function topActiveUsers(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->topActiveUsers($request->limit())]);
    }

    public function ispDistribution(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->ispDistribution($request->range())]);
    }

    public function alerts(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->alerts($request->range())]);
    }

    public function comparisons(DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->comparisons()]);
    }

    public function healthHistory(RangeRequest $request, Isp $isp, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->healthHistory($isp, $request->range())]);
    }

    public function quotaTimeline(RangeRequest $request, MonitoredUser $monitoredUser, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->quotaTimeline($monitoredUser, $request->range())]);
    }

    public function throttlingHistory(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->throttlingHistory($request->range())]);
    }

    public function userThrottlingHistory(RangeRequest $request, MonitoredUser $monitoredUser, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->throttlingHistory($request->range(), $monitoredUser)]);
    }

    public function reports(RangeRequest $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->reports($request->range())]);
    }
}
