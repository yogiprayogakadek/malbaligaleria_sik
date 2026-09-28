@extends('layouts.portal')

@section('title', 'Permohonan Izin Kerja Diterima : Mal Bali Galeria')
@section('page-title', 'Permohonan Diterima')

@section('content')
<div class="page-wrap page-wrap--narrow">
  <div class="success-screen">
    <div class="success-icon-wrap"><div class="success-icon-ring"></div><div class="success-icon-core"><svg width="32" height="32" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 12 10 16 18 8"/></svg></div></div>
    <h1 class="success-title">Permohonan Izin Kerja Terkirim</h1>
    <p class="success-sub">Data kontraktor, pekerja, dan dokumen identitas telah tercatat dan menunggu pemeriksaan pengelola gedung.</p>

    <div class="permit-ref-card"><div class="permit-ref-label">Nomor Permohonan</div><div class="permit-ref-number" id="refNumber">{{ $permit->permit_number }}</div><button type="button" class="permit-ref-copy" id="copyRefBtn"><svg width="15" height="15"><use href="#i-file"/></svg><span>Salin</span></button></div>

    <div class="success-detail-grid">
      <div class="success-detail-item"><span class="sdl">Kontraktor / Tenant</span><span class="sdv">{{ $permit->contractor_name }}</span></div>
      <div class="success-detail-item"><span class="sdl">Penanggung Jawab</span><span class="sdv">{{ $permit->applicant_name }}</span></div>
      <div class="success-detail-item"><span class="sdl">Lokasi</span><span class="sdv">{{ $permit->work_location }}</span></div>
      <div class="success-detail-item"><span class="sdl">Kategori</span><span class="sdv">{{ $permit->work_category_label }}</span></div>
      <div class="success-detail-item"><span class="sdl">Divisi Pemeriksa</span><span class="sdv">{{ $permit->assigned_division }}</span></div>
      <div class="success-detail-item"><span class="sdl">Jumlah Pekerja</span><span class="sdv">{{ $permit->workers->count() }} orang</span></div>
      <div class="success-detail-item"><span class="sdl">Periode</span><span class="sdv">{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</span></div>
      <div class="success-detail-item"><span class="sdl">Status</span><span class="sdv badge-status badge-status--pending">Menunggu Verifikasi</span></div>
    </div>

    <div class="success-info-box"><svg width="20" height="20"><use href="#i-info"/></svg><div><strong>Simpan nomor permohonan</strong><p>Pengelola gedung akan memeriksa jadwal, daftar pekerja, dan dokumen yang diajukan.</p></div></div>
    <div class="success-info-box"><svg width="20" height="20"><use href="#i-lock"/></svg><div><strong>Simpan tautan status pribadi</strong><p>Tautan acak ini diperlukan pemohon tanpa akun untuk mengikuti perkembangan permohonan@if($permit->assigned_division === 'MEP') dan mengunggah bukti deposit jika diminta@endif.</p></div></div>
    <div class="success-actions"><a href="{{ route('work-permits.status', $permit->applicant_token) }}" class="btn-primary">Buka Status Permohonan</a><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Kembali ke Beranda</a></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copyRefBtn')?.addEventListener('click', async () => {
  const reference = document.getElementById('refNumber')?.textContent.trim();
  if (!reference) return;
  try { await navigator.clipboard.writeText(reference); window.showToast?.('Nomor permohonan disalin.'); }
  catch { window.prompt('Salin nomor permohonan:', reference); }
});
</script>
@endpush
