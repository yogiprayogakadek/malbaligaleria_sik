<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventWhatsappGroupLimiterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'group_sync_max_attempts' => ['required', 'integer', 'between:1,30'],
            'group_sync_decay_seconds' => ['required', 'integer', 'between:10,3600'],
            'outbound_max_attempts' => ['required', 'integer', 'between:1,10'],
            'outbound_decay_seconds' => ['required', 'integer', 'between:60,86400'],
            'current_password' => ['required', 'current_password', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_sync_max_attempts.between' => 'Batas sinkronisasi harus antara 1 sampai 30 percobaan.',
            'group_sync_decay_seconds.between' => 'Periode sinkronisasi harus antara 10 sampai 3.600 detik.',
            'outbound_max_attempts.between' => 'Batas outbound-only harus antara 1 sampai 10 percobaan.',
            'outbound_decay_seconds.between' => 'Periode outbound-only harus antara 60 sampai 86.400 detik.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan limiter.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ];
    }
}
