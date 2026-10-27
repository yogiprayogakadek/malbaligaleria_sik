<?php

return [
    'max_upload_kilobytes' => 10 * 1024,
    'max_uncompressed_bytes' => 30 * 1024 * 1024,
    'circuit_breaker_seconds' => 60,
    'permit_types' => [
        'loading' => 'Loading / unloading',
        'work' => 'Surat izin kerja',
        'event' => 'Surat izin event',
    ],
    'common_placeholders' => [
        'permit_number', 'permit_title', 'applicant_name', 'applicant_phone', 'applicant_email',
        'start_date', 'end_date', 'approved_by', 'approved_at', 'verification_url',
        'validator_verification_url', 'logo', 'qr_code', 'validator_qr_code',
    ],
    'placeholders' => [
        'loading' => [
            'tenant_name', 'direction', 'movement_time', 'item_count', 'item_unit',
            'item_description', 'vehicle_plate', 'review_notes',
        ],
        'work' => [
            'contractor_name', 'tenant_name', 'work_location', 'work_category', 'work_type',
            'work_schedule', 'other_work_type', 'notes', 'needs', 'deposit_amount',
            'worker_number', 'worker_name', 'worker_identity_number', 'review_notes',
        ],
        'event' => [
            'organizer_name', 'event_type', 'work_location', 'work_category', 'work_type',
            'work_schedule', 'event_requirements', 'includes_loading', 'notes', 'review_notes',
        ],
    ],
];
