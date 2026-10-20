<?php

namespace App\Http\Controllers;

use App\Models\WorkPermitCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminWorkPermitCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.work-categories.index', [
            'categories' => WorkPermitCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.work-categories.form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => str($request->string('code')->toString())->slug('_')->limit(60)->toString(),
        ]);
        $request->validate(['code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:work_permit_categories,code']]);
        $data = $this->validated($request);
        $data['code'] = $request->string('code')->toString();

        WorkPermitCategory::query()->create($data);

        return redirect()->route('admin.work-categories.index')->with('success', 'Kategori pekerjaan berhasil ditambahkan.');
    }

    public function edit(WorkPermitCategory $workCategory): View
    {
        return view('admin.work-categories.form', ['category' => $workCategory]);
    }

    public function update(Request $request, WorkPermitCategory $workCategory): RedirectResponse
    {
        $data = $this->validated($request);
        if ($workCategory->is_system && ! $data['is_active']) {
            return back()->withInput()->withErrors(['is_active' => 'Kategori sistem wajib tetap aktif.']);
        }

        $workCategory->update($data);

        return redirect()->route('admin.work-categories.index')->with('success', 'Kategori pekerjaan berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'division' => ['required', Rule::in(['TR', 'MEP', 'EP'])],
            'max_duration_days' => ['required', 'integer', 'between:1,30'],
            'sort_order' => ['required', 'integer', 'between:0,1000'],
            'is_active' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ]);

        unset($data['current_password']);

        return $data;
    }
}
