<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePermitSubmissionLimitRequest;
use App\Models\PermitSubmissionLimitSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminPermitSubmissionLimitController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.permit-limits', [
            'setting' => PermitSubmissionLimitSetting::current(),
        ]);
    }

    public function update(UpdatePermitSubmissionLimitRequest $request): RedirectResponse
    {
        PermitSubmissionLimitSetting::query()->updateOrCreate(
            ['id' => 1],
            $request->validated(),
        );

        return redirect()->route('admin.settings.permit-limits.edit')
            ->with('success', 'Batas pengajuan tenant berhasil diperbarui.');
    }
}
