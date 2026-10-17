<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnnouncementCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'title' => ['required', 'string', 'min:4', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', Rule::in(['whatsapp', 'email'])],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => ['required', Rule::in(['accounts', 'applicants'])],
            'current_password' => ['required', 'string', 'max:255'],
            'authorized_contact' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! Hash::check((string) $this->input('current_password'), $this->user()->password)) {
                $validator->errors()->add('current_password', 'Kata sandi admin tidak sesuai.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'channels.required' => 'Pilih minimal satu kanal pengiriman.',
            'audiences.required' => 'Pilih minimal satu kelompok penerima.',
            'authorized_contact.accepted' => 'Konfirmasi kewenangan komunikasi wajib disetujui.',
        ];
    }
}
