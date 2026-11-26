@extends('layouts.master')

@section('title', 'Verifikasi Invoice Deposit : Mal Bali Galeria')
@section('body-class', 'approval-verification-page')

@section('body')
<main class="approval-verification-shell">
  <a href="{{ route('portal.dashboard') }}" class="approval-verification-brand" aria-label="Portal Perizinan Mal Bali Galeria">
    <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
  </a>

  <section class="approval-verification-panel" aria-labelledby="invoiceVerificationTitle">
    <div class="approval-verification-status {{ $valid ? 'is-valid' : 'is-invalid' }}">
      <svg aria-hidden="true"><use href="#i-{{ $valid ? 'shield-check' : 'info' }}"/></svg>
      <div>
        <p>VERIFIKASI INVOICE DEPOSIT</p>
        <h1 id="invoiceVerificationTitle">{{ $valid ? 'Invoice tercatat dan utuh' : 'Integritas invoice tidak valid' }}</h1>
      </div>
    </div>

    <dl class="approval-verification-data">
      <div><dt>Nomor invoice</dt><dd>{{ $permit->deposit_invoice_number }}</dd></div>
      <div><dt>Referensi izin</dt><dd>{{ $permit->permit_number }}</dd></div>
      <div><dt>Diterbitkan oleh</dt><dd>{{ $permit->deposit_invoice_issuer_name }}</dd></div>
      <div><dt>Unit penerbit</dt><dd>{{ $permit->deposit_invoice_issuer_title }}</dd></div>
      <div><dt>Tanggal terbit</dt><dd>{{ $permit->deposit_invoice_issued_at?->copy()->timezone('Asia/Makassar')->locale('id')->translatedFormat('d F Y, H:i') ?? '-' }} WITA</dd></div>
      <div><dt>ID dokumen</dt><dd class="approval-document-id">{{ $documentId }}</dd></div>
    </dl>

    <div class="approval-verification-note">
      <svg aria-hidden="true"><use href="#i-lock"/></svg>
      <p>{{ $valid ? 'Hash file invoice sesuai dengan catatan penerbitan sistem.' : 'File invoice tidak ditemukan atau isinya tidak lagi sesuai dengan catatan penerbitan. Hubungi Finance Mal Bali Galeria.' }}</p>
    </div>
  </section>
</main>
@endsection
