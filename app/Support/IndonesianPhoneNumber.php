<?php

namespace App\Support;

class IndonesianPhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (! preg_match('/\A62[1-9][0-9]{7,12}\z/', $digits)) {
            return null;
        }

        return $digits;
    }

    public static function chatId(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized ? $normalized.'@c.us' : null;
    }

    public static function mask(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return '-';
        }

        return substr($normalized, 0, 4).str_repeat('*', max(4, strlen($normalized) - 8)).substr($normalized, -4);
    }
}
