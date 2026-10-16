<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'staff_two_factor_required' => ['required', 'boolean'],
            'login_alerts_enabled' => ['required', 'boolean'],
            'failed_login_alert_threshold' => ['required', 'integer', 'between:3,5'],
            'current_password' => ['required', 'current_password', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'staff_two_factor_required.required' => 'Pilih kebijakan autentikasi dua langkah.',
            'staff_two_factor_required.boolean' => 'Kebijakan autentikasi dua langkah tidak valid.',
            'login_alerts_enabled.required' => 'Pilih kebijakan peringatan login.',
            'login_alerts_enabled.boolean' => 'Kebijakan peringatan login tidak valid.',
            'failed_login_alert_threshold.required' => 'Pilih batas percobaan login gagal.',
            'failed_login_alert_threshold.between' => 'Batas percobaan login gagal harus antara 3 sampai 5 kali.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan perubahan.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ];
    }
}
