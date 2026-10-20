<?php

namespace App\Services;

use App\Events\ValidatorAccountDeactivated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountStatusService
{
    public function update(User $actor, User $account, bool $isActive): User
    {
        if ($actor->is($account) && ! $isActive) {
            throw ValidationException::withMessages([
                'account' => 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.',
            ]);
        }

        $wasDeactivated = false;

        $account = DB::transaction(function () use ($account, $isActive, &$wasDeactivated): User {
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($account->getKey());

            if ($lockedAccount->isAdmin() && $lockedAccount->is_active && ! $isActive) {
                $activeAdminIds = User::query()
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($activeAdminIds->count() <= 1) {
                    throw ValidationException::withMessages([
                        'account' => 'Admin aktif terakhir tidak dapat dinonaktifkan.',
                    ]);
                }
            }

            $wasDeactivated = $lockedAccount->is_active && ! $isActive;
            $lockedAccount->forceFill([
                'is_active' => $isActive,
                'remember_token' => $isActive ? $lockedAccount->remember_token : null,
            ])->save();

            if ($wasDeactivated) {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $lockedAccount->getKey())
                    ->delete();
            }

            return $lockedAccount;
        });

        if ($wasDeactivated) {
            try {
                ValidatorAccountDeactivated::dispatch($account);
            } catch (Throwable $exception) {
                Log::warning('Sinyal logout realtime akun gagal dikirim.', [
                    'account_id' => $account->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $account;
    }
}
