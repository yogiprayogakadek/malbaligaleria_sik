<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateValidatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true
            && $this->route('validator')?->isValidator() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validatorId = $this->route('validator')->id;

        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:120', Rule::unique('users', 'email')->ignore($validatorId)],
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/', Rule::unique('users', 'phone')->ignore($validatorId)],
            'division' => ['required', 'string', Rule::in(array_keys(config('divisions')))],
            'password' => [
                'nullable',
                'string',
                'max:255',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'division' => strtoupper(trim((string) $this->input('division'))),
        ]);
    }
}
