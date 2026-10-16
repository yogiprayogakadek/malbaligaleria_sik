<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SecuritySetting;
use App\Models\StaffLoginEvent;
use App\Support\ClientDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller
{
    public function index(Request $request)
    {
        $sessions = collect();
        $sessionManagementAvailable = config('session.driver') === 'database';

        if ($sessionManagementAvailable) {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn (object $session): array => [
                    'id' => $session->id,
                    'is_current' => hash_equals($request->session()->getId(), $session->id),
                    'device' => ClientDevice::device($session->user_agent),
                    'browser' => ClientDevice::browser($session->user_agent),
                    'ip_address' => $session->ip_address ?: 'Tidak tersedia',
                    'last_active_at' => now()->setTimestamp($session->last_activity),
                ]);
        }

        $staffTwoFactorRequired = SecuritySetting::requiresStaffTwoFactor();
        $loginEvents = $request->user()->isStaff()
            ? StaffLoginEvent::query()
                ->where('user_id', $request->user()->id)
                ->latest('occurred_at')
                ->limit((int) config('login-security.history_limit', 20))
                ->get()
            : collect();

        return view('account.security', compact('sessions', 'sessionManagementAvailable', 'staffTwoFactorRequired', 'loginEvents'));
    }

    public function destroy(Request $request, string $sessionId)
    {
        $this->ensureDatabaseSessions();
        $this->validatePassword($request);

        if (hash_equals($request->session()->getId(), $sessionId)) {
            return back()->withErrors(['session' => 'Sesi yang sedang digunakan harus diakhiri melalui tombol Keluar.']);
        }

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted > 0) {
            $this->recordAudit($request, 'session_revoked', 1);
        }

        return back()->with('success', $deleted > 0 ? 'Sesi perangkat berhasil dicabut.' : 'Sesi sudah tidak aktif.');
    }

    public function destroyOthers(Request $request)
    {
        $this->ensureDatabaseSessions();
        $this->validatePassword($request);

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->recordAudit($request, 'other_sessions_revoked', $deleted);

        return back()->with('success', $deleted > 0
            ? "{$deleted} sesi perangkat lain berhasil dicabut."
            : 'Tidak ada sesi perangkat lain yang perlu dicabut.');
    }

    private function validatePassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'string', 'max:255']]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }
    }

    private function ensureDatabaseSessions(): void
    {
        abort_unless(config('session.driver') === 'database', 409, 'Pengelolaan sesi memerlukan SESSION_DRIVER=database.');
    }

    private function recordAudit(Request $request, string $event, int $revokedCount): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'event' => $event,
            'auditable_type' => 'account_security',
            'auditable_id' => $request->user()->id,
            'route_name' => $request->route()?->getName(),
            'request_method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['revoked_count' => $revokedCount],
        ]);
    }
}
