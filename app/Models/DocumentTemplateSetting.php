<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplateSetting extends Model
{
    public const RENDERERS = ['default', 'libreoffice'];

    protected $fillable = [
        'converter_enabled',
        'converter_scheme',
        'converter_host',
        'converter_port',
        'converter_token',
        'converter_timeout',
        'loading_renderer',
        'work_renderer',
        'event_renderer',
        'fallback_enabled',
        'last_tested_at',
        'last_test_succeeded',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'converter_enabled' => 'boolean',
            'converter_port' => 'integer',
            'converter_token' => 'encrypted',
            'converter_timeout' => 'integer',
            'fallback_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_test_succeeded' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? new self([
            'converter_enabled' => false,
            'converter_scheme' => 'http',
            'converter_host' => '192.168.0.249',
            'converter_port' => 3000,
            'converter_timeout' => 60,
            'loading_renderer' => 'default',
            'work_renderer' => 'default',
            'event_renderer' => 'default',
            'fallback_enabled' => true,
        ]);
    }

    public function rendererFor(string $permitType): string
    {
        return match ($permitType) {
            'loading' => $this->loading_renderer,
            'work' => $this->work_renderer,
            'event' => $this->event_renderer,
            default => 'default',
        };
    }

    public function usesLibreOfficeFor(string $permitType): bool
    {
        return $this->converter_enabled && $this->rendererFor($permitType) === 'libreoffice';
    }

    public function baseUrl(): string
    {
        return sprintf('%s://%s:%d', $this->converter_scheme, $this->converter_host, $this->converter_port);
    }
}
