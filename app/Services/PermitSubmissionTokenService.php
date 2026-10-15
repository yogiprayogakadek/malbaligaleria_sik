<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermitSubmissionTokenService
{
    private const TTL_SECONDS = 7200;

    private const MAX_TOKENS_PER_FORM = 8;

    public function issue(Request $request, string $permitType): string
    {
        $token = (string) Str::uuid();
        $tokens = $this->activeTokens($request, $permitType);
        $tokens[$this->hash($token)] = now()->timestamp;
        $tokens = array_slice($tokens, -self::MAX_TOKENS_PER_FORM, null, true);

        $request->session()->put($this->sessionKey($permitType), $tokens);

        return $token;
    }

    public function isValid(Request $request, string $permitType, mixed $token): bool
    {
        if (! is_string($token) || ! Str::isUuid($token)) {
            return false;
        }

        $tokens = $this->activeTokens($request, $permitType);
        $hash = $this->hash($token);
        $request->session()->put($this->sessionKey($permitType), $tokens);

        return array_key_exists($hash, $tokens);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, int>
     */
    private function activeTokens(Request $request, string $permitType): array
    {
        $cutoff = now()->timestamp - self::TTL_SECONDS;

        return collect($request->session()->get($this->sessionKey($permitType), []))
            ->filter(fn (mixed $issuedAt, mixed $hash): bool => is_string($hash)
                && is_numeric($issuedAt)
                && (int) $issuedAt >= $cutoff)
            ->map(fn (mixed $issuedAt): int => (int) $issuedAt)
            ->all();
    }

    private function sessionKey(string $permitType): string
    {
        return "permit_submission_tokens.{$permitType}";
    }
}
