<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Tampilkan formulir login.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Proses autentikasi menggunakan email ATAU nomor HP.
     * Dilindungi dengan rate limiting untuk mencegah brute-force.
     */
    public function login(LoginRequest $request)
    {
        $this->ensureIsNotRateLimited($request);

        $loginInput = $request->input('login');
        $password   = $request->input('password');
        $remember   = $request->boolean('remember');

        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            $authenticated = $this->attemptEmailLogin(strtolower($loginInput), $password, $remember);
        } else {
            $authenticated = $this->attemptPhoneLogin($loginInput, $password, $remember);
        }

        if ($authenticated) {
            RateLimiter::clear($this->throttleKey($request));
            $request->session()->regenerate();
            $user = Auth::user();

            $targetRoute = ($user->role === 'validator' && $user->division === 'TR')
                ? route('tr.index')
                : route('portal.dashboard');

            return redirect()->intended($targetRoute)
                ->with('success', 'Selamat datang kembali, ' . ($user->tenant_name ?? $user->name));
        }

        RateLimiter::hit($this->throttleKey($request), 300);

        throw ValidationException::withMessages([
            'login' => 'Email/Nomor HP atau kata sandi tidak cocok dengan data kami.',
        ]);
    }

    /**
     * Tampilkan formulir registrasi khusus tenant.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('auth.register');
    }

    /**
     * Proses registrasi akun tenant baru.
     * Validasi dilakukan sepenuhnya oleh RegisterRequest.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'        => $request->input('tenant_name'),
            'tenant_name' => $request->input('tenant_name'),
            'email'       => $request->input('email'),
            'phone'       => $request->input('phone'),
            'role'        => 'tenant',
            'division'    => null,
            'password'    => Hash::make($request->input('password')),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('success', 'Akun tenant berhasil didaftarkan. Selamat datang di Portal Mal Bali Galeria!');
    }

    /**
     * Logout dan alihkan ke halaman login.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('info', 'Anda telah keluar dari sesi portal.');
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function attemptEmailLogin(string $email, string $password, bool $remember): bool
    {
        return Auth::attempt(['email' => $email, 'password' => $password], $remember);
    }

    private function attemptPhoneLogin(string $phone, string $password, bool $remember): bool
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);

        $variants = array_unique(array_filter([
            $phone,
            $cleanPhone,
            str_starts_with($cleanPhone, '62') ? '0' . substr($cleanPhone, 2) : null,
            str_starts_with($cleanPhone, '0')  ? '62' . substr($cleanPhone, 1) : null,
        ]));

        $user = User::whereIn('phone', $variants)->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Kunci unik per IP + identifier untuk rate limiter.
     */
    private function throttleKey(Request $request): string
    {
        return Str::lower($request->input('login', '')) . '|' . $request->ip();
    }

    /**
     * Periksa apakah login telah melebihi batas percobaan (5x per 5 menit).
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam ' . $seconds . ' detik.',
        ]);
    }
}
