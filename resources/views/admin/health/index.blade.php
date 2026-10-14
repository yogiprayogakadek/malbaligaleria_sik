@extends('layouts.admin')

@section('title', 'Kesehatan Sistem : Admin MBG')
@section('page-title', 'Kesehatan Sistem')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Kesehatan sistem</span></div>
      <h1 class="page-title">Status layanan</h1>
      <p class="page-subtitle">Pemeriksaan terakhir {{ $checkedAt->format('d M Y, H:i:s') }} WITA.</p>
    </div>
    <a href="{{ route('admin.health.index') }}" class="admin-secondary-button"><svg><use href="#i-clock"/></svg>Periksa ulang</a>
  </div>

  <div class="system-summary" aria-label="Ringkasan kesehatan sistem">
    <div class="is-healthy"><span>Normal</span><strong>{{ $healthyCount }}</strong></div>
    <div class="is-warning"><span>Perlu perhatian</span><strong>{{ $warningCount }}</strong></div>
    <div class="is-error"><span>Bermasalah</span><strong>{{ $errorCount }}</strong></div>
  </div>

  <section class="admin-panel health-panel">
    <div class="admin-panel-header"><div><h2>Komponen aplikasi</h2><p>Status tidak menampilkan kredensial atau isi dokumen.</p></div></div>
    <div class="health-list">
      @foreach($checks as $check)
        <article class="health-row">
          <span class="health-indicator health-indicator--{{ $check['status'] }}" aria-hidden="true"></span>
          <div><strong>{{ $check['name'] }}</strong><p>{{ $check['detail'] }}</p></div>
          <span class="health-state health-state--{{ $check['status'] }}">{{ ['healthy' => 'Normal', 'warning' => 'Perhatian', 'error' => 'Gangguan'][$check['status']] }}</span>
          @if($check['lastSeen'])<small>Terakhir aktif {{ $check['lastSeen'] }}</small>@endif
        </article>
      @endforeach
    </div>
  </section>

  <section class="admin-panel health-alert-panel">
    <div class="admin-panel-header"><div><h2>Riwayat gangguan</h2><p>Alarm aktif ditutup otomatis setelah pemeriksaan berikutnya menyatakan layanan pulih.</p></div></div>
    <div class="health-alert-list">
      @forelse($systemAlerts as $alert)
        <article class="health-alert-row">
          <span class="health-alert-mark {{ $alert->resolved_at ? 'is-resolved' : 'is-active' }}" aria-hidden="true"></span>
          <div><strong>{{ $alert->component }}</strong><p>{{ $alert->detail }}</p></div>
          <span>{{ $alert->resolved_at ? 'Pulih' : 'Aktif' }}</span>
          <time>{{ $alert->last_detected_at->format('d M Y, H:i') }} WITA</time>
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-shield-check"/></svg><strong>Belum ada gangguan tercatat</strong><span>Alarm sistem akan muncul di bagian ini.</span></div>
      @endforelse
    </div>
  </section>

  <section class="admin-panel system-links-panel">
    <div class="admin-panel-header"><div><h2>Konfigurasi sistem</h2><p>Akses cepat untuk pengaturan dan pemeriksaan keamanan.</p></div></div>
    <nav class="system-links" aria-label="Konfigurasi sistem">
      <a href="{{ route('admin.audit.index') }}"><svg><use href="#i-file"/></svg><span><strong>Audit log</strong><small>Riwayat perubahan data</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.mail.edit') }}"><svg><use href="#i-mail"/></svg><span><strong>Email</strong><small>Server pengiriman pesan</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.email-logs.index') }}"><svg><use href="#i-file"/></svg><span><strong>Log email</strong><small>Status kirim dan pengiriman ulang</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.whatsapp.edit') }}"><svg><use href="#i-phone"/></svg><span><strong>WhatsApp</strong><small>WAHA dan penerima</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.scanner.edit') }}"><svg><use href="#i-scan"/></svg><span><strong>Scanner</strong><small>Lokasi dan akses QR</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.security.edit') }}"><svg><use href="#i-lock"/></svg><span><strong>Keamanan</strong><small>Kebijakan autentikasi staf</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.sla.edit') }}"><svg><use href="#i-clock"/></svg><span><strong>SLA antrean</strong><small>Batas waktu pemeriksaan</small></span><svg><use href="#i-chevron-right"/></svg></a>
    </nav>
  </section>
</div>
@endsection
