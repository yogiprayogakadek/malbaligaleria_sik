@extends('layouts.admin')

@section('title', 'Konfigurasi Email : Admin MBG')
@section('page-title', 'Konfigurasi Email')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Email</span></div>
      <h1 class="page-title">Akun pengirim email</h1>
      <p class="page-subtitle">Konfigurasi SMTP ini digunakan untuk mengirim pembaruan status kepada pemohon.</p>
    </div>
    <span class="admin-live-state {{ ($setting?->is_active ?? false) ? 'is-open' : 'is-closed' }}"><span></span>{{ ($setting?->is_active ?? false) ? 'Konfigurasi aktif' : 'Menggunakan .env' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('mail_test_success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('mail_test_success') }}</span></div>@endif
  @if(session('mail_test_error'))<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ session('mail_test_error') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali konfigurasi yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Server SMTP keluar</h2><p>IMAP dan POP3 tidak diperlukan karena sistem hanya mengirim email.</p></div>
      <span class="admin-security-label"><svg><use href="#i-lock"/></svg>Rahasia terenkripsi</span>
    </div>

    <form action="{{ route('admin.settings.mail.update') }}" method="POST" class="admin-form admin-mail-form" autocomplete="off">
      @csrf @method('PUT')
      <div class="admin-form-grid admin-mail-grid">
        <label>
          <span>Server SMTP</span>
          <input name="host" value="{{ old('host', $setting?->host ?? config('mail.mailers.smtp.host')) }}" placeholder="mail.example.com" required maxlength="253" inputmode="url" autocomplete="off">
          @error('host')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Port SMTP</span>
          <select name="port" required>
            @foreach([465 => '465 - SSL/TLS', 587 => '587 - STARTTLS', 25 => '25 - SMTP', 2525 => '2525 - Alternatif'] as $port => $label)
              <option value="{{ $port }}" @selected((int) old('port', $setting?->port ?? config('mail.mailers.smtp.port')) === $port)>{{ $label }}</option>
            @endforeach
          </select>
          @error('port')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Keamanan koneksi</span>
          @php $selectedScheme = old('scheme', $setting?->scheme ?? config('mail.mailers.smtp.scheme', 'smtps')); @endphp
          <select name="scheme" required>
            <option value="smtps" @selected($selectedScheme === 'smtps')>SSL/TLS (SMTPS)</option>
            <option value="smtp" @selected($selectedScheme === 'smtp')>SMTP / STARTTLS</option>
          </select>
          @error('scheme')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Batas waktu koneksi</span>
          <input type="number" name="timeout" value="{{ old('timeout', $setting?->timeout ?? config('mail.mailers.smtp.timeout', 10)) }}" min="3" max="60" required inputmode="numeric">
          <small>Dalam detik, antara 3 sampai 60.</small>
          @error('timeout')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Email pengguna</span>
          <input type="email" name="username" value="{{ old('username', $setting?->username ?? config('mail.mailers.smtp.username')) }}" required maxlength="255" autocomplete="off">
          @error('username')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Kata sandi email</span>
          <input type="password" name="password" value="" {{ $setting ? '' : 'required' }} minlength="8" maxlength="512" autocomplete="new-password">
          <small>{{ $setting ? 'Kosongkan untuk mempertahankan kata sandi yang tersimpan.' : 'Wajib diisi saat konfigurasi pertama.' }}</small>
          @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Alamat pengirim</span>
          <input type="email" name="from_address" value="{{ old('from_address', $setting?->from_address ?? config('mail.from.address')) }}" required maxlength="255">
          @error('from_address')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>

        <label>
          <span>Nama pengirim</span>
          <input name="from_name" value="{{ old('from_name', $setting?->from_name ?? config('mail.from.name', 'Mal Bali Galeria')) }}" required maxlength="120">
          @error('from_name')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="admin-mail-options">
        <label class="admin-check">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setting?->is_active ?? true))>
          <span>Gunakan konfigurasi database untuk pengiriman email</span>
        </label>
        <p>Jika dinonaktifkan, aplikasi kembali memakai konfigurasi email dari server (`.env`).</p>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan konfigurasi</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Uji pengiriman email</h2><p>Menggunakan konfigurasi yang sudah disimpan dan sedang aktif.</p></div>
      <span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>5 percobaan per menit</span>
    </div>
    <form action="{{ route('admin.settings.mail.test') }}" method="POST" class="admin-mail-test-form" data-mail-test-form>
      @csrf
      <label>
        <span>Email tujuan pengujian</span>
        <input type="email" name="recipient" value="{{ old('recipient', Auth::user()->email) }}" required maxlength="255" autocomplete="email" placeholder="nama@example.com">
        <small>Simpan perubahan konfigurasi sebelum menjalankan pengujian.</small>
        @error('recipient')<span class="admin-field-error">{{ $message }}</span>@enderror
      </label>
      <button type="submit" class="admin-secondary-button" data-mail-test-submit><svg><use href="#i-mail"/></svg><span>Kirim email uji</span></button>
    </form>
  </section>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-mail-test-form]')?.addEventListener('submit', event => {
  const button = event.currentTarget.querySelector('[data-mail-test-submit]');
  if (!button || button.disabled) return;
  button.disabled = true;
  button.querySelector('span').textContent = 'Mengirim...';
});
</script>
@endpush
