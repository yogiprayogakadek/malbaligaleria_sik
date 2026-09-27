<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TRController extends Controller
{
    /**
     * Dashboard TR : daftar permohonan loading yang masuk.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $validStatuses = ['all', 'pending', 'approved', 'rejected'];
        if (! in_array($status, $validStatuses)) $status = 'pending';

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
            'all'      => LoadingPermit::count(),
            'pending'  => LoadingPermit::where('status', 'pending')->count(),
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
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'review_notes' => $request->input('review_notes'),
            'reviewed_at'  => now(),
        ]);

        // Generate barcode token unik untuk surat
        $permit->generateBarcodeToken();

        // Kirim notifikasi ke tenant jika dia punya akun
        if ($permit->user_id) {
            PermitNotification::create([
                'user_id'   => $permit->user_id,
                'permit_id' => $permit->id,
                'type'      => 'approved',
                'title'     => 'Permohonan Disetujui',
                'body'      => "Permohonan loading #{$permit->permit_number} ({$permit->direction_label}) telah disetujui. Silakan unduh surat izin Anda.",
            ]);
        }

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
            'review_notes.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->where('status', 'pending')
            ->firstOrFail();

        $permit->update([
            'status'       => 'rejected',
            'reviewed_by'  => Auth::id(),
            'review_notes' => $request->input('review_notes'),
            'reviewed_at'  => now(),
        ]);

        // Kirim notifikasi penolakan ke tenant
        if ($permit->user_id) {
            PermitNotification::create([
                'user_id'   => $permit->user_id,
                'permit_id' => $permit->id,
                'type'      => 'rejected',
                'title'     => 'Permohonan Ditolak',
                'body'      => "Permohonan loading #{$permit->permit_number} ditolak. Alasan: {$request->input('review_notes')}",
            ]);
        }

        return redirect()->route('tr.index')
            ->with('info', "Permohonan {$permit->permit_number} telah ditolak.");
    }

    /**
     * Tampilkan atau unduh dokumen identitas yang diupload pemohon.
     */
    public function viewIdDoc(string $permitNumber)
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)->firstOrFail();

        if (! Storage::disk('local')->exists($permit->id_doc_path)) {
            abort(404, 'Dokumen identitas tidak ditemukan.');
        }

        return Storage::disk('local')->response($permit->id_doc_path);
    }
}
