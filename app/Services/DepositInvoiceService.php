<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DepositInvoiceSetting;
use App\Models\WorkPermit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class DepositInvoiceService
{
    public function issue(WorkPermit $permit): WorkPermit
    {
        $setting = DepositInvoiceSetting::current()->load('accounts');
        $storedPath = null;

        try {
            if (! $setting->isAutomatic() || $setting->accounts->isEmpty()) {
                throw new RuntimeException('Penerbitan invoice otomatis belum dikonfigurasi.');
            }
            if (! in_array($setting->signature_method, [DepositInvoiceSetting::SIGNATURE_BARCODE, DepositInvoiceSetting::SIGNATURE_UPLOAD], true)
                || blank($setting->issuer_name)
                || blank($setting->issuer_title)) {
                throw new RuntimeException('Identitas penerbit invoice belum dikonfigurasi.');
            }

            return DB::transaction(function () use ($permit, $setting, &$storedPath): WorkPermit {
                $locked = WorkPermit::query()->lockForUpdate()->findOrFail($permit->id);
                if ($locked->deposit_invoice_path) {
                    return $locked;
                }
                if ($locked->status !== 'awaiting_invoice' || ! $locked->deposit_required) {
                    throw new RuntimeException('Permohonan tidak sedang menunggu invoice.');
                }

                $number = $locked->deposit_invoice_number ?: $this->nextNumber();
                $issuedAt = now();
                $dueDate = $locked->start_date->isAfter($issuedAt->copy()->startOfDay())
                    ? $locked->start_date->toDateString()
                    : $issuedAt->toDateString();
                $documentId = (string) Str::uuid();
                $signatureMethod = $setting->signature_method ?: DepositInvoiceSetting::SIGNATURE_BARCODE;
                $verificationUrl = route('deposit-invoices.verify', ['documentId' => $documentId]);
                $signatureDataUri = $signatureMethod === DepositInvoiceSetting::SIGNATURE_UPLOAD
                    ? $this->uploadedSignatureDataUri($setting)
                    : $this->qrDataUri($verificationUrl);
                $content = Pdf::loadView('pdf.deposit-invoice', [
                    'permit' => $locked,
                    'setting' => $setting,
                    'accounts' => $setting->accounts,
                    'invoiceNumber' => $number,
                    'issuedAt' => $issuedAt,
                    'dueDate' => $dueDate,
                    'logoDataUri' => $this->logoDataUri(),
                    'documentId' => $documentId,
                    'issuerName' => $setting->issuer_name,
                    'issuerTitle' => $setting->issuer_title,
                    'signatureMethod' => $signatureMethod,
                    'signatureDataUri' => $signatureDataUri,
                ])->setPaper('a4')->output();
                $hash = hash('sha256', $content);
                $storedPath = "permits/work/deposit-invoices/{$locked->id}-{$hash}.pdf";
                if (! Storage::disk('local')->put($storedPath, $content)) {
                    throw new RuntimeException('Invoice otomatis gagal disimpan.');
                }

                $locked->update([
                    'status' => 'awaiting_payment',
                    'deposit_invoice_path' => $storedPath,
                    'deposit_invoice_type' => 'application/pdf',
                    'deposit_invoice_uploaded_by' => null,
                    'deposit_invoice_uploaded_at' => $issuedAt,
                    'deposit_invoice_number' => $number,
                    'deposit_invoice_issued_at' => $issuedAt,
                    'deposit_invoice_due_date' => $dueDate,
                    'deposit_invoice_source' => 'generated',
                    'deposit_invoice_sha256' => $hash,
                    'deposit_invoice_document_id' => $documentId,
                    'deposit_invoice_issuer_name' => $setting->issuer_name,
                    'deposit_invoice_issuer_title' => $setting->issuer_title,
                    'deposit_invoice_signature_method' => $signatureMethod,
                ]);
                $locked->statusLogs()->create([
                    'from_status' => 'awaiting_invoice',
                    'to_status' => 'awaiting_payment',
                    'action' => 'deposit_invoice_generated',
                    'metadata' => [
                        'deposit_amount' => $locked->deposit_amount,
                        'invoice_number' => $number,
                        'document_id' => $documentId,
                        'signature_method' => $signatureMethod,
                    ],
                ]);

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            $current = WorkPermit::query()->find($permit->id);
            if ($current?->status === 'awaiting_invoice') {
                $current->statusLogs()->create([
                    'actor_id' => Auth::id(),
                    'from_status' => 'awaiting_invoice',
                    'to_status' => 'awaiting_invoice',
                    'action' => 'deposit_invoice_generation_failed',
                    'notes' => 'Invoice otomatis gagal dibuat dan memerlukan unggahan Finance.',
                ]);
                AuditLog::query()->create([
                    'actor_id' => Auth::id(),
                    'event' => 'deposit_invoice_generation_failed',
                    'auditable_type' => WorkPermit::class,
                    'auditable_id' => $current->id,
                    'route_name' => request()?->route()?->getName(),
                    'request_method' => request()?->method(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'old_values' => [],
                    'new_values' => ['status' => 'awaiting_invoice', 'fallback' => 'manual'],
                ]);
            }

            throw $exception;
        }
    }

    public function registerUpload(WorkPermit $permit, string $path, string $mimeType, ?int $uploaderId): void
    {
        $hash = hash_file('sha256', Storage::disk('local')->path($path));
        if ($hash === false) {
            throw new RuntimeException('Hash invoice gagal dibuat.');
        }

        $permit->update([
            'status' => 'awaiting_payment',
            'deposit_invoice_path' => $path,
            'deposit_invoice_type' => $mimeType,
            'deposit_invoice_uploaded_by' => $uploaderId,
            'deposit_invoice_uploaded_at' => now(),
            'deposit_invoice_number' => $permit->deposit_invoice_number ?: $this->nextNumber(),
            'deposit_invoice_issued_at' => $permit->deposit_invoice_issued_at ?: now(),
            'deposit_invoice_due_date' => $permit->deposit_invoice_due_date ?: ($permit->start_date->isAfter(now()->startOfDay()) ? $permit->start_date : now())->toDateString(),
            'deposit_invoice_source' => 'uploaded',
            'deposit_invoice_sha256' => $hash,
        ]);
    }

    public function assertIntegrity(WorkPermit $permit): void
    {
        if (! $permit->deposit_invoice_sha256) {
            return;
        }

        $path = $permit->deposit_invoice_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $actualHash = hash_file('sha256', Storage::disk('local')->path($path));
        abort_unless(is_string($actualHash) && hash_equals($permit->deposit_invoice_sha256, $actualHash), 409, 'Integritas invoice tidak valid.');
    }

    private function nextNumber(): string
    {
        $period = now()->format('Y-m');
        $counter = DB::table('permit_number_sequences')
            ->where('permit_type', 'deposit_invoice')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            DB::table('permit_number_sequences')->insertOrIgnore([
                'permit_type' => 'deposit_invoice',
                'period' => $period,
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $counter = DB::table('permit_number_sequences')
                ->where('permit_type', 'deposit_invoice')
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $next = (int) $counter->current_value + 1;
        DB::table('permit_number_sequences')->where('id', $counter->id)->update([
            'current_value' => $next,
            'updated_at' => now(),
        ]);

        return 'DEP/'.now()->format('Y/m').'/'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function logoDataUri(): string
    {
        $path = public_path('logo.png');

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    private function qrDataUri(string $url): string
    {
        $qrCode = (string) QrCode::format('svg')
            ->errorCorrection('H')
            ->size(132)
            ->margin(0)
            ->generate($url);

        return 'data:image/svg+xml;base64,'.base64_encode($qrCode);
    }

    private function uploadedSignatureDataUri(DepositInvoiceSetting $setting): string
    {
        if (! $setting->signature_path || ! $setting->signature_sha256) {
            throw new RuntimeException('Gambar tanda tangan penerbit belum dikonfigurasi.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($setting->signature_path)) {
            throw new RuntimeException('Gambar tanda tangan penerbit tidak ditemukan.');
        }

        $content = $disk->get($setting->signature_path);
        $actualHash = hash('sha256', $content);
        if (! hash_equals($setting->signature_sha256, $actualHash)) {
            throw new RuntimeException('Integritas gambar tanda tangan penerbit tidak valid.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Format gambar tanda tangan penerbit tidak didukung.');
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($content);
    }
}
