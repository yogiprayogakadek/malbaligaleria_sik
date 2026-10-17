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
            'risk_monitoring_enabled' => ['required', 'boolean'],
            'auto_block_enabled' => ['required', 'boolean'],
            'risk_threshold' => ['required_if:risk_monitoring_enabled,1', 'integer', 'min:3', 'max:100'],
            'risk_window_minutes' => ['required_if:risk_monitoring_enabled,1', 'integer', 'min:1', 'max:1440'],
            'block_duration_minutes' => ['required_if:auto_block_enabled,1', 'integer', 'min:5', 'max:10080'],
            'risk_alerts_enabled' => ['required', 'boolean'],
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
            'risk_threshold.min' => 'Ambang minimal adalah 3 kejadian.',
            'risk_threshold.max' => 'Ambang maksimal adalah 100 kejadian.',
            'risk_window_minutes.min' => 'Jendela pemantauan minimal 1 menit.',
            'risk_window_minutes.max' => 'Jendela pemantauan maksimal 1.440 menit.',
            'block_duration_minutes.min' => 'Durasi blokir minimal 5 menit.',
            'block_duration_minutes.max' => 'Durasi blokir maksimal 7 hari.',
        ];
    }
}
