<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'range' => $this['range'],
            'current_billing_cycle' => BillingCycleResource::make($this['billing_cycle']),
            'total_monitored_users' => $this['total_monitored_users'],
            'throttled_user_count' => $this['throttled_user_count'],
            'active_isp_count' => $this['active_isp_count'],
            'total_isp_traffic_this_cycle' => $this['total_isp_traffic_this_cycle'],
            'total_user_traffic_this_cycle' => $this['total_user_traffic_this_cycle'],
            'total_isp_traffic_for_range' => $this['total_isp_traffic_for_range'],
            'total_user_traffic_for_range' => $this['total_user_traffic_for_range'],
            'last_poll_timestamp' => $this['last_poll_timestamp'],
            'group_policies' => $this['group_policies'] ?? [],
            'starlink_usage' => $this['starlink_usage'] ?? null,
            'smartbro_total' => $this['smartbro_total'] ?? null,
            'distribution_note' => $this['distribution_note'] ?? null,
        ];
    }
}
