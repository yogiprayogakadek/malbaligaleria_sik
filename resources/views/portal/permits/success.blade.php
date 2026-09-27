@extends('layouts.portal')

@section('title', 'Permohonan Berhasil Dicatat')
@section('page-title', 'Permohonan Tersimpan')

@section('content')
<section class="view active" id="view-success" aria-labelledby="successTitle">
  <div class="page-wrap page-wrap--narrow">
    <div class="success-card">
      <div class="success-icon">
        <svg><use href="#i-check"/></svg>
      </div>
      <p class="page-eyebrow">PERMOHONAN TERSIMPAN</p>
      <h1 id="successTitle">Pengajuan berhasil dicatat</h1>
      <p class="page-subtitle">Simpan nomor referensi berikut untuk memantau status persetujuan pengelola gedung.</p>

      <div class="reference-box">
        <span class="reference-label">NOMOR REFERENSI</span>
        <strong id="successReference">{{ $reference }}</strong>
        <button id="copyReference" type="button" class="reference-copy-btn">Salin nomor</button>
      </div>

      <div class="success-meta" id="successMeta">
        {{ $typeInfo['title'] ?? 'Surat Izin' }} &middot; {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}
      </div>

      <div class="alert-info">
        <svg><use href="#i-info"/></svg>
        <span>Status: <strong>Menunggu peninjauan</strong>. Notifikasi resmi dan konfirmasi diteruskan kepada penanggung jawab permohonan.</span>
      </div>

      <div class="success-actions">
        <a href="{{ route('permits.index') }}" class="btn-primary btn-primary--full">
          Lihat permohonan saya
          <svg><use href="#i-arrow-right"/></svg>
        </a>
        <a href="{{ route('portal.dashboard') }}" class="btn-ghost btn-ghost--full">
          Kembali ke beranda
        </a>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  // Copy reference button
  document.getElementById('copyReference')?.addEventListener('click', async () => {
    const text = document.getElementById('successReference')?.textContent || '';
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      window.showToast('Nomor referensi berhasil disalin ke papan klip.');
    } catch {
      window.showToast('Gagal menyalin otomatis. Silakan salin teks secara manual.');
    }
  });
</script>
@endpush
