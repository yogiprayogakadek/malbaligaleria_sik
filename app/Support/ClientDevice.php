<?php

namespace App\Support;

class ClientDevice
{
    public static function browser(?string $userAgent): string
    {
        $agent = strtolower($userAgent ?? '');

        return match (true) {
            str_contains($agent, 'edg/') => 'Microsoft Edge',
            str_contains($agent, 'opr/') || str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'chrome/') && ! str_contains($agent, 'edg/') => 'Google Chrome',
            str_contains($agent, 'firefox/') => 'Mozilla Firefox',
            str_contains($agent, 'safari/') && ! str_contains($agent, 'chrome/') => 'Safari',
            default => 'Browser tidak dikenal',
        };
    }

    public static function device(?string $userAgent): string
    {
        $agent = strtolower($userAgent ?? '');

        return match (true) {
            str_contains($agent, 'iphone') || str_contains($agent, 'ipad') => 'Apple iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'macintosh') || str_contains($agent, 'mac os') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Perangkat tidak dikenal',
        };
    }

    public static function signature(?string $userAgent): string
    {
        return hash('sha256', self::device($userAgent).'|'.self::browser($userAgent));
    }
}
