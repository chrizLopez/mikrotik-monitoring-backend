<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Services\BillingCycleService;
use App\Services\DashboardService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportController extends Controller
{
    public function users(
        RangeRequest $request,
        DashboardService $dashboardService,
        BillingCycleService $billingCycleService,
    ): StreamedResponse {
        $cycle = $billingCycleService->resolveCurrent();
        $filename = sprintf('user-usage-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($dashboardService): void {
            $handle = fopen('php://output', 'w');
            $users = $dashboardService->currentUserStats();

            fputcsv($handle, [
                'Name',
                'Queue Name',
                'Group',
                'Subnet',
                'Upload Bytes',
                'Download Bytes',
                'Total Bytes',
                'Quota Bytes',
                'Remaining Bytes',
                'Usage Percent',
                'State',
                'Current Max Limit',
                'Last Snapshot At',
            ]);

            foreach ($users as $user) {
                $summary = $user->monthlySummaries->first();

                fputcsv($handle, [
                    $user->name,
                    $user->queue_name,
                    $user->group_name,
                    $user->subnet,
                    $summary?->upload_bytes ?? 0,
                    $summary?->download_bytes ?? 0,
                    $summary?->total_bytes ?? 0,
                    $summary?->quota_bytes ?? $user->monthly_quota_bytes,
                    $summary?->remaining_bytes ?? $user->monthly_quota_bytes,
                    $summary?->usage_percent ?? 0,
                    $summary?->state ?? 'NORMAL',
                    $summary?->current_max_limit,
                    $summary?->last_snapshot_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'X-Billing-Cycle' => $cycle->label,
            'X-Range' => $request->range(),
        ]);
    }
}
