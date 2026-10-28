<?php

namespace App\Services;

use App\Events\ValidatorAccountDeactivated;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function delete(User $actor, User $account): void
    {
        if ($actor->is($account)) {
            throw ValidationException::withMessages([
                'account' => 'Anda tidak dapat menghapus akun yang sedang digunakan.',
            ]);
        }

        $deletedAccount = DB::transaction(function () use ($account): User {
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($account->getKey());

            if ($lockedAccount->is_active) {
                throw ValidationException::withMessages([
                    'account' => 'Nonaktifkan akun terlebih dahulu sebelum menghapusnya permanen.',
                ]);
            }

            if ($lockedAccount->isAdmin()) {
                $adminIds = User::query()->where('role', 'admin')->lockForUpdate()->pluck('id');
                if ($adminIds->count() <= 1) {
                    throw ValidationException::withMessages([
                        'account' => 'Admin terakhir tidak dapat dihapus.',
                    ]);
                }
            }

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $lockedAccount->getKey())
                ->delete();

            try {
                $lockedAccount->delete();
            } catch (QueryException $exception) {
                Log::warning('Penghapusan akun ditolak karena masih memiliki relasi data.', [
                    'account_id' => $lockedAccount->getKey(),
                    'sql_state' => $exception->getCode(),
                ]);

                throw ValidationException::withMessages([
                    'account' => 'Akun memiliki riwayat yang wajib dipertahankan. Nonaktifkan akun sebagai pengganti penghapusan.',
                ]);
            }

            return $lockedAccount;
        });

        try {
            ValidatorAccountDeactivated::dispatch($deletedAccount);
        } catch (Throwable $exception) {
            Log::warning('Sinyal logout realtime akun yang dihapus gagal dikirim.', [
                'account_id' => $deletedAccount->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
