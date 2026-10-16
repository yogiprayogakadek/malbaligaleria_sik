<?php

return [
    'device_cookie' => env('STAFF_DEVICE_COOKIE', 'mbg_staff_device'),
    'device_cookie_days' => (int) env('STAFF_DEVICE_COOKIE_DAYS', 365),
    'history_limit' => (int) env('STAFF_LOGIN_HISTORY_LIMIT', 20),
];
