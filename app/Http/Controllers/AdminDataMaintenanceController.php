<?php

namespace App\Http\Controllers;

use App\Services\AdminDataMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AdminDataMaintenanceController extends Controller
{
    public function index(Request $request, AdminDataMaintenanceService $maintenance): View
    {
        $datasets = $maintenance->datasets();
        $dataset = array_key_exists($request->string('dataset')->toString(), $datasets)
            ? $request->string('dataset')->toString()
            : 'loading';

        return view('admin.data-maintenance.index', [
            'datasets' => $datasets,
            'dataset' => $dataset,
            'records' => $maintenance->query($dataset)->paginate(25)->withQueryString(),
            'counts' => collect(array_keys($datasets))->mapWithKeys(fn (string $key): array => [$key => $maintenance->query($key)->count()]),
            'productionResetEnabled' => (bool) config('app.production_data_reset_enabled'),
            'productionResetCompleted' => $maintenance->productionResetCompleted(),
            'backupConfigured' => $maintenance->backupConfigured(),
        ]);
    }

    public function destroy(Request $request, AdminDataMaintenanceService $maintenance): RedirectResponse
    {
        $datasetKeys = array_keys($maintenance->datasets());
        $data = $request->validate([
            'dataset' => ['required', Rule::in($datasetKeys)],
            'delete_mode' => ['required', Rule::in(['selected', 'all'])],
            'record_ids' => [Rule::requiredIf($request->input('delete_mode') === 'selected'), 'array', 'max:100'],
            'record_ids.*' => ['integer', 'min:1'],
            'confirmation' => [Rule::requiredIf($request->input('delete_mode') === 'all'), 'nullable', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'current_password'],
        ], [
            'record_ids.required' => 'Pilih sedikitnya satu data yang akan dihapus.',
            'reason.min' => 'Alasan penghapusan minimal 10 karakter.',
        ]);

        if ($data['delete_mode'] === 'all' && $data['confirmation'] !== 'HAPUS SEMUA') {
            return back()->withErrors(['confirmation' => 'Ketik HAPUS SEMUA secara persis untuk melanjutkan.'])->withInput();
        }

        $deleted = $data['delete_mode'] === 'all'
            ? $maintenance->deleteAll($data['dataset'], $request->user(), $data['reason'])
            : $maintenance->deleteSelected($data['dataset'], $data['record_ids'] ?? [], $request->user(), $data['reason']);

        return redirect()->route('admin.data-maintenance.index', ['dataset' => $data['dataset']])
            ->with('success', "{$deleted} data berhasil dihapus secara permanen.");
    }

    public function productionReset(Request $request, AdminDataMaintenanceService $maintenance): RedirectResponse
    {
        abort_unless(config('app.production_data_reset_enabled'), 404);

        $data = $request->validate([
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'current_password'],
        ], [
            'reason.min' => 'Alasan reset minimal 10 karakter.',
        ]);

        if ($data['confirmation'] !== 'RESET PRODUKSI') {
            return back()
                ->withErrors(['confirmation' => 'Ketik RESET PRODUKSI secara persis untuk melanjutkan.'])
                ->withInput($request->except('current_password'));
        }

        try {
            $result = $maintenance->resetForProduction(
                $request->user(),
                $data['reason'],
                $request->hasSession() ? $request->session()->getId() : '',
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withErrors([
                    'production_reset' => 'Backup terenkripsi gagal dibuat atau diverifikasi. Tidak ada data yang dihapus. Periksa konfigurasi backup dan log server.',
                ])
                ->withInput($request->except('current_password'));
        }

        return redirect()->route('admin.data-maintenance.index')
            ->with('production_reset_result', $result)
            ->with('success', "Reset go-live selesai. {$result['deleted']} data dihapus setelah backup {$result['backup']} dibuat.");
    }
}
