<?php

namespace App\Http\Controllers;

use App\Models\ApplicantSupportSetting;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminApplicantSupportSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.applicant-support', ['setting' => ApplicantSupportSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
            'current_password' => ['required', 'current_password'],
        ], ['current_password.current_password' => 'Kata sandi admin tidak sesuai.']);

        $active = $request->boolean('is_active');
        $number = IndonesianPhoneNumber::normalize($data['whatsapp_number'] ?? null);
        if ($active && blank($data['contact_name'] ?? null)) {
            throw ValidationException::withMessages(['contact_name' => 'Nama kontak wajib diisi saat bantuan diaktifkan.']);
        }
        if ($active && ! $number) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Masukkan nomor WhatsApp Indonesia yang valid.']);
        }

        ApplicantSupportSetting::query()->updateOrCreate(['id' => 1], [
            'is_active' => $active,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'whatsapp_number' => $number,
        ]);

        return redirect()->route('admin.settings.applicant-support.edit')->with('success', 'Kontak bantuan pemohon berhasil diperbarui.');
    }
}
