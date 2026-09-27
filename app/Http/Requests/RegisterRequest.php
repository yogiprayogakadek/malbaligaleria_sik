<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_name'           => ['required', 'string', 'min:3', 'max:120'],
            'email'                 => ['required', 'string', 'email:rfc,dns', 'max:120', 'unique:users,email'],
            'phone'                 => ['required', 'string', 'min:9', 'max:20', 'unique:users,phone', 'regex:/^[\d\+\-\s\(\)]+$/'],
            'password'              => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'password_confirmation' => ['required', 'string', 'min:8'],
            'terms'                 => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_name.required' => 'Nama tenant wajib diisi.',
            'tenant_name.min'      => 'Nama tenant minimal 3 karakter.',
            'tenant_name.max'      => 'Nama tenant maksimal 120 karakter.',

            'email.required' => 'Alamat email wajib diisi.',
            'email.email'    => 'Format alamat email tidak valid.',
            'email.max'      => 'Alamat email terlalu panjang.',
            'email.unique'   => 'Alamat email sudah terdaftar. Silakan masuk.',

            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.min'      => 'Nomor HP minimal 9 digit.',
            'phone.max'      => 'Nomor HP maksimal 20 karakter.',
            'phone.unique'   => 'Nomor HP sudah terdaftar.',
            'phone.regex'    => 'Nomor HP hanya boleh berisi angka, +, -, spasi, atau tanda kurung.',

            'password.required'  => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.letters'   => 'Kata sandi harus mengandung minimal satu huruf.',
            'password.numbers'   => 'Kata sandi harus mengandung minimal satu angka.',

            'password_confirmation.required' => 'Ulangi kata sandi Anda.',
            'password_confirmation.min'      => 'Konfirmasi minimal 8 karakter.',

            'terms.accepted' => 'Anda harus menyetujui ketentuan penggunaan portal.',
        ];
    }

    /**
     * Normalisasi input sebelum validasi berjalan.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }

        if ($this->filled('phone')) {
            // Simpan hanya digit dan awalan +62 / 0
            $phone = trim($this->input('phone'));
            $this->merge(['phone' => $phone]);
        }

        if ($this->filled('tenant_name')) {
            $this->merge(['tenant_name' => trim($this->input('tenant_name'))]);
        }
    }

    /**
     * Kembalikan JSON saat request AJAX.
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
