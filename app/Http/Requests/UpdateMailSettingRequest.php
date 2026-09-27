<?php

namespace App\Http\Requests;

use App\Models\MailSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host' => strtolower(trim((string) $this->input('host'))),
            'username' => strtolower(trim((string) $this->input('username'))),
            'from_address' => strtolower(trim((string) $this->input('from_address'))),
            'from_name' => trim((string) $this->input('from_name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:253', 'regex:/\A[a-z0-9.-]+\z/i'],
            'port' => ['required', 'integer', Rule::in([25, 465, 587, 2525])],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'username' => ['required', 'email:rfc', 'max:255'],
            'password' => [Rule::requiredIf(fn (): bool => MailSetting::current() === null), 'nullable', 'string', 'min:8', 'max:512'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
            'timeout' => ['required', 'integer', 'between:3,60'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.regex' => 'Server hanya boleh berisi nama host atau alamat IP, bukan URL.',
            'port.in' => 'Gunakan port SMTP standar: 25, 465, 587, atau 2525.',
            'password.required' => 'Kata sandi wajib diisi saat konfigurasi pertama.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $host = (string) $this->input('host');
                $isValidHost = filter_var($host, FILTER_VALIDATE_IP) !== false
                    || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

                if (! $isValidHost) {
                    $validator->errors()->add('host', 'Gunakan nama host atau alamat IP yang valid.');
                }

                $port = (int) $this->input('port');
                $scheme = $this->input('scheme');

                if (($port === 465 && $scheme !== 'smtps') || ($port !== 465 && $scheme === 'smtps')) {
                    $validator->errors()->add('scheme', 'Port 465 harus menggunakan SSL/TLS; port lainnya menggunakan SMTP/STARTTLS.');
                }
            },
        ];
    }
}
