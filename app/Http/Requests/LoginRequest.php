<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'min:5', 'max:120'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Alamat email atau nomor HP wajib diisi.',
            'login.min'      => 'Input terlalu pendek, minimal 5 karakter.',
            'login.max'      => 'Input terlalu panjang, maksimal 120 karakter.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min'      => 'Kata sandi minimal 6 karakter.',
            'password.max'      => 'Kata sandi terlalu panjang.',
        ];
    }

    /**
     * Sanitize input sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('login')) {
            $login = trim($this->input('login'));

            // Jika terlihat seperti nomor telepon, strip karakter non-digit kecuali awalan +
            if (! filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $login = preg_replace('/[^\d+]/', '', $login);
            }

            $this->merge(['login' => $login]);
        }
    }

    /**
     * Kembalikan JSON saat request Accept: application/json (AJAX).
     */
    protected function failedValidation(Validator $validator): never
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Validasi gagal.',
                    'errors'  => $validator->errors(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}
