<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AdminEmergencyValidationService
{
    public function __construct(
        private PermitPdfService $pdf,
        private WorkPermitWorkflowNotifier $workNotifier,
        private WhatsappNotificationDispatcher $whatsapp,
    ) {}

    public function loading(LoadingPermit $permit, User $admin, array $data): LoadingPermit
    {
        $permit = DB::transaction(function () use ($permit, $admin, $data): LoadingPermit {
            $locked = LoadingPermit::query()->lockForUpdate()->findOrFail($permit->id);
            abort_unless($locked->status === 'pending', 409, 'Permohonan sudah diproses oleh pengguna lain.');

            $locked->update([
                'status' => $data['decision'],
                'reviewed_by' => $admin->id,
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_at' => now(),
            ]);
            if ($locked->status === 'approved') {
                $locked->generateBarcodeToken();
                $this->pdf->sealLoadingPermit($locked);
            }
            if ($locked->user_id) {
                PermitNotification::query()->create([
                    'user_id' => $locked->user_id,
                    'permit_id' => $locked->id,
                    'type' => $locked->status,
                    'title' => $locked->status === 'approved' ? 'Permohonan Disetujui' : 'Permohonan Ditolak',
                    'body' => $locked->status === 'approved'
                        ? "Permohonan loading #{$locked->permit_number} telah disetujui."
                        : "Permohonan loading #{$locked->permit_number} ditolak. ".($locked->review_notes ?: ''),
                ]);
            }

            return $locked->fresh();
        }, 3);

        $mailType = $permit->status === 'approved' ? LoadingPermitApplicantMail::APPROVED : LoadingPermitApplicantMail::REJECTED;
        if ($permit->applicant_email) {
            Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                ->notify(new LoadingPermitApplicantMail($permit, $mailType));
        }
        $this->whatsapp->loading(
            $permit,
            'admin_emergency_'.$permit->status.'_'.$permit->updated_at->timestamp,
            $permit->status === 'approved' ? 'Permohonan Loading / Unloading Disetujui' : 'Permohonan Loading / Unloading Ditolak',
            $permit->status === 'approved' ? 'Permohonan telah disetujui oleh admin.' : ($permit->review_notes ?: 'Permohonan belum dapat disetujui.'),
        );
        $this->recordReason($permit, $admin, $data['emergency_reason']);

        return $permit;
    }

    public function work(WorkPermit $permit, User $admin, array $data): WorkPermit
    {
        $permit = DB::transaction(function () use ($permit, $admin, $data): WorkPermit {
            $locked = WorkPermit::query()->lockForUpdate()->findOrFail($permit->id);
            $from = $locked->status;
            abort_unless(in_array($from, ['tr_review', 'ep_review', 'mep_review', 'payment_review', 'mep_final_review'], true), 409, 'Tahap permohonan sudah berubah dan tidak dapat divalidasi dari halaman ini.');

            [$to, $updates, $action] = $this->workTransition($locked, $admin, $data);
            $locked->update([...$updates, 'status' => $to]);
            $locked->statusLogs()->create([
                'actor_id' => $admin->id,
                'from_status' => $from,
                'to_status' => $to,
                'action' => $action,
                'notes' => $data['review_notes'] ?? null,
                'metadata' => [
                    'emergency_reason' => $data['emergency_reason'],
                    'acted_as_admin' => true,
                ],
            ]);
            if ($to === 'approved') {
                $this->pdf->sealWorkPermit($locked);
            }

            return $locked->fresh();
        }, 3);

        [$title, $body] = $this->applicantMessage($permit);
        $this->workNotifier->notifyApplicant($permit, $title, $body);

        if ($permit->status === 'mep_final_review') {
            $this->workNotifier->notifyDivision(
                $permit,
                'MEP',
                'Deposit terverifikasi',
                "Admin telah memverifikasi deposit {$permit->permit_number}. Permohonan siap diperiksa Engineering.",
                'work',
                WorkPermit::needsMepAction()->count(),
                false,
            );
        }
        $this->recordReason($permit, $admin, $data['emergency_reason']);

        return $permit;
    }

    /** @return array{string, array<string, mixed>, string} */
    private function workTransition(WorkPermit $permit, User $admin, array $data): array
    {
        $notes = $data['review_notes'] ?? null;

        if (in_array($permit->status, ['tr_review', 'ep_review', 'mep_final_review'], true)) {
            $this->requireDecision($data['decision'], ['approved', 'rejected']);

            return [$data['decision'], [
                'review_notes' => $notes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'deposit_required' => $permit->status === 'mep_final_review' ? $permit->deposit_required : false,
                'security_deposit' => $permit->status === 'mep_final_review' ? $permit->security_deposit : false,
            ], 'admin_emergency_decision'];
        }

        if ($permit->status === 'mep_review') {
            $this->requireDecision($data['decision'], ['approved', 'rejected', 'require_deposit']);
            $requiresDeposit = $data['decision'] === 'require_deposit';
            if ($requiresDeposit && empty($data['deposit_amount'])) {
                throw ValidationException::withMessages(['deposit_amount' => 'Nominal deposit wajib diisi.']);
            }
            $to = $requiresDeposit ? 'awaiting_payment' : $data['decision'];

            return [$to, [
                'security_deposit' => $requiresDeposit,
                'deposit_required' => $requiresDeposit,
                'deposit_amount' => $requiresDeposit ? $data['deposit_amount'] : null,
                'deposit_notes' => $notes,
                'deposit_set_by' => $admin->id,
                'deposit_set_at' => now(),
                'review_notes' => $data['decision'] === 'rejected' ? $notes : null,
                'reviewed_by' => in_array($to, ['approved', 'rejected'], true) ? $admin->id : null,
                'reviewed_at' => in_array($to, ['approved', 'rejected'], true) ? now() : null,
            ], $requiresDeposit ? 'admin_emergency_deposit_requested' : 'admin_emergency_decision'];
        }

        $this->requireDecision($data['decision'], ['verified', 'revision']);
        $verified = $data['decision'] === 'verified';

        return [$verified ? 'mep_final_review' : 'payment_revision', [
            'payment_verified_by' => $verified ? $admin->id : null,
            'payment_verified_at' => $verified ? now() : null,
            'finance_notes' => $notes,
        ], $verified ? 'admin_emergency_payment_verified' : 'admin_emergency_payment_revision'];
    }

    private function requireDecision(string $decision, array $allowed): void
    {
        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages(['decision' => 'Keputusan tidak sesuai dengan tahap permohonan.']);
        }
    }

    /** @return array{string, string} */
    private function applicantMessage(WorkPermit $permit): array
    {
        return match ($permit->status) {
            'approved' => ['Permohonan Izin Kerja Disetujui', 'Permohonan telah disetujui melalui prosedur validasi darurat admin.'],
            'rejected' => ['Permohonan Izin Kerja Ditolak', $permit->review_notes ?: 'Permohonan belum dapat disetujui.'],
            'awaiting_payment' => ['Security Deposit Diperlukan', 'Security deposit ditetapkan sebesar Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.').'. Silakan unggah bukti pembayaran.'],
            'payment_revision' => ['Bukti Pembayaran Perlu Diperbaiki', $permit->finance_notes ?: 'Silakan unggah ulang bukti pembayaran.'],
            default => ['Pembayaran Terverifikasi', 'Security deposit telah diterima dan permohonan kembali diperiksa oleh Engineering.'],
        };
    }

    private function recordReason(LoadingPermit|WorkPermit $permit, User $admin, string $reason): void
    {
        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'event' => 'emergency_validation',
            'auditable_type' => $permit::class,
            'auditable_id' => $permit->id,
            'route_name' => request()->route()?->getName(),
            'request_method' => request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => [],
            'new_values' => ['reason' => $reason, 'resulting_status' => $permit->status],
        ]);
    }
}
