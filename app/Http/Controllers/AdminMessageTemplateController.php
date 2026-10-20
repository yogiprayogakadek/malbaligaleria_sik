<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminMessageTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.message-templates.index', ['templates' => MessageTemplate::query()->orderBy('name')->get()]);
    }

    public function edit(MessageTemplate $messageTemplate): View
    {
        return view('admin.message-templates.form', ['template' => $messageTemplate]);
    }

    public function update(Request $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $messageTemplate->update($this->validated($request));

        return redirect()->route('admin.message-templates.index')->with('success', 'Template pesan berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password'],
        ]);
        $allowed = $request->route('message_template')->allowed_variables ?: [];
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', ($data['subject'] ?? '').' '.$data['body'], $matches);
        $unsupported = array_values(array_diff(array_unique($matches[1] ?? []), $allowed));
        if ($unsupported !== []) {
            throw ValidationException::withMessages([
                'body' => 'Variabel tidak didukung: '.implode(', ', $unsupported).'.',
            ]);
        }
        unset($data['current_password']);

        return $data;
    }
}
