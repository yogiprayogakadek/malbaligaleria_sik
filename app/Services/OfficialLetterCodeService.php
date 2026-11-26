<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\OfficialLetterCode;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfficialLetterCodeService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function issue(LoadingPermit|WorkPermit $permit, string $documentId): OfficialLetterCode
    {
        $type = $permit->officialLetterCodeType();
        $activeKey = $type.':'.$permit->getKey();
        $existing = OfficialLetterCode::query()->where('active_permit_key', $activeKey)->first();
        if ($existing) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = $this->randomCode();
            $now = now();
            $inserted = DB::table('official_letter_codes')->insertOrIgnore([
                'code' => $code,
                'permit_type' => $type,
                'permit_id' => $permit->getKey(),
                'document_id' => $documentId,
                'active_permit_key' => $activeKey,
                'is_active' => true,
                'issued_at' => $now,
                'retired_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 1) {
                return OfficialLetterCode::query()->where('code', $code)->firstOrFail();
            }

            $existing = OfficialLetterCode::query()->where('active_permit_key', $activeKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        throw new RuntimeException('Kode Surat unik gagal dibuat.');
    }

    public function rotate(LoadingPermit|WorkPermit $permit, string $documentId): OfficialLetterCode
    {
        OfficialLetterCode::query()
            ->where('permit_type', $permit->officialLetterCodeType())
            ->where('permit_id', $permit->getKey())
            ->where('is_active', true)
            ->update([
                'active_permit_key' => null,
                'is_active' => false,
                'retired_at' => now(),
                'updated_at' => now(),
            ]);

        $permit->unsetRelation('activeLetterCode');

        return $this->issue($permit, $documentId);
    }

    private function randomCode(): string
    {
        $code = '';
        $lastIndex = strlen(self::ALPHABET) - 1;
        for ($index = 0; $index < 8; $index++) {
            $code .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $code;
    }
}
