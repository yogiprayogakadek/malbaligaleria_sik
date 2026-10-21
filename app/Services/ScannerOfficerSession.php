<?php

namespace App\Services;

use App\Models\SecurityOfficer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScannerOfficerSession
{
    private const SESSION_HOURS = 12;

    public function current(Request $request): ?SecurityOfficer
    {
        $id = $request->session()->get('scanner_officer_id');
        $authenticatedAt = (int) $request->session()->get('scanner_officer_authenticated_at', 0);
        if (! $id || $authenticatedAt < now()->subHours(self::SESSION_HOURS)->timestamp) {
            $this->forget($request);

            return null;
        }

        $officer = SecurityOfficer::query()->whereKey($id)->where('is_active', true)->first();
        $signature = (string) $request->session()->get('scanner_officer_pin_signature', '');
        $expectedSignature = $officer
            ? hash_hmac('sha256', $officer->pin_hash, (string) config('app.key'))
            : '';
        if (! $officer || ! hash_equals($expectedSignature, $signature)) {
            $this->forget($request);

            return null;
        }

        return $officer;
    }

    public function start(Request $request, SecurityOfficer $officer): void
    {
        $request->session()->migrate(true);
        $request->session()->put([
            'scanner_officer_id' => $officer->id,
            'scanner_officer_authenticated_at' => now()->timestamp,
            'scanner_officer_pin_signature' => hash_hmac('sha256', $officer->pin_hash, (string) config('app.key')),
        ]);
        $officer->forceFill(['last_authenticated_at' => now()])->save();
    }

    public function end(Request $request): void
    {
        $this->forget($request);
        $request->session()->migrate(true);
    }

    public function issueChallenge(Request $request, SecurityOfficer $officer, string $token): string
    {
        $challenge = Str::random(64);
        $request->session()->put('scanner_gate_challenge', [
            'hash' => hash('sha256', $challenge),
            'token_hash' => hash('sha256', $token),
            'officer_id' => $officer->id,
            'expires_at' => now()->addMinutes(3)->timestamp,
        ]);

        return $challenge;
    }

    public function consumeChallenge(Request $request, SecurityOfficer $officer, string $token, string $challenge): bool
    {
        $stored = $request->session()->pull('scanner_gate_challenge');

        return is_array($stored)
            && ($stored['expires_at'] ?? 0) >= now()->timestamp
            && (int) ($stored['officer_id'] ?? 0) === $officer->id
            && hash_equals((string) ($stored['token_hash'] ?? ''), hash('sha256', $token))
            && hash_equals((string) ($stored['hash'] ?? ''), hash('sha256', $challenge));
    }

    private function forget(Request $request): void
    {
        $request->session()->forget([
            'scanner_officer_id',
            'scanner_officer_authenticated_at',
            'scanner_officer_pin_signature',
            'scanner_gate_challenge',
        ]);
    }
}
