@extends('layouts.admin')

@section('title', 'Konfigurasi WhatsApp : Admin MBG')
@section('page-title', 'Konfigurasi WhatsApp')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page admin-whatsapp-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>WhatsApp</span></div>
      <h1 class="page-title">Notifikasi WhatsApp</h1>
      <p class="page-subtitle">Hubungkan aplikasi ke WAHA dan tentukan akun staf yang menerima pembaruan permohonan.</p>
    </div>
    <span class="admin-live-state {{ ($setting?->is_active ?? false) ? 'is-open' : 'is-closed' }}"><span></span>{{ ($setting?->is_active ?? false) ? 'WAHA aktif' : 'WAHA nonaktif' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('whatsapp_test_success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('whatsapp_test_success') }}</span></div>@endif
  @if(session('whatsapp_test_error'))<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ session('whatsapp_test_error') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali konfigurasi yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Koneksi WAHA</h2><p>API key disimpan terenkripsi dan hanya dikirim melalui header ke host yang diizinkan.</p></div>
      <span class="admin-security-label"><svg><use href="#i-lock"/></svg>Host dibatasi</span>
    </div>

    <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST" class="admin-form admin-mail-form" autocomplete="off">
      @csrf @method('PUT')
      <div class="admin-form-grid">
        <label>
          <span>Alamat server WAHA</span>
          <input type="url" name="base_url" value="{{ old('base_url', $setting?->base_url ?? config('waha.default_base_url')) }}" required maxlength="255" placeholder="http://192.168.0.253:3000" autocomplete="off">
          <small>Host harus terdaftar pada `WAHA_ALLOWED_HOSTS` di server aplikasi.</small>
          @error('base_url')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Nama sesi</span>
          <input name="session" value="{{ old('session', $setting?->session ?? config('waha.default_session')) }}" required maxlength="100" autocomplete="off">
          <small>Sesi harus sudah terhubung dan berstatus WORKING pada WAHA.</small>
          @error('session')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>API key</span>
          <input type="password" name="api_key" value="" {{ $setting ? '' : 'required' }} minlength="16" maxlength="512" autocomplete="new-password">
          <small>{{ $setting ? 'Kosongkan untuk mempertahankan API key yang tersimpan.' : 'Masukkan API key WAHA saat konfigurasi pertama.' }}</small>
          @error('api_key')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Batas waktu koneksi</span>
          <input type="number" name="timeout" value="{{ old('timeout', $setting?->timeout ?? 10) }}" min="3" max="30" required inputmode="numeric">
          <small>Dalam detik, antara 3 sampai 30.</small>
          @error('timeout')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="admin-mail-options whatsapp-setting-options">
        <label class="admin-check">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setting?->is_active ?? false))>
          <span>Aktifkan pengiriman melalui WhatsApp</span>
        </label>
        <label class="admin-check">
          <input type="hidden" name="notify_applicants" value="0">
          <input type="checkbox" name="notify_applicants" value="1" @checked(old('notify_applicants', $setting?->notify_applicants ?? true))>
          <span>Kirim pembaruan kepada pemohon</span>
        </label>
        <p>Pesan pemohon hanya dikirim jika nomor WhatsApp valid. Tautan status memakai URL bertanda tangan dan memiliki masa berlaku.</p>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan konfigurasi</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Penerima internal</h2><p>Hanya akun aktif dan relevan dengan alur permohonan yang akan menerima pesan.</p></div>
      <span class="admin-recipient-count">{{ $recipients->where('whatsapp_notifications_enabled', true)->count() }} dipilih</span>
    </div>
    <form action="{{ route('admin.settings.whatsapp.recipients') }}" method="POST">
      @csrf @method('PUT')
      <div class="whatsapp-recipient-list">
        @forelse($recipients as $recipient)
          @php
            $roleLabel = match ($recipient->role) {
              'admin' => 'Administrator',
              'secretary' => 'Secretary',
              'validator' => 'Validator '.($recipient->division ?: '-'),
              default => ucfirst($recipient->role),
            };
          @endphp
          <label class="whatsapp-recipient-row {{ $recipient->is_active ? '' : 'is-disabled' }}">
            <span class="admin-validator-avatar">{{ strtoupper(substr($recipient->name, 0, 1)) }}</span>
            <span class="whatsapp-recipient-copy"><strong>{{ $recipient->name }}</strong><small>{{ $roleLabel }} · {{ \App\Support\IndonesianPhoneNumber::mask($recipient->phone) }}</small></span>
            @unless($recipient->is_active)<span class="admin-status-pill is-inactive">Nonaktif</span>@endunless
            <input type="checkbox" name="recipients[]" value="{{ $recipient->id }}" @checked($recipient->whatsapp_notifications_enabled && $recipient->is_active) @disabled(! $recipient->is_active)>
          </label>
        @empty
          <div class="admin-empty"><svg><use href="#i-user"/></svg><strong>Belum ada akun staf</strong><span>Buat akun validator atau secretary terlebih dahulu.</span></div>
        @endforelse
      </div>
      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan penerima</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Uji koneksi dan pengiriman</h2><p>Sistem memeriksa status sesi sebelum mengirim satu pesan uji.</p></div>
      <span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>3 percobaan per menit</span>
    </div>
    <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST" class="admin-mail-test-form" data-whatsapp-test-form>
      @csrf
      <label>
        <span>Nomor tujuan pengujian</span>
        <input name="recipient" value="{{ old('recipient', Auth::user()->phone) }}" required maxlength="25" inputmode="tel" autocomplete="tel" placeholder="081234567890">
        <small>Simpan konfigurasi sebelum menjalankan pengujian.</small>
        @error('recipient')<span class="admin-field-error">{{ $message }}</span>@enderror
      </label>
      <button type="submit" class="admin-secondary-button" data-whatsapp-test-submit><svg><use href="#i-phone"/></svg><span>Kirim pesan uji</span></button>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Aktivitas terakhir</h2><p>Nomor tujuan disamarkan. Isi pesan dan API key tidak ditampilkan.</p></div></div>
    <div class="admin-table-wrap">
      <table class="admin-table whatsapp-log-table">
        <thead><tr><th>Waktu</th><th>Penerima</th><th>Jenis</th><th>Event</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td>{{ $log->created_at->format('d M Y H:i') }}</td>
              <td><strong>{{ $log->recipient_name ?: 'Pemohon' }}</strong><span>{{ \App\Support\IndonesianPhoneNumber::mask($log->recipient_phone) }}</span></td>
              <td>{{ $log->permit_type === 'loading' ? 'Loading / Unloading' : 'Izin Kerja' }}</td>
              <td>{{ str($log->event)->replace('_', ' ')->title() }}</td>
              <td><span class="whatsapp-log-status is-{{ $log->status }}">{{ ucfirst($log->status) }}</span></td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="admin-table-empty"><svg><use href="#i-phone"/></svg><strong>Belum ada aktivitas WhatsApp</strong><span>Log akan tampil setelah pesan pertama dimasukkan ke antrean.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelector('[data-whatsapp-test-form]')?.addEventListener('submit', event => {
  const button = event.currentTarget.querySelector('[data-whatsapp-test-submit]');
  if (!button || button.disabled) return;
  button.disabled = true;
  button.querySelector('span').textContent = 'Mengirim...';
});
</script>
@endpush
