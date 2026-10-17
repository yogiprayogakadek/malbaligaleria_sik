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
    }

    public function ensureWorkAssignee(WorkPermit $permit, User $user): void
    {
        $division = $permit->actionDivision();
        abort_unless($division !== null, 409, 'Permohonan tidak memerlukan tindakan validator pada status ini.');
        $this->assertValidator($user, $division);
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
