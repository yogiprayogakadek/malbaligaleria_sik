<?php

namespace App\Http\Requests;

use App\Models\ScannerSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScannerSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'access_mode' => ['required', Rule::in([ScannerSetting::MODE_ANYWHERE, ScannerSetting::MODE_GEOFENCE])],
            'latitude' => ['exclude_unless:access_mode,geofence', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:access_mode,geofence', 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['exclude_unless:access_mode,geofence', 'required', 'integer', 'min:20', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'access_mode.required' => 'Pilih cakupan akses scanner.',
            'access_mode.in' => 'Cakupan akses scanner tidak valid.',
            'latitude.required' => 'Latitude wajib diisi untuk pembatasan lokasi.',
            'latitude.between' => 'Latitude harus berada antara -90 dan 90.',
            'longitude.required' => 'Longitude wajib diisi untuk pembatasan lokasi.',
            'longitude.between' => 'Longitude harus berada antara -180 dan 180.',
            'radius_meters.required' => 'Radius akses wajib diisi.',
            'radius_meters.min' => 'Radius minimal 20 meter.',
            'radius_meters.max' => 'Radius maksimal 10.000 meter.',
        ];
    }
}
