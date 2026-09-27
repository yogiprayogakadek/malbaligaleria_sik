@extends('layouts.portal')

@section('title', 'Permohonan Berhasil Diajukan : Mal Bali Galeria')
@section('page-title', 'Permohonan Diterima')

@section('content')
<div class="page-wrap page-wrap--narrow">
  <div class="success-screen">

    <div class="success-icon-wrap">
      <div class="success-icon-ring"></div>
      <div class="success-icon-core">
        <svg width="32" height="32" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <polyline points="6 12 10 16 18 8"/>
        </svg>
      </div>
    </div>

    <h1 class="success-title">Permohonan Berhasil Dikirimkan</h1>
    <p class="success-sub">
      Permohonan izin loading barang telah tercatat dalam sistem dan segera diverifikasi oleh tim Tenant Relationship (TR).
    </p>

    <div class="permit-ref-card">
      <div class="permit-ref-label">Nomor Surat Izin</div>
      <div class="permit-ref-number" id="refNumber">{{ $permit->permit_number }}</div>
      <button type="button" class="permit-ref-copy" id="copyRefBtn" aria-label="Salin nomor surat">
        <svg width="15" height="15"><use href="#i-file"/></svg>
        <span>Salin</span>
      </button>
    </div>

    <div class="success-detail-grid">
      <div class="success-detail-item">
        <span class="sdl">Unit Tenant</span>
        <span class="sdv">{{ $permit->tenant_name }}</span>
      </div>
      <div class="success-detail-item">
        <span class="sdl">Arah Loading</span>
        <span class="sdv">{{ $permit->direction_label }}</span>
      </div>
      <div class="success-detail-item">
        <span class="sdl">Periode Izin</span>
        <span class="sdv">{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</span>
      </div>
      <div class="success-detail-item">
        <span class="sdl">Jumlah Barang</span>
        <span class="sdv">{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</span>
      </div>
      <div class="success-detail-item">
        <span class="sdl">Status Saat Ini</span>
        <span class="sdv badge-status badge-status--pending">Menunggu Verifikasi TR</span>
      </div>
    </div>

    <div class="success-info-box">
      <svg width="20" height="20"><use href="#i-info"/></svg>
      <div>
        <strong>Simpan Nomor Surat Anda</strong>
        <p>Anda dapat memantau perkembangan status izin kapan saja melalui menu <a href="{{ route('portal.track') }}">Cek Status</a> menggunakan nomor surat di atas.</p>
      </div>
    </div>

    <div class="success-actions">
      @auth
        <a href="{{ route('loading.show', $permit->permit_number) }}" class="btn-primary">Lihat Detail Permohonan</a>
        <a href="{{ route('permits.index') }}" class="btn-secondary">Riwayat Permohonan</a>
      @else
        <a href="{{ route('loading.track') }}" class="btn-primary" onclick="event.preventDefault(); document.getElementById('quickTrackForm').submit();">Pantau Status Surat</a>
        <form id="quickTrackForm" action="{{ route('loading.track') }}" method="POST" style="display:none;">
          @csrf
          <input type="hidden" name="permit_number" value="{{ $permit->permit_number }}">
        </form>
        <a href="{{ route('login') }}" class="btn-secondary">Masuk ke Akun Tenant</a>
      @endauth
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copyRefBtn')?.addEventListener('click', async () => {
  const ref = document.getElementById('refNumber')?.textContent.trim();
  if (!ref) return;
  try {
    await navigator.clipboard.writeText(ref);
    if (window.showToast) window.showToast('Nomor surat disalin ke clipboard.');
    else alert('Nomor surat berhasil disalin: ' + ref);
  } catch {
    alert('Nomor surat: ' + ref);
  }
});
</script>
@endpush
