<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDocumentRetentionSettingRequest;
use App\Models\DocumentRetentionSetting;
use App\Services\SensitiveDocumentRetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminDocumentRetentionSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.retention', [
            'setting' => DocumentRetentionSetting::current(),
        ]);
    }

    public function update(UpdateDocumentRetentionSettingRequest $request): RedirectResponse
    {
        DocumentRetentionSetting::query()->updateOrCreate(['id' => 1], [
            'enabled' => $request->boolean('enabled'),
            'identity_days' => $request->integer('identity_days'),
            'assignment_days' => $request->integer('assignment_days'),
            'financial_days' => $request->integer('financial_days'),
        ]);

        return redirect()->route('admin.settings.retention.edit')
            ->with('success', 'Kebijakan retensi dokumen berhasil disimpan.');
    }

    public function preview(SensitiveDocumentRetentionService $retention): RedirectResponse
    {
        $result = $retention->run(true);

        return redirect()->route('admin.settings.retention.edit')->with(
            'retention_preview',
            $result['eligible'] === 0
                ? 'Belum ada dokumen yang melewati masa retensi.'
                : $result['eligible'].' dokumen akan dihapus pada proses terjadwal berikutnya.',
        );
    }
}
