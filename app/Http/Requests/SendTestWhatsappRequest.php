<?php

namespace App\Http\Requests;

use App\Support\IndonesianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendTestWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! IndonesianPhoneNumber::normalize($this->input('recipient'))) {
                    $validator->errors()->add('recipient', 'Gunakan nomor Indonesia yang valid, misalnya 081234567890.');
                }
            },
        ];
    }
}
