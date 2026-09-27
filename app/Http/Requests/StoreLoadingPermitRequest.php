<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
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
            'tenant_name'      => ['required', 'string', 'min:2', 'max:120'],
            'applicant_name'   => ['required', 'string', 'min:2', 'max:120'],
            'applicant_phone'  => ['required', 'string', 'min:9', 'max:25', 'regex:/^[\d\+\-\s\(\)]+$/'],
            'applicant_email'  => ['nullable', 'email:rfc', 'max:120'],
            'direction'        => ['required', 'in:in,out,both'],
            'start_date'       => ['required', 'date', 'after_or_equal:today'],
            'end_date'         => [
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
            'item_count'       => ['required', 'integer', 'min:1', 'max:9999'],
            'item_unit'        => ['required', 'string', 'min:1', 'max:30'],
            'item_description' => ['nullable', 'string', 'max:500'],
            'id_doc'           => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:4096',
                function ($attr, $val, $fail) {
                    if (! $val || ! $val->isValid()) return;

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

                    // Rasio aspek: KTP horizontal ~1.4–1.8, SIM mirip
                    if ($height > 0) {
                        $ratio = $width / $height;
                        if ($ratio < 1.2 || $ratio > 2.5) {
                            $fail('Proporsi gambar tidak sesuai KTP/SIM. Pastikan foto diambil secara horizontal/landscape.');
                        }
                    }
                },
            ],
            'id_doc_type'      => ['required', 'in:ktp,sim'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_name.required'      => 'Nama tenant wajib diisi.',
            'applicant_name.required'   => 'Nama PIC / penanggung jawab wajib diisi.',
            'applicant_phone.required'  => 'Nomor HP wajib diisi.',
            'applicant_phone.regex'     => 'Format nomor HP tidak valid.',
            'applicant_email.email'     => 'Format email tidak valid.',
            'direction.required'        => 'Arah loading wajib dipilih.',
            'direction.in'              => 'Pilihan arah tidak valid.',
            'start_date.required'       => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required'         => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal'   => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'item_count.required'       => 'Jumlah barang wajib diisi.',
            'item_count.integer'        => 'Jumlah barang harus angka.',
            'item_count.min'            => 'Jumlah barang minimal 1.',
            'item_unit.required'        => 'Satuan barang wajib diisi (contoh: koli, pcs, dus).',
            'id_doc.required'           => 'Upload foto KTP atau SIM wajib dilampirkan.',
            'id_doc.mimes'              => 'File harus berformat JPG atau PNG.',
            'id_doc.max'                => 'Ukuran file maksimal 4MB.',
            'id_doc_type.required'      => 'Jenis dokumen identitas wajib dipilih.',
            'id_doc_type.in'            => 'Pilihan jenis dokumen tidak valid.',
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
                'tenant_name'     => $this->input('tenant_name')     ?: $user->tenant_name,
                'applicant_name'  => $this->input('applicant_name')  ?: $user->name,
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
