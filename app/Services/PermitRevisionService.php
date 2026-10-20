<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\PermitRevision;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermitRevisionService
{
    public const LOADING_FIELDS = [
        'tenant_name' => 'Nama tenant / toko',
        'applicant_name' => 'Nama penanggung jawab',
        'applicant_phone' => 'Nomor WhatsApp',
        'applicant_email' => 'Email',
        'direction' => 'Arah pergerakan barang',
        'movement_time' => 'Waktu loading / unloading',
        'start_date' => 'Tanggal mulai',
        'end_date' => 'Tanggal selesai',
        'item_count' => 'Jumlah barang',
        'item_unit' => 'Satuan barang',
        'item_description' => 'Keterangan barang',
        'id_doc' => 'Dokumen KTP / SIM',
        'instruction_letter' => 'Surat izin atau surat perintah',
    ];

    public const WORK_FIELDS = [
        'contractor_name' => 'Nama kontraktor / vendor',
        'tenant_name' => 'Nama tenant',
        'applicant_name' => 'Nama penanggung jawab',
        'applicant_phone' => 'Nomor WhatsApp',
        'applicant_email' => 'Email',
        'workers' => 'Penanggung jawab / daftar kerja',
        'work_schedules' => 'Waktu kerja',
        'work_location' => 'Lokasi pekerjaan',
        'work_category' => 'Kategori pekerjaan',
        'work_type' => 'Uraian pekerjaan',
        'work_options' => 'Rincian pekerjaan lainnya',
        'start_date' => 'Tanggal berlaku mulai',
        'end_date' => 'Tanggal berlaku sampai',
        'needs_water' => 'Kebutuhan air',
        'needs_lighting' => 'Tambahan penerangan',
        'notes' => 'Catatan pemohon',
        'id_doc' => 'Dokumen KTP / SIM',
        'assignment_letter' => 'Surat tugas',
    ];

    /**
     * @return array<string, string>
     */
    public function fieldsFor(LoadingPermit|WorkPermit $permit): array
    {
        return $permit instanceof LoadingPermit ? self::LOADING_FIELDS : self::WORK_FIELDS;
    }

    /**
     * @param  array<int, string>  $fields
     * @param  array<string, string>  $reasons
     * @return array<string, string>
     */
    public function validateSelection(LoadingPermit|WorkPermit $permit, array $fields, array $reasons): array
    {
        $labels = $this->fieldsFor($permit);
        validator(['fields' => $fields], [
            'fields' => ['required', 'array', 'min:1', 'max:'.count($labels)],
            'fields.*' => ['required', 'string', 'distinct', Rule::in(array_keys($labels))],
        ], [
            'fields.required' => 'Pilih minimal satu field yang perlu diperbaiki.',
            'fields.min' => 'Pilih minimal satu field yang perlu diperbaiki.',
            'fields.*.in' => 'Field revisi tidak valid.',
        ])->validate();

        $selected = [];
        foreach (array_unique($fields) as $field) {
            $reason = trim((string) Arr::get($reasons, $field));
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages([
                    "reasons.{$field}" => "Alasan untuk {$labels[$field]} wajib diisi antara 10 sampai 500 karakter.",
                ]);
            }
            $selected[$field] = $reason;
        }

        return $selected;
    }

    /**
     * @param  array<string, string>  $selected
     * @return Collection<int, PermitRevision>
     */
    public function create(LoadingPermit|WorkPermit $permit, User $requester, array $selected): Collection
    {
        $batchId = (string) Str::uuid();
        $revisions = new Collection;

        foreach ($selected as $field => $reason) {
            $revisions->push($permit->revisions()->create([
                'batch_id' => $batchId,
                'requested_by' => $requester->id,
                'field_key' => $field,
                'reason' => $reason,
            ]));
        }

        return $revisions;
    }

    /**
     * @return Collection<int, PermitRevision>
     */
    public function active(LoadingPermit|WorkPermit $permit): Collection
    {
        $batchId = $permit->revisions()->whereNull('resolved_at')->latest('id')->value('batch_id');

        if (! $batchId) {
            return new Collection;
        }

        return $permit->revisions()
            ->where('batch_id', $batchId)
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->get();
    }

    public function resolve(LoadingPermit|WorkPermit $permit, string $batchId): void
    {
        $permit->revisions()
            ->where('batch_id', $batchId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  Collection<int, PermitRevision>  $revisions
     */
    public function summary(LoadingPermit|WorkPermit $permit, Collection $revisions): string
    {
        $labels = $this->fieldsFor($permit);

        return $revisions
            ->map(fn (PermitRevision $revision): string => $labels[$revision->field_key] ?? $revision->field_key)
            ->implode(', ');
    }
}
