<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailSettingsConfigurator
{
    public function apply(bool $purgeMailer = false): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }

            $setting = MailSetting::current();

            if (! $setting?->is_active) {
                $this->applyFallback($purgeMailer);

                return;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.url' => null,
                'mail.mailers.smtp.scheme' => $setting->scheme,
                'mail.mailers.smtp.host' => $setting->host,
                'mail.mailers.smtp.port' => $setting->port,
                'mail.mailers.smtp.username' => $setting->username,
                'mail.mailers.smtp.password' => $setting->password,
                'mail.mailers.smtp.timeout' => $setting->timeout,
                'mail.from.address' => $setting->from_address,
                'mail.from.name' => $setting->from_name,
            ]);

            if ($purgeMailer) {
                Mail::purge('smtp');
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function applyFallback(bool $purgeMailer): void
    {
        $fallback = config('mail.managed_fallback');

        config([
            'mail.default' => $fallback['default'],
            'mail.mailers.smtp.url' => $fallback['smtp']['url'],
            'mail.mailers.smtp.scheme' => $fallback['smtp']['scheme'],
            'mail.mailers.smtp.host' => $fallback['smtp']['host'],
            'mail.mailers.smtp.port' => $fallback['smtp']['port'],
            'mail.mailers.smtp.username' => $fallback['smtp']['username'],
            'mail.mailers.smtp.password' => $fallback['smtp']['password'],
            'mail.mailers.smtp.timeout' => $fallback['smtp']['timeout'],
            'mail.from.address' => $fallback['from']['address'],
            'mail.from.name' => $fallback['from']['name'],
        ]);

        if ($purgeMailer) {
            Mail::purge('smtp');
        }
    }
}
