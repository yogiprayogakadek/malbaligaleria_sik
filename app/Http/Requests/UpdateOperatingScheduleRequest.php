<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOperatingScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.is_open' => ['required', 'boolean'],
            'days.*.is_24_hours' => ['required', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('days', []) as $index => $day) {
                    if (! ($day['is_open'] ?? false) || ($day['is_24_hours'] ?? false)) {
                        continue;
                    }

                    $opensAt = $day['opens_at'] ?? null;
                    $closesAt = $day['closes_at'] ?? null;

                    if (! $opensAt) {
                        $validator->errors()->add("days.{$index}.opens_at", 'Jam buka wajib diisi.');
                    }

                    if (! $closesAt) {
                        $validator->errors()->add("days.{$index}.closes_at", 'Jam tutup wajib diisi.');
                    }

                    if ($opensAt && $closesAt && $opensAt === $closesAt) {
                        $validator->errors()->add("days.{$index}.closes_at", 'Jam buka dan tutup tidak boleh sama.');
                    }
                }
            },
        ];
    }
}
