<?php

namespace App\Http\Requests;

use App\Models\WhatsappSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWhatsappSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_url' => rtrim(trim((string) $this->input('base_url')), '/'),
            'session' => trim((string) $this->input('session')),
            'is_active' => $this->boolean('is_active'),
            'notify_applicants' => $this->boolean('notify_applicants'),
        ]);
    }

    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'api_key' => [Rule::requiredIf(fn (): bool => WhatsappSetting::current() === null), 'nullable', 'string', 'min:16', 'max:512'],
            'session' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]+\z/'],
            'timeout' => ['required', 'integer', 'between:3,30'],
            'is_active' => ['required', 'boolean'],
            'notify_applicants' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'api_key.required' => 'API key wajib diisi saat konfigurasi pertama.',
            'api_key.min' => 'API key minimal 16 karakter.',
            'session.regex' => 'Nama sesi hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parts = parse_url((string) $this->input('base_url'));
                $host = strtolower((string) ($parts['host'] ?? ''));
                $allowedHosts = array_map('strtolower', config('waha.allowed_hosts', []));

                if (! in_array($host, $allowedHosts, true)) {
                    $validator->errors()->add('base_url', 'Host WAHA tidak ada dalam WAHA_ALLOWED_HOSTS pada server aplikasi.');
                }

                if (isset($parts['user']) || isset($parts['pass'])
                    || ! in_array($parts['path'] ?? '', ['', '/'], true)
                    || isset($parts['query']) || isset($parts['fragment'])) {
                    $validator->errors()->add('base_url', 'Gunakan URL dasar tanpa kredensial, path, query, atau fragment.');
                }
            },
        ];
    }
}
