<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLoadingController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'all';
        $query = LoadingPermit::query()->with('reviewer')->latest();

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

    public function show(string $permitNumber)
    {
        $permit = LoadingPermit::query()
            ->where('permit_number', $permitNumber)
            ->with('user', 'reviewer')
            ->firstOrFail();

        return view('admin.loading.show', compact('permit'));
    }

    public function document(string $documentToken): StreamedResponse
    {
        $permit = LoadingPermit::query()
            ->where('document_token', $documentToken)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($permit->id_doc_path), 404);

        $response = Storage::disk('local')->response($permit->id_doc_path);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function previewDocument(string $documentToken): View
    {
        $permit = LoadingPermit::query()
            ->where('document_token', $documentToken)
            ->firstOrFail();
        abort_unless(Storage::disk('local')->exists($permit->id_doc_path), 404);

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
