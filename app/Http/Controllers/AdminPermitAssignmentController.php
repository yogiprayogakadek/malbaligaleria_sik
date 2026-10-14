<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPermitAssignmentController extends Controller
{
    public function loading(Request $request, string $permitNumber): RedirectResponse
    {
        $validatorId = $this->validatorId($request, 'TR');

        DB::transaction(function () use ($permitNumber, $validatorId): void {
            $permit = LoadingPermit::where('permit_number', $permitNumber)->lockForUpdate()->firstOrFail();
            abort_unless($permit->status === 'pending', 409, 'Hanya permohonan yang menunggu yang dapat ditugaskan.');
            $permit->forceFill([
                'assigned_validator_id' => $validatorId,
                'assigned_at' => $validatorId ? now() : null,
                'assignment_note' => null,
            ])->save();
        });

        return back()->with('success', $validatorId ? 'Validator penanggung jawab berhasil ditetapkan.' : 'Penugasan validator berhasil dilepas.');
    }

    public function work(Request $request, string $token): RedirectResponse
    {
        DB::transaction(function () use ($request, $token): void {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            $division = $permit->actionDivision();
            abort_unless($division !== null, 409, 'Permohonan tidak memerlukan penugasan pada status ini.');
            $validatorId = $this->validatorId($request, $division);
            $previousId = $permit->assigned_validator_id;

            $permit->forceFill([
                'assigned_validator_id' => $validatorId,
                'assigned_at' => $validatorId ? now() : null,
                'assignment_note' => null,
            ])->save();
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => $permit->status,
                'to_status' => $permit->status,
                'action' => $validatorId ? 'assignment_set_by_admin' : 'assignment_released_by_admin',
                'metadata' => ['previous_validator_id' => $previousId, 'assigned_validator_id' => $validatorId],
            ]);
        });

        return back()->with('success', $request->filled('validator_id') ? 'Validator penanggung jawab berhasil ditetapkan.' : 'Penugasan validator berhasil dilepas.');
    }

    private function validatorId(Request $request, string $division): ?int
    {
        $data = $request->validate([
            'validator_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'validator')
                    ->where('division', $division)
                    ->where('is_active', true)),
            ],
        ], [
            'validator_id.exists' => 'Validator tidak aktif atau tidak sesuai dengan divisi pemeriksa.',
        ]);

        return isset($data['validator_id']) ? (int) $data['validator_id'] : null;
    }
}
