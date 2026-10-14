<?php

namespace App\Services;

use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\SystemHealthAlertPush;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthMonitor
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function run(): void
    {
        $errors = collect($this->health->checks())->where('status', 'error')->keyBy('name');

        DB::transaction(function () use ($errors): void {
            SystemAlert::query()
                ->whereNull('resolved_at')
                ->whereNotIn('component', $errors->keys())
                ->update(['resolved_at' => now(), 'updated_at' => now()]);

            foreach ($errors as $component => $check) {
                $alert = SystemAlert::query()
                    ->where('component', $component)
                    ->whereNull('resolved_at')
                    ->first();

                if ($alert) {
                    $alert->update(['detail' => $check['detail'], 'last_detected_at' => now()]);

                    continue;
                }

                $alert = SystemAlert::query()->create([
                    'component' => $component,
                    'severity' => 'error',
                    'detail' => $check['detail'],
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]);

                User::query()->where('role', 'admin')->where('is_active', true)->each(
                    function (User $admin) use ($alert): void {
                        try {
                            $admin->notify(new SystemHealthAlertPush($alert));
                        } catch (Throwable) {
                            // The persisted alert remains visible even when a push endpoint is unavailable.
                        }
                    }
                );
            }
        });
    }
}
