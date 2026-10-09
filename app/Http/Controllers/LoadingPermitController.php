<?php

namespace App\Http\Controllers;

use App\Events\LoadingPermitSubmitted;
use App\Http\Requests\StoreLoadingPermitRequest;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\User;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\LoadingPermitSubmittedPush;
use App\Services\PermitPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Throwable;

class LoadingPermitController extends Controller
{
    /**
     * Formulir pengajuan permohonan loading in/out.
     */
    public function create()
    {
        $user = Auth::user();

        return view('portal.permits.loading.create', compact('user'));
    }

    /**
     * Simpan permohonan ke database.
     */
    public function store(StoreLoadingPermitRequest $request)
    {
        // Simpan file KTP/SIM
        $docPath = $request->file('id_doc')->store('permits/id-docs', 'local');

        $permit = LoadingPermit::create([
            'permit_number' => LoadingPermit::generatePermitNumber(),
            'user_id' => Auth::id(),
            'tenant_name' => $request->input('tenant_name'),
            'applicant_name' => $request->input('applicant_name'),
            'applicant_phone' => $request->input('applicant_phone'),
            'applicant_email' => $request->input('applicant_email'),
            'direction' => $request->input('direction'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'movement_time' => $request->input('movement_time'),
            'item_count' => $request->integer('item_count'),
            'item_unit' => $request->input('item_unit', 'pcs'),
            'item_description' => $request->input('item_description'),
            'id_doc_path' => $docPath,
            'id_doc_type' => $request->input('id_doc_type'),
            'status' => 'pending',
        ]);

        $pendingCount = LoadingPermit::where('status', 'pending')->count();

        if ($permit->applicant_email) {
            Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
                ->notify(new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::SUBMITTED));
        }

        User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where(function ($validatorQuery): void {
                    $validatorQuery->where('role', 'validator')->where('division', 'TR');
                })->orWhereIn('role', ['admin', 'secretary']);
            })
            ->get()
            ->each(function (User $recipient) use ($permit, $pendingCount): void {
                $notification = PermitNotification::create([
                    'user_id' => $recipient->id,
                    'permit_id' => $permit->id,
                    'type' => 'pending_review',
                    'title' => 'Permohonan Baru',
                    'body' => "{$permit->tenant_name} mengajukan {$permit->direction_label} #{$permit->permit_number}.",
                ]);

                $recipientPendingCount = $recipient->isSecretary()
                    ? PermitNotification::where('user_id', $recipient->id)->whereNull('read_at')->count()
                    : $pendingCount;

                try {
                    LoadingPermitSubmitted::dispatch($notification, $permit, $recipientPendingCount);
                } catch (Throwable $exception) {
                    Log::warning('Notifikasi realtime permohonan loading gagal dikirim.', [
                        'permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }

                try {
                    $recipient->notify(new LoadingPermitSubmittedPush($permit));
                } catch (Throwable $exception) {
                    Log::warning('Push notification permohonan loading gagal dimasukkan ke antrean.', [
                        'permit_id' => $permit->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

        if (Auth::user()?->isTenant()) {
            PermitNotification::create([
                'user_id' => Auth::id(),
                'permit_id' => $permit->id,
                'type' => 'pending_review',
                'title' => 'Permohonan Diterima',
                'body' => "Permohonan #{$permit->permit_number} sedang diproses tim TR. Kami akan memberitahu Anda setelah diverifikasi.",
            ]);
        }

        return redirect(URL::temporarySignedRoute(
            'loading.success',
            now()->addMinutes(30),
            ['permitNumber' => $permit->permit_number],
        ))
            ->with('success', 'Permohonan berhasil diajukan.');
    }

    /**
     * Halaman konfirmasi setelah submit.
     */
    public function success(Request $request, string $permitNumber)
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)->firstOrFail();

        return view('portal.permits.loading.success', compact('permit'));
    }

    /**
     * Detail permohonan (untuk tenant yang login).
     */
    public function show(Request $request, string $permitNumber)
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)->firstOrFail();

        $user = Auth::user();
        $canView = $request->hasValidSignature()
            || ($user && (
                $user->isAdmin()
                || ($user->isValidator() && $user->division === 'TR')
                || $permit->user_id === $user->id
            ));

        abort_unless($canView, 403);

        if (! $user || $request->hasValidSignature()) {
            return view('portal.permits.loading.track', compact('permit'));
        }

        return view('portal.permits.loading.show', compact('permit'));
    }

    /**
     * Unduh surat resmi yang telah disetujui.
     */
    public function downloadLetter(Request $request, string $permitNumber, PermitPdfService $pdf): Response
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->where('status', 'approved')
            ->firstOrFail();

        $user = Auth::user();
        $canView = $request->hasValidSignature()
            || ($user && (
                $user->isAdmin()
                || ($user->isValidator() && $user->division === 'TR')
                || $permit->user_id === $user->id
            ));

        abort_unless($canView, 403);

        // Pastikan token tersedia
        if (! $permit->barcode_token) {
            $permit->generateBarcodeToken();
        }

        return $pdf->loadingPermit($permit);
    }

    /**
     * Penelusuran status surat tanpa login (via nomor surat).
     */
    public function track(Request $request)
    {
        $permitNumber = trim((string) $request->input('permit_number', ''));
        $phone = trim((string) $request->input('phone', ''));

        return redirect()->route('portal.track', array_filter([
            'permit_number' => $permitNumber,
            'phone' => $phone,
        ]));
    }
}
