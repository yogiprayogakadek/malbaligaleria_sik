@extends('layouts.master')

@section('title', 'Verifikasi Persetujuan : Mal Bali Galeria')
@section('body-class', 'approval-verification-page')

@section('body')
<main class="approval-verification-shell">
  <a href="{{ route('portal.dashboard') }}" class="approval-verification-brand" aria-label="Portal Perizinan Mal Bali Galeria">
    <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
  </a>

  <section class="approval-verification-panel" aria-labelledby="approvalVerificationTitle">
    <div class="approval-verification-status {{ $valid ? 'is-valid' : 'is-invalid' }}">
      <svg aria-hidden="true"><use href="#i-{{ $valid ? 'shield-check' : 'info' }}"/></svg>
      <div>
        <p>VERIFIKASI PERSETUJUAN DIGITAL</p>
        <h1 id="approvalVerificationTitle">{{ $valid ? 'Persetujuan tercatat dan sah' : (!$integrityValid ? 'Integritas dokumen tidak valid' : ($replaced ? 'Surat ini telah digantikan' : 'Surat izin telah dicabut')) }}</h1>
      </div>
    </div>

    <dl class="approval-verification-data">
      <div><dt>Nomor surat</dt><dd>{{ $permit->permit_number }}</dd></div>
      <div><dt>Jenis surat</dt><dd>{{ $permitTypeLabel }}</dd></div>
      <div><dt>Disetujui oleh</dt><dd>{{ $validatorName }}</dd></div>
      <div><dt>Divisi</dt><dd>{{ $division }}</dd></div>
      <div><dt>Waktu persetujuan</dt><dd>{{ $permit->reviewed_at?->copy()->timezone('Asia/Makassar')->locale('id')->translatedFormat('d F Y, H:i') ?? '-' }} WITA</dd></div>
      <div><dt>ID dokumen</dt><dd class="approval-document-id">{{ $documentId }}</dd></div>
    </dl>

    @if($replaced)
      <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Dokumen ini tidak berlaku karena telah diterbitkan ulang. Alasan: {{ $replacementReason }}</span></div>
    @endif

    <div class="approval-verification-note">
      <svg aria-hidden="true"><use href="#i-lock"/></svg>
      <p>Halaman ini hanya memverifikasi pencatat persetujuan dan integritas surat. Masa berlaku serta akses operasional tetap diperiksa melalui QR utama dokumen.</p>
    </div>
  </section>
</main>
@endsection
