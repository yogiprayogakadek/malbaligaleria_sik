<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Models\DocumentRetentionSetting;
use App\Models\OperationTrace;
use App\Models\WebPushMessageLog;
use App\Services\FormPresenceService;
use App\Services\PermitSlaMonitor;
use App\Services\SystemHealthMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => Cache::put('system-health:scheduler', now()->toIso8601String(), now()->addMinutes(10)))
    ->name('system-health-scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

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

Schedule::command('permits:purge-sensitive-documents')
    ->name('sensitive-document-retention')
    ->dailyAt('02:30')
    ->when(fn (): bool => DocumentRetentionSetting::current()->enabled)
    ->withoutOverlapping();

Schedule::call(fn () => OperationTrace::query()->where('started_at', '<', now()->subDays(30))->delete())
    ->name('operation-trace-retention')
    ->dailyAt('02:45')
    ->withoutOverlapping();

Schedule::call(fn () => WebPushMessageLog::query()
    ->where('created_at', '<', now()->subDays(90))
    ->whereNotIn('status', ['processing', 'retrying'])
    ->delete())
    ->name('web-push-log-retention')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::call(fn () => app(FormPresenceService::class)->prune())
    ->name('form-presence-prune')
    ->everyMinute()
    ->withoutOverlapping();
