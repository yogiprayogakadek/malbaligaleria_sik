<?php

return [
    'timezone' => env('OPERATING_TIMEZONE', 'Asia/Makassar'),
    'holidays' => [
        'api_url' => env('NATIONAL_HOLIDAY_API_URL', 'https://use.apiindonesia.id/api/v1/libur'),
        'api_key' => env('NATIONAL_HOLIDAY_API_KEY'),
    ],
    'days' => [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ],
];
