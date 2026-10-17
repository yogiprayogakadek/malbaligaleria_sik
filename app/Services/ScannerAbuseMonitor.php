<?php

namespace App\Services;

use App\Models\PermitNotification;
use App\Models\ScannerIpBlock;
use App\Models\ScannerSetting;
use App\Models\ScannerVerificationLog;
use App\Models\User;
use App\Notifications\ScannerRiskAlertPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ScannerAbuseMonitor
{
    private const RISK_RESULTS = ['invalid', 'not_found', 'integrity_error', 'location_denied'];

    public function inspect(ScannerVerificationLog $log): void
    {
        $setting = ScannerSetting::current();
        if (! $setting->risk_monitoring_enabled || ! $log->ip_hash || ! in_array($log->result, self::RISK_RESULTS, true)) {
            return;
        }

        $count = ScannerVerificationLog::query()
            ->where('ip_hash', $log->ip_hash)
            ->whereIn('result', self::RISK_RESULTS)
            ->where('created_at', '>=', now()->subMinutes($setting->risk_window_minutes))
            ->count();

        if ($count < $setting->risk_threshold) {
            return;
        }

        $shouldAlert = false;
        $block = DB::transaction(function () use ($log, $setting, $count, &$shouldAlert): ScannerIpBlock {
            $block = ScannerIpBlock::query()->lockForUpdate()->firstOrNew(['ip_hash' => $log->ip_hash]);
            $shouldAlert = ! $block->last_alerted_at
                || $block->last_alerted_at->lte(now()->subMinutes($setting->risk_window_minutes));
            $block->fill([
                'ip_address' => $log->ip_address,
                'trigger_count' => $count,
                'last_result' => $log->result,
                'blocked_until' => $setting->auto_block_enabled
                    ? now()->addMinutes($setting->block_duration_minutes)
                    : null,
                'last_alerted_at' => $shouldAlert ? now() : $block->last_alerted_at,
                'unblocked_at' => null,
                'unblocked_by' => null,
            ])->save();

            return $block;
        });

        if ($shouldAlert && $setting->risk_alerts_enabled) {
            $this->alertAdmins($block);
        }
    }

    private function alertAdmins(ScannerIpBlock $block): void
    {
        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            PermitNotification::create([
                'user_id' => $admin->id,
                'type' => 'scanner_risk',
                'title' => 'Aktivitas scanner berisiko',
                'body' => "{$block->trigger_count} percobaan berisiko terdeteksi dari satu sumber. Periksa log scanner.",
            ]);
        }

        Notification::send($admins, new ScannerRiskAlertPush($block->id));
    }
}
