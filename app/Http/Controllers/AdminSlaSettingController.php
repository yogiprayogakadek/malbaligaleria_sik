<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSlaSettingRequest;
use App\Models\SlaSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSlaSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.sla', ['setting' => SlaSetting::current()]);
    }

    public function update(UpdateSlaSettingRequest $request): RedirectResponse
    {
        SlaSetting::query()->updateOrCreate(['id' => 1], $request->validated());

        return redirect()->route('admin.settings.sla.edit')
            ->with('success', 'Batas waktu antrean berhasil diperbarui.');
    }
}
