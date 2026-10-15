<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Events\LoadingPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\PermitRevision;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitSubmittedPush;
use App\Services\PermitRevisionService;
use App\Services\WorkPermitWorkflowNotifier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PermitRevisionApplicantController extends Controller
{
    public function editLoading(Request $request, string $permitNumber, PermitRevisionService $revisions): View
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)->firstOrFail();
        $this->authorizeLoadingApplicant($request, $permit);
        $activeRevisions = $this->activeOrFail($permit, $revisions);

        return view('portal.permits.revisions.loading', compact('permit', 'activeRevisions'));
    }

    public function updateLoading(
        Request $request,
        string $permitNumber,
        PermitRevisionService $revisions,
        MalwareScanner $malwareScanner,
    ): RedirectResponse {
        $permit = LoadingPermit::where('permit_number', $permitNumber)->firstOrFail();
        $this->authorizeLoadingApplicant($request, $permit);
        $activeRevisions = $this->activeOrFail($permit, $revisions);
        $fields = $activeRevisions->pluck('field_key')->all();
        $data = $this->validateLoading($request, $permit, $fields);
        $newDocumentPath = null;

        if (in_array('id_doc', $fields, true)) {
            $malwareScanner->scan($request->file('id_doc'), 'id_doc');
            $newDocumentPath = $request->file('id_doc')->store('permits/id-docs', 'local');
        }

        $oldDocumentPath = null;
        try {
            $permit = DB::transaction(function () use ($permit, $activeRevisions, $fields, $data, $newDocumentPath, $revisions, &$oldDocumentPath): LoadingPermit {
                $locked = LoadingPermit::lockForUpdate()->findOrFail($permit->id);
                $this->ensureSameRevisionBatch($locked, $activeRevisions, $revisions);
                $updates = Arr::only($data, array_diff($fields, ['id_doc']));

                if ($newDocumentPath) {
                    $oldDocumentPath = $locked->id_doc_path;
                    $updates['id_doc_path'] = $newDocumentPath;
                    $updates['id_doc_type'] = $data['id_doc_type'];
                }

                $updates += [
                    'status' => 'pending',
                    'revision_return_status' => null,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
                $locked->update($updates);
                $revisions->resolve($locked, $activeRevisions->first()->batch_id);

                return $locked;
            });
        } catch (Throwable $exception) {
            if ($newDocumentPath) {
                Storage::disk('local')->delete($newDocumentPath);
            }
            throw $exception;
        }

        if ($oldDocumentPath && $oldDocumentPath !== $newDocumentPath) {
            Storage::disk('local')->delete($oldDocumentPath);
        }
        $this->notifyLoadingStaff($permit);

        return redirect(URL::temporarySignedRoute('loading.show', now()->addDays(30), [
            'permitNumber' => $permit->permit_number,
        ]))->with('success', 'Perbaikan berhasil dikirim dan kembali ke antrean TR.');
    }

    public function editWork(Request $request, string $token, PermitRevisionService $revisions): View
    {
        $permit = $this->workPermitForApplicant($request, $token)->load('workers');
        $activeRevisions = $this->activeOrFail($permit, $revisions);

        return view('portal.permits.revisions.work', compact('permit', 'activeRevisions'));
    }

    public function updateWork(
        Request $request,
        string $token,
        PermitRevisionService $revisions,
        MalwareScanner $malwareScanner,
        WorkPermitWorkflowNotifier $notifier,
    ): RedirectResponse {
        $permit = $this->workPermitForApplicant($request, $token)->load('workers');
        $activeRevisions = $this->activeOrFail($permit, $revisions);
        $fields = $activeRevisions->pluck('field_key')->all();
        $data = $this->validateWork($request, $permit, $fields);
        [$newIdPath, $newAssignmentPath] = $this->storeWorkFiles($request, $fields, $malwareScanner);
        $oldPaths = [];

        try {
            $permit = DB::transaction(function () use ($request, $permit, $activeRevisions, $fields, $data, $newIdPath, $newAssignmentPath, $revisions, &$oldPaths): WorkPermit {
                $locked = WorkPermit::lockForUpdate()->findOrFail($permit->id);
                $this->ensureSameRevisionBatch($locked, $activeRevisions, $revisions);
                $updates = Arr::only($data, array_diff($fields, ['workers', 'work_options', 'id_doc', 'assignment_letter']));

                if (in_array('work_options', $fields, true)) {
                    $updates += Arr::only($data, ['includes_loading_material', 'is_rooftop_work', 'rooftop_network_type', 'is_atm_repair']);
                }
                if (in_array('work_category', $fields, true)) {
                    $updates['assigned_division'] = WorkPermit::divisionForCategory($data['work_category']);
                    if ($data['work_category'] !== 'other') {
                        $updates += [
                            'includes_loading_material' => false,
                            'is_rooftop_work' => false,
                            'rooftop_network_type' => null,
                            'is_atm_repair' => false,
                        ];
                    }
                }
                if ($newIdPath) {
                    $oldPaths[] = $locked->id_doc_path;
                    $updates['id_doc_path'] = $newIdPath;
                    $updates['id_doc_type'] = $data['id_doc_type'];
                }
                if ($newAssignmentPath) {
                    $oldPaths[] = $locked->assignment_letter_path;
                    $updates['assignment_letter_path'] = $newAssignmentPath;
                    $updates['assignment_letter_type'] = $request->file('assignment_letter')->getMimeType();
                }

                $division = $updates['assigned_division'] ?? $locked->assigned_division;
                $returnStatus = match ($division) {
                    'TR' => 'tr_review',
                    'EP' => 'ep_review',
                    default => 'mep_review',
                };
                $updates += [
                    'status' => $returnStatus,
                    'revision_return_status' => null,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
                $locked->update($updates);

                if (in_array('workers', $fields, true)) {
                    $locked->workers()->delete();
                    $locked->workers()->createMany(collect($data['workers'])->values()->map(
                        fn (array $worker, int $index): array => [
                            'name' => filled($worker['name'] ?? null) ? trim($worker['name']) : null,
                            'identity_number' => filled($worker['identity_number'] ?? null) ? trim($worker['identity_number']) : null,
                            'position' => $index + 1,
                        ],
                    )->all());
                }

                $batchId = $activeRevisions->first()->batch_id;
                $revisions->resolve($locked, $batchId);
                $locked->statusLogs()->create([
                    'actor_id' => $request->user()?->id,
                    'from_status' => 'revision_required',
                    'to_status' => $returnStatus,
                    'action' => 'revision_submitted',
                    'metadata' => ['fields' => $fields, 'batch_id' => $batchId],
                ]);

                return $locked;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_filter([$newIdPath, $newAssignmentPath]));
            throw $exception;
        }

        Storage::disk('local')->delete(array_filter($oldPaths));
        $pendingCount = match ($permit->assigned_division) {
            'TR' => WorkPermit::needsTrAction()->count(),
            'EP' => WorkPermit::needsEpAction()->count(),
            default => WorkPermit::needsMepAction()->count(),
        };
        $notifier->notifyDivision(
            $permit,
            $permit->assigned_division,
            'Perbaikan Izin Kerja Dikirim',
            "Perbaikan #{$permit->permit_number} siap diperiksa kembali.",
            strtolower($permit->assigned_division),
            $pendingCount,
        );
        $notifier->notifyApplicant($permit, 'Perbaikan Berhasil Dikirim', 'Permohonan kembali ke antrean pemeriksaan.');

        return redirect()->route('work-permits.status', $permit->applicant_token)
            ->with('success', 'Perbaikan berhasil dikirim dan kembali ke antrean pemeriksaan.');
    }

    /** @param array<int, string> $fields */
    private function validateLoading(Request $request, LoadingPermit $permit, array $fields): array
    {
        $allRules = [
            'tenant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_name' => ['required', 'string', 'min:2', 'max:120'],
            'applicant_phone' => ['required', 'string', 'min:9', 'max:25', 'regex:/^[\d+\-\s()]+$/'],
            'applicant_email' => ['nullable', 'email:rfc', 'max:120'],
            'direction' => ['required', Rule::in(['in', 'out', 'both'])],
            'movement_time' => ['required', 'date_format:H:i'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date'],
            'item_count' => ['required', 'integer', 'between:1,9999'],
            'item_unit' => ['required', Rule::in(['pcs', 'koli'])],
            'item_description' => ['nullable', 'string', 'max:500'],
            'id_doc' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', $this->readableIdentityImage()],
            'id_doc_type' => ['required', Rule::in(['ktp', 'sim'])],
        ];
        $rules = Arr::only($allRules, $fields);
        if (in_array('id_doc', $fields, true)) {
            $rules['id_doc_type'] = $allRules['id_doc_type'];
        }
        $validator = validator($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $permit, $fields): void {
            $start = in_array('start_date', $fields, true) ? $request->input('start_date') : $permit->start_date->toDateString();
            $end = in_array('end_date', $fields, true) ? $request->input('end_date') : $permit->end_date->toDateString();
            if ($start && $end) {
                $days = Carbon::parse($start)->diffInDays(Carbon::parse($end), false);
                if ($days < 0 || $days > 2) {
                    $validator->errors()->add('end_date', 'Periode loading harus 1 sampai 3 hari kalender.');
                }
            }
            if (in_array('movement_time', $fields, true) && preg_match('/^(\d{2}):(\d{2})$/', (string) $request->input('movement_time'), $matches)) {
                $minutes = ((int) $matches[1] * 60) + (int) $matches[2];
                if ($minutes > 660 && $minutes < 1320) {
                    $validator->errors()->add('movement_time', 'Waktu hanya diizinkan pukul 22.00 sampai 11.00 WITA.');
                }
            }
        });

        return $validator->validate();
    }

    /** @param array<int, string> $fields */
    private function validateWork(Request $request, WorkPermit $permit, array $fields): array
    {
        $rulesByField = [
            'contractor_name' => ['contractor_name' => ['required', 'string', 'min:2', 'max:150']],
            'applicant_name' => ['applicant_name' => ['required', 'string', 'min:2', 'max:120']],
            'applicant_phone' => ['applicant_phone' => ['required', 'string', 'min:9', 'max:25', 'regex:/^[\d+\-\s()]+$/']],
            'applicant_email' => ['applicant_email' => ['nullable', 'email:rfc', 'max:120']],
            'workers' => ['workers' => ['required', 'array', 'min:1', 'max:50'], 'workers.*.name' => ['nullable', 'string', 'min:2', 'max:120'], 'workers.*.identity_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\/\-\s]+$/']],
            'work_schedules' => ['work_schedules' => ['required', 'array', 'min:1', 'max:2'], 'work_schedules.*' => ['required', 'distinct', Rule::in(['inside_store', 'outside_store'])]],
            'work_location' => ['work_location' => ['required', 'string', 'min:2', 'max:180']],
            'work_category' => ['work_category' => ['required', Rule::in(array_keys(WorkPermit::WORK_CATEGORIES))]],
            'work_type' => ['work_type' => ['required', 'string', 'min:3', 'max:180']],
            'work_options' => ['includes_loading_material' => ['required', 'boolean'], 'is_rooftop_work' => ['required', 'boolean'], 'rooftop_network_type' => ['nullable', Rule::in(['internet_installation', 'bts_repair', 'ac_cleaning'])], 'is_atm_repair' => ['required', 'boolean']],
            'start_date' => ['start_date' => ['required', 'date', 'after_or_equal:today']],
            'end_date' => ['end_date' => ['required', 'date']],
            'needs_water' => ['needs_water' => ['required', 'boolean']],
            'notes' => ['notes' => ['nullable', 'string', 'max:1000']],
            'id_doc' => ['id_doc' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', $this->readableIdentityImage()], 'id_doc_type' => ['required', Rule::in(['ktp', 'sim'])]],
            'assignment_letter' => ['assignment_letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192']],
        ];
        $rules = [];
        foreach ($fields as $field) {
            $rules += $rulesByField[$field] ?? [];
        }
        $validator = validator($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $permit, $fields): void {
            $value = function (string $key, mixed $current) use ($request, $fields): mixed {
                $revisionField = in_array($key, ['includes_loading_material', 'is_rooftop_work', 'rooftop_network_type', 'is_atm_repair'], true)
                    ? 'work_options'
                    : $key;

                return in_array($revisionField, $fields, true) ? $request->input($key) : $current;
            };
            $start = $value('start_date', $permit->start_date->toDateString());
            $end = $value('end_date', $permit->end_date->toDateString());
            if ($start && $end) {
                $days = Carbon::parse($start)->diffInDays(Carbon::parse($end), false);
                if ($days < 0 || $days > 6) {
                    $validator->errors()->add('end_date', 'Masa izin kerja harus 1 sampai 7 hari kalender.');
                }
            }

            $category = $value('work_category', $permit->work_category);
            $schedules = (array) $value('work_schedules', $permit->work_schedules);
            $rooftop = filter_var($value('is_rooftop_work', $permit->is_rooftop_work), FILTER_VALIDATE_BOOL);
            $atm = filter_var($value('is_atm_repair', $permit->is_atm_repair), FILTER_VALIDATE_BOOL);
            $networkType = $value('rooftop_network_type', $permit->rooftop_network_type);
            if ($category !== 'other' && ($rooftop || $atm)) {
                $validator->errors()->add('work_options', 'Rincian rooftop atau ATM hanya berlaku untuk kategori Pekerjaan Lainnya.');
            }
            if ($rooftop && ! in_array('outside_store', $schedules, true)) {
                $validator->errors()->add('is_rooftop_work', 'Pekerjaan rooftop memerlukan waktu kerja di luar toko.');
            }
            if ($rooftop && $atm) {
                $validator->errors()->add('is_atm_repair', 'Pekerjaan rooftop dan perbaikan ATM tidak dapat dipilih bersamaan.');
            }
            if ($rooftop && ! $networkType) {
                $validator->errors()->add('rooftop_network_type', 'Pilih jenis pekerjaan rooftop.');
            }
            $needsAssignment = $category === 'other' && ($atm || ($rooftop && in_array($networkType, ['internet_installation', 'bts_repair'], true)));
            $hasAssignment = $request->hasFile('assignment_letter') || filled($permit->assignment_letter_path);
            if ($needsAssignment && ! $hasAssignment) {
                $validator->errors()->add('assignment_letter', 'Surat tugas wajib diunggah untuk pekerjaan ini.');
            }
        });

        return $validator->validate();
    }

    /** @param array<int, string> $fields @return array{0: ?string, 1: ?string} */
    private function storeWorkFiles(Request $request, array $fields, MalwareScanner $scanner): array
    {
        $idPath = null;
        $assignmentPath = null;
        if (in_array('id_doc', $fields, true)) {
            $scanner->scan($request->file('id_doc'), 'id_doc');
            $idPath = $request->file('id_doc')->store('permits/work/id-docs', 'local');
        }
        if (in_array('assignment_letter', $fields, true)) {
            try {
                $scanner->scan($request->file('assignment_letter'), 'assignment_letter');
                $assignmentPath = $request->file('assignment_letter')->store('permits/work/assignment-letters', 'local');
            } catch (Throwable $exception) {
                if ($idPath) {
                    Storage::disk('local')->delete($idPath);
                }
                throw $exception;
            }
        }

        return [$idPath, $assignmentPath];
    }

    /** @return Collection<int, PermitRevision> */
    private function activeOrFail(LoadingPermit|WorkPermit $permit, PermitRevisionService $revisions): Collection
    {
        abort_unless($permit->status === 'revision_required', 409, 'Permohonan tidak sedang menunggu perbaikan.');
        $active = $revisions->active($permit);
        abort_if($active->isEmpty(), 409, 'Permintaan revisi tidak ditemukan.');

        return $active;
    }

    /** @param Collection<int, PermitRevision> $expected */
    private function ensureSameRevisionBatch(LoadingPermit|WorkPermit $permit, Collection $expected, PermitRevisionService $revisions): void
    {
        $active = $this->activeOrFail($permit, $revisions);
        abort_unless(hash_equals((string) $expected->first()->batch_id, (string) $active->first()->batch_id), 409, 'Permintaan revisi telah berubah. Muat ulang halaman.');
    }

    private function authorizeLoadingApplicant(Request $request, LoadingPermit $permit): void
    {
        $isOwner = $request->user() && $permit->user_id === $request->user()->id;
        $hasValidSignature = $request->hasValidSignature() || $request->hasValidSignature(false);
        abort_unless($isOwner || $hasValidSignature, 403);
    }

    private function workPermitForApplicant(Request $request, string $token): WorkPermit
    {
        $permit = WorkPermit::where('applicant_token', $token)->firstOrFail();
        if ($permit->user_id !== null && ! $request->hasValidSignature(false)) {
            abort_unless($request->user()?->id === $permit->user_id, 403);
        }

        return $permit;
    }

    private function notifyLoadingStaff(LoadingPermit $permit): void
    {
        $pendingCount = LoadingPermit::where('status', 'pending')->count();
        User::query()->where('is_active', true)->where(function ($query): void {
            $query->where(fn ($validator) => $validator->where('role', 'validator')->where('division', 'TR'))
                ->orWhere('role', 'admin');
        })->get()->each(function (User $recipient) use ($permit, $pendingCount): void {
            $notification = PermitNotification::create([
                'user_id' => $recipient->id,
                'permit_id' => $permit->id,
                'type' => 'revision_submitted',
                'title' => 'Perbaikan Permohonan Dikirim',
                'body' => "Perbaikan #{$permit->permit_number} siap diperiksa kembali.",
            ]);
            try {
                LoadingPermitSubmitted::dispatch($notification, $permit, $pendingCount);
                $recipient->notify(new LoadingPermitSubmittedPush($permit));
            } catch (Throwable $exception) {
                Log::warning('Notifikasi perbaikan permohonan loading gagal dikirim.', [
                    'permit_id' => $permit->id,
                    'recipient_id' => $recipient->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function readableIdentityImage(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value || ! $value->isValid()) {
                return;
            }

            $imageInfo = @getimagesize($value->getRealPath());
            if (! $imageInfo) {
                $fail('File tidak dapat diproses sebagai gambar.');

                return;
            }

            [$width, $height] = $imageInfo;
            if ($width < 200 || $height < 100) {
                $fail('Gambar terlalu kecil. Unggah foto KTP/SIM yang jelas.');
            }
        };
    }
}
