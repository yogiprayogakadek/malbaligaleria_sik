@extends('layouts.auth')

@section('title', 'Mal Bali Galeria : Masuk ke Portal')

@section('auth-card')
<div class="auth-tab-panel active" id="panel-login" role="tabpanel">
  <div class="auth-heading">
    <h1>Selamat Datang!</h1>
    <p>Masuk dengan Email atau Nomor HP untuk mengelola permohonan surat izin gedung.</p>
  </div>

  {{-- Flash error --}}
  @if (session('error'))
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  {{-- Validation errors dari backend --}}
  @if ($errors->any())
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  {{-- Flash success (misal: baru logout) --}}
  @if (session('success'))
    <div class="auth-alert auth-alert--success" role="alert">
      <svg aria-hidden="true"><use href="#i-check"/></svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if (session('info'))
    <div class="auth-alert auth-alert--info" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ session('info') }}</span>
    </div>
  @endif

  <form id="loginForm" action="{{ route('login.post') }}" method="POST" novalidate>
    @csrf

    {{-- Login (Email / Nomor HP) --}}
    <div class="auth-field" id="field-login">
      <label for="loginInput">Email atau Nomor HP <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-user"/></svg>
        <input id="loginInput" name="login" type="text"
               value="{{ old('login') }}"
               autocomplete="username"
               placeholder="nama@tenant.com atau 08123456789"
               aria-required="true"
               aria-describedby="err-login"
               data-validate="login"
               required>
      </div>
      <span class="auth-field-error" id="err-login" role="alert" aria-live="polite"></span>
    </div>

    {{-- Kata Sandi --}}
    <div class="auth-field" id="field-password">
      <label for="loginPassword">Kata Sandi <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="loginPassword" name="password" type="password"
               autocomplete="current-password"
               placeholder="Masukkan kata sandi Anda"
               aria-required="true"
               aria-describedby="err-password"
               data-validate="password-login"
               required minlength="6">
        <button type="button" class="auth-eye-btn"
                data-toggle-pw="loginPassword" aria-label="Tampilkan kata sandi">
          <svg aria-hidden="true"><use href="#i-eye"/></svg>
        </button>
      </div>
      <span class="auth-field-error" id="err-password" role="alert" aria-live="polite"></span>
    </div>

    <div class="auth-options">
      <label class="auth-remember">
        <input type="checkbox" name="remember" id="rememberMe" {{ old('remember') ? 'checked' : '' }}>
        <span>Ingat saya</span>
      </label>
      <button type="button" class="auth-link-btn" id="forgotPwBtn">Lupa kata sandi?</button>
    </div>

    <button type="submit" class="btn-auth" id="loginSubmit" disabled>
      <span class="btn-auth-label">Masuk ke Portal</span>
      <span class="btn-auth-spinner" aria-hidden="true"></span>
    </button>
  </form>

  <p class="auth-switch">
    Belum punya akun?
    <a href="{{ route('register') }}" class="auth-switch-link">Daftar sekarang</a>
  </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  // ── Eye toggle ─────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePw);
      if (!input) return;
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      const use = btn.querySelector('use');
      if (use) use.setAttribute('href', isText ? '#i-eye' : '#i-eye-off');
      btn.setAttribute('aria-label', isText ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
    });
  });

  // ── Validators ─────────────────────────────────────────────────────────────
  const RULES = {
    'login': (val) => {
      if (!val) return 'Alamat email atau nomor HP wajib diisi.';
      if (val.length < 5) return 'Input terlalu pendek.';
      // Accept email format or phone (digits + allowed chars)
      const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
      const isPhone = /^[\d\+\-\s\(\)]{9,20}$/.test(val);
      if (!isEmail && !isPhone) return 'Masukkan alamat email yang valid atau nomor HP (min. 9 digit).';
      return null;
    },
    'password-login': (val) => {
      if (!val) return 'Kata sandi wajib diisi.';
      if (val.length < 6) return 'Kata sandi minimal 6 karakter.';
      return null;
    },
  };

  // ── State & UI ─────────────────────────────────────────────────────────────
  const fieldStates = {};

  function setFieldState(input, errEl, fieldId, state, message) {
    fieldStates[fieldId] = state;
    const wrap = input.closest('.auth-field');

    wrap.classList.remove('is-valid', 'is-invalid', 'is-empty');
    if (state === 'valid')   wrap.classList.add('is-valid');
    if (state === 'invalid') wrap.classList.add('is-invalid');
    if (state === 'empty')   wrap.classList.add('is-empty');

    errEl.textContent = message ?? '';
    input.setAttribute('aria-invalid', state === 'invalid' ? 'true' : 'false');

    updateSubmitButton();
  }

  function validateField(input) {
    const rule = input.dataset.validate;
    if (!rule || !RULES[rule]) return;

    const val   = input.value.trim();
    const errEl = document.getElementById(input.getAttribute('aria-describedby'));
    if (!errEl) return;

    if (!val) {
      setFieldState(input, errEl, rule, 'empty', null);
      return;
    }

    const error = RULES[rule](val);
    if (error) {
      setFieldState(input, errEl, rule, 'invalid', error);
    } else {
      setFieldState(input, errEl, rule, 'valid', null);
    }
  }

  function updateSubmitButton() {
    const btn = document.getElementById('loginSubmit');
    if (!btn) return;
    const allValid = Object.values(fieldStates).every(s => s === 'valid');
    btn.disabled = !allValid;
  }

  // ── Wire up inputs ──────────────────────────────────────────────────────────
  document.querySelectorAll('[data-validate]').forEach(input => {
    input.addEventListener('input',  () => validateField(input));
    input.addEventListener('blur',   () => validateField(input));
    // If old value restored by browser, validate immediately
    if (input.value.trim()) validateField(input);
  });

  // ── Form submit: show spinner, disable double-submit ───────────────────────
  const form = document.getElementById('loginForm');
  const btn  = document.getElementById('loginSubmit');

  form?.addEventListener('submit', (e) => {
    // Re-run all validations before submit
    let hasError = false;
    document.querySelectorAll('[data-validate]').forEach(input => {
      validateField(input);
      if (fieldStates[input.dataset.validate] !== 'valid') hasError = true;
    });

    if (hasError) { e.preventDefault(); return; }

    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.btn-auth-label').textContent = 'Memproses...';
  });

  // ── Forgot password notice ─────────────────────────────────────────────────
  document.getElementById('forgotPwBtn')?.addEventListener('click', () => {
    window.showToast?.('Untuk pemulihan akun, silakan hubungi tim Customer Service Mal Bali Galeria.');
  });

})();
</script>
@endpush
