<?php

namespace App\Services;

use App\Models\SecuritySetting;
use App\Models\StaffLoginEvent;
use App\Models\TrustedLoginDevice;
use App\Models\User;
use App\Notifications\StaffLoginSecurityAlert;
use App\Support\ClientDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StaffLoginSecurityService
{
    public function findStaff(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($login)])
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'validator', 'secretary'])
                ->first();
        }

        $phone = preg_replace('/\D/', '', $login);
        $variants = array_unique(array_filter([
            $login,
            $phone,
            str_starts_with($phone, '62') ? '0'.substr($phone, 2) : null,
            str_starts_with($phone, '0') ? '62'.substr($phone, 1) : null,
        ]));

        return User::query()
            ->whereIn('phone', $variants)
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'validator', 'secretary'])
            ->first();
    }

    public function recordFailedAttempt(Request $request, User $user, int $attempts): void
    {
        try {
            $this->storeFailedAttempt($request, $user, $attempts);
        } catch (Throwable $exception) {
            $this->logStorageFailure($request, $user, 'failed_attempt', $exception);
        }
    }

    public function completeLogin(Request $request, User $user): void
    {
        try {
            $this->storeCompletedLogin($request, $user);
        } catch (Throwable $exception) {
            $this->logStorageFailure($request, $user, 'completed_login', $exception);
        }
    }

    private function storeFailedAttempt(Request $request, User $user, int $attempts): void
    {
        $setting = SecuritySetting::current();
        if ($attempts !== $setting->failed_login_alert_threshold) {
            return;
        }

        $event = $this->createEvent($request, $user, StaffLoginEvent::FAILED_ATTEMPTS, $attempts);
        if ($setting->login_alerts_enabled) {
            $this->notify($event, true);
        }
    }

    private function storeCompletedLogin(Request $request, User $user): void
    {
        $cookieName = (string) config('login-security.device_cookie');
        $rawToken = (string) $request->cookie($cookieName, '');
        $tokenIsValid = preg_match('/^[A-Za-z0-9]{64}$/', $rawToken) === 1;
        $signature = ClientDevice::signature($request->userAgent());

        $device = $tokenIsValid
            ? TrustedLoginDevice::query()
                ->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $rawToken))
                ->where('client_signature', $signature)
                ->first()
            : null;

        $isNewDevice = $device === null;
        if ($isNewDevice) {
            $rawToken = Str::random(64);
            $device = TrustedLoginDevice::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $rawToken),
                'client_signature' => $signature,
                'device_label' => ClientDevice::device($request->userAgent()),
                'browser_label' => ClientDevice::browser($request->userAgent()),
                'last_ip' => $request->ip(),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            Cookie::queue(Cookie::make(
                $cookieName,
                $rawToken,
                max(1, (int) config('login-security.device_cookie_days', 365)) * 1440,
                '/',
                config('session.domain'),
                (bool) (config('session.secure') ?? $request->isSecure()),
                true,
                false,
                'strict',
            ));
        } else {
            $device->forceFill([
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
            ])->save();
        }

        $event = $this->createEvent(
            $request,
            $user,
            $isNewDevice ? StaffLoginEvent::NEW_DEVICE : StaffLoginEvent::LOGIN_SUCCESS,
        );

        if ($isNewDevice && SecuritySetting::current()->login_alerts_enabled) {
            $this->notify($event, false);
        }
    }

    private function createEvent(Request $request, User $user, string $event, ?int $attempts = null): StaffLoginEvent
    {
        return StaffLoginEvent::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'device_label' => ClientDevice::device($request->userAgent()),
            'browser_label' => ClientDevice::browser($request->userAgent()),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'attempt_count' => $attempts,
            'occurred_at' => now(),
        ]);
    }

    private function notify(StaffLoginEvent $event, bool $includeAdmins): void
    {
        User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($event, $includeAdmins): void {
                $query->whereKey($event->user_id);
                if ($includeAdmins && $event->user?->isStaff()) {
                    $query->orWhere('role', 'admin');
                }
            })
            ->get()
            ->unique('id')
            ->each(function (User $recipient) use ($event): void {
                try {
                    $recipient->notify(new StaffLoginSecurityAlert($event->id));
                } catch (Throwable $exception) {
                    Log::warning('Notifikasi keamanan login gagal dimasukkan ke antrean.', [
                        'event_id' => $event->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
    }

    private function logStorageFailure(Request $request, User $user, string $stage, Throwable $exception): void
    {
        Log::error('Pencatatan keamanan login gagal.', [
            'stage' => $stage,
            'user_id' => $user->id,
            'request_id' => $request->attributes->get('request_id'),
            'exception' => $exception::class,
        ]);
    }
}
