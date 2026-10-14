<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Services\PermitSlaMonitor;
use App\Services\SystemHealthMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RecordQueueHeartbeat)
    ->name('system-health-queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn () => app(SystemHealthMonitor::class)->run())
    ->name('system-health-monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::call(fn () => app(PermitSlaMonitor::class)->run())
    ->name('permit-sla-monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('backup:create')
    ->name('encrypted-daily-backup')
    ->dailyAt((string) config('backup.schedule', '01:30'))
    ->when(fn (): bool => (bool) config('backup.enabled'))
    ->withoutOverlapping();

Schedule::command('backup:test-restore')
    ->name('encrypted-backup-restore-test')
    ->weeklyOn(1, '03:00')
    ->when(fn (): bool => (bool) config('backup.enabled'))
    ->withoutOverlapping();
