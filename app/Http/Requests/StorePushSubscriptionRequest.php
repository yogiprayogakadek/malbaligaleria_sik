<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isAdmin() || ($user->isValidator() && $user->division === 'TR'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:1024', 'url'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'min:40', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'keys.auth' => ['required', 'string', 'min:8', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'content_encoding' => ['required', 'string', 'in:aes128gcm,aesgcm'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $endpoint = $this->string('endpoint')->toString();

                if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || ! parse_url($endpoint, PHP_URL_HOST)) {
                    $validator->errors()->add('endpoint', 'Endpoint push harus menggunakan HTTPS yang valid.');
                }
            },
        ];
    }
}
