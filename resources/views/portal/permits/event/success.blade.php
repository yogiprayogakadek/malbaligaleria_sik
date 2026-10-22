@extends('layouts.portal')

@section('title', 'Permohonan Event Diterima : Mal Bali Galeria')
@section('page-title', 'Permohonan Diterima')

@section('content')
<div class="page-wrap page-wrap--narrow"><div class="success-screen">
  <div class="success-icon-wrap"><div class="success-icon-ring"></div><div class="success-icon-core"><svg viewBox="0 0 24 24"><polyline points="6 12 10 16 18 8"/></svg></div></div>
  <h1 class="success-title">Permohonan Surat Izin Event Terkirim</h1>
  <p class="success-sub">Permohonan sudah tercatat dan menunggu pemeriksaan Divisi Event Promotion.</p>
  <div class="permit-ref-card"><div class="permit-ref-label">Nomor Permohonan</div><div class="permit-ref-number" id="refNumber">{{ $permit->permit_number }}</div><button type="button" class="permit-ref-copy" id="copyRefBtn"><svg><use href="#i-file"/></svg><span>Salin</span></button></div>
  <div class="success-detail-grid">
    <div class="success-detail-item"><span class="sdl">EO / Tenant</span><span class="sdv">{{ $permit->contractor_name }}</span></div>
    <div class="success-detail-item"><span class="sdl">PIC</span><span class="sdv">{{ $permit->applicant_name }}</span></div>
    <div class="success-detail-item"><span class="sdl">Jenis Surat</span><span class="sdv">{{ $permit->event_type_label }}</span></div>
    <div class="success-detail-item"><span class="sdl">Kategori</span><span class="sdv">{{ $permit->work_category_label }}</span></div>
    <div class="success-detail-item"><span class="sdl">Periode</span><span class="sdv">{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</span></div>
    <div class="success-detail-item"><span class="sdl">Status</span><span class="sdv badge-status badge-status--pending">Pemeriksaan EP</span></div>
  </div>
  <div class="success-info-box"><svg><use href="#i-mail"/></svg><div><strong>Notifikasi telah dijadwalkan</strong><p>Periksa inbox atau folder spam pada {{ $permit->applicant_email }} untuk tautan status pribadi.</p></div></div>
  <div class="success-actions"><a href="{{ route('event-permits.status', $permit->applicant_token) }}" class="btn-primary">Buka Status Permohonan</a><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Kembali ke Beranda</a></div>
</div></div>
@endsection

@push('scripts')<script nonce="{{ request()->attributes->get('csp_nonce') }}">document.getElementById('copyRefBtn')?.addEventListener('click', async () => { const value = document.getElementById('refNumber')?.textContent.trim(); if (!value) return; try { await navigator.clipboard.writeText(value); window.showToast?.('Nomor permohonan disalin.'); } catch { window.prompt('Salin nomor permohonan:', value); } });</script>@endpush
