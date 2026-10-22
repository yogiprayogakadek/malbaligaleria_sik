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
            'terms_accepted' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
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
        ]);
    }

    public function requirementSnapshot(): array
    {
        return EventRequirement::query()
            ->where('is_active', true)
            ->whereIn('id', $this->validated('requirement_ids', []))
            ->orderBy('sort_order')
            ->get(['group_name', 'name'])
            ->map(fn (EventRequirement $item): array => ['group' => $item->group_name, 'name' => $item->name])
            ->all();
    }
}
