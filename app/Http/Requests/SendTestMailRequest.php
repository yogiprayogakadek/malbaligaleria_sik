<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient.required' => 'Alamat email tujuan wajib diisi.',
            'recipient.email' => 'Alamat email tujuan tidak valid.',
        ];
    }
}
