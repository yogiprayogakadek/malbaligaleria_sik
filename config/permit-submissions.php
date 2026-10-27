<?php

return [
    'slow_request_ms' => (int) env('PERMIT_SUBMISSION_SLOW_MS', 3000),
    'slow_malware_scan_ms' => (int) env('MALWARE_SLOW_SCAN_MS', 2000),
];
