<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Http\Requests\Dashboard\TopUsersRequest;
use App\Http\Resources\HistoryResource;
use App\Http\Resources\MonitoredUserResource;
use App\Http\Resources\TopUserResource;
use App\Models\MonitoredUser;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardUserController extends Controller
{
    public function index(DashboardService $dashboardService): AnonymousResourceCollection
    {
        return MonitoredUserResource::collection($dashboardService->currentUserStats());
    }

    public function history(RangeRequest $request, MonitoredUser $monitoredUser, DashboardService $dashboardService): HistoryResource
    {
        return HistoryResource::make($dashboardService->userHistory($monitoredUser, $request->range()));
    }

    public function topUsers(TopUsersRequest $request, DashboardAnalyticsService $analytics): AnonymousResourceCollection
    {
        return TopUserResource::collection(collect($analytics->topUsers($request->range(), $request->limit())));
    }
}
