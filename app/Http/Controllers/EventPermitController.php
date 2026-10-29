<?php

namespace App\Http\Controllers;

use App\Events\WorkPermitSubmitted;
use App\Http\Requests\StoreEventPermitRequest;
use App\Models\EventRequirement;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\WorkPermitSubmittedPush;
use App\Services\OperationTimeline;
use App\Services\PermitPdfService;
use App\Services\PermitSubmissionTokenService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

class EventPermitController extends Controller
{
    public function create(Request $request, PermitSubmissionTokenService $tokens): View
    {
        return view('portal.permits.event.create', [
            'user' => $request->user(),
            'categories' => config('event-permits.categories'),
            'requirements' => EventRequirement::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->groupBy('group_name'),
            'submissionToken' => $tokens->issue($request, 'event'),
        ]);
    }

    public function store(
        StoreEventPermitRequest $request,
        PermitSubmissionTokenService $tokens,
        WorkPermitWorkflowNotifier $notifier,
        OperationTimeline $timeline,
    ): RedirectResponse {
        $timeline->measure(
            'Validasi token formulir',
            fn () => abort_unless($tokens->isValid($request, 'event', $request->validated('submission_token')), 419, 'Sesi formulir berakhir. Muat ulang halaman lalu kirim kembali.'),
        );
        $submissionHash = $tokens->hash($request->validated('submission_token'));
        $existing = $timeline->measure(
            'Pemeriksaan duplikasi',
            fn () => WorkPermit::query()->where('submission_token_hash', $submissionHash)->first(),
        );

        if ($existing) {
            return $this->successRedirect($existing)->with('success', 'Permohonan sudah diterima sebelumnya. Tidak ada data ganda yang dibuat.');
        }

        try {
            $permit = $timeline->measure('Transaksi data permohonan', fn () => DB::transaction(function () use ($request, $submissionHash): WorkPermit {
                $permit = WorkPermit::query()->create([
                    'permit_kind' => 'event',
                    'user_id' => Auth::id(),
                    'tenant_id' => Auth::user()?->tenant_id,
                    'submission_token_hash' => $submissionHash,
                    'contractor_name' => $request->validated('organizer_name'),
                    'tenant_name' => $request->validated('organizer_name'),
                    'applicant_name' => $request->validated('applicant_name'),
                    'applicant_phone' => $request->validated('applicant_phone'),
                    'applicant_email' => $request->validated('applicant_email'),
                    'work_location' => $request->validated('work_location'),
                    'work_category' => $request->validated('work_category'),
                    'event_type' => $request->validated('event_type'),
                    'event_requirements' => $request->requirementSnapshot(),
                    'assigned_division' => 'EP',
                    'work_type' => $request->validated('work_type'),
                    'includes_loading_material' => $request->boolean('includes_loading_material'),
                    'work_schedule' => $request->validated('event_type') === 'organizer' ? 'event_organizer' : 'event_tenant',
                    'work_schedules' => [],
                    'start_date' => $request->validated('start_date'),
                    'end_date' => $request->validated('end_date'),
                    'notes' => $request->validated('notes'),
                    'status' => 'ep_review',
                ]);

                $permit->statusLogs()->create([
                    'actor_id' => Auth::id(),
                    'from_status' => null,
                    'to_status' => 'ep_review',
                    'action' => 'submitted',
                    'metadata' => ['assigned_division' => 'EP', 'permit_kind' => 'event'],
                ]);

                return $permit;
            }));
            $timeline->associatePermit($permit);
        } catch (UniqueConstraintViolationException $exception) {
            $permit = WorkPermit::query()->where('submission_token_hash', $submissionHash)->first();
            if (! $permit) {
                throw $exception;
            }

            return $this->successRedirect($permit)->with('success', 'Permohonan sudah diterima sebelumnya. Tidak ada data ganda yang dibuat.');
        }

        $timeline->measure('Notifikasi staf', fn () => $this->notifyStaff($permit));
        $timeline->measure(
            'Notifikasi pemohon',
            fn () => $notifier->notifyApplicant(
                $permit,
                'Permohonan Surat Izin Event Diterima',
                'Permohonan telah diterima dan masuk ke antrean pemeriksaan Event Promotion.',
            ),
        );

        return $this->successRedirect($permit);
    }

    public function success(string $token): View
    {
        $permit = WorkPermit::query()->where('permit_kind', 'event')->where('public_token', $token)->firstOrFail();

        return view('portal.permits.event.success', compact('permit'));
    }

    public function status(Request $request, string $token): Response|RedirectResponse
    {
        $permit = $this->permit($request, $token)->load(['statusLogs', 'revisions']);
        if ($request->user()?->isStaff()) {
            return redirect()->route('staff.work-permits.show', $permit->public_token);
        }

        $letterPreviewUrl = $permit->status === 'approved'
            ? URL::temporarySignedRoute('event-permits.letter.preview', now()->addDays(30), ['token' => $permit->applicant_token])
            : null;
        $letterDownloadUrl = $permit->status === 'approved'
            ? URL::temporarySignedRoute('event-permits.letter', now()->addDays(30), ['token' => $permit->applicant_token])
            : null;

        return response()->view('portal.permits.event.status', compact('permit', 'letterPreviewUrl', 'letterDownloadUrl'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function downloadLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->approvedPermit($request, $token);

        return $pdf->workPermit($permit);
    }

    public function inlineLetter(Request $request, string $token, PermitPdfService $pdf): Response
    {
        $permit = $this->approvedPermit($request, $token);

        return $pdf->workPermitInline($permit);
    }

    public function previewLetter(Request $request, string $token): View
    {
        $permit = $this->approvedPermit($request, $token);

        return view('documents.preview', [
            'title' => 'Surat Izin Event',
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute('event-permits.letter.inline', now()->addMinutes(10), ['token' => $permit->applicant_token]),
            'downloadUrl' => URL::temporarySignedRoute('event-permits.letter', now()->addMinutes(10), ['token' => $permit->applicant_token]),
            'backUrl' => URL::temporarySignedRoute('event-permits.status', now()->addDays(30), ['token' => $permit->applicant_token]),
            'isImage' => false,
        ]);
    }

    private function notifyStaff(WorkPermit $permit): void
    {
        $pendingCount = WorkPermit::needsEpAction()->count();
        User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereIn('role', ['admin', 'secretary'])
                    ->orWhere(function ($validator): void {
                        $validator->where('role', 'validator')->where('division', 'EP');
                    });
            })
            ->get()
            ->each(function (User $recipient) use ($permit, $pendingCount): void {
                $notification = PermitNotification::query()->create([
                    'user_id' => $recipient->id,
                    'work_permit_id' => $permit->id,
                    'type' => 'pending_review',
                    'title' => 'Permohonan Surat Izin Event Baru',
                    'body' => "{$permit->contractor_name} mengajukan #{$permit->permit_number}.",
                ]);

                try {
                    WorkPermitSubmitted::dispatch($notification, $permit, $recipient->isSecretary()
                        ? PermitNotification::query()->where('user_id', $recipient->id)->whereNull('read_at')->count()
                        : $pendingCount);
                    $recipient->notify(new WorkPermitSubmittedPush($permit));
                } catch (Throwable $exception) {
                    Log::warning('Notifikasi Surat Izin Event gagal dikirim.', [
                        'work_permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
    }

    private function successRedirect(WorkPermit $permit): RedirectResponse
    {
        return redirect(URL::temporarySignedRoute(
            'event-permits.success',
            now()->addMinutes(30),
            ['token' => $permit->public_token],
        ));
    }

    private function permit(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::query()->where('permit_kind', 'event')->where('applicant_token', $token)->firstOrFail();
        $hasValidSignature = $request->hasValidSignature() || $request->hasValidSignature(false);
        if ($permit->user_id !== null && ! $hasValidSignature) {
            abort_unless($request->user()?->id === $permit->user_id, 403);
        }

        return $permit;
    }

    private function approvedPermit(Request $request, string $token): WorkPermit
    {
        $permit = $this->permit($request, $token)->load('reviewer');
        abort_if($permit->revoked_at, 404);
        abort_unless($permit->status === 'approved', 404);

        return $permit;
    }
}
