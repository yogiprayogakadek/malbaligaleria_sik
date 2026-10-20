<?php

namespace App\Http\Controllers;

use App\Models\PermitNumberSetting;
use App\Services\PermitNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPermitNumberSettingController extends Controller
{
    public function edit(PermitNumberGenerator $generator): View
    {
        $settings = collect(['loading', 'work'])->mapWithKeys(function (string $type) use ($generator): array {
            $setting = PermitNumberSetting::for($type);

            return [$type => ['setting' => $setting, 'preview' => $generator->preview($setting)]];
        });

        return view('admin.permit-number-settings.edit', compact('settings'));
    }

    public function update(Request $request, PermitNumberGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'permit_type' => ['required', Rule::in(['loading', 'work'])],
            'pattern' => ['required', 'string', 'min:5', 'max:100'],
            'sequence_padding' => ['required', 'integer', 'min:2', 'max:8'],
            'reset_period' => ['required', Rule::in(['global', 'yearly', 'monthly'])],
            'current_password' => ['required', 'current_password'],
        ]);

        $data['pattern'] = trim($data['pattern']);
        $generator->validatePattern($data['permit_type'], $data['pattern'], (int) $data['sequence_padding']);

        PermitNumberSetting::query()->updateOrCreate(
            ['permit_type' => $data['permit_type']],
            collect($data)->except(['current_password', 'permit_type'])->all(),
        );

        return back()->with('success', 'Pola nomor surat berhasil diperbarui. Nomor yang sudah terbit tidak berubah.');
    }
}
