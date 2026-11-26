<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Jobs\GenerateDepositInvoice;
use App\Models\DepositInvoiceSetting;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminDepositInvoiceSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.deposit-invoices', [
            'setting' => DepositInvoiceSetting::current()->load('accounts'),
            'backlogCount' => WorkPermit::query()->where('status', 'awaiting_invoice')->where('deposit_required', true)->count(),
        ]);
    }

    public function update(Request $request, MalwareScanner $malwareScanner): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in([DepositInvoiceSetting::MODE_MANUAL, DepositInvoiceSetting::MODE_AUTOMATIC])],
            'issuer_name' => ['required', 'string', 'max:150'],
            'issuer_title' => ['required', 'string', 'max:150'],
            'signature_method' => ['required', Rule::in([DepositInvoiceSetting::SIGNATURE_BARCODE, DepositInvoiceSetting::SIGNATURE_UPLOAD])],
            'signature_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_signature' => ['nullable', 'boolean'],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
            'accounts' => ['nullable', 'array', 'max:10'],
            'accounts.*.bank_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.account_number' => ['nullable', 'string', 'max:80'],
            'accounts.*.account_holder' => ['nullable', 'string', 'max:150'],
            'current_password' => ['required', 'current_password'],
        ], ['current_password.current_password' => 'Kata sandi admin tidak sesuai.']);

        $accounts = collect($data['accounts'] ?? [])
            ->map(fn (array $account): array => Arr::map($account, fn ($value) => trim((string) $value)))
            ->filter(fn (array $account): bool => collect($account)->filter()->isNotEmpty())
            ->values();

        foreach ($accounts as $index => $account) {
            if (blank($account['bank_name'] ?? null) || blank($account['account_number'] ?? null) || blank($account['account_holder'] ?? null)) {
                throw ValidationException::withMessages(["accounts.{$index}.bank_name" => 'Nama bank, nomor rekening, dan pemilik rekening wajib diisi lengkap.']);
            }
        }
        if ($data['mode'] === DepositInvoiceSetting::MODE_AUTOMATIC && $accounts->isEmpty()) {
            throw ValidationException::withMessages(['accounts' => 'Tambahkan minimal satu rekening sebelum mengaktifkan invoice otomatis.']);
        }

        $setting = DepositInvoiceSetting::current();
        $signatureImage = $request->file('signature_image');
        $removeSignature = $request->boolean('remove_signature') && ! $signatureImage;
        $existingSignatureValid = false;
        if ($setting->signature_path && $setting->signature_sha256 && Storage::disk('local')->exists($setting->signature_path)) {
            $actualSignatureHash = hash_file('sha256', Storage::disk('local')->path($setting->signature_path));
            $existingSignatureValid = is_string($actualSignatureHash)
                && hash_equals($setting->signature_sha256, $actualSignatureHash);
        }
        if ($data['signature_method'] === DepositInvoiceSetting::SIGNATURE_UPLOAD
            && ! $signatureImage
            && ($removeSignature || ! $existingSignatureValid)) {
            throw ValidationException::withMessages(['signature_image' => 'Unggah gambar tanda tangan untuk menggunakan metode ini.']);
        }

        $newSignaturePath = null;
        $newSignatureHash = null;
        if ($signatureImage) {
            $malwareScanner->scan($signatureImage, 'signature_image');
            $newSignatureHash = hash_file('sha256', $signatureImage->getRealPath());
            if ($newSignatureHash === false) {
                throw ValidationException::withMessages(['signature_image' => 'Checksum gambar tanda tangan tidak dapat dihitung.']);
            }
            $newSignaturePath = $signatureImage->storeAs(
                'settings/deposit-invoice-signatures',
                Str::uuid().'.'.strtolower($signatureImage->extension()),
                'local',
            );
            if (! $newSignaturePath) {
                throw ValidationException::withMessages(['signature_image' => 'Gambar tanda tangan gagal disimpan.']);
            }
        }

        $wasAutomatic = $setting->isAutomatic();
        $oldSignaturePath = $setting->signature_path;
        try {
            DB::transaction(function () use ($setting, $data, $accounts, $newSignaturePath, $newSignatureHash, $removeSignature): void {
                $signatureUpdate = [];
                if ($newSignaturePath) {
                    $signatureUpdate = ['signature_path' => $newSignaturePath, 'signature_sha256' => $newSignatureHash];
                } elseif ($removeSignature) {
                    $signatureUpdate = ['signature_path' => null, 'signature_sha256' => null];
                }

                $setting->update([
                    'mode' => $data['mode'],
                    'issuer_name' => trim($data['issuer_name']),
                    'issuer_title' => trim($data['issuer_title']),
                    'signature_method' => $data['signature_method'],
                    'payment_instructions' => trim((string) ($data['payment_instructions'] ?? '')) ?: null,
                    ...$signatureUpdate,
                ]);
                $setting->accounts()->delete();
                $accounts->each(fn (array $account, int $index) => $setting->accounts()->create([...$account, 'sort_order' => $index]));
            });
        } catch (Throwable $exception) {
            if ($newSignaturePath) {
                Storage::disk('local')->delete($newSignaturePath);
            }
            throw $exception;
        }

        if (($newSignaturePath || $removeSignature) && $oldSignaturePath && $oldSignaturePath !== $newSignaturePath) {
            Storage::disk('local')->delete($oldSignaturePath);
        }

        if (! $wasAutomatic && $setting->fresh()->isAutomatic()) {
            WorkPermit::query()
                ->where('status', 'awaiting_invoice')
                ->where('deposit_required', true)
                ->whereNull('deposit_invoice_path')
                ->select('id')
                ->chunkById(100, fn ($permits) => $permits->each(fn (WorkPermit $permit) => GenerateDepositInvoice::dispatch($permit->id)->afterCommit()));
        }

        return redirect()->route('admin.settings.deposit-invoices.edit')->with('success', 'Pengaturan invoice deposit berhasil disimpan.');
    }
}
