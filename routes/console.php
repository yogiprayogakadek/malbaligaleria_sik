<?php

use App\Jobs\RecordQueueHeartbeat;
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
