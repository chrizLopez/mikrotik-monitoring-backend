<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Services\TrafficAnalytics\TrafficAnalyticsService;
use Illuminate\Http\JsonResponse;

class TrafficAnalyticsController extends Controller
{
    public function topSites(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->topSites($request->range(), $request->limit())]);
    }

    public function topApps(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->topApps($request->range(), $request->limit())]);
    }

    public function topGames(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->topGames($request->range(), $request->limit())]);
    }

    public function topCategories(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->topCategories($request->range(), $request->limit())]);
    }

    public function userTopDestinations(RangeRequest $request, MonitoredUser $user, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->userTopDestinations($user, $request->range(), $request->limit())]);
    }

    public function ispTopDestinations(RangeRequest $request, Isp $isp, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->ispTopDestinations($isp, $request->range(), $request->limit())]);
    }

    public function groupTopDestinations(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->groupTopDestinations((string) $request->query('group', 'A'), $request->range(), $request->limit())]);
    }

    public function history(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->history((int) $request->integer('entity_id'), $request->range())]);
    }

    public function overview(RangeRequest $request, TrafficAnalyticsService $service): JsonResponse
    {
        return response()->json(['data' => $service->overview($request->range())]);
    }
}
