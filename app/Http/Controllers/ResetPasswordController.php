<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TrustedLoginDevice;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\WhatsappNotificationDispatcher;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request, WhatsappNotificationDispatcher $whatsapp): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'email.required' => 'Email akun tidak tersedia pada tautan.',
            'email.email' => 'Format email akun tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.letters' => 'Kata sandi harus mengandung minimal satu huruf.',
            'password.numbers' => 'Kata sandi harus mengandung minimal satu angka.',
        ]);

        $resetUser = null;
        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                ])->save();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }

                TrustedLoginDevice::query()->where('user_id', $user->id)->delete();
                $user->pushSubscriptions()->delete();

                event(new PasswordReset($user));
                $resetUser = $user;
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            AuditLog::query()->create([
                'actor_id' => null,
                'event' => 'password_reset_completed',
                'auditable_type' => User::class,
                'auditable_id' => $resetUser?->id,
                'route_name' => $request->route()?->getName(),
                'request_method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => ['sessions_revoked' => true, 'trusted_devices_revoked' => true],
            ]);
            $resetUser?->notify(new PasswordChangedNotification);
            if ($resetUser) {
                $whatsapp->passwordChanged($resetUser);
            }

            return redirect()->route('login')->with('success', 'Kata sandi berhasil diperbarui. Silakan masuk kembali.');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => 'Tautan tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
        ]);
    }
}
