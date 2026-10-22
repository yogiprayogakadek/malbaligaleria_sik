<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSetting;
use App\Services\DocumentConverterAddress;
use App\Services\DocxTemplateInspector;
use App\Services\LibreOfficeConverterClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminDocumentTemplateController extends Controller
{
    public function index(DocxTemplateInspector $inspector): View
    {
        return view('admin.document-templates.index', [
            'setting' => DocumentTemplateSetting::current(),
            'templates' => DocumentTemplate::query()->with('uploader')->latest('version')->get()->groupBy('permit_type'),
            'permitTypes' => config('document-templates.permit_types'),
            'placeholders' => collect(DocumentTemplate::PERMIT_TYPES)->mapWithKeys(
                fn (string $type): array => [$type => $inspector->supportedFor($type)],
            ),
        ]);
    }

    public function update(Request $request, DocumentConverterAddress $address): RedirectResponse
    {
        $data = $request->validate([
            'converter_enabled' => ['required', 'boolean'],
            'converter_scheme' => ['required', Rule::in(['http', 'https'])],
            'converter_host' => ['required', 'string', 'max:45'],
            'converter_port' => ['required', 'integer', 'between:1,65535'],
            'converter_token' => ['nullable', 'string', 'min:32', 'max:512'],
            'converter_timeout' => ['required', 'integer', 'between:10,120'],
            'loading_renderer' => ['required', Rule::in(DocumentTemplateSetting::RENDERERS)],
            'work_renderer' => ['required', Rule::in(DocumentTemplateSetting::RENDERERS)],
            'event_renderer' => ['required', Rule::in(DocumentTemplateSetting::RENDERERS)],
            'fallback_enabled' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password'],
        ]);
        $address->validate($data['converter_host']);

        $current = DocumentTemplateSetting::current();
        if ($request->boolean('converter_enabled') && blank($data['converter_token'] ?? null) && blank($current->converter_token)) {
            throw ValidationException::withMessages(['converter_token' => 'Token minimal 32 karakter wajib diisi saat converter diaktifkan.']);
        }
        foreach (DocumentTemplate::PERMIT_TYPES as $type) {
            if ($data["{$type}_renderer"] === 'libreoffice' && ! DocumentTemplate::activeFor($type)) {
                throw ValidationException::withMessages(["{$type}_renderer" => 'Unggah template DOCX aktif sebelum memilih renderer LibreOffice.']);
            }
        }

        $payload = collect($data)->except(['converter_token', 'current_password'])->all();
        $payload['converter_enabled'] = $request->boolean('converter_enabled');
        $payload['fallback_enabled'] = $request->boolean('fallback_enabled');
        if ($request->filled('converter_token')) {
            $payload['converter_token'] = $data['converter_token'];
        }
        DocumentTemplateSetting::query()->updateOrCreate(['id' => 1], $payload);
        Cache::forget('document-converter:unavailable');

        return back()->with('success', 'Konfigurasi template dokumen berhasil disimpan.');
    }

    public function test(Request $request, LibreOfficeConverterClient $converter): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $setting = DocumentTemplateSetting::current();
        if (! $setting->exists || ! $setting->converter_enabled) {
            return back()->with('converter_error', 'Simpan dan aktifkan konfigurasi converter terlebih dahulu.');
        }

        try {
            $health = $converter->health($setting);
            $message = 'Converter aktif'.(filled($health['version'] ?? null) ? ' (LibreOffice '.$health['version'].').' : '.');
            $setting->update([
                'last_tested_at' => now(),
                'last_test_succeeded' => true,
                'last_test_message' => $message,
            ]);
            Cache::forget('document-converter:unavailable');

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            Log::warning('Pengujian document converter gagal.', [
                'admin_id' => $request->user()->id,
                'converter_host' => $setting->converter_host,
                'exception' => $exception->getMessage(),
            ]);
            $setting->update([
                'last_tested_at' => now(),
                'last_test_succeeded' => false,
                'last_test_message' => 'Converter tidak dapat dihubungi.',
            ]);

            return back()->with('converter_error', 'Koneksi gagal. Periksa IP, port, token, firewall, dan layanan converter.');
        }
    }

    public function store(Request $request, MalwareScanner $malwareScanner, DocxTemplateInspector $inspector): RedirectResponse
    {
        $data = $request->validate([
            'permit_type' => ['required', Rule::in(DocumentTemplate::PERMIT_TYPES)],
            'template' => ['required', 'file', 'extensions:docx', 'max:'.config('document-templates.max_upload_kilobytes')],
            'current_password' => ['required', 'current_password'],
        ]);
        $file = $request->file('template');
        $malwareScanner->scan($file, 'template');
        $placeholders = $inspector->inspect($file, $data['permit_type']);
        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw ValidationException::withMessages(['template' => 'Checksum template tidak dapat dihitung.']);
        }

        $path = $file->storeAs(
            'document-templates/'.$data['permit_type'],
            Str::uuid().'.docx',
            'local',
        );
        if (! $path) {
            throw ValidationException::withMessages(['template' => 'Template gagal disimpan.']);
        }

        try {
            DB::transaction(function () use ($request, $data, $file, $path, $hash, $placeholders): void {
                $existing = DocumentTemplate::query()
                    ->where('permit_type', $data['permit_type'])
                    ->lockForUpdate()
                    ->get();
                $version = (int) $existing->max('version') + 1;
                if ($existing->isNotEmpty()) {
                    DocumentTemplate::query()->whereKey($existing->modelKeys())->update(['is_active' => false]);
                }
                DocumentTemplate::query()->create([
                    'permit_type' => $data['permit_type'],
                    'version' => $version,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'storage_path' => $path,
                    'sha256' => $hash,
                    'size' => $file->getSize(),
                    'placeholders' => $placeholders,
                    'uploaded_by' => $request->user()->id,
                    'is_active' => true,
                    'activated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Template DOCX berhasil diperiksa, disimpan, dan diaktifkan.');
    }

    public function activate(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_unless(Storage::disk('local')->exists($documentTemplate->storage_path), 409, 'File template tidak ditemukan.');

        DB::transaction(function () use ($documentTemplate): void {
            DocumentTemplate::query()->where('permit_type', $documentTemplate->permit_type)->update(['is_active' => false]);
            $documentTemplate->update(['is_active' => true, 'activated_at' => now()]);
        });

        return back()->with('success', 'Versi template berhasil diaktifkan.');
    }

    public function download(DocumentTemplate $documentTemplate): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($documentTemplate->storage_path), 404);

        return Storage::disk('local')->download(
            $documentTemplate->storage_path,
            Str::slug($documentTemplate->typeLabel()).'-v'.$documentTemplate->version.'.docx',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
