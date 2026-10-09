<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Services\WorkPermitWorkflowNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TRController extends Controller
{
    /**
     * Dashboard TR : daftar permohonan loading yang masuk.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $validStatuses = ['all', 'pending', 'approved', 'rejected'];
        if (! in_array($status, $validStatuses)) {
            $status = 'pending';
        }

        $allowedPerPage = [5, 10, 20, 50, 100];
        $perPageRaw = $request->query('per_page', 10);
        $showAll = ($perPageRaw === 'all');
        $perPage = in_array((int) $perPageRaw, $allowedPerPage) ? (int) $perPageRaw : 10;

        $query = LoadingPermit::with('user')->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $permits = $showAll
            ? (clone $query)->paginate((clone $query)->count() ?: 1)->withQueryString()
            : $query->paginate($perPage)->withQueryString();

        $counts = [
            'all' => LoadingPermit::count(),
            'pending' => LoadingPermit::where('status', 'pending')->count(),
            'approved' => LoadingPermit::where('status', 'approved')->count(),
            'rejected' => LoadingPermit::where('status', 'rejected')->count(),
        ];

        return view('tr.index', compact('permits', 'status', 'counts', 'perPageRaw'));
    }

    /**
     * Detail permohonan untuk di-review.
     */
    public function show(string $permitNumber)
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->with('user', 'reviewer')
            ->firstOrFail();

        return view('tr.show', compact('permit'));
    }

    public function workPermits(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'tr_review', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'tr_review';

        $query = WorkPermit::query()
            ->where('assigned_division', 'TR')
            ->withCount('workers')
            ->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('tr.work-permits.index', [
            'permits' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => WorkPermit::where('assigned_division', 'TR')->count(),
                'tr_review' => WorkPermit::needsTrAction()->count(),
                'approved' => WorkPermit::where('assigned_division', 'TR')->where('status', 'approved')->count(),
                'rejected' => WorkPermit::where('assigned_division', 'TR')->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function reviewWorkPermit(Request $request, string $token, WorkPermitWorkflowNotifier $notifier)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => [
                $request->input('decision') === 'rejected' ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'review_notes.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $permit = DB::transaction(function () use ($request, $token, $data): WorkPermit {
            $permit = WorkPermit::where('public_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($permit->assigned_division === 'TR' && $permit->status === 'tr_review', 409, 'Permohonan sudah diproses atau bukan kewenangan TR.');

            $permit->update([
                'status' => $data['decision'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'deposit_required' => false,
                'security_deposit' => false,
            ]);
            $permit->statusLogs()->create([
                'actor_id' => $request->user()->id,
                'from_status' => 'tr_review',
                'to_status' => $data['decision'],
                'action' => 'tr_decision',
                'notes' => $data['review_notes'] ?? null,
            ]);

            return $permit;
        });

        $notifier->notifyApplicant(
            $permit,
            $permit->status === 'approved' ? 'Permohonan Izin Kerja Disetujui' : 'Permohonan Izin Kerja Ditolak',
            $permit->status === 'approved'
                ? 'Divisi TR telah menyetujui permohonan izin kerja Anda.'
                : ($permit->review_notes ?: 'Permohonan belum dapat disetujui.'),
        );

        return redirect()->route('tr.work-permits.index')->with(
            $permit->status === 'approved' ? 'success' : 'info',
            "Permohonan {$permit->permit_number} telah {$permit->status_label}.",
        );
    }

    public function readNotification(PermitNotification $notification)
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        $notification->markRead();

        if ($notification->workPermit) {
            abort_unless($notification->workPermit->assigned_division === 'TR', 403);

            return redirect()->route('staff.work-permits.show', $notification->workPermit->public_token);
        }

        $permit = $notification->permit;

        return $permit
            ? redirect()->route('tr.show', $permit->permit_number)
            : redirect()->route('tr.index');
    }

    /**
     * Approve permohonan loading.
     */
    public function approve(Request $request, string $permitNumber)
    {
        $request->validate([
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->where('status', 'pending')
            ->firstOrFail();

        $permit->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'review_notes' => $request->input('review_notes'),
            'reviewed_at' => now(),
        ]);

        // Generate barcode token unik untuk surat
        $permit->generateBarcodeToken();

        // Kirim notifikasi ke tenant jika dia punya akun
        if ($permit->user_id) {
            PermitNotification::create([
                'user_id' => $permit->user_id,
                'permit_id' => $permit->id,
                'type' => 'approved',
                'title' => 'Permohonan Disetujui',
                'body' => "Permohonan loading #{$permit->permit_number} ({$permit->direction_label}) telah disetujui. Silakan unduh surat izin Anda.",
            ]);
        }

        $this->notifyApplicant($permit, LoadingPermitApplicantMail::APPROVED);

        return redirect()->route('tr.index')
            ->with('success', "Permohonan {$permit->permit_number} berhasil disetujui.");
    }

    /**
     * Tolak permohonan loading.
     */
    public function reject(Request $request, string $permitNumber)
    {
        $request->validate([
            'review_notes' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'review_notes.required' => 'Alasan penolakan wajib diisi.',
            'review_notes.min' => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->where('status', 'pending')
            ->firstOrFail();

        $permit->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'review_notes' => $request->input('review_notes'),
            'reviewed_at' => now(),
        ]);

        // Kirim notifikasi penolakan ke tenant
        if ($permit->user_id) {
            PermitNotification::create([
                'user_id' => $permit->user_id,
                'permit_id' => $permit->id,
                'type' => 'rejected',
                'title' => 'Permohonan Ditolak',
                'body' => "Permohonan loading #{$permit->permit_number} ditolak. Alasan: {$request->input('review_notes')}",
            ]);
        }

        $this->notifyApplicant($permit, LoadingPermitApplicantMail::REJECTED);

        return redirect()->route('tr.index')
            ->with('info', "Permohonan {$permit->permit_number} telah ditolak.");
    }

    /**
     * Tampilkan atau unduh dokumen identitas yang diupload pemohon.
     */
    public function viewIdDoc(string $documentToken)
    {
        $permit = LoadingPermit::where('document_token', $documentToken)->firstOrFail();

        if (! Storage::disk('local')->exists($permit->id_doc_path)) {
            abort(404, 'Dokumen identitas tidak ditemukan.');
        }

        $response = Storage::disk('local')->response($permit->id_doc_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function previewIdDoc(string $documentToken): View
    {
        $permit = LoadingPermit::where('document_token', $documentToken)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($permit->id_doc_path), 404);

        return view('documents.preview', [
            'title' => 'Dokumen Identitas Tenant',
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), [
                'documentToken' => $permit->document_token,
            ]),
            'downloadUrl' => null,
            'backUrl' => route('tr.show', $permit->permit_number),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($permit->id_doc_path), 'image/'),
        ]);
    }

    private function notifyApplicant(LoadingPermit $permit, string $type): void
    {
        if (! $permit->applicant_email) {
            return;
        }

        Notification::route('mail', [$permit->applicant_email => $permit->applicant_name])
            ->notify(new LoadingPermitApplicantMail($permit, $type));
    }
}
