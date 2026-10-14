<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'temporary_path' => storage_path('app/backup-tmp'),
    'restore_path' => storage_path('app/restore-staging'),
    'documents_path' => storage_path('app/private'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'schedule' => env('BACKUP_SCHEDULE', '01:30'),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
];
