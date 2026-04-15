<?php

return [
    'default_monthly_quota_bytes' => 214748364800,
    'default_normal_max_limit' => '2M/5M',
    'default_throttled_max_limit' => '512k/2M',
    'billing_cycle_day' => (int) env('BILLING_CYCLE_DAY', 23),
    'billing_cycle_timezone' => env('BILLING_CYCLE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'group_names' => [
        'A' => 'Group A',
        'B' => 'Group B',
    ],
    'network_model' => [
        'mode' => 'shared_equal_pcc',
        'summary' => 'All monitored users share three WANs through equal PCC with per-WAN failover.',
        'distribution_label' => '33.33% / 33.33% / 33.33%',
        'wan_count' => 3,
        'is_group_routing_enabled' => false,
        'priority_apps_status' => 'planned',
        'streaming_shaping_status' => 'planned',
        'retired_features' => [
            'Old Starlink WAN',
            'group-based WAN pinning',
            'GROUP_A_TOTAL parent queue',
        ],
        'wans' => [
            [
                'name' => 'Gomo',
                'interface_name' => 'ether1',
                'gateway' => '192.168.254.1',
                'connection_mark' => 'conn_gomo',
                'routing_mark' => 'to_GOMO',
                'display_order' => 1,
                'share_percent' => 33.33,
            ],
            [
                'name' => 'Starlink ISP New',
                'interface_name' => 'ether2',
                'gateway' => '100.64.0.1',
                'connection_mark' => 'conn_starlink',
                'routing_mark' => 'to_STARLINK',
                'display_order' => 2,
                'share_percent' => 33.33,
            ],
            [
                'name' => 'Smart Bro ISP',
                'interface_name' => 'ether4',
                'gateway' => '192.168.1.1',
                'connection_mark' => 'conn_smart',
                'routing_mark' => 'to_SMART',
                'display_order' => 3,
                'share_percent' => 33.33,
            ],
        ],
    ],
    'slow_query_log' => [
        'enabled' => (bool) env('DASHBOARD_SLOW_QUERY_LOG', false),
        'threshold_ms' => (int) env('DASHBOARD_SLOW_QUERY_THRESHOLD_MS', 250),
        'channel' => env('DASHBOARD_SLOW_QUERY_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],
];
