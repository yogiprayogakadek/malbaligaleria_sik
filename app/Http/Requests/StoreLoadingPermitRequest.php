<?php

namespace App\Http\Requests;

use App\Services\PermitSubmissionTokenService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class StoreLoadingPermitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_token' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! app(PermitSubmissionTokenService::class)->isValid($this, 'loading', $value)) {
                        $fail('Sesi pengajuan tidak valid atau sudah kedaluwarsa. Muat ulang formulir lalu coba lagi.');
                    }
                },
            ],
            'tenant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_phone' => ['required', 'string', 'min:9', 'max:25', 'regex:/^[\d\+\-\s\(\)]+$/'],
            'applicant_email' => ['required', 'email:rfc', 'max:120'],
            'direction' => ['required', 'in:in,out,both'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attr, $val, $fail) {
                    $start = $this->input('start_date');
                    if ($start && $val) {
                        $diff = (new \DateTime($start))->diff(new \DateTime($val))->days;
                        if ($diff > 2) {
                            $fail('Tanggal selesai maksimal 3 hari dari tanggal mulai (contoh: mulai tgl 1, selesai maks. tgl 3).');
                        }
                    }
                },
            ],
            'movement_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    if (! is_string($value) || ! preg_match('/^(\d{2}):(\d{2})$/', $value, $matches)) {
                        return;
                    }

                    $minutes = ((int) $matches[1] * 60) + (int) $matches[2];

                    if ($minutes > 11 * 60 && $minutes < 22 * 60) {
                        $fail('Waktu loading atau unloading hanya diizinkan mulai pukul 22.00 sampai 11.00 WITA keesokan harinya.');
                    }
                },
            ],
            'item_count' => ['required', 'integer', 'min:1', 'max:9999'],
            'item_unit' => ['required', 'in:pcs,koli'],
            'item_description' => ['required', 'string', 'min:3', 'max:500'],
            'id_doc' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                function ($attr, $val, $fail) {
                    if (! $val || ! $val->isValid()) {
                        return;
                    }

                    // Cek dimensi: KTP/SIM Indonesia ~rasio 1.5:1 (landscape)
                    $imageInfo = @getimagesize($val->getRealPath());
                    if (! $imageInfo) {
                        $fail('File tidak dapat diproses sebagai gambar.');

                        return;
                    }

                    [$width, $height] = $imageInfo;

                    // Minimum size agar teks bisa terbaca (tidak boleh terlalu kecil)
                    if ($width < 200 || $height < 100) {
                        $fail('Gambar terlalu kecil. Upload foto KTP/SIM yang jelas dan ukuran cukup.');

                        return;
                    }
                },
            ],
            'id_doc_type' => ['required', 'in:ktp,sim'],
            'instruction_letter' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:8192',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'submission_token.required' => 'Sesi pengajuan tidak ditemukan. Muat ulang formulir lalu coba lagi.',
            'submission_token.uuid' => 'Sesi pengajuan tidak valid. Muat ulang formulir lalu coba lagi.',
            'tenant_name.required' => 'Nama tenant wajib diisi.',
            'applicant_name.required' => 'Nama PIC / penanggung jawab wajib diisi.',
            'applicant_phone.required' => 'Nomor HP wajib diisi.',
            'applicant_phone.regex' => 'Format nomor HP tidak valid.',
            'applicant_email.email' => 'Format email tidak valid.',
            'applicant_email.required' => 'Email wajib diisi agar pembaruan status dapat dikirim.',
            'direction.required' => 'Arah loading wajib dipilih.',
            'direction.in' => 'Pilihan arah tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'movement_time.required' => 'Waktu loading atau unloading wajib diisi.',
            'movement_time.date_format' => 'Format waktu loading atau unloading tidak valid.',
            'item_count.required' => 'Jumlah barang wajib diisi.',
            'item_count.integer' => 'Jumlah barang harus angka.',
            'item_count.min' => 'Jumlah barang minimal 1.',
            'item_unit.required' => 'Satuan barang wajib dipilih.',
            'item_unit.in' => 'Satuan barang hanya dapat dipilih antara pcs atau koli.',
            'item_description.required' => 'Deskripsi barang wajib diisi.',
            'item_description.min' => 'Deskripsi barang minimal 3 karakter.',
            'id_doc.required' => 'Upload foto KTP atau SIM wajib dilampirkan.',
            'id_doc.uploaded' => 'Foto gagal diunggah. Coba gunakan foto berukuran lebih kecil atau pilih dari galeri.',
            'id_doc.mimes' => 'File harus berformat JPG, PNG, atau WebP.',
            'id_doc.max' => 'Ukuran file maksimal 8 MB.',
            'id_doc_type.required' => 'Jenis dokumen identitas wajib dipilih.',
            'id_doc_type.in' => 'Pilihan jenis dokumen tidak valid.',
            // Field ini belum digunakan dalam alur operasional loading/unloading.
            // 'instruction_letter.required' => 'Surat izin atau surat perintah wajib diunggah.',
            'instruction_letter.uploaded' => 'Surat izin atau perintah gagal diunggah. Coba gunakan file yang lebih kecil.',
            'instruction_letter.mimes' => 'Surat izin atau perintah harus berupa PDF, JPG, PNG, atau WebP.',
            'instruction_letter.max' => 'Ukuran surat izin atau perintah maksimal 8 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('applicant_email')));

        $this->merge([
            'applicant_email' => $email !== '' ? $email : null,
        ]);

        // Auto-fill dari user yang login
        if (Auth::check()) {
            $user = Auth::user();
            $this->merge([
                'tenant_name' => $user->tenant?->displayName() ?: ($this->input('tenant_name') ?: $user->tenant_name),
                'applicant_name' => $this->input('applicant_name') ?: $user->name,
                'applicant_phone' => $this->input('applicant_phone') ?: $user->phone,
                'applicant_email' => $this->input('applicant_email') ?: $user->email,
            ]);
        }
    }

    protected function failedValidation(Validator $validator): never
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422)
            );
        }
        parent::failedValidation($validator);
    }
}
