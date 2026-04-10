<?php

return [
    'default_monthly_quota_bytes' => 214748364800,
    'default_normal_max_limit' => '2M/5M',
    'default_throttled_max_limit' => '512k/2M',
    'billing_cycle_day' => (int) env('BILLING_CYCLE_DAY', 23),
    'billing_cycle_timezone' => env('BILLING_CYCLE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'group_totals_queue' => 'GROUP_A_TOTAL',
    'group_names' => [
        'A' => 'Group A',
        'B' => 'Group B',
    ],
    'slow_query_log' => [
        'enabled' => (bool) env('DASHBOARD_SLOW_QUERY_LOG', false),
        'threshold_ms' => (int) env('DASHBOARD_SLOW_QUERY_THRESHOLD_MS', 250),
        'channel' => env('DASHBOARD_SLOW_QUERY_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],
];
