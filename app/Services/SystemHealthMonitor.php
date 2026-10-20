<?php

namespace App\Services;

use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\SystemHealthAlertPush;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthMonitor
{
    private readonly MonitoringWebhookService $webhook;

    private readonly MessageTemplateRenderer $templates;

    public function __construct(
        private readonly SystemHealthService $health,
        ?MonitoringWebhookService $webhook = null,
        ?MessageTemplateRenderer $templates = null,
    ) {
        $this->webhook = $webhook ?? app(MonitoringWebhookService::class);
        $this->templates = $templates ?? app(MessageTemplateRenderer::class);
    }

    public function run(): void
    {
        $errors = collect($this->health->checks())->where('status', 'error')->keyBy('name');

        $newAlerts = DB::transaction(function () use ($errors) {
            $created = collect();
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
                $created->push($alert);
            }

            return $created;
        });

        foreach ($newAlerts as $alert) {
            User::query()->where('role', 'admin')->where('is_active', true)->each(
                function (User $admin) use ($alert): void {
                    try {
                        $admin->notify(new SystemHealthAlertPush($alert));
                    } catch (Throwable) {
                        // The persisted alert remains visible even when a push endpoint is unavailable.
                    }
                }
            );

            try {
                $message = $this->templates->render('system_health_alert', [
                    'component' => $alert->component,
                    'detail' => $alert->detail,
                    'detected_at' => $alert->last_detected_at->toIso8601String(),
                ], "Gangguan {$alert->component}", $alert->detail);
                $this->webhook->send('system.health.error', [
                    'component' => $alert->component,
                    'severity' => $alert->severity,
                    'title' => $message['subject'],
                    'detail' => $message['body'],
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
