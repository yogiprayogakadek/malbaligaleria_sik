<?php

namespace App\Http\Controllers;

use App\Services\AdminDataMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
}
