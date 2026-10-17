<?php

return [
    'max_recipients' => (int) env('ANNOUNCEMENT_MAX_RECIPIENTS', 2000),
    'whatsapp_interval_seconds' => (int) env('ANNOUNCEMENT_WHATSAPP_INTERVAL', 20),
    'email_interval_seconds' => (int) env('ANNOUNCEMENT_EMAIL_INTERVAL', 2),
];
