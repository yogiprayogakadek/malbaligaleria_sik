<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermitSubmissionLimitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'max_per_minute' => ['required', 'integer', 'min:1', 'max:100'],
            'max_per_day' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_per_minute.required' => 'Batas per menit wajib diisi.',
            'max_per_day.required' => 'Batas harian wajib diisi.',
            '*.integer' => 'Batas harus berupa angka bulat.',
            'max_per_minute.min' => 'Batas per menit minimal 1 pengajuan.',
            'max_per_minute.max' => 'Batas per menit maksimal 100 pengajuan.',
            'max_per_day.min' => 'Batas harian minimal 1 pengajuan.',
            'max_per_day.max' => 'Batas harian maksimal 1.000 pengajuan.',
        ];
    }
}
