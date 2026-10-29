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

    /** @return array<int, array{id: string, name: string}> */
    public function groups(WhatsappSetting $setting): array
    {
        $response = $this->request($setting)->get(
            '/api/'.rawurlencode($setting->session).'/groups',
            [
                'limit' => 200,
                'offset' => 0,
                'sortBy' => 'subject',
                'sortOrder' => 'asc',
                'exclude' => 'participants',
            ],
        );

        $this->ensureSuccessful($response);
        $payload = $response->json();
        $items = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (! is_array($items)) {
            throw new RuntimeException('Daftar grup dari WAHA tidak dapat dibaca.');
        }

        return collect($items)
            ->map(function ($group): ?array {
                if (! is_array($group)) {
                    return null;
                }

                $rawId = $group['id'] ?? null;
                $id = is_array($rawId)
                    ? ($rawId['_serialized'] ?? $rawId['id'] ?? null)
                    : $rawId;

                if (! is_string($id) || preg_match('/^[0-9-]+@g\.us$/', $id) !== 1) {
                    return null;
                }

                $name = trim((string) ($group['subject'] ?? $group['name'] ?? $group['title'] ?? ''));

                return [
                    'id' => $id,
                    'name' => $name !== '' ? $name : 'Grup tanpa nama',
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy(fn (array $group): string => mb_strtolower($group['name']))
            ->values()
            ->all();
    }

    public function enableOutboundOnly(WhatsappSetting $setting): array
    {
        $session = $this->sessionStatus($setting);
        $config = $session['config'] ?? null;

        if (! is_array($config)) {
            throw new RuntimeException('Konfigurasi sesi WAHA tidak tersedia sehingga perubahan dibatalkan.');
        }

        $ignore = is_array($config['ignore'] ?? null) ? $config['ignore'] : [];
        $ignore['groups'] = true;
        $config['ignore'] = $ignore;

        $response = $this->request($setting)
            ->put('/api/sessions/'.rawurlencode($setting->session), [
                'name' => $setting->session,
                'config' => $config,
            ]);

        $this->ensureSuccessful($response);

        return $response->json() ?: [];
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

    /** @param array<int, string> $mentions */
    public function sendGroupText(WhatsappSetting $setting, string $groupId, string $message, array $mentions = []): array
    {
        $this->assertGroupId($groupId);
        $mentions = array_values(array_unique(array_filter($mentions, fn (string $jid): bool => preg_match('/^\d+@c\.us$/', $jid) === 1)));

        $response = $this->request($setting)->post('/api/sendText', [
            'session' => $setting->session,
            'chatId' => $groupId,
            'text' => $message,
            'mentions' => $mentions,
            'linkPreview' => false,
        ]);

        $this->ensureSuccessful($response);

        return $response->json() ?: [];
    }

    public function sendGroupFile(WhatsappSetting $setting, string $groupId, string $filename, string $contents, string $caption): array
    {
        $this->assertGroupId($groupId);
        if (strlen($contents) > 10 * 1024 * 1024) {
            throw new RuntimeException('Dokumen WhatsApp melebihi batas 10 MB.');
        }

        $response = $this->request($setting)->post('/api/sendFile', [
            'session' => $setting->session,
            'chatId' => $groupId,
            'caption' => $caption,
            'file' => [
                'mimetype' => 'application/pdf',
                'filename' => $filename,
                'data' => base64_encode($contents),
            ],
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

    private function assertGroupId(string $groupId): void
    {
        if (preg_match('/^[0-9-]+@g\.us$/', $groupId) !== 1) {
            throw new RuntimeException('ID grup WhatsApp tidak valid.');
        }
    }
}
