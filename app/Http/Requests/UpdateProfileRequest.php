<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\IndonesianPhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'tenant_name' => [$this->user()->isTenant() ? 'required' : 'nullable', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:120', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => [
                'required',
                'string',
                'max:25',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $normalized = IndonesianPhoneNumber::normalize((string) $value);
                    if (! $normalized) {
                        $fail('Nomor HP Indonesia tidak valid.');

                        return;
                    }

                    $duplicate = User::query()
                        ->whereKeyNot($this->user()->id)
                        ->whereNotNull('phone')
                        ->get(['id', 'phone'])
                        ->contains(fn ($user): bool => IndonesianPhoneNumber::normalize($user->phone) === $normalized);
                    if ($duplicate) {
                        $fail('Nomor HP sudah digunakan akun lain.');
                    }
                },
            ],
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'tenant_name.required' => 'Nama tenant atau toko wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'tenant_name' => trim((string) $this->input('tenant_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
