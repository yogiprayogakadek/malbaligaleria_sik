<?php

namespace App\Http\Controllers;

use App\Models\EventRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEventRequirementController extends Controller
{
    public function index(): View
    {
        return view('admin.event-requirements.index', [
            'requirements' => EventRequirement::query()->orderBy('sort_order')->orderBy('group_name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EventRequirement::query()->create($this->validated($request));

        return back()->with('success', 'Kebutuhan Event berhasil ditambahkan.');
    }

    public function update(Request $request, EventRequirement $eventRequirement): RedirectResponse
    {
        $eventRequirement->update($this->validated($request, $eventRequirement));

        return back()->with('success', 'Kebutuhan Event berhasil diperbarui.');
    }

    public function destroy(EventRequirement $eventRequirement): RedirectResponse
    {
        $eventRequirement->delete();

        return back()->with('success', 'Kebutuhan Event dihapus. Surat yang sudah terbit tidak berubah.');
    }

    private function validated(Request $request, ?EventRequirement $requirement = null): array
    {
        $data = $request->validate([
            'group_name' => ['required', 'string', 'min:2', 'max:80'],
            'name' => [
                'required', 'string', 'min:2', 'max:160',
                Rule::unique('event_requirements')->where(fn ($query) => $query->where('group_name', trim((string) $request->input('group_name'))))->ignore($requirement),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['group_name'] = trim($data['group_name']);
        $data['name'] = trim($data['name']);

        return $data;
    }
}
