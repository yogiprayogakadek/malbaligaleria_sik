<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'loading_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'work_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function messages(): array
    {
        return [
            'loading_hours.required' => 'Batas waktu loading wajib diisi.',
            'work_hours.required' => 'Batas waktu izin kerja wajib diisi.',
            '*.integer' => 'Batas waktu harus berupa jam bulat.',
            '*.min' => 'Batas waktu minimal 1 jam.',
            '*.max' => 'Batas waktu maksimal 168 jam.',
        ];
    }
}
