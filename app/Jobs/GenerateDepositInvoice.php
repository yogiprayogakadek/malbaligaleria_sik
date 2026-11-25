<?php

namespace App\Jobs;

use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDepositInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $permitId) {}

    public function handle(DepositInvoiceService $invoices, WorkPermitWorkflowNotifier $notifier): void
    {
        $permit = WorkPermit::query()->find($this->permitId);
        if (! $permit || $permit->status !== 'awaiting_invoice' || $permit->deposit_invoice_path) {
            return;
        }

        try {
            $permit = $invoices->issue($permit);
            if (! $permit->wasChanged('deposit_invoice_path')) {
                return;
            }
            $notifier->notifyApplicant(
                $permit,
                'Invoice Security Deposit Tersedia',
                'Invoice security deposit sebesar Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.').' tersedia pada halaman status.',
                true,
            );
        } catch (Throwable $exception) {
            Log::error('Invoice deposit otomatis gagal diterbitkan.', [
                'work_permit_id' => $permit->id,
                'exception' => $exception->getMessage(),
            ]);
            $notifier->notifyDivision(
                $permit,
                'FIN',
                'Invoice Deposit Perlu Diunggah Manual',
                "Invoice otomatis #{$permit->permit_number} gagal dibuat. Unggah invoice melalui antrean Finance.",
                'finance',
                WorkPermit::query()->where('assigned_division', 'MEP')->whereIn('status', ['awaiting_invoice', 'payment_review'])->count(),
            );
        }
    }
}
