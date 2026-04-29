<?php

return [
    'default_monthly_quota_bytes' => 214748364800,
    'default_normal_max_limit' => '2M/5M',
    'default_throttled_max_limit' => '512k/2M',
    'billing_cycle_day' => (int) env('BILLING_CYCLE_DAY', 1),
    'billing_cycle_timezone' => env('BILLING_CYCLE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'group_totals_queue' => 'GROUP_A_TOTAL',
    'distribution_note' => 'PCC percentages are connection distribution targets, not guaranteed bandwidth percentages.',
    'isps' => [
        'starlink' => [
            'key' => 'STARLINK',
            'label' => 'Starlink',
            'interface' => 'ether1 - Starlink',
            'gateway' => '100.64.0.1',
            'monthly_cap_gb' => 500,
        ],
        'smart_a' => [
            'key' => 'SMART_A',
            'label' => 'SmartBro A',
            'interface' => 'ether2 - SmartBro A',
            'gateway' => '192.168.10.1',
        ],
        'smart_b' => [
            'key' => 'SMART_B',
            'label' => 'SmartBro B',
            'interface' => 'ether4 - SmartBro B',
            'gateway' => '192.168.1.1',
        ],
    ],
    'user_groups' => [
        'starlink_group' => [
            'key' => 'STARLINK_GROUP',
            'label' => 'Starlink Group',
            'subnets' => [
                '192.168.88.16/28',
                '192.168.88.64/28',
                '192.168.88.80/28',
                '192.168.88.96/28',
            ],
            'policy' => [
                'starlink' => 70,
                'smart_a' => 15,
                'smart_b' => 15,
            ],
        ],
        'smart_group' => [
            'key' => 'SMART_GROUP',
            'label' => 'Smart Group',
            'subnets' => [
                '192.168.88.112/28',
                '192.168.88.128/28',
                '192.168.88.144/28',
            ],
            'policy' => [
                'starlink' => 0,
                'smart_a' => 50,
                'smart_b' => 50,
            ],
        ],
    ],
    'starlink_warning_thresholds' => [
        50,
        75,
        90,
        100,
    ],
];
