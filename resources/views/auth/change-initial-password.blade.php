@extends('layouts.auth')

@section('title', 'Mal Bali Galeria : Amankan Akun')
@section('auth-tagline', 'Pengamanan Akun Internal')

@section('auth-card')
<div class="auth-tab-panel active" role="main">
  <div class="auth-security-mark" aria-hidden="true"><svg><use href="#i-lock"/></svg></div>

  <div class="auth-heading auth-heading--password">
    <h1>Ganti kata sandi awal</h1>
    <p>Akun {{ Auth::user()->email }} masih menggunakan kata sandi sementara. Buat kata sandi pribadi sebelum melanjutkan.</p>
  </div>

  @if ($errors->any())
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <div class="auth-alert auth-alert--info" role="note">
    <svg aria-hidden="true"><use href="#i-shield-check"/></svg>
    <span>Setelah disimpan, sesi lain yang memakai akun ini akan dihentikan.</span>
  </div>

  <form id="initialPasswordForm" action="{{ route('password.required.update') }}" method="POST" novalidate>
    @csrf
    @method('PUT')

    <div class="auth-field" id="field-current-password">
      <label for="currentPassword">Kata sandi awal <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required minlength="6" maxlength="255" aria-describedby="err-current-password">
        <button type="button" class="auth-eye-btn" data-toggle-password="currentPassword" aria-label="Tampilkan kata sandi awal"><svg aria-hidden="true"><use href="#i-eye"/></svg></button>
      </div>
      <span class="auth-field-error" id="err-current-password" role="alert" aria-live="polite"></span>
    </div>

    <div class="auth-field" id="field-new-password">
      <label for="newPassword">Kata sandi baru <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="newPassword" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="255" aria-describedby="err-new-password passwordRequirements">
        <button type="button" class="auth-eye-btn" data-toggle-password="newPassword" aria-label="Tampilkan kata sandi baru"><svg aria-hidden="true"><use href="#i-eye"/></svg></button>
      </div>
      <span class="auth-field-error" id="err-new-password" role="alert" aria-live="polite"></span>
    </div>

    <ul class="password-requirements" id="passwordRequirements" aria-label="Syarat kata sandi">
      <li data-requirement="length">Minimal 12 karakter</li>
      <li data-requirement="case">Huruf besar dan kecil</li>
      <li data-requirement="number">Minimal satu angka</li>
      <li data-requirement="symbol">Minimal satu simbol</li>
    </ul>

    <div class="auth-field" id="field-confirm-password">
      <label for="passwordConfirmation">Konfirmasi kata sandi baru <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="passwordConfirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="255" aria-describedby="err-confirm-password">
        <button type="button" class="auth-eye-btn" data-toggle-password="passwordConfirmation" aria-label="Tampilkan konfirmasi kata sandi"><svg aria-hidden="true"><use href="#i-eye"/></svg></button>
      </div>
      <span class="auth-field-error" id="err-confirm-password" role="alert" aria-live="polite"></span>
    </div>

    <button type="submit" class="btn-auth" id="initialPasswordSubmit" disabled>
      <span class="btn-auth-label">Simpan kata sandi baru</span>
      <span class="btn-auth-spinner" aria-hidden="true"></span>
    </button>
  </form>

  <form action="{{ route('logout') }}" method="POST" class="forced-password-logout">
    @csrf
    <button type="submit">Keluar dari akun</button>
  </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('initialPasswordForm');
  if (!form) return;

  const current = document.getElementById('currentPassword');
  const password = document.getElementById('newPassword');
  const confirmation = document.getElementById('passwordConfirmation');
  const submit = document.getElementById('initialPasswordSubmit');
  const requirements = {
    length: value => value.length >= 12,
    case: value => /[a-z]/.test(value) && /[A-Z]/.test(value),
    number: value => /\d/.test(value),
    symbol: value => /[^A-Za-z0-9]/.test(value),
  };

  const setError = (input, message) => {
    const field = input.closest('.auth-field');
    const error = document.getElementById(input.getAttribute('aria-describedby').split(' ')[0]);
    field.classList.toggle('is-invalid', Boolean(message));
    field.classList.toggle('is-valid', !message && Boolean(input.value));
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    error.textContent = message;
  };

  const validate = () => {
    const value = password.value;
    const strong = Object.values(requirements).every(rule => rule(value));

    Object.entries(requirements).forEach(([name, rule]) => {
      document.querySelector(`[data-requirement="${name}"]`)?.classList.toggle('is-met', rule(value));
    });

    setError(current, current.value.length >= 6 ? '' : 'Kata sandi awal wajib diisi.');
    setError(password, !value ? 'Kata sandi baru wajib diisi.' : (!strong ? 'Kata sandi belum memenuhi seluruh persyaratan.' : (value === current.value ? 'Kata sandi baru harus berbeda.' : '')));
    setError(confirmation, !confirmation.value ? 'Konfirmasi wajib diisi.' : (confirmation.value !== value ? 'Konfirmasi kata sandi tidak cocok.' : ''));

    submit.disabled = !(current.value.length >= 6 && strong && value !== current.value && confirmation.value === value);
  };

  [current, password, confirmation].forEach(input => input.addEventListener('input', validate));

  document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.querySelector('use')?.setAttribute('href', visible ? '#i-eye' : '#i-eye-off');
      button.setAttribute('aria-label', visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
    });
  });

  form.addEventListener('submit', event => {
    validate();
    if (submit.disabled) {
      event.preventDefault();
      return;
    }
    submit.classList.add('loading');
    submit.disabled = true;
    submit.querySelector('.btn-auth-label').textContent = 'Menyimpan...';
  });
})();
</script>
@endpush
