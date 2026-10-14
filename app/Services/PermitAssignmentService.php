<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;

class PermitAssignmentService
{
    public function ensureLoadingAssignee(LoadingPermit $permit, User $user): void
    {
        $this->assertValidator($user, 'TR');
        abort_unless($permit->status === 'pending', 409, 'Permohonan loading sudah tidak berada dalam antrean.');
        $this->claimIfAvailable($permit, $user);
    }

    public function ensureWorkAssignee(WorkPermit $permit, User $user): void
    {
        $division = $permit->actionDivision();
        abort_unless($division !== null, 409, 'Permohonan tidak memerlukan tindakan validator pada status ini.');
        $this->assertValidator($user, $division);
        $this->claimIfAvailable($permit, $user);
    }

    public function assignLoading(LoadingPermit $permit, User $user, bool $takeover = false, ?string $reason = null): void
    {
        $this->assertValidator($user, 'TR');
        abort_unless($permit->status === 'pending', 409, 'Permohonan loading sudah tidak berada dalam antrean.');
        $this->assign($permit, $user, $takeover, $reason);
    }

    public function assignWork(WorkPermit $permit, User $user, bool $takeover = false, ?string $reason = null): void
    {
        $division = $permit->actionDivision();
        abort_unless($division !== null, 409, 'Permohonan tidak memerlukan tindakan validator pada status ini.');
        $this->assertValidator($user, $division);
        $previousId = $permit->assigned_validator_id;
        $this->assign($permit, $user, $takeover, $reason);

        if ($previousId !== $user->id) {
            $permit->statusLogs()->create([
                'actor_id' => $user->id,
                'from_status' => $permit->status,
                'to_status' => $permit->status,
                'action' => $previousId ? 'assignment_taken_over' : 'assignment_claimed',
                'notes' => $reason,
                'metadata' => ['previous_validator_id' => $previousId, 'assigned_validator_id' => $user->id],
            ]);
        }
    }

    private function claimIfAvailable(LoadingPermit|WorkPermit $permit, User $user): void
    {
        if ($permit->assigned_validator_id === null) {
            $permit->forceFill([
                'assigned_validator_id' => $user->id,
                'assigned_at' => now(),
                'assignment_note' => null,
            ])->save();

            return;
        }

        abort_unless(
            $permit->assigned_validator_id === $user->id,
            409,
            'Permohonan sedang ditangani validator lain. Ambil alih terlebih dahulu jika memang diperlukan.',
        );
    }

    private function assign(LoadingPermit|WorkPermit $permit, User $user, bool $takeover, ?string $reason): void
    {
        if ($permit->assigned_validator_id !== null && $permit->assigned_validator_id !== $user->id) {
            abort_unless($takeover, 409, 'Permohonan sedang ditangani validator lain.');
            abort_unless(mb_strlen(trim((string) $reason)) >= 5, 422, 'Alasan pengambilalihan minimal 5 karakter.');
        }

        $permit->forceFill([
            'assigned_validator_id' => $user->id,
            'assigned_at' => now(),
            'assignment_note' => $permit->assigned_validator_id !== null && $permit->assigned_validator_id !== $user->id ? trim((string) $reason) : null,
        ])->save();
    }

    private function assertValidator(User $user, string $division): void
    {
        abort_unless(
            $user->is_active && $user->isValidator() && $user->division === $division,
            403,
            'Penugasan ini bukan kewenangan akun Anda.',
        );
    }
}
