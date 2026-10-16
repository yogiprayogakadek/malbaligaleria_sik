<?php

$configuredHosts = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('APP_TRUSTED_HOSTS', '')),
)));

if ($configuredHosts === []) {
    $applicationHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
    $configuredHosts = $applicationHost ? [strtolower($applicationHost)] : [];
}

return [
    'enabled' => (bool) env('HTTP_SECURITY_HEADERS_ENABLED', true),

    'same_origin_frame_routes' => [
        'loading.letter.inline',
        'work-permits.letter.inline',
        'staff.work-permits.letter.inline',
    ],

    'trusted_hosts' => $configuredHosts,
    'trusted_host_patterns' => array_map(
        static fn (string $host): string => '^'.preg_quote($host, '/').'$',
        $configuredHosts,
    ),

    'csp' => [
        'enabled' => (bool) env('HTTP_CSP_ENABLED', true),
        'report_only' => (bool) env('HTTP_CSP_REPORT_ONLY', false),
    ],

    'hsts' => [
        'enabled' => (bool) env('HTTP_HSTS_ENABLED', true),
        'max_age' => (int) env('HTTP_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HTTP_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('HTTP_HSTS_PRELOAD', false),
    ],
];
