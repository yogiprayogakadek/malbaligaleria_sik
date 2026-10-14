<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class AdminReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $timezone = config('operating-hours.timezone');
        $today = now($timezone)->toDateString();

        $this->merge([
            'date_from' => $this->filled('date_from') ? $this->input('date_from') : now($timezone)->subDays(29)->toDateString(),
            'date_to' => $this->filled('date_to') ? $this->input('date_to') : $today,
            'permit_type' => $this->filled('permit_type') ? $this->input('permit_type') : 'all',
            'status_group' => $this->filled('status_group') ? $this->input('status_group') : 'all',
            'search' => trim((string) $this->input('search', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today'],
            'permit_type' => ['required', 'in:all,loading,work'],
            'status_group' => ['required', 'in:all,active,approved,rejected'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_from'));
            $to = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_to'));
            if ($from->diffInDays($to) > 365) {
                $validator->errors()->add('date_to', 'Rentang laporan maksimal 366 hari.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_from.required' => 'Tanggal awal wajib diisi.',
            'date_from.before_or_equal' => 'Tanggal awal tidak boleh melewati tanggal akhir.',
            'date_to.required' => 'Tanggal akhir wajib diisi.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'date_to.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
            'permit_type.in' => 'Jenis permohonan tidak valid.',
            'status_group.in' => 'Kelompok status tidak valid.',
            'search.max' => 'Pencarian maksimal 100 karakter.',
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only(['date_from', 'date_to', 'permit_type', 'status_group', 'search']);
    }
}
