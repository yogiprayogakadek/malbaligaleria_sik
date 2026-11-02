<?php

return [
    'health_token' => env('MONITORING_HEALTH_TOKEN'),
    'allowed_hosts' => array_values(array_filter(array_map(
        fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('MONITORING_ALLOWED_HOSTS', '')),
    ))),
];
