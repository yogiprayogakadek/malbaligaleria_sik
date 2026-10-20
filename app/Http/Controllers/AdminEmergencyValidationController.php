<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use App\Services\AdminEmergencyValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEmergencyValidationController extends Controller
{
    public function index(): View
    {
        return view('admin.emergency-validation.index', [
            'items' => $this->items(),
        ]);
    }

    public function loading(Request $request, LoadingPermit $loadingPermit, AdminEmergencyValidationService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [Rule::requiredIf($request->input('decision') === 'rejected'), 'nullable', 'string', 'min:10', 'max:1000'],
            'emergency_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'current_password' => ['required', 'current_password'],
        ]);
        $service->loading($loadingPermit, $request->user(), $data);

        return back()->with('success', "Keputusan admin untuk {$loadingPermit->permit_number} berhasil disimpan dan pemohon telah diberi tahu.");
    }

    public function work(Request $request, WorkPermit $workPermit, AdminEmergencyValidationService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected', 'require_deposit', 'verified', 'revision'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:1', 'max:999999999999.99'],
            'review_notes' => [Rule::requiredIf(in_array($request->input('decision'), ['rejected', 'revision'], true)), 'nullable', 'string', 'min:10', 'max:1000'],
            'emergency_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'current_password' => ['required', 'current_password'],
        ]);
        $service->work($workPermit, $request->user(), $data);

        return back()->with('success', "Keputusan admin untuk {$workPermit->permit_number} berhasil disimpan dan pemohon telah diberi tahu.");
    }

    private function items(): Collection
    {
        $loading = LoadingPermit::query()->where('status', 'pending')->slaPriority()->limit(100)->get()
            ->map(fn (LoadingPermit $permit): array => [
                'kind' => 'loading',
                'permit' => $permit,
                'division' => 'TR',
                'applicant' => $permit->tenant_name,
            ]);
        $work = WorkPermit::query()
            ->whereIn('status', ['tr_review', 'ep_review', 'mep_review', 'payment_review', 'mep_final_review'])
            ->slaPriority()->limit(100)->get()
            ->map(fn (WorkPermit $permit): array => [
                'kind' => 'work',
                'permit' => $permit,
                'division' => $permit->slaDivision(),
                'applicant' => $permit->contractor_name,
            ]);

        return $loading->concat($work)
            ->sortBy(fn (array $item) => $item['permit']->sla_started_at?->timestamp ?? PHP_INT_MAX)
            ->values();
    }
}
