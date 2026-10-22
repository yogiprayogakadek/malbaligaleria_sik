<?php

namespace App\Services;

use App\Models\DocumentTemplateSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LibreOfficeConverterClient
{
    public function __construct(private readonly DocumentConverterAddress $address) {}

    /** @return array<string, mixed> */
    public function health(DocumentTemplateSetting $setting): array
    {
        $response = $this->request($setting)
            ->acceptJson()
            ->get($setting->baseUrl().'/health');

        if (! $response->successful() || $response->json('status') !== 'ok') {
            throw new RuntimeException('Layanan converter merespons tidak sehat.');
        }

        return $response->json();
    }

    public function convert(string $path, string $filename, DocumentTemplateSetting $setting): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Dokumen sementara tidak dapat dibaca.');
        }

        try {
            $response = $this->request($setting)
                ->accept('application/pdf')
                ->attach('document', $handle, $filename)
                ->post($setting->baseUrl().'/convert');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Layanan converter tidak dapat dihubungi.', previous: $exception);
        } finally {
            fclose($handle);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Konversi DOCX gagal dengan status HTTP '.$response->status().'.');
        }

        $content = $response->body();
        if (! str_starts_with($content, '%PDF-') || strlen($content) > 30 * 1024 * 1024) {
            throw new RuntimeException('Converter tidak mengembalikan PDF yang valid.');
        }

        return $content;
    }

    private function request(DocumentTemplateSetting $setting): PendingRequest
    {
        $this->address->validate($setting->converter_host);
        if (blank($setting->converter_token)) {
            throw new RuntimeException('Token converter belum dikonfigurasi.');
        }

        return Http::withToken($setting->converter_token)
            ->connectTimeout(3)
            ->timeout($setting->converter_timeout)
            ->retry(1, 200, throw: false)
            ->withoutRedirecting();
    }
}
