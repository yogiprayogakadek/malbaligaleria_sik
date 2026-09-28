<?php

namespace App\Http\Controllers;

use App\Events\WorkPermitSubmitted;
use App\Http\Requests\StoreWorkPermitRequest;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\WorkPermitSubmittedPush;
use App\Services\WorkPermitWorkflowNotifier;
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
    public function create(): View
    {
        return view('portal.permits.work.create', [
            'user' => Auth::user(),
            'workCategories' => WorkPermit::WORK_CATEGORIES,
        ]);
    }

    public function store(StoreWorkPermitRequest $request): RedirectResponse
    {
        $documentPath = $request->file('id_doc')->store('permits/work/id-docs', 'local');
        $category = $request->validated('work_category');
        $assignedDivision = WorkPermit::divisionForCategory($category);
        $initialStatus = $assignedDivision === 'TR' ? 'tr_review' : 'mep_review';

        try {
            $permit = DB::transaction(function () use ($request, $documentPath, $category, $assignedDivision, $initialStatus): WorkPermit {
                $permit = WorkPermit::create([
                    'user_id' => Auth::id(),
                    'contractor_name' => $request->string('contractor_name')->toString(),
                    'applicant_name' => $request->string('applicant_name')->toString(),
                    'applicant_phone' => $request->string('applicant_phone')->toString(),
                    'applicant_email' => $request->input('applicant_email'),
                    'work_location' => $request->string('work_location')->toString(),
                    'work_category' => $category,
                    'assigned_division' => $assignedDivision,
                    'work_type' => $request->string('work_type')->toString(),
                    'work_schedule' => $request->validated('work_schedules')[0],
                    'work_schedules' => $request->validated('work_schedules'),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'needs_water' => $request->boolean('needs_water'),
                    'security_deposit' => false,
                    'notes' => $request->input('notes'),
                    'id_doc_path' => $documentPath,
                    'id_doc_type' => $request->string('id_doc_type')->toString(),
                    'status' => $initialStatus,
                ]);

                $permit->workers()->createMany(
                    collect($request->validated('workers'))
                        ->values()
                        ->map(fn (array $worker, int $index): array => [
                            'name' => trim($worker['name']),
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
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($documentPath);
            throw $exception;
        }

        $this->notifyStaff($permit);
        app(WorkPermitWorkflowNotifier::class)->notifyApplicant(
            $permit,
            'Permohonan Izin Kerja Diterima',
            "Permohonan telah diterima dan masuk ke antrean pemeriksaan {$assignedDivision}.",
        );

        return redirect(URL::temporarySignedRoute(
            'work-permits.success',
            now()->addMinutes(30),
            ['token' => $permit->public_token],
        ));
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
        $pendingCount = $permit->assigned_division === 'TR'
            ? WorkPermit::where('assigned_division', 'TR')->where('status', 'tr_review')->count()
            : WorkPermit::where('assigned_division', 'MEP')->needsMepAction()->count();

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
