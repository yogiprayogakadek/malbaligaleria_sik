<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminMessageTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.message-templates.index', ['templates' => MessageTemplate::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.message-templates.form', [
            'template' => null,
            'availableVariables' => $this->availableVariables(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MessageTemplate::query()->create($this->validated($request));

        return redirect()->route('admin.message-templates.index')->with('success', 'Template pesan berhasil ditambahkan.');
    }

    public function edit(MessageTemplate $messageTemplate): View
    {
        return view('admin.message-templates.form', [
            'template' => $messageTemplate,
            'availableVariables' => $this->availableVariables(),
        ]);
    }

    public function update(Request $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $messageTemplate->update($this->validated($request, $messageTemplate));

        return redirect()->route('admin.message-templates.index')->with('success', 'Template pesan berhasil diperbarui.');
    }

    private function validated(Request $request, ?MessageTemplate $messageTemplate = null): array
    {
        $data = $request->validate([
            'key' => [$messageTemplate ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('message_templates', 'key')->ignore($messageTemplate?->id)],
            'name' => ['required', 'string', 'max:120'],
            'channel' => [$messageTemplate ? 'sometimes' : 'required', Rule::in(['all', 'email', 'whatsapp', 'push', 'webhook'])],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:3000'],
            'allowed_variables' => ['nullable', 'array', 'max:12'],
            'allowed_variables.*' => ['string', Rule::in(array_keys($this->availableVariables()))],
            'is_active' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password'],
        ], [
            'key.regex' => 'Kunci hanya boleh berisi huruf kecil, angka, dan garis bawah.',
        ]);
        $allowed = array_values($data['allowed_variables'] ?? []);
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', ($data['subject'] ?? '').' '.$data['body'], $matches);
        $unsupported = array_values(array_diff(array_unique($matches[1] ?? []), $allowed));
        if ($unsupported !== []) {
            throw ValidationException::withMessages([
                'body' => 'Variabel tidak didukung: '.implode(', ', $unsupported).'.',
            ]);
        }
        unset($data['current_password']);
        $data['allowed_variables'] = $allowed;

        if ($messageTemplate) {
            unset($data['key'], $data['channel']);
        } else {
            $data['is_system'] = false;
        }

        return $data;
    }

    /** @return array<string, string> */
    private function availableVariables(): array
    {
        return [
            'applicant_name' => 'Nama pemohon',
            'permit_number' => 'Nomor permohonan',
            'tenant_name' => 'Nama tenant',
            'permit_type' => 'Jenis permohonan',
            'status' => 'Status permohonan',
            'division' => 'Divisi pemeriksa',
            'status_url' => 'Tautan cek status',
            'notes' => 'Catatan pemeriksa',
            'date' => 'Tanggal',
            'component' => 'Komponen sistem',
            'detail' => 'Detail gangguan',
            'detected_at' => 'Waktu gangguan',
        ];
    }
}
