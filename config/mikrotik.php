<?php

return [
    'host' => env('MIKROTIK_HOST'),
    'port' => (int) env('MIKROTIK_PORT', 8728),
    'username' => env('MIKROTIK_USERNAME'),
    'password' => env('MIKROTIK_PASSWORD'),
    'push_token' => env('MIKROTIK_PUSH_TOKEN'),
    'use_ssl' => filter_var(env('MIKROTIK_USE_SSL', false), FILTER_VALIDATE_BOOL),
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 5),
    'polled_interfaces' => [
        'ether1 - Starlink',
        'ether2 - SmartBro A',
        'ether4 - SmartBro B',
    ],
    'polled_queue_names' => [
        'Home Router',
        'VLAN20 - Camaymayan',
        'VLAN30 - Rutor',
        'VLAN40 - Peleyo',
        'VLAN50 - Yamba',
        'VLAN60 - Piso WiFi',
        'VLAN70 - Olario',
        'GROUP_A_TOTAL',
    ],
    'user_queue_names' => [
        'Home Router',
        'VLAN20 - Camaymayan',
        'VLAN30 - Rutor',
        'VLAN40 - Peleyo',
        'VLAN50 - Yamba',
        'VLAN60 - Piso WiFi',
        'VLAN70 - Olario',
    ],
    'excluded_queue_names' => [
        'GROUP_A_TOTAL',
    ],
    'health_targets' => [
        'ether1 - Starlink' => env('MIKROTIK_ETHER1_HEALTH_TARGET', '1.1.1.1'),
        'ether2 - SmartBro A' => env('MIKROTIK_ETHER2_HEALTH_TARGET', '8.8.8.8'),
        'ether4 - SmartBro B' => env('MIKROTIK_ETHER4_HEALTH_TARGET', '8.8.4.4'),
    ],
    'health_ping_count' => (int) env('MIKROTIK_HEALTH_PING_COUNT', 3),
];
