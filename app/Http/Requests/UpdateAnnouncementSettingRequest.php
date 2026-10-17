<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'max_attempts' => ['required', 'integer', 'min:1', 'max:30'],
            'decay_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'current_password' => ['required', 'current_password', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_attempts.required' => 'Jumlah percobaan wajib diisi.',
            'max_attempts.integer' => 'Jumlah percobaan harus berupa angka bulat.',
            'max_attempts.min' => 'Jumlah percobaan minimal 1.',
            'max_attempts.max' => 'Jumlah percobaan maksimal 30.',
            'decay_seconds.required' => 'Periode limiter wajib diisi.',
            'decay_seconds.integer' => 'Periode limiter harus berupa angka bulat.',
            'decay_seconds.min' => 'Periode limiter minimal 10 detik.',
            'decay_seconds.max' => 'Periode limiter maksimal 3.600 detik.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan pengaturan.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ];
    }
}
