<?php

namespace App\Http\Controllers;

use App\Models\UploadSecuritySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUploadSecuritySettingController extends Controller
{
    public function edit(): View
    {
        $binary = (string) config('malware.binary');

        return view('admin.settings.upload-security', [
            'setting' => UploadSecuritySetting::current(),
            'serverScanningEnabled' => (bool) config('malware.enabled'),
            'scannerBinaryReady' => is_file($binary) && is_executable($binary),
            'scannerBinaryName' => basename($binary),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'malware_scanning_enabled' => ['required', 'boolean'],
            'disabled_reason' => [
                Rule::requiredIf(! $request->boolean('malware_scanning_enabled')),
                'nullable',
                'string',
                'min:10',
                'max:500',
            ],
            'current_password' => ['required', 'current_password', 'max:255'],
        ], [
            'disabled_reason.required' => 'Tuliskan alasan saat pemeriksaan upload dinonaktifkan.',
            'disabled_reason.min' => 'Alasan penonaktifan minimal 10 karakter.',
            'current_password.required' => 'Masukkan kata sandi admin untuk menyimpan perubahan.',
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ]);

        if ($request->boolean('malware_scanning_enabled') && ! config('malware.enabled')) {
            throw ValidationException::withMessages([
                'malware_scanning_enabled' => 'Aktifkan MALWARE_SCAN_ENABLED pada server terlebih dahulu.',
            ]);
        }

        UploadSecuritySetting::query()->updateOrCreate(['id' => 1], [
            'malware_scanning_enabled' => $request->boolean('malware_scanning_enabled'),
            'disabled_reason' => $request->boolean('malware_scanning_enabled')
                ? null
                : trim((string) $data['disabled_reason']),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.settings.upload-security.edit')
            ->with('success', $request->boolean('malware_scanning_enabled')
                ? 'Pemeriksaan keamanan upload telah diaktifkan.'
                : 'Pemeriksaan keamanan upload dinonaktifkan sementara.');
    }
}
