<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Services\PermitCommunicationTimeline;
use App\Services\SensitiveDocumentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AdminLoadingController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'all';
        $query = LoadingPermit::query()->with('reviewer', 'assignee')->slaPriority();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.loading.index', [
            'permits' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => LoadingPermit::count(),
                'pending' => LoadingPermit::where('status', 'pending')->count(),
                'approved' => LoadingPermit::where('status', 'approved')->count(),
                'rejected' => LoadingPermit::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(string $permitNumber, PermitCommunicationTimeline $timeline)
    {
        $permit = LoadingPermit::query()
            ->where('permit_number', $permitNumber)
            ->with('user', 'reviewer', 'assignee')
            ->firstOrFail();

        $eligibleValidators = User::query()
            ->where('role', 'validator')
            ->where('division', 'TR')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $communicationTimeline = $timeline->forLoading($permit);

        return view('admin.loading.show', compact('permit', 'eligibleValidators', 'communicationTimeline'));
    }

    public function document(Request $request, string $documentToken, SensitiveDocumentResponse $documents): Response
    {
        $permit = LoadingPermit::query()
            ->where('document_token', $documentToken)
            ->firstOrFail();

        abort_unless($permit->id_doc_path && Storage::disk('local')->exists($permit->id_doc_path), 404);

        return $documents->make($permit->id_doc_path, $request->user(), $permit, 'identity');
    }

    public function previewDocument(string $documentToken): View
    {
        $permit = LoadingPermit::query()
            ->where('document_token', $documentToken)
            ->firstOrFail();
        abort_unless($permit->id_doc_path && Storage::disk('local')->exists($permit->id_doc_path), 404);

        return view('documents.preview', [
            'title' => 'Dokumen Identitas Tenant',
            'reference' => $permit->permit_number,
            'sourceUrl' => URL::temporarySignedRoute('admin.loading.document', now()->addMinutes(5), [
                'documentToken' => $permit->document_token,
            ]),
            'downloadUrl' => null,
            'backUrl' => route('admin.loading.show', $permit->permit_number),
            'isImage' => str_starts_with((string) Storage::disk('local')->mimeType($permit->id_doc_path), 'image/'),
        ]);
    }
}
