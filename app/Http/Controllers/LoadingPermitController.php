<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoadingPermitRequest;
use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            'permit_number'    => LoadingPermit::generatePermitNumber(),
            'user_id'          => Auth::id(),
            'tenant_name'      => $request->input('tenant_name'),
            'applicant_name'   => $request->input('applicant_name'),
            'applicant_phone'  => $request->input('applicant_phone'),
            'applicant_email'  => $request->input('applicant_email'),
            'direction'        => $request->input('direction'),
            'start_date'       => $request->input('start_date'),
            'end_date'         => $request->input('end_date'),
            'item_count'       => $request->integer('item_count'),
            'item_unit'        => $request->input('item_unit', 'pcs'),
            'item_description' => $request->input('item_description'),
            'id_doc_path'      => $docPath,
            'id_doc_type'      => $request->input('id_doc_type'),
            'status'           => 'pending',
        ]);

        // Notifikasi ke TR division jika ada user TR di sistem (via DB notification)
        // Tenant yang login mendapat konfirmasi
        if (Auth::check()) {
            PermitNotification::create([
                'user_id'   => Auth::id(),
                'permit_id' => $permit->id,
                'type'      => 'pending_review',
                'title'     => 'Permohonan Diterima',
                'body'      => "Permohonan #{$permit->permit_number} sedang diproses tim TR. Kami akan memberitahu Anda setelah diverifikasi.",
            ]);
        }

        return redirect()->route('loading.success', $permit->permit_number)
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

        // Guest hanya boleh lihat status dasar
        if (! Auth::check()) {
            return view('portal.permits.loading.track', compact('permit'));
        }

        // Tenant hanya boleh lihat milik sendiri di view show, selain itu tampilkan track view
        if (Auth::id() && $permit->user_id !== Auth::id() && ! Auth::user()->isValidator() && ! Auth::user()->isAdmin()) {
            return view('portal.permits.loading.track', compact('permit'));
        }

        return view('portal.permits.loading.show', compact('permit'));
    }

    /**
     * Download / tampilkan surat yang sudah diapprove (PDF-like view).
     */
    public function downloadLetter(string $permitNumber)
    {
        $permit = LoadingPermit::where('permit_number', $permitNumber)
            ->where('status', 'approved')
            ->firstOrFail();

        // Pastikan token tersedia
        if (! $permit->barcode_token) {
            $permit->generateBarcodeToken();
        }

        return view('portal.permits.loading.letter', compact('permit'));
    }

    /**
     * Penelusuran status surat tanpa login (via nomor surat).
     */
    public function track(Request $request)
    {
        $permitNumber = trim((string) $request->input('permit_number', ''));
        return redirect()->route('portal.track', ['permit_number' => $permitNumber]);
    }
}
