<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\DepositInvoiceSetting;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Services\DepositInvoiceService;
use App\Services\PermitAssignmentService;
use App\Services\PermitPdfService;
use App\Services\PermitRevisionService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MEPController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'action', 'revision_required', 'approved', 'completed', 'refunded', 'rejected'], true)
            ? $request->query('status')
            : 'action';

        $query = WorkPermit::query()->where('assigned_division', 'MEP')->withCount('workers')->slaPriority();
        if ($status === 'action') {
            $query->needsMepAction();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        [$perPage, $perPageRaw] = $this->tablePageSize($request);

        return view('mep.index', [
            'permits' => $query->paginate($perPage)->withQueryString(),
            'perPageRaw' => $perPageRaw,
            'status' => $status,
            'counts' => [
                'all' => WorkPermit::where('assigned_division', 'MEP')->count(),
                'action' => WorkPermit::needsMepAction()->count(),
                'revision_required' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'revision_required')->count(),
                'approved' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'approved')->count(),
                'completed' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'completed')->count(),
                'refunded' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'refunded')->count(),
                'rejected' => WorkPermit::where('assigned_division', 'MEP')->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function decideDeposit(
        Request $request,
        string $token,
        WorkPermitWorkflowNotifier $notifier,
        PermitAssignmentService $assignments,
        PermitRevisionService $revisions,
        PermitPdfService $pdf,
        DepositInvoiceService $invoices,
    ): RedirectResponse {
        $requiredInput = $request->boolean('deposit_required');
        if ($request->has('deposit_amount')) {
            $request->merge([
                'deposit_amount' => WorkPermit::normalizeRupiahAmount($request->input('deposit_amount')),
            ]);
        }
        if ($requiredInput) {
            if (is_string($request->input('deposit_invoice_recipient_name'))) {
                $request->merge([
                    'deposit_invoice_recipient_name' => WorkPermit::normalizeDepositInvoiceRecipientName($request->input('deposit_invoice_recipient_name')),
                ]);
            }
            if (is_string($request->input('deposit_invoice_recipient_address'))) {
                $request->merge([
                    'deposit_invoice_recipient_address' => WorkPermit::normalizeDepositInvoiceRecipientAddress($request->input('deposit_invoice_recipient_address')),
                ]);
            }
        }

        $data = $request->validate([
            'deposit_required' => ['required', 'boolean'],
            'deposit_amount' => [Rule::requiredIf($requiredInput), 'nullable', 'numeric', 'min:1', 'max:999999999999.99'],
            'deposit_notes' => ['nullable', 'string', 'max:1000'],
            'deposit_invoice_recipient_name' => $requiredInput
                ? ['nullable', 'string', 'max:'.WorkPermit::DEPOSIT_INVOICE_RECIPIENT_NAME_MAX]
                : ['nullable'],
            'deposit_invoice_recipient_address' => $requiredInput
                ? ['nullable', 'string', 'max:'.WorkPermit::DEPOSIT_INVOICE_RECIPIENT_ADDRESS_MAX]
                : ['nullable'],
            'has_revision' => ['sometimes', 'boolean'],
            'coordination_required' => ['sometimes', 'boolean'],
            'coordination_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'deposit_invoice_recipient_name.max' => 'Nama penerima / perusahaan maksimal 200 karakter.',
            'deposit_invoice_recipient_address.max' => 'Alamat penagihan maksimal 500 karakter.',
        ]);

        $fields = $request->boolean('has_revision')
            ? array_values(array_diff((array) $request->input('fields', []), [PermitRevisionService::MEP_COORDINATION_FIELD]))
            : [];
        $reasons = (array) $request->input('reasons', []);
        if ($request->boolean('coordination_required')) {
            $fields[] = PermitRevisionService::MEP_COORDINATION_FIELD;
            $reasons[PermitRevisionService::MEP_COORDINATION_FIELD] = (string) $request->input('coordination_notes');
        }
        $selected = $request->boolean('has_revision') || $request->boolean('coordination_required')
            ? $revisions->validateSelection(
                WorkPermit::where('public_token', $token)->firstOrFail(),
                $fields,
                $reasons,
            )
            : [];

        [$permit, $revisionItems] = DB::transaction(function () use ($request, $token, $data, $selected, $assignments, $revisions, $pdf, $invoices): array {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'mep_review', 409, 'Keputusan Engineering sudah dibuat.');
            abort_if($permit->hasConfirmedDepositInvoiceRecipient(), 409, 'Tujuan penagihan invoice deposit sudah dikonfirmasi dan tidak dapat diubah.');
            $assignments->ensureWorkAssignee($permit, $request->user());

            $required = $request->boolean('deposit_required');
            $revisionItems = collect();
            $status = $required ? 'awaiting_invoice' : 'approved';
            $revisionReturnStatus = null;
            if ($selected !== []) {
                $revisionItems = $revisions->create($permit, $request->user(), $selected);
                $status = 'revision_required';
                $hasCoordination = array_key_exists(PermitRevisionService::MEP_COORDINATION_FIELD, $selected);
                $hasEditableRevision = count(array_diff(array_keys($selected), [PermitRevisionService::MEP_COORDINATION_FIELD])) > 0;
                $revisionReturnStatus = $hasCoordination && ! $hasEditableRevision
                    ? ($required ? 'awaiting_invoice' : 'mep_final_review')
                    : 'mep_review';
            }

            $confirmRecipient = $required && $status === 'awaiting_invoice';
            $recipientAttributes = $required
                ? $invoices->recipientAttributes(
                    $permit,
                    null,
                    $data['deposit_invoice_recipient_name'] ?? null,
                    $data['deposit_invoice_recipient_address'] ?? null,
                    $confirmRecipient,
                    $request->user()->id,
                )
                : $invoices->clearRecipientAttributes();

            $permit->update([
                ...$recipientAttributes,
                'security_deposit' => $required,
                'deposit_required' => $required,
                'deposit_amount' => $required ? $data['deposit_amount'] : null,
                'deposit_notes' => $data['deposit_notes'] ?? null,
                'deposit_set_by' => $request->user()->id,
                'deposit_set_at' => now(),
                'deposit_invoice_path' => null,
                'deposit_invoice_type' => null,
                'deposit_invoice_uploaded_by' => null,
                'deposit_invoice_uploaded_at' => null,
                'deposit_invoice_number' => null,
                'deposit_invoice_issued_at' => null,
                'deposit_invoice_due_date' => null,
                'deposit_invoice_source' => null,
                'deposit_invoice_sha256' => null,
                'status' => $status,
                'revision_return_status' => $revisionReturnStatus,
                'review_notes' => $selected !== [] ? 'Tindak lanjut diminta pada: '.$revisions->summary($permit, $revisionItems).'.' : ($data['deposit_notes'] ?? null),
                'reviewed_by' => $status === 'approved' ? $request->user()->id : null,
                'reviewed_at' => $status === 'approved' ? now() : null,
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'mep_review',
                'to_status' => $status,
                'action' => $selected !== [] ? 'revision_requested' : ($required ? 'deposit_requested' : 'approved_without_deposit'),
                'notes' => $selected !== [] ? $permit->review_notes : ($data['deposit_notes'] ?? null),
                'metadata' => [
                    'deposit_required' => $required,
                    'deposit_amount' => $required ? $data['deposit_amount'] : null,
                    'fields' => array_keys($selected),
                    'deposit_invoice_recipient' => $required ? $invoices->recipientAuditMetadata($permit) : null,
                ],
            ]);

            if ($confirmRecipient) {
                $invoices->recordRecipientConfirmationAudit($permit, $request->user()->id);
            }

            if ($status === 'approved') {
                $pdf->sealWorkPermit($permit);
            }

            return [$permit->fresh(), $revisionItems];
        });

        if ($revisionItems->isNotEmpty()) {
            $coordination = $revisionItems->firstWhere('field_key', PermitRevisionService::MEP_COORDINATION_FIELD);
            $editable = $revisionItems->where('field_key', '!=', PermitRevisionService::MEP_COORDINATION_FIELD);
            $message = $editable->isNotEmpty()
                ? 'Perbaiki field berikut sebelum pemeriksaan dilanjutkan: '.$revisions->summary($permit, $editable).'.'
                : null;
            if ($coordination) {
                $message = $revisions->coordinationMessage($coordination->reason, $message);
            }
            $notifier->notifyApplicantOnly(
                $permit,
                $editable->isEmpty() ? 'Surat Izin Kerja Memerlukan Koordinasi' : 'Surat Izin Kerja Perlu Ditindaklanjuti',
                $message,
            );

            return back()->with('info', 'Tindak lanjut Engineering telah dikirim. Keputusan deposit tersimpan dan akan diteruskan setelah tindak lanjut selesai.');
        }

        if ($permit->status === 'awaiting_invoice') {
            $permit = $this->processAwaitingInvoice($permit, $notifier, $invoices);

            return back()->with('success', $permit->status === 'awaiting_payment'
                ? 'Keputusan deposit tersimpan dan invoice otomatis telah diterbitkan.'
                : 'Keputusan deposit tersimpan dan permohonan telah dikirim ke Finance untuk mengunggah invoice.');
        }

        $notifier->notifyApplicant($permit, 'Permohonan Izin Kerja Disetujui', 'Permohonan telah disetujui tanpa security deposit.', true);

        return back()->with('success', 'Permohonan disetujui tanpa security deposit.');
    }

    public function completeCoordination(
        Request $request,
        string $token,
        PermitRevisionService $revisions,
        WorkPermitWorkflowNotifier $notifier,
        DepositInvoiceService $invoices,
    ): RedirectResponse {
        $permit = DB::transaction(function () use ($request, $token, $revisions, $invoices): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'revision_required', 409, 'Permohonan tidak sedang menunggu koordinasi MEP.');
            abort_unless($request->user()?->is_active && $request->user()->canValidateDivision('MEP'), 403);
            $active = $revisions->active($permit);
            $manual = $active->firstWhere('field_key', 'other_mep_coordination');
            abort_unless($manual, 409, 'Koordinasi MEP sudah diselesaikan.');

            $recipientPayload = null;
            if ($permit->deposit_required) {
                abort_if($permit->hasConfirmedDepositInvoiceRecipient(), 409, 'Tujuan penagihan invoice deposit sudah dikonfirmasi dan tidak dapat diubah.');

                $rawName = $request->input('deposit_invoice_recipient_name');
                $rawAddress = $request->input('deposit_invoice_recipient_address');
                $validatedInput = [
                    'deposit_invoice_recipient_mode' => $request->input('deposit_invoice_recipient_mode'),
                    'deposit_invoice_recipient_name' => is_string($rawName)
                        ? WorkPermit::normalizeDepositInvoiceRecipientName($rawName)
                        : $rawName,
                    'deposit_invoice_recipient_address' => is_string($rawAddress)
                        ? WorkPermit::normalizeDepositInvoiceRecipientAddress($rawAddress)
                        : $rawAddress,
                ];

                $recipientPayload = validator($validatedInput, [
                    'deposit_invoice_recipient_mode' => ['required', Rule::in([
                        WorkPermit::DEPOSIT_INVOICE_RECIPIENT_SOURCE_APPLICATION,
                        WorkPermit::DEPOSIT_INVOICE_RECIPIENT_SOURCE_CUSTOM,
                    ])],
                    'deposit_invoice_recipient_name' => ['nullable', 'string', 'max:'.WorkPermit::DEPOSIT_INVOICE_RECIPIENT_NAME_MAX],
                    'deposit_invoice_recipient_address' => ['nullable', 'string', 'max:'.WorkPermit::DEPOSIT_INVOICE_RECIPIENT_ADDRESS_MAX],
                ], [
                    'deposit_invoice_recipient_mode.required' => 'Pilih sumber tujuan penagihan invoice sebelum menyelesaikan koordinasi.',
                    'deposit_invoice_recipient_mode.in' => 'Pilihan sumber tujuan penagihan invoice tidak valid.',
                    'deposit_invoice_recipient_name.max' => 'Nama penerima / perusahaan maksimal 200 karakter.',
                    'deposit_invoice_recipient_address.max' => 'Alamat penagihan maksimal 500 karakter.',
                ])->validate();

                if ($recipientPayload['deposit_invoice_recipient_mode'] === WorkPermit::DEPOSIT_INVOICE_RECIPIENT_SOURCE_CUSTOM
                    && $recipientPayload['deposit_invoice_recipient_name'] === null
                    && $recipientPayload['deposit_invoice_recipient_address'] === null) {
                    throw ValidationException::withMessages([
                        'deposit_invoice_recipient_name' => 'Isi nama penerima / perusahaan atau alamat penagihan jika memilih gunakan data lain.',
                    ]);
                }
            }

            $revisions->resolveFields($permit, $manual->batch_id, ['other_mep_coordination']);
            $remaining = $revisions->active($permit);
            $returnStatus = 'revision_required';
            $updates = [];
            if ($remaining->isEmpty()) {
                $returnStatus = $permit->revision_return_status ?: 'mep_review';
                $updates = [
                    'status' => $returnStatus,
                    'revision_return_status' => null,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            }

            $confirmRecipient = false;
            if ($permit->deposit_required && $recipientPayload !== null) {
                $confirmRecipient = $remaining->isEmpty() && $returnStatus === 'awaiting_invoice';
                $updates += $invoices->recipientAttributes(
                    $permit,
                    $recipientPayload['deposit_invoice_recipient_mode'],
                    $recipientPayload['deposit_invoice_recipient_name'] ?? null,
                    $recipientPayload['deposit_invoice_recipient_address'] ?? null,
                    $confirmRecipient,
                    $request->user()->id,
                );
            }

            if ($updates !== []) {
                $permit->update($updates);
            }

            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'revision_required',
                'to_status' => $returnStatus,
                'action' => 'mep_coordination_completed',
                'metadata' => $permit->deposit_required
                    ? ['deposit_invoice_recipient' => $invoices->recipientAuditMetadata($permit)]
                    : null,
            ]);

            if ($confirmRecipient) {
                $invoices->recordRecipientConfirmationAudit($permit, $request->user()->id);
            }

            return $permit->fresh();
        });

        if ($permit->status === 'awaiting_invoice') {
            $permit = $this->processAwaitingInvoice($permit, $notifier, $invoices);

            return back()->with('success', $permit->status === 'awaiting_payment'
                ? 'Koordinasi selesai dan invoice otomatis telah diterbitkan.'
                : 'Koordinasi selesai dan Finance telah diminta menyiapkan invoice.');
        }

        $body = match ($permit->status) {
            'revision_required' => 'Koordinasi selesai. Lengkapi perbaikan data lain yang masih diminta.',
            'mep_final_review' => 'Koordinasi selesai. Permohonan menunggu keputusan akhir Engineering.',
            default => 'Koordinasi selesai dan permohonan kembali diperiksa Engineering.',
        };
        $notifier->notifyApplicantOnly($permit, 'Koordinasi dengan Office Management Selesai', $body);

        return back()->with('success', 'Koordinasi MEP ditandai selesai.');
    }

    public function finalDecision(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, PermitPdfService $pdf): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => [
                ...($request->input('decision') === 'rejected' ? ['required', 'min:10'] : ['nullable']),
                'string',
                'max:1000',
            ],
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data, $assignments, $pdf): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'mep_final_review', 409, 'Permohonan belum siap untuk keputusan akhir.');
            $assignments->ensureWorkAssignee($permit, $request->user());
            $from = $permit->status;
            $permit->update([
                'status' => $data['decision'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $permit->status,
                'action' => 'mep_final_decision',
                'notes' => $data['review_notes'] ?? null,
            ]);

            if ($permit->status === 'approved') {
                $pdf->sealWorkPermit($permit);
            }

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            $permit->status === 'approved' ? 'Permohonan Izin Kerja Disetujui' : 'Permohonan Izin Kerja Ditolak',
            $permit->status === 'approved' ? 'Engineering telah memberikan persetujuan akhir.' : ($permit->review_notes ?: 'Permohonan belum dapat disetujui.'),
            true,
        );

        return back()->with('success', 'Keputusan akhir Engineering berhasil disimpan. Pembaruan status telah dikirim kepada pemohon.');
    }

    public function complete(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments): RedirectResponse
    {
        $permit = DB::transaction(function () use ($request, $token, $assignments): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'approved', 409, 'Pekerjaan belum dapat ditandai selesai.');
            $assignments->ensureWorkAssignee($permit, $request->user());
            $permit->update(['status' => 'completed', 'completed_by' => $request->user()->id, 'completed_at' => now()]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'approved',
                'to_status' => 'completed',
                'action' => 'work_completed',
            ]);

            return $permit;
        });

        $notifier->notifyApplicant($permit, 'Pekerjaan Ditandai Selesai', $permit->deposit_required ? 'Management akan memproses pengembalian security deposit.' : 'Proses izin kerja telah selesai.');

        return back()->with('success', 'Pekerjaan telah ditandai selesai.');
    }

    public function startRefund(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments): RedirectResponse
    {
        $permit = DB::transaction(function () use ($request, $token, $assignments): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'completed' && $permit->deposit_required && $permit->payment_verified_at, 409);
            $assignments->ensureWorkAssignee($permit, $request->user());
            $permit->update(['status' => 'refund_processing']);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'completed',
                'to_status' => 'refund_processing',
                'action' => 'refund_started',
            ]);

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            'Pengembalian Deposit Diproses',
            'Management sedang memproses pengembalian security deposit.',
        );

        return back()->with('success', 'Proses pengembalian deposit dimulai.');
    }

    public function finishRefund(Request $request, string $token, WorkPermitWorkflowNotifier $notifier, PermitAssignmentService $assignments, MalwareScanner $malwareScanner): RedirectResponse
    {
        if ($request->has('refund_amount')) {
            $request->merge([
                'refund_amount' => WorkPermit::normalizeRupiahAmount($request->input('refund_amount')),
            ]);
        }

        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'refund_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $malwareScanner->scan($request->file('refund_proof'), 'refund_proof');
        $path = $request->file('refund_proof')->store('permits/work/refund-proofs', 'local');

        try {
            $permit = DB::transaction(function () use ($request, $token, $data, $path, $assignments): WorkPermit {
                $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
                abort_unless($permit->assigned_division === 'MEP' && $permit->status === 'refund_processing', 409);
                $assignments->ensureWorkAssignee($permit, $request->user());
                abort_if((float) $data['refund_amount'] > (float) $permit->deposit_amount, 422, 'Nominal pengembalian melebihi deposit.');
                $permit->update([
                    'status' => 'refunded',
                    'refund_amount' => $data['refund_amount'],
                    'refund_proof_path' => $path,
                    'refund_proof_type' => $request->file('refund_proof')->getMimeType(),
                    'refund_notes' => $data['refund_notes'] ?? null,
                    'refund_processed_by' => $request->user()->id,
                    'refund_processed_at' => now(),
                ]);
                $permit->statusLogs()->create([
                    'actor_id' => $request->user()->id,
                    'from_status' => 'refund_processing',
                    'to_status' => 'refunded',
                    'action' => 'refund_completed',
                    'notes' => $data['refund_notes'] ?? null,
                    'metadata' => ['refund_amount' => $data['refund_amount']],
                ]);

                return $permit;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $notifier->notifyApplicant($permit, 'Security Deposit Dikembalikan', 'Pengembalian security deposit telah diproses oleh Management. Bukti pengembalian tersedia pada halaman status.');

        return back()->with('success', 'Pengembalian deposit berhasil dicatat.');
    }

    public function readNotification(PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);
        abort_unless($notification->work_permit_id !== null, 404);

        $workspace = request()->user()->workspaceForWorkPermit($notification->workPermit);
        abort_unless($workspace, 403);
        $notification->markRead();

        return redirect()->route('staff.work-permits.show', request()->user()->workPermitRouteParameters($notification->workPermit));
    }

    /** @return array{int, string|int} */
    private function tablePageSize(Request $request): array
    {
        $raw = $request->query('per_page', 10);
        $allowed = [5, 10, 20, 50, 100];

        if ($raw === 'all') {
            return [max(1, WorkPermit::where('assigned_division', 'MEP')->count()), 'all'];
        }

        $perPage = in_array((int) $raw, $allowed, true) ? (int) $raw : 10;

        return [$perPage, $perPage];
    }

    private function processAwaitingInvoice(
        WorkPermit $permit,
        WorkPermitWorkflowNotifier $notifier,
        DepositInvoiceService $invoices,
    ): WorkPermit {
        if (DepositInvoiceSetting::current()->isAutomatic()) {
            try {
                $permit = $invoices->issue($permit);
                if ($permit->wasChanged('deposit_invoice_path')) {
                    $notifier->notifyApplicant(
                        $permit,
                        'Invoice Security Deposit Tersedia',
                        'Invoice security deposit sebesar Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.').' tersedia pada halaman status. Periksa invoice lalu unggah bukti pembayaran setelah melakukan pembayaran.',
                        true,
                    );
                }

                return $permit;
            } catch (Throwable $exception) {
                Log::error('Invoice deposit otomatis gagal diterbitkan setelah keputusan MEP.', [
                    'work_permit_id' => $permit->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $permit->refresh();
        if ($permit->status !== 'awaiting_invoice') {
            return $permit;
        }
        $notifier->notifyDivision(
            $permit,
            'FIN',
            'Invoice Deposit Perlu Diunggah',
            "Finance perlu mengunggah invoice deposit #{$permit->permit_number}.",
            'finance',
            WorkPermit::where('assigned_division', 'MEP')->whereIn('status', ['awaiting_invoice', 'payment_review'])->count(),
        );
        $notifier->notifyStaffWhatsapp($permit, 'deposit_invoice_requested_'.$permit->updated_at->timestamp, 'Invoice Deposit Perlu Disiapkan', "Finance perlu mengunggah invoice deposit #{$permit->permit_number} sebesar Rp ".number_format((float) $permit->deposit_amount, 0, ',', '.').'.');

        return $permit;
    }
}
