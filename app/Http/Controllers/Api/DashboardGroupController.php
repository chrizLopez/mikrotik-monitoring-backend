<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Http\Resources\GroupUsageResource;
use App\Services\DashboardService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardGroupController extends Controller
{
    public function usage(RangeRequest $request, DashboardService $dashboardService): AnonymousResourceCollection
    {
        return GroupUsageResource::collection(collect($dashboardService->groupUsage($request->range())));
    }
}
