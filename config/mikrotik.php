<?php

return [
    'host' => env('MIKROTIK_HOST'),
    'port' => (int) env('MIKROTIK_PORT', 8728),
    'username' => env('MIKROTIK_USERNAME'),
    'password' => env('MIKROTIK_PASSWORD'),
    'use_ssl' => filter_var(env('MIKROTIK_USE_SSL', false), FILTER_VALIDATE_BOOL),
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 5),
    'polled_interfaces' => [
        'ether1',
        'ether2',
        'ether4',
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
];
