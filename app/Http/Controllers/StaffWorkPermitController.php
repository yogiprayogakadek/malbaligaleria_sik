<?php

namespace App\Http\Controllers;

use App\Models\WorkPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffWorkPermitController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with('workers')
            ->firstOrFail();
        $this->authorizeStaff($request, $permit);

        return view('staff.work-permits.show', compact('permit'));
    }

    public function document(Request $request, string $token): StreamedResponse
    {
        $permit = WorkPermit::query()->where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary() || $request->user()->division === 'FIN', 403);
        abort_unless(Storage::disk('local')->exists($permit->id_doc_path), 404);

        $response = Storage::disk('local')->response($permit->id_doc_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function paymentProof(Request $request, string $token): StreamedResponse
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_unless($permit->payment_proof_path && Storage::disk('local')->exists($permit->payment_proof_path), 404);

        return $this->privateFile($permit->payment_proof_path);
    }

    public function refundProof(Request $request, string $token): StreamedResponse
    {
        $permit = WorkPermit::where('public_token', $token)->firstOrFail();
        $this->authorizeStaff($request, $permit);
        abort_if($request->user()->isSecretary(), 403);
        abort_unless($permit->assigned_division === 'MEP', 403);
        abort_if($request->user()->isValidator() && $request->user()->division === 'FIN', 403);
        abort_unless($permit->refund_proof_path && Storage::disk('local')->exists($permit->refund_proof_path), 404);

        return $this->privateFile($permit->refund_proof_path);
    }

    private function authorizeStaff(Request $request, WorkPermit $permit): void
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);

        if ($user->isAdmin() || $user->isSecretary()) {
            return;
        }

        $isAssignedValidator = $user->isValidator() && $user->division === $permit->assigned_division;
        $isFinanceReviewer = $user->isValidator()
            && $user->division === 'FIN'
            && $permit->assigned_division === 'MEP'
            && $permit->deposit_required;

        abort_unless($isAssignedValidator || $isFinanceReviewer, 403);
    }

    private function privateFile(string $path): StreamedResponse
    {
        $response = Storage::disk('local')->response($path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
