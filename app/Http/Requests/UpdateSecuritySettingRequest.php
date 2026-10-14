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
            'current_password' => ['required', 'current_password', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'staff_two_factor_required.required' => 'Pilih kebijakan autentikasi dua langkah.',
            'staff_two_factor_required.boolean' => 'Kebijakan autentikasi dua langkah tidak valid.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan perubahan.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ];
    }
}
