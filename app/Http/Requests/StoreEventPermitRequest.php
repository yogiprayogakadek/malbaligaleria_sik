<?php

namespace App\Http\Requests;

use App\Models\EventRequirement;
use App\Support\IndonesianPhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventPermitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'string', 'uuid'],
            'event_type' => ['required', Rule::in(['organizer', 'tenant'])],
            'organizer_name' => ['required', 'string', 'min:2', 'max:150'],
            'applicant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_phone' => ['required', 'string', 'max:25'],
            'applicant_email' => ['required', 'email:rfc', 'max:120'],
            'work_location' => ['required', 'string', 'min:2', 'max:180'],
            'work_category' => ['required', Rule::in(array_keys(config('event-permits.categories')))],
            'work_type' => ['required', 'string', 'min:3', 'max:180'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['required', 'string', 'min:10', 'max:3000'],
            'includes_loading_material' => ['nullable', 'boolean'],
            'requirement_ids' => ['nullable', 'array', 'max:30'],
            'requirement_ids.*' => ['integer', 'distinct', Rule::exists('event_requirements', 'id')->where('is_active', true)],
            'requirement_other' => ['nullable', 'string', 'min:2', 'max:160'],
            'terms_accepted' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->otherRequirementSelected() && ! filled($this->input('requirement_other'))) {
                $validator->errors()->add('requirement_other', 'Jelaskan fasilitas lainnya yang diperlukan.');
            }

            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $start = CarbonImmutable::parse($this->input('start_date'))->startOfDay();
            $end = CarbonImmutable::parse($this->input('end_date'))->startOfDay();
            if ($end->greaterThan($start->addDays(6))) {
                $validator->errors()->add('end_date', 'Masa berlaku Surat Izin Event maksimal 7 hari kalender termasuk tanggal mulai.');
            }

        }];
    }

    public function messages(): array
    {
        return [
            'submission_token.required' => 'Sesi pengajuan tidak ditemukan. Muat ulang halaman lalu coba kembali.',
            'submission_token.uuid' => 'Sesi pengajuan tidak valid. Muat ulang halaman lalu coba kembali.',
            'event_type.required' => 'Pilih jenis Surat Izin Event.',
            'event_type.in' => 'Jenis Surat Izin Event tidak valid.',
            'organizer_name.required' => 'Nama EO atau tenant wajib diisi.',
            'organizer_name.min' => 'Nama EO atau tenant minimal 2 karakter.',
            'organizer_name.max' => 'Nama EO atau tenant maksimal 150 karakter.',
            'applicant_name.required' => 'PIC atau penanggung jawab wajib diisi.',
            'applicant_name.min' => 'Nama PIC atau penanggung jawab minimal 2 karakter.',
            'applicant_name.max' => 'Nama PIC atau penanggung jawab maksimal 120 karakter.',
            'applicant_phone.required' => 'Masukkan nomor WhatsApp Indonesia yang valid.',
            'applicant_phone.max' => 'Nomor WhatsApp maksimal 25 karakter.',
            'applicant_email.required' => 'Email wajib diisi.',
            'applicant_email.email' => 'Masukkan alamat email yang valid.',
            'applicant_email.max' => 'Email maksimal 120 karakter.',
            'work_location.required' => 'Lokasi event wajib diisi.',
            'work_location.min' => 'Lokasi event minimal 2 karakter.',
            'work_location.max' => 'Lokasi event maksimal 180 karakter.',
            'work_category.required' => 'Pilih kategori pekerjaan.',
            'work_category.in' => 'Kategori pekerjaan tidak valid.',
            'work_type.required' => 'Jenis pekerjaan wajib diisi.',
            'work_type.min' => 'Jenis pekerjaan minimal 3 karakter.',
            'work_type.max' => 'Jenis pekerjaan maksimal 180 karakter.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'notes.required' => 'Rincian pekerjaan wajib diisi.',
            'notes.min' => 'Rincian pekerjaan minimal 10 karakter.',
            'notes.max' => 'Rincian pekerjaan maksimal 3.000 karakter.',
            'requirement_ids.array' => 'Pilihan kebutuhan Event tidak valid.',
            'requirement_ids.max' => 'Maksimal 30 kebutuhan Event dapat dipilih.',
            'requirement_ids.*.distinct' => 'Terdapat kebutuhan Event yang dipilih lebih dari sekali.',
            'requirement_ids.*.exists' => 'Salah satu kebutuhan Event sudah tidak tersedia.',
            'requirement_other.min' => 'Fasilitas lainnya minimal 2 karakter.',
            'requirement_other.max' => 'Fasilitas lainnya maksimal 160 karakter.',
            'terms_accepted.accepted' => 'Anda harus menyetujui ketentuan Event sebelum mengajukan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organizer_name' => trim((string) $this->input('organizer_name')),
            'applicant_name' => trim((string) $this->input('applicant_name')),
            'applicant_phone' => IndonesianPhoneNumber::normalize((string) $this->input('applicant_phone')),
            'applicant_email' => mb_strtolower(trim((string) $this->input('applicant_email'))),
            'work_location' => trim((string) $this->input('work_location')),
            'work_type' => trim((string) $this->input('work_type')),
            'notes' => trim((string) $this->input('notes')),
            'includes_loading_material' => $this->boolean('includes_loading_material'),
            'requirement_other' => trim((string) $this->input('requirement_other')),
        ]);
    }

    public function requirementSnapshot(): array
    {
        $requirements = EventRequirement::query()
            ->where('is_active', true)
            ->whereIn('id', $this->validated('requirement_ids', []))
            ->orderBy('sort_order')
            ->get(['id', 'group_name', 'name'])
            ->reject(fn (EventRequirement $item): bool => $item->group_name === 'Fasilitas' && $item->name === 'Lainnya')
            ->map(fn (EventRequirement $item): array => ['id' => $item->id, 'group' => $item->group_name, 'name' => $item->name]);

        if ($this->otherRequirementSelected()) {
            $requirements->push(['id' => $this->otherRequirementId(), 'group' => 'Fasilitas', 'name' => 'Lainnya: '.$this->validated('requirement_other')]);
        }

        return $requirements->values()->all();
    }

    private function otherRequirementSelected(): bool
    {
        $otherId = $this->otherRequirementId();

        return $otherId !== null && in_array((int) $otherId, array_map('intval', (array) $this->input('requirement_ids', [])), true);
    }

    private function otherRequirementId(): ?int
    {
        return EventRequirement::query()
            ->where('is_active', true)
            ->where('group_name', 'Fasilitas')
            ->where('name', 'Lainnya')
            ->value('id');
    }
}
