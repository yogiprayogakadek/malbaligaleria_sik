<?php

namespace App\Http\Requests;

use App\Models\OperatingSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperatingScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_open' => $this->boolean('is_open'),
            'is_24_hours' => $this->boolean('is_24_hours'),
        ]);
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in([OperatingSchedule::SCOPE_GENERAL, OperatingSchedule::SCOPE_EVENT])],
            'date' => [
                'required',
                'date',
                'after_or_equal:today',
                Rule::unique('operating_schedule_exceptions', 'date')->where('scope', $this->input('scope')),
            ],
            'label' => ['required', 'string', 'max:120'],
            'is_open' => ['required', 'boolean'],
            'is_24_hours' => ['required', 'boolean'],
            'opens_at' => ['nullable', Rule::requiredIf(fn () => $this->boolean('is_open') && ! $this->boolean('is_24_hours')), 'date_format:H:i'],
            'closes_at' => ['nullable', Rule::requiredIf(fn () => $this->boolean('is_open') && ! $this->boolean('is_24_hours')), 'date_format:H:i', 'different:opens_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'Tanggal ini sudah memiliki jadwal khusus.',
            'date.after_or_equal' => 'Jadwal khusus hanya dapat dibuat untuk hari ini atau setelahnya.',
            'label.required' => 'Nama hari libur atau kegiatan wajib diisi.',
            'opens_at.required' => 'Jam buka wajib diisi ketika portal dibuka.',
            'closes_at.required' => 'Jam tutup wajib diisi ketika portal dibuka.',
            'closes_at.different' => 'Jam buka dan tutup tidak boleh sama.',
        ];
    }
}
