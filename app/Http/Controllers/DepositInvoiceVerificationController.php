<?php

namespace App\Http\Controllers;

use App\Models\WorkPermit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepositInvoiceVerificationController extends Controller
{
    public function __invoke(string $documentId): Response
    {
        abort_unless(Str::isUuid($documentId), 404);

        $permit = WorkPermit::query()
            ->where('deposit_invoice_document_id', $documentId)
            ->where('deposit_invoice_source', 'generated')
            ->firstOrFail();

        $integrityValid = false;
        if ($permit->deposit_invoice_path && $permit->deposit_invoice_sha256) {
            $disk = Storage::disk('local');
            if ($disk->exists($permit->deposit_invoice_path)) {
                $actualHash = hash('sha256', $disk->get($permit->deposit_invoice_path));
                $integrityValid = hash_equals($permit->deposit_invoice_sha256, $actualHash);
            }
        }

        return response()->view('documents.deposit-invoice-verification', [
            'permit' => $permit,
            'documentId' => $documentId,
            'valid' => $integrityValid,
        ], $integrityValid ? 200 : 409)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
