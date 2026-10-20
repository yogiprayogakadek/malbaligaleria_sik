<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Events\WorkPermitSubmitted;
use App\Http\Requests\StoreWorkPermitRequest;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Models\WorkPermitDurationSetting;
use App\Notifications\WorkPermitSubmittedPush;
use App\Services\MessageTemplateRenderer;
use App\Services\PermitDraftService;
use App\Services\PermitSubmissionTokenService;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

class WorkPermitController extends Controller
{
    public function create(
        Request $request,
        PermitDraftService $drafts,
        PermitSubmissionTokenService $submissionTokens,
    ): View {
        $draft = $drafts->get($request, 'work');

        return view('portal.permits.work.create', [
            'user' => Auth::user(),
            'workCategories' => WorkPermit::categoryOptions(),
            'workCategorySettings' => WorkPermit::categorySettings(),
            'durationSettings' => WorkPermitDurationSetting::current(),
            'draft' => $draft['data'],
            'draftSavedAt' => $draft['saved_at'],
            'submissionToken' => $submissionTokens->issue($request, 'work'),
        ]);
    }

    public function store(
        StoreWorkPermitRequest $request,
        MalwareScanner $malwareScanner,
        PermitDraftService $drafts,
        PermitSubmissionTokenService $submissionTokens,
    ): RedirectResponse {
        $submissionHash = $submissionTokens->hash($request->validated('submission_token'));
        $existingPermit = WorkPermit::where('submission_token_hash', $submissionHash)->first();

        if ($existingPermit) {
            $drafts->clear($request, 'work');

            return $this->duplicateSubmissionResponse($existingPermit);
        }

        $category = $request->validated('work_category');
        $assignedDivision = WorkPermit::divisionForCategory($category);
        $initialStatus = match ($assignedDivision) {
            'TR' => 'tr_review',
            'EP' => 'ep_review',
            default => 'mep_review',
        };
        $requiresAssignmentLetter = $request->requiresAssignmentLetter();
        $documentPath = null;
        $assignmentLetterPath = null;

        $malwareScanner->scan($request->file('id_doc'), 'id_doc');
        if ($requiresAssignmentLetter) {
            $malwareScanner->scan($request->file('assignment_letter'), 'assignment_letter');
        }

        try {
            $documentPath = $request->file('id_doc')->store('permits/work/id-docs', 'local');
            $assignmentLetterPath = $requiresAssignmentLetter
                ? $request->file('assignment_letter')->store('permits/work/assignment-letters', 'local')
                : null;

            $permit = DB::transaction(function () use ($request, $documentPath, $assignmentLetterPath, $category, $assignedDivision, $initialStatus, $submissionHash): WorkPermit {
                $permit = WorkPermit::create([
                    'user_id' => Auth::id(),
                    'tenant_id' => Auth::user()?->tenant_id,
                    'submission_token_hash' => $submissionHash,
                    'contractor_name' => $request->string('contractor_name')->toString(),
                    'tenant_name' => $request->string('tenant_name')->toString(),
                    'applicant_name' => $request->string('applicant_name')->toString(),
                    'applicant_phone' => $request->string('applicant_phone')->toString(),
                    'applicant_email' => $request->input('applicant_email'),
                    'work_location' => $request->string('work_location')->toString(),
                    'work_category' => $category,
                    'assigned_division' => $assignedDivision,
                    'work_type' => $request->string('work_type')->toString(),
                    'other_work_type' => $request->input('other_work_type'),
                    'other_work_description' => $request->input('other_work_description'),
                    'includes_loading_material' => $request->boolean('includes_loading_material'),
                    'is_rooftop_work' => $request->boolean('is_rooftop_work'),
                    'rooftop_network_type' => $request->input('rooftop_network_type'),
                    'is_atm_repair' => $request->boolean('is_atm_repair'),
                    'work_schedule' => $request->validated('work_schedules')[0],
                    'work_schedules' => $request->validated('work_schedules'),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'needs_water' => $request->boolean('needs_water'),
                    'needs_lighting' => $request->boolean('needs_lighting'),
                    'security_deposit' => false,
                    'notes' => $request->input('notes'),
                    'id_doc_path' => $documentPath,
                    'id_doc_type' => $request->string('id_doc_type')->toString(),
                    'assignment_letter_path' => $assignmentLetterPath,
                    'assignment_letter_type' => $assignmentLetterPath ? $request->file('assignment_letter')->extension() : null,
                    'status' => $initialStatus,
                ]);

                $permit->workers()->createMany(
                    collect($request->validated('workers'))
                        ->values()
                        ->map(fn (array $worker, int $index): array => [
                            'name' => filled($worker['name'] ?? null) ? trim($worker['name']) : null,
                            'identity_number' => filled($worker['identity_number'] ?? null)
                                ? trim($worker['identity_number'])
                                : null,
                            'position' => $index + 1,
                        ])->all(),
                );

                $permit->statusLogs()->create([
                    'actor_id' => Auth::id(),
                    'from_status' => null,
                    'to_status' => $initialStatus,
                    'action' => 'submitted',
                    'metadata' => ['assigned_division' => $assignedDivision, 'work_category' => $category],
                ]);

                return $permit;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($documentPath) {
                Storage::disk('local')->delete($documentPath);
            }
            if ($assignmentLetterPath) {
                Storage::disk('local')->delete($assignmentLetterPath);
            }

            $existingPermit = WorkPermit::where('submission_token_hash', $submissionHash)->first();
            if (! $existingPermit) {
                throw $exception;
            }

            $drafts->clear($request, 'work');

            return $this->duplicateSubmissionResponse($existingPermit);
        } catch (Throwable $exception) {
            if ($documentPath) {
                Storage::disk('local')->delete($documentPath);
            }
            if ($assignmentLetterPath) {
                Storage::disk('local')->delete($assignmentLetterPath);
            }
            throw $exception;
        }

        $this->notifyStaff($permit);
        $divisionLabel = $assignedDivision === 'MEP' ? 'Engineering' : $assignedDivision;
        $message = app(MessageTemplateRenderer::class)->render('permit_submitted', [
            'permit_number' => $permit->permit_number,
            'division' => $divisionLabel,
            'tenant_name' => $permit->tenant_name,
        ], 'Permohonan Izin Kerja Diterima', "Permohonan telah diterima dan masuk ke antrean pemeriksaan {$divisionLabel}.");
        app(WorkPermitWorkflowNotifier::class)->notifyApplicant($permit, $message['subject'], $message['body']);

        $drafts->clear($request, 'work');

        return redirect(URL::temporarySignedRoute(
            'work-permits.success',
            now()->addMinutes(30),
            ['token' => $permit->public_token],
        ));
    }

    private function duplicateSubmissionResponse(WorkPermit $permit): RedirectResponse
    {
        return redirect(URL::temporarySignedRoute(
            'work-permits.success',
            now()->addMinutes(30),
            ['token' => $permit->public_token],
        ))->with('success', 'Permohonan ini sudah diterima sebelumnya. Tidak ada data ganda yang dibuat.');
    }

    public function success(Request $request, string $token): View
    {
        $permit = WorkPermit::query()
            ->where('public_token', $token)
            ->with('workers')
            ->firstOrFail();

        return view('portal.permits.work.success', compact('permit'));
    }

    private function notifyStaff(WorkPermit $permit): void
    {
        $pendingCount = match ($permit->assigned_division) {
            'TR' => WorkPermit::needsTrAction()->count(),
            'EP' => WorkPermit::needsEpAction()->count(),
            default => WorkPermit::needsMepAction()->count(),
        };

        User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($permit): void {
                $query->where(function ($validatorQuery) use ($permit): void {
                    $validatorQuery->where('role', 'validator')->where('division', $permit->assigned_division);
                })->orWhereIn('role', ['admin', 'secretary']);
            })
            ->get()
            ->each(function (User $recipient) use ($permit, $pendingCount): void {
                $notification = PermitNotification::create([
                    'user_id' => $recipient->id,
                    'work_permit_id' => $permit->id,
                    'type' => 'pending_review',
                    'title' => 'Permohonan Izin Kerja Baru',
                    'body' => "{$permit->contractor_name} mengajukan #{$permit->permit_number}.",
                ]);

                $recipientPendingCount = $recipient->isSecretary()
                    ? PermitNotification::where('user_id', $recipient->id)->whereNull('read_at')->count()
                    : $pendingCount;

                try {
                    WorkPermitSubmitted::dispatch($notification, $permit, $recipientPendingCount);
                } catch (Throwable $exception) {
                    Log::warning('Notifikasi realtime izin kerja gagal dikirim.', [
                        'work_permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }

                try {
                    $recipient->notify(new WorkPermitSubmittedPush($permit));
                } catch (Throwable $exception) {
                    Log::warning('Push notification izin kerja gagal dimasukkan ke antrean.', [
                        'work_permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
