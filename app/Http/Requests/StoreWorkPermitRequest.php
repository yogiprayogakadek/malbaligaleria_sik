<?php

namespace App\Http\Requests;

use App\Models\WorkPermit;
use DateTime;
use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreWorkPermitRequest extends FormRequest
{
    private const MAX_DURATION_DAYS = 7;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('applicant_email')));

        $this->merge([
            'applicant_email' => $email !== '' ? $email : null,
            'needs_water' => $this->boolean('needs_water'),
            'includes_loading_material' => $this->boolean('includes_loading_material'),
            'is_rooftop_work' => $this->boolean('is_rooftop_work'),
            'is_atm_repair' => $this->boolean('is_atm_repair'),
            'consent' => $this->boolean('consent'),
        ]);

        if ($this->input('work_category') !== 'other') {
            $this->merge([
                'includes_loading_material' => false,
                'is_rooftop_work' => false,
                'rooftop_network_type' => null,
                'is_atm_repair' => false,
            ]);
        } elseif (! $this->boolean('is_rooftop_work')) {
            $this->merge(['rooftop_network_type' => null]);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $this->merge([
                'contractor_name' => $this->input('contractor_name') ?: $user->tenant_name,
                'applicant_name' => $this->input('applicant_name') ?: $user->name,
                'applicant_phone' => $this->input('applicant_phone') ?: $user->phone,
                'applicant_email' => $this->input('applicant_email') ?: $user->email,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'contractor_name' => ['required', 'string', 'min:2', 'max:150'],
            'applicant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_phone' => ['required', 'string', 'min:9', 'max:25', 'regex:/^[\d\+\-\s\(\)]+$/'],
            'applicant_email' => ['nullable', 'email:rfc', 'max:120'],
            'workers' => ['required', 'array', 'min:1', 'max:50'],
            'workers.*.name' => ['nullable', 'string', 'min:2', 'max:120'],
            'workers.*.identity_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\.\-\/\s]+$/'],
            'work_location' => ['required', 'string', 'min:2', 'max:180'],
            'work_category' => ['required', 'string', Rule::in(array_keys(WorkPermit::WORK_CATEGORIES))],
            'work_type' => ['required', 'string', 'min:3', 'max:180'],
            'includes_loading_material' => ['required', 'boolean'],
            'is_rooftop_work' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $schedules = (array) $this->input('work_schedules', []);

                    if ($this->boolean('is_rooftop_work') && ! in_array('outside_store', $schedules, true)) {
                        $fail('Pekerjaan rooftop hanya dapat dipilih jika waktu kerja di luar toko dipilih.');
                    }
                },
            ],
            'rooftop_network_type' => [
                Rule::requiredIf(fn (): bool => $this->input('work_category') === 'other' && $this->boolean('is_rooftop_work')),
                'nullable',
                Rule::in(['internet_installation', 'bts_repair', 'ac_cleaning']),
            ],
            'is_atm_repair' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->boolean('is_rooftop_work') && $this->boolean('is_atm_repair')) {
                        $fail('Pekerjaan rooftop dan perbaikan ATM hanya dapat dipilih salah satu.');
                    }
                },
            ],
            'work_schedules' => ['required', 'array', 'min:1', 'max:2'],
            'work_schedules.*' => ['required', 'distinct', 'in:inside_store,outside_store'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('start_date');
                    if (! $start || ! $value) {
                        return;
                    }

                    try {
                        $duration = (new DateTime((string) $start))->diff(new DateTime((string) $value))->days;
                    } catch (Exception) {
                        return;
                    }

                    if ($duration >= self::MAX_DURATION_DAYS) {
                        $fail('Masa izin kerja maksimal '.self::MAX_DURATION_DAYS.' hari kalender termasuk tanggal mulai.');
                    }
                },
            ],
            'needs_water' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'id_doc_type' => ['required', 'in:ktp,sim'],
            'id_doc' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value || ! $value->isValid()) {
                        return;
                    }

                    $imageInfo = @getimagesize($value->getRealPath());
                    if (! $imageInfo) {
                        $fail('File tidak dapat diproses sebagai gambar.');

                        return;
                    }

                    [$width, $height] = $imageInfo;
                    if ($width < 200 || $height < 100) {
                        $fail('Gambar terlalu kecil. Unggah foto KTP/SIM yang jelas.');

                        return;
                    }
                },
            ],
            'assignment_letter' => [
                Rule::requiredIf(fn (): bool => $this->requiresAssignmentLetter()),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:8192',
            ],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'contractor_name.required' => 'Nama kontraktor atau tenant wajib diisi.',
            'applicant_name.required' => 'Nama penanggung jawab wajib diisi.',
            'applicant_phone.required' => 'Nomor WhatsApp wajib diisi.',
            'applicant_phone.regex' => 'Format nomor WhatsApp tidak valid.',
            'applicant_email.email' => 'Format email tidak valid.',
            'workers.required' => 'Tambahkan minimal satu pekerja.',
            'workers.min' => 'Tambahkan minimal satu pekerja.',
            'workers.max' => 'Maksimal 50 pekerja dalam satu permohonan.',
            'workers.*.identity_number.regex' => 'Nomor ID hanya boleh memuat huruf, angka, spasi, titik, garis miring, dan tanda hubung.',
            'work_location.required' => 'Lokasi pekerjaan wajib diisi.',
            'work_category.required' => 'Kategori pekerjaan wajib dipilih.',
            'work_category.in' => 'Kategori pekerjaan tidak valid.',
            'work_type.required' => 'Jenis pekerjaan wajib diisi.',
            'rooftop_network_type.required' => 'Pilih jenis pekerjaan di rooftop.',
            'rooftop_network_type.in' => 'Pilihan pekerjaan rooftop tidak valid.',
            'work_schedules.required' => 'Pilih minimal satu waktu kerja.',
            'work_schedules.min' => 'Pilih minimal satu waktu kerja.',
            'work_schedules.*.in' => 'Pilihan waktu kerja tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'id_doc_type.required' => 'Jenis dokumen identitas wajib dipilih.',
            'id_doc.required' => 'Foto KTP atau SIM penanggung jawab wajib diunggah.',
            'id_doc.uploaded' => 'Foto gagal diunggah. Coba gunakan foto berukuran lebih kecil atau pilih dari galeri.',
            'id_doc.mimes' => 'Dokumen harus berupa JPG, PNG, atau WebP.',
            'id_doc.max' => 'Ukuran dokumen maksimal 8 MB.',
            'assignment_letter.required' => 'Surat tugas wajib diunggah untuk pemasangan internet, perbaikan BTS, atau perbaikan ATM.',
            'assignment_letter.uploaded' => 'Surat tugas gagal diunggah. Coba gunakan file yang lebih kecil.',
            'assignment_letter.mimes' => 'Surat tugas harus berupa PDF, JPG, atau PNG.',
            'assignment_letter.max' => 'Ukuran surat tugas maksimal 8 MB.',
            'consent.accepted' => 'Anda harus menyetujui peraturan pekerjaan.',
        ];
    }

    public function requiresAssignmentLetter(): bool
    {
        if ($this->input('work_category') !== 'other') {
            return false;
        }

        return $this->boolean('is_atm_repair')
            || ($this->boolean('is_rooftop_work')
                && in_array($this->input('rooftop_network_type'), ['internet_installation', 'bts_repair'], true));
    }

    protected function failedValidation(Validator $validator): never
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
