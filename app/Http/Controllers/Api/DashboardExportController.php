<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RangeRequest;
use App\Services\BillingCycleService;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
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

    public function topUsers(RangeRequest $request, DashboardAnalyticsService $analytics): StreamedResponse
    {
        $filename = sprintf('top-users-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($analytics, $request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Group', 'Subnet', 'Upload Bytes', 'Download Bytes', 'Total Bytes', 'Remaining Quota Bytes', 'Usage Percent', 'State', 'Current Max Limit', 'Peak Combined Bps', 'Peak At']);

            foreach ($analytics->topUsers($request->range(), $request->limit()) as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['group_name'],
                    $row['subnet'],
                    $row['upload_bytes'],
                    $row['download_bytes'],
                    $row['total_bytes'],
                    $row['remaining_quota_bytes'],
                    $row['usage_percent'],
                    $row['state'],
                    $row['current_max_limit'],
                    $row['peak_combined_bps'],
                    $row['peak_at'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function isps(RangeRequest $request, DashboardAnalyticsService $analytics): StreamedResponse
    {
        $filename = sprintf('isps-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($analytics, $request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ISP', 'Interface', 'Download Bytes', 'Upload Bytes', 'Total Bytes', 'Share Percent']);

            foreach ($analytics->ispDistribution($request->range())['items'] as $item) {
                fputcsv($handle, [
                    $item['name'],
                    $item['interface_name'],
                    $item['download_bytes'],
                    $item['upload_bytes'],
                    $item['total_bytes'],
                    $item['share_percent'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function alerts(RangeRequest $request, DashboardAnalyticsService $analytics): StreamedResponse
    {
        $filename = sprintf('alerts-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($analytics, $request): void {
            $handle = fopen('php://output', 'w');
            $alerts = $analytics->alerts($request->range());
            fputcsv($handle, ['Type', 'Severity', 'Title', 'Subject', 'Usage Percent', 'Latency Ms', 'Packet Loss Percent', 'Combined Bps', 'State']);

            foreach (['quota_alerts', 'health_alerts', 'usage_alerts'] as $bucket) {
                foreach ($alerts[$bucket] as $alert) {
                    fputcsv($handle, [
                        $alert['type'] ?? '',
                        $alert['severity'] ?? '',
                        $alert['title'] ?? '',
                        $alert['subject'] ?? '',
                        $alert['usage_percent'] ?? '',
                        $alert['latency_ms'] ?? '',
                        $alert['packet_loss_percent'] ?? '',
                        $alert['combined_bps'] ?? '',
                        $alert['state'] ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function throttlingHistory(RangeRequest $request, DashboardAnalyticsService $analytics): StreamedResponse
    {
        $filename = sprintf('throttling-history-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($analytics, $request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Group', 'Current State', 'Last State Change', 'Throttled Events']);

            foreach ($analytics->throttlingHistory($request->range())['items'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['group_name'],
                    $row['current_state'],
                    $row['last_state_change'],
                    $row['throttled_events'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function printSummary(RangeRequest $request, DashboardAnalyticsService $analytics): View
    {
        return view('print-summary', [
            'range' => $request->range(),
            'report' => $analytics->reports($request->range()),
        ]);
    }
}
