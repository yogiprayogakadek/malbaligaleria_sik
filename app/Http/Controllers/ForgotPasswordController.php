<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use App\Services\WhatsappNotificationDispatcher;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, WhatsappNotificationDispatcher $whatsapp): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [
            'identifier.required' => 'Email atau nomor HP wajib diisi.',
            'identifier.max' => 'Email atau nomor HP terlalu panjang.',
        ]);

        $identifier = trim($validated['identifier']);
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $user = $isEmail
            ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->where('is_active', true)->first()
            : $this->findByPhone($identifier);

        if ($user) {
            try {
                $token = Password::broker()->createToken($user);
                $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

                if ($isEmail) {
                    $user->notify(new PasswordResetLinkNotification($url));
                } else {
                    $whatsapp->passwordReset($user, $url);
                }
            } catch (Throwable $exception) {
                Log::warning('Pengiriman tautan pemulihan akun gagal.', [
                    'user_id' => $user->id,
                    'channel' => $isEmail ? 'email' : 'whatsapp',
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Jika data terdaftar dan akun masih aktif, tautan pengaturan ulang kata sandi akan dikirim melalui kanal yang dipilih. Periksa juga folder spam untuk email.');
    }

    private function findByPhone(string $phone): ?User
    {
        $normalized = IndonesianPhoneNumber::normalize($phone);
        if (! $normalized) {
            return null;
        }

        $national = '0'.substr($normalized, 2);
        $local = substr($normalized, 2);

        $user = User::query()
            ->where('is_active', true)
            ->whereIn('phone', array_unique([$normalized, '+'.$normalized, $national, $local]))
            ->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $candidate): bool => IndonesianPhoneNumber::normalize($candidate->phone) === $normalized);
    }
}
