<?php

namespace App\Http\Requests;

use App\Models\WorkPermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePermitDraftRequest extends FormRequest
{
    private const MAX_PAYLOAD_BYTES = 32_768;

    public function authorize(): bool
    {
        return in_array($this->route('type'), ['loading', 'work', 'event'], true);
    }

    public function rules(): array
    {
        $shared = [
            'applicant_name' => ['nullable', 'string', 'max:120'],
            'applicant_phone' => ['nullable', 'string', 'max:25'],
            'applicant_email' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'id_doc_type' => ['nullable', Rule::in(['ktp', 'sim'])],
            'draft_step' => ['nullable', 'integer', 'between:1,5'],
            'id_doc' => ['prohibited'],
            'assignment_letter' => ['prohibited'],
        ];

        if ($this->route('type') === 'loading') {
            return $shared + [
                'tenant_name' => ['nullable', 'string', 'max:120'],
                'direction' => ['nullable', Rule::in(['in', 'out', 'both'])],
                'movement_time' => ['nullable', 'date_format:H:i'],
                'item_count' => ['nullable', 'integer', 'between:1,9999'],
                'item_unit' => ['nullable', Rule::in(['koli', 'unit', 'pack', 'Koli', 'Unit', 'Pack'])],
                'item_description' => ['nullable', 'string', 'max:500'],
            ];
        }

        if ($this->route('type') === 'event') {
            return $shared + [
                'event_type' => ['nullable', Rule::in(['organizer', 'tenant'])],
                'organizer_name' => ['nullable', 'string', 'max:150'],
                'work_location' => ['nullable', 'string', 'max:180'],
                'work_category' => ['nullable', Rule::in(array_keys(config('event-permits.categories')))],
                'work_type' => ['nullable', 'string', 'max:180'],
                'requirement_ids' => ['nullable', 'array', 'max:30'],
                'requirement_ids.*' => ['integer', 'distinct'],
                'requirement_other' => ['nullable', 'string', 'max:160'],
                'includes_loading_material' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string', 'max:3000'],
                'terms_accepted' => ['prohibited'],
            ];
        }

        return $shared + [
            'contractor_name' => ['nullable', 'string', 'max:150'],
            'workers' => ['nullable', 'array', 'max:50'],
            'workers.*.name' => ['nullable', 'string', 'max:120'],
            'workers.*.identity_number' => ['nullable', 'string', 'max:50'],
            'work_schedules' => ['nullable', 'array', 'max:2'],
            'work_schedules.*' => ['string', 'distinct', Rule::in(['inside_store', 'outside_store'])],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_category' => ['nullable', 'string', Rule::in(array_keys(WorkPermit::WORK_CATEGORIES))],
            'work_type' => ['nullable', 'string', 'max:180'],
            'other_work_type' => ['nullable', Rule::in(['rooftop', 'atm_repair', 'fit_out', 'demolition', 'other'])],
            'other_work_description' => ['nullable', 'string', 'max:180'],
            'includes_loading_material' => ['nullable', 'boolean'],
            'is_rooftop_work' => ['nullable', 'boolean'],
            'rooftop_network_type' => ['nullable', Rule::in(['internet_installation', 'bts_repair', 'ac_cleaning'])],
            'is_atm_repair' => ['nullable', 'boolean'],
            'needs_water' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function passedValidation(): void
    {
        $size = strlen(json_encode($this->validated(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        if ($size > self::MAX_PAYLOAD_BYTES) {
            throw ValidationException::withMessages([
                'draft' => 'Draft terlalu besar untuk disimpan.',
            ]);
        }
    }
}
