<?php

namespace App\Services;

use App\Models\WhatsappSetting;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WahaClient
{
    public function sessionStatus(WhatsappSetting $setting): array
    {
        $response = $this->request($setting)
            ->get('/api/sessions/'.rawurlencode($setting->session));

        $this->ensureSuccessful($response);

        return $response->json();
    }

    public function sendText(WhatsappSetting $setting, string $phone, string $message): array
    {
        $chatId = IndonesianPhoneNumber::chatId($phone);

        if (! $chatId) {
            throw new RuntimeException('Nomor WhatsApp tidak valid.');
        }

        $response = $this->request($setting)->post('/api/sendText', [
            'session' => $setting->session,
            'chatId' => $chatId,
            'text' => $message,
            'linkPreview' => false,
        ]);

        $this->ensureSuccessful($response);

        return $response->json() ?: [];
    }

    private function request(WhatsappSetting $setting): PendingRequest
    {
        $this->assertAllowedBaseUrl($setting->base_url);

        return Http::baseUrl(rtrim($setting->base_url, '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Api-Key' => $setting->api_key])
            ->connectTimeout(min(5, $setting->timeout))
            ->timeout($setting->timeout)
            ->withoutRedirecting();
    }

    private function assertAllowedBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = array_map('strtolower', config('waha.allowed_hosts', []));

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || $host === ''
            || ! in_array($host, $allowedHosts, true)
            || isset($parts['user'], $parts['pass'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['query'], $parts['fragment'])) {
            throw new RuntimeException('Alamat server WAHA tidak diizinkan.');
        }
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException('WAHA merespons dengan status HTTP '.$response->status().'.');
    }
}
