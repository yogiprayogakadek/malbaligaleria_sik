<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRetentionSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'identity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'assignment_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'financial_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'current_password' => ['required', 'current_password', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required' => 'Pilih status penghapusan otomatis.',
            'enabled.boolean' => 'Status penghapusan otomatis tidak valid.',
            '*.required' => 'Field ini wajib diisi.',
            '*.integer' => 'Masa simpan harus berupa jumlah hari.',
            '*.min' => 'Masa simpan minimal 1 hari.',
            '*.max' => 'Masa simpan maksimal 3.650 hari.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan perubahan.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ];
    }
}
