<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Http\Resources\DashboardSummaryResource;
use App\Services\DashboardService;

class DashboardSummaryController extends Controller
{
    public function __invoke(RangeRequest $request, DashboardService $dashboardService): DashboardSummaryResource
    {
        return DashboardSummaryResource::make($dashboardService->summary($request->range()));
    }
}
