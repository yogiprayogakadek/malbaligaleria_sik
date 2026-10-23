@extends('layouts.admin')

@section('title', 'Kesehatan Sistem : Admin MBG')
@section('page-title', 'Kesehatan Sistem')

@section('content')
<div class="page-wrap admin-page-wrap">
  @if(session('monitoring_error'))<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ session('monitoring_error') }}</span></div>@endif
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

  <section class="admin-panel monitoring-panel">
    <div class="admin-panel-header"><div><h2>Monitoring eksternal</h2><p>Kirim gangguan baru ke webhook HTTPS yang telah diizinkan server.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>HMAC SHA-256</span></div>
    <form method="POST" action="{{ route('admin.settings.monitoring.update') }}" class="monitoring-form">
      @csrf @method('PUT')
      <div class="monitoring-form-grid">
        <label><span>Status pengiriman</span><select name="enabled"><option value="1" @selected((int)old('enabled',$monitoringSetting->enabled)===1)>Aktif</option><option value="0" @selected((int)old('enabled',$monitoringSetting->enabled)===0)>Nonaktif</option></select></label>
        <label><span>Timeout permintaan</span><div class="monitoring-input-unit"><input type="number" name="timeout_seconds" min="3" max="20" value="{{ old('timeout_seconds',$monitoringSetting->timeout_seconds) }}" required><span>detik</span></div></label>
        <label class="monitoring-form-span"><span>Alamat webhook HTTPS</span><input type="url" name="webhook_url" value="{{ old('webhook_url',$monitoringSetting->webhook_url) }}" placeholder="https://monitor.example.com/hooks/mbg"><small>Domain tujuan wajib tercantum pada allowlist server.</small></label>
        <label><span>Signing secret baru</span><input type="password" name="signing_secret" minlength="32" autocomplete="new-password"><small>Kosongkan untuk mempertahankan secret lama.</small></label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan monitoring</button></div>
    </form>
    <div class="monitoring-test-block">
      <div><strong>Uji koneksi webhook</strong><p>Kirim payload uji tanpa data permohonan atau kredensial.</p></div>
      <form method="POST" action="{{ route('admin.settings.monitoring.test') }}" class="monitoring-test-form">@csrf<label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label><button class="admin-secondary-button"><svg><use href="#i-mail"/></svg>Kirim pesan uji</button></form>
    </div>
  </section>

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
      <a href="{{ route('admin.schedule.edit') }}"><svg><use href="#i-clock"/></svg><span><strong>Jam operasional</strong><small>Jadwal portal dan tanggal khusus</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.permit-limits.edit') }}"><svg><use href="#i-clock"/></svg><span><strong>Batas pengajuan</strong><small>Kuota permohonan tenant</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.audit.index') }}"><svg><use href="#i-file"/></svg><span><strong>Audit log</strong><small>Riwayat perubahan data</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.mail.edit') }}"><svg><use href="#i-mail"/></svg><span><strong>Email</strong><small>Server pengiriman pesan</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.email-logs.index') }}"><svg><use href="#i-file"/></svg><span><strong>Log email</strong><small>Status kirim dan pengiriman ulang</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.failed-jobs.index') }}"><svg><use href="#i-info"/></svg><span><strong>Antrean gagal</strong><small>Pekerjaan queue yang perlu ditinjau</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.whatsapp.edit') }}"><svg><use href="#i-phone"/></svg><span><strong>WhatsApp</strong><small>WAHA dan penerima</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.scanner.edit') }}"><svg><use href="#i-scan"/></svg><span><strong>Scanner</strong><small>Lokasi dan akses QR</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.security.edit') }}"><svg><use href="#i-lock"/></svg><span><strong>Keamanan</strong><small>Kebijakan autentikasi staf</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.upload-security.edit') }}"><svg><use href="#i-shield-check"/></svg><span><strong>Pemeriksaan upload</strong><small>ClamAV dan kebijakan lampiran</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.retention.edit') }}"><svg><use href="#i-trash"/></svg><span><strong>Retensi dokumen</strong><small>Masa simpan file sensitif</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.document-templates.index') }}"><svg><use href="#i-file"/></svg><span><strong>Template surat</strong><small>DOCX dan document converter</small></span><svg><use href="#i-chevron-right"/></svg></a>
      <a href="{{ route('admin.settings.sla.edit') }}"><svg><use href="#i-clock"/></svg><span><strong>SLA antrean</strong><small>Batas waktu pemeriksaan</small></span><svg><use href="#i-chevron-right"/></svg></a>
    </nav>
  </section>
</div>
@endsection
