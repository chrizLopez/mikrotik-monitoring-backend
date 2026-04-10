<?php

namespace App\Services;

use App\Models\IspSnapshot;
use App\Models\UserSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LatestSnapshotService
{
    public function latestUserSnapshots(): Collection
    {
        $latestPerUser = UserSnapshot::query()
            ->select('monitored_user_id', DB::raw('MAX(recorded_at) as recorded_at'))
            ->groupBy('monitored_user_id');

        return UserSnapshot::query()
            ->joinSub($latestPerUser, 'latest_user_snapshots', function ($join): void {
                $join->on('user_snapshots.monitored_user_id', '=', 'latest_user_snapshots.monitored_user_id')
                    ->on('user_snapshots.recorded_at', '=', 'latest_user_snapshots.recorded_at');
            })
            ->select('user_snapshots.*')
            ->get();
    }

    public function latestIspSnapshots(): Collection
    {
        $latestPerIsp = IspSnapshot::query()
            ->select('isp_id', DB::raw('MAX(recorded_at) as recorded_at'))
            ->groupBy('isp_id');

        return IspSnapshot::query()
            ->joinSub($latestPerIsp, 'latest_isp_snapshots', function ($join): void {
                $join->on('isp_snapshots.isp_id', '=', 'latest_isp_snapshots.isp_id')
                    ->on('isp_snapshots.recorded_at', '=', 'latest_isp_snapshots.recorded_at');
            })
            ->select('isp_snapshots.*')
            ->get();
    }
}
