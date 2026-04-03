<?php

return [
    'default_monthly_quota_bytes' => 214748364800,
    'default_normal_max_limit' => '2M/5M',
    'default_throttled_max_limit' => '512k/2M',
    'billing_cycle_day' => (int) env('BILLING_CYCLE_DAY', 1),
    'billing_cycle_timezone' => env('BILLING_CYCLE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'group_totals_queue' => 'GROUP_A_TOTAL',
    'group_names' => [
        'A' => 'Group A',
        'B' => 'Group B',
    ],
];
