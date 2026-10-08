<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateScannerSettingRequest;
use App\Models\ScannerSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminScannerSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.scanner', [
            'setting' => ScannerSetting::current(),
        ]);
    }

    public function update(UpdateScannerSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($data['access_mode'] === ScannerSetting::MODE_ANYWHERE) {
            $data['latitude'] = null;
            $data['longitude'] = null;
            $data['radius_meters'] = null;
        }

        ScannerSetting::query()->updateOrCreate(['id' => 1], $data);

        return redirect()->route('admin.settings.scanner.edit')
            ->with('success', 'Pengaturan akses scanner berhasil disimpan.');
    }
}
