<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use App\Services\PermitAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermitAssignmentController extends Controller
{
    public function loading(Request $request, string $permitNumber, PermitAssignmentService $assignments): RedirectResponse
    {
        $data = $this->validateRequest($request);

        DB::transaction(function () use ($request, $permitNumber, $assignments, $data): void {
            $permit = LoadingPermit::where('permit_number', $permitNumber)->lockForUpdate()->firstOrFail();
            $assignments->assignLoading($permit, $request->user(), $data['takeover'], $data['takeover_reason']);
        });

        return back()->with('success', $data['takeover'] ? 'Permohonan berhasil diambil alih.' : 'Permohonan berhasil Anda ambil.');
    }

    public function work(Request $request, string $token, PermitAssignmentService $assignments): RedirectResponse
    {
        $data = $this->validateRequest($request);

        DB::transaction(function () use ($request, $token, $assignments, $data): void {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            $assignments->assignWork($permit, $request->user(), $data['takeover'], $data['takeover_reason']);
        });

        return back()->with('success', $data['takeover'] ? 'Permohonan berhasil diambil alih.' : 'Permohonan berhasil Anda ambil.');
    }

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'takeover' => ['nullable', 'boolean'],
            'takeover_reason' => [Rule::requiredIf($request->boolean('takeover')), 'nullable', 'string', 'min:5', 'max:500'],
        ], [
            'takeover_reason.required' => 'Alasan pengambilalihan wajib diisi.',
            'takeover_reason.min' => 'Alasan pengambilalihan minimal 5 karakter.',
        ]);

        return [
            'takeover' => $request->boolean('takeover'),
            'takeover_reason' => isset($validated['takeover_reason']) ? trim($validated['takeover_reason']) : null,
        ];
    }
}
