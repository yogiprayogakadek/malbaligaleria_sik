<?php

return [
    'default_base_url' => env('WAHA_BASE_URL', 'http://192.168.0.253:3000'),
    'default_session' => env('WAHA_SESSION', 'default'),
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WAHA_ALLOWED_HOSTS', '192.168.0.253')),
    ))),
];
