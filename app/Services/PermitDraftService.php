<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class PermitDraftService
{
    private const SESSION_PREFIX = 'permit_drafts';

    /**
     * @return array{data: array<string, mixed>, saved_at: ?string}
     */
    public function get(Request $request, string $type): array
    {
        $key = $this->key($request, $type);
        $encrypted = $request->session()->get($key);

        if (! is_string($encrypted) || $encrypted === '') {
            return ['data' => [], 'saved_at' => null];
        }

        try {
            $payload = json_decode(Crypt::decryptString($encrypted), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $request->session()->forget($key);

            return ['data' => [], 'saved_at' => null];
        }

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            $request->session()->forget($key);

            return ['data' => [], 'saved_at' => null];
        }

        return [
            'data' => $payload['data'],
            'saved_at' => is_string($payload['saved_at'] ?? null) ? $payload['saved_at'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Request $request, string $type, array $data): string
    {
        $savedAt = now()->toIso8601String();
        $payload = json_encode([
            'data' => $data,
            'saved_at' => $savedAt,
        ], JSON_THROW_ON_ERROR);

        $request->session()->put($this->key($request, $type), Crypt::encryptString($payload));

        return $savedAt;
    }

    public function clear(Request $request, string $type): void
    {
        $request->session()->forget($this->key($request, $type));
    }

    private function key(Request $request, string $type): string
    {
        $owner = $request->user()
            ? 'user.'.$request->user()->getAuthIdentifier()
            : 'guest';

        return self::SESSION_PREFIX.'.'.$type.'.'.$owner;
    }
}
