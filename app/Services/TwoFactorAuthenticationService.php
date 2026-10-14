<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            'Mal Bali Galeria',
            $user->email,
            $secret,
        );
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->verifyTimestamp($secret, $code) !== false;
    }

    public function verifyTimestamp(string $secret, string $code, ?int $lastUsedStep = null): int|false
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $result = $this->google2fa->verifyKey(
            $secret,
            $code,
            1,
            null,
            $lastUsedStep ?? 0,
        );

        return is_int($result) ? $result : false;
    }

    public function verifyForUser(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $step = $this->verifyTimestamp(
                $lockedUser->two_factor_secret,
                $code,
                $lockedUser->two_factor_last_used_step,
            );

            if ($step === false) {
                return false;
            }

            $lockedUser->forceFill(['two_factor_last_used_step' => $step])->saveQuietly();

            return true;
        });
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))->map(function (): string {
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $value = '';

            for ($index = 0; $index < 10; $index++) {
                $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return substr($value, 0, 5).'-'.substr($value, 5);
        })->all();
    }

    /** @param array<int, string> $codes */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code): string => $this->recoveryHash($code), $codes);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $hash = $this->recoveryHash($code);
            $codes = $lockedUser->two_factor_recovery_codes ?? [];
            $index = array_search($hash, $codes, true);

            if ($index === false) {
                return false;
            }

            unset($codes[$index]);
            $lockedUser->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

            return true;
        });
    }

    private function recoveryHash(string $code): string
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($code)));

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
