<?php

namespace App\Services;

use App\Models\MonitoringSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MonitoringWebhookService
{
    public function send(string $event, array $payload): void
    {
        $setting = MonitoringSetting::current();
        if (! $setting->enabled || ! $setting->webhook_url) {
            return;
        }

        $host = parse_url($setting->webhook_url, PHP_URL_HOST);
        $scheme = parse_url($setting->webhook_url, PHP_URL_SCHEME);
        if ($scheme !== 'https' || ! is_string($host) || ! in_array(strtolower($host), config('monitoring.allowed_hosts', []), true)) {
            throw new RuntimeException('Host webhook monitoring tidak diizinkan.');
        }

        $body = json_encode([
            'event' => $event,
            'application' => config('app.name'),
            'occurred_at' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) $setting->signing_secret);

        Http::timeout($setting->timeout_seconds)
            ->withHeaders(['X-MBG-Signature' => 'sha256='.$signature, 'Content-Type' => 'application/json'])
            ->withBody($body, 'application/json')
            ->post($setting->webhook_url)
            ->throw();
    }
}
