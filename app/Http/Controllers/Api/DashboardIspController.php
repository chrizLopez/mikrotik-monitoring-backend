<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Http\Resources\HistoryResource;
use App\Http\Resources\IspResource;
use App\Models\Isp;
use App\Services\DashboardService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardIspController extends Controller
{
    public function index(DashboardService $dashboardService): AnonymousResourceCollection
    {
        return IspResource::collection($dashboardService->currentIspStats());
    }

    public function show(Isp $isp, DashboardService $dashboardService): IspResource
    {
        return IspResource::make($dashboardService->currentIspStat($isp));
    }

    public function history(RangeRequest $request, Isp $isp, DashboardService $dashboardService): HistoryResource
    {
        return HistoryResource::make($dashboardService->ispHistory($isp, $request->range()));
    }
}
