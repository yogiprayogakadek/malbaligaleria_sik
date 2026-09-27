@extends('layouts.auth')

@section('title', 'Mal Bali Galeria : Registrasi Akun Tenant')

@section('auth-card')
<div class="auth-tab-panel active" id="panel-register" role="tabpanel">
  <div class="auth-heading">
    <h1>Daftar Akun Tenant</h1>
    <p>Registrasi akun khusus mitra tenant Mal Bali Galeria untuk pengajuan surat izin gedung.</p>
  </div>

  @if ($errors->any())
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form id="registerForm" action="{{ route('register.post') }}" method="POST" novalidate>
    @csrf

    {{-- 1. Nama Tenant --}}
    <div class="auth-field" id="field-tenant_name">
      <label for="regTenant">Nama Tenant / Toko <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-building"/></svg>
        <input id="regTenant" name="tenant_name" type="text"
               value="{{ old('tenant_name') }}"
               autocomplete="organization"
               placeholder="Contoh: Starbucks Coffee (Unit GF-12)"
               aria-required="true"
               aria-describedby="err-tenant"
               data-validate="tenant_name"
               required>
      </div>
      <span class="auth-field-error" id="err-tenant" role="alert" aria-live="polite"></span>
    </div>

    {{-- 2. Nomor HP --}}
    <div class="auth-field" id="field-phone">
      <label for="regPhone">Nomor HP / WhatsApp <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-user"/></svg>
        <input id="regPhone" name="phone" type="tel" inputmode="tel"
               value="{{ old('phone') }}"
               autocomplete="tel"
               placeholder="Contoh: 081234567890"
               aria-required="true"
               aria-describedby="err-phone"
               data-validate="phone"
               minlength="9" required>
      </div>
      <span class="auth-field-error" id="err-phone" role="alert" aria-live="polite"></span>
    </div>

    {{-- 3. Email --}}
    <div class="auth-field" id="field-email">
      <label for="regEmail">Alamat Email <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-mail"/></svg>
        <input id="regEmail" name="email" type="email" inputmode="email"
               value="{{ old('email') }}"
               autocomplete="email"
               placeholder="nama@tenant.com"
               aria-required="true"
               aria-describedby="err-email"
               data-validate="email"
               required>
      </div>
      <span class="auth-field-error" id="err-email" role="alert" aria-live="polite"></span>
    </div>

    {{-- 4. Kata Sandi --}}
    <div class="auth-field" id="field-password">
      <label for="regPassword">Kata Sandi <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="regPassword" name="password" type="password"
               autocomplete="new-password"
               placeholder="Min. 8 karakter, huruf dan angka"
               aria-required="true"
               aria-describedby="err-password pw-strength"
               data-validate="password-reg"
               required minlength="8">
        <button type="button" class="auth-eye-btn"
                data-toggle-pw="regPassword" aria-label="Tampilkan kata sandi">
          <svg aria-hidden="true"><use href="#i-eye"/></svg>
        </button>
      </div>
      {{-- Strength meter --}}
      <div class="pw-strength-bar" id="pw-strength-bar" aria-hidden="true">
        <span class="pw-strength-fill"></span>
      </div>
      <span class="pw-strength-label" id="pw-strength" aria-live="polite"></span>
      <span class="auth-field-error" id="err-password" role="alert" aria-live="polite"></span>
    </div>

    {{-- 5. Konfirmasi Kata Sandi --}}
    <div class="auth-field" id="field-password_confirmation">
      <label for="regPasswordConfirm">Konfirmasi Kata Sandi <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="regPasswordConfirm" name="password_confirmation" type="password"
               autocomplete="new-password"
               placeholder="Ulangi kata sandi"
               aria-required="true"
               aria-describedby="err-confirm"
               data-validate="password_confirmation"
               required minlength="8">
        <button type="button" class="auth-eye-btn"
                data-toggle-pw="regPasswordConfirm" aria-label="Tampilkan konfirmasi kata sandi">
          <svg aria-hidden="true"><use href="#i-eye"/></svg>
        </button>
      </div>
      <span class="auth-field-error" id="err-confirm" role="alert" aria-live="polite"></span>
    </div>

    {{-- Terms --}}
    <div class="auth-field auth-field--checkbox" id="field-terms">
      <label class="auth-remember auth-remember--terms">
        <input type="checkbox" name="terms" id="agreeTerms" value="1"
               {{ old('terms') ? 'checked' : '' }}
               aria-describedby="err-terms"
               data-validate="terms">
        <span>Saya menyatakan data tenant benar dan menyetujui ketentuan perizinan gedung Mal Bali Galeria</span>
      </label>
      <span class="auth-field-error" id="err-terms" role="alert" aria-live="polite"></span>
    </div>

    <button type="submit" class="btn-auth" id="registerSubmit" disabled>
      <span class="btn-auth-label">Daftar Akun Tenant</span>
      <span class="btn-auth-spinner" aria-hidden="true"></span>
    </button>
  </form>

  <p class="auth-switch">
    Sudah punya akun?
    <a href="{{ route('login') }}" class="auth-switch-link">Masuk di sini</a>
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

  // ── Password strength scorer ───────────────────────────────────────────────
  function scorePassword(val) {
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
    if (/\d/.test(val))   score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    return score; // 0-5
  }

  function updateStrengthMeter(val) {
    const bar   = document.querySelector('#pw-strength-bar .pw-strength-fill');
    const label = document.getElementById('pw-strength');
    if (!bar || !label) return;

    if (!val) {
      bar.style.width = '0%';
      bar.className = 'pw-strength-fill';
      label.textContent = '';
      return;
    }

    const score = scorePassword(val);
    const levels = [
      { pct: '20%', cls: 'pw-strength-fill--weak',   text: 'Sangat lemah' },
      { pct: '40%', cls: 'pw-strength-fill--weak',   text: 'Lemah' },
      { pct: '60%', cls: 'pw-strength-fill--fair',   text: 'Cukup' },
      { pct: '80%', cls: 'pw-strength-fill--good',   text: 'Kuat' },
      { pct: '100%', cls: 'pw-strength-fill--strong', text: 'Sangat kuat' },
    ];
    const lvl = levels[Math.min(score - 1, 4)] ?? levels[0];
    bar.style.width = lvl.pct;
    bar.className = 'pw-strength-fill ' + lvl.cls;
    label.textContent = lvl.text;
  }

  // ── Validators ─────────────────────────────────────────────────────────────
  const RULES = {
    'tenant_name': (val) => {
      if (!val) return 'Nama tenant wajib diisi.';
      if (val.length < 3)   return 'Nama tenant minimal 3 karakter.';
      if (val.length > 120) return 'Nama tenant maksimal 120 karakter.';
      return null;
    },
    'phone': (val) => {
      if (!val) return 'Nomor HP wajib diisi.';
      const cleaned = val.replace(/[\s\-\(\)]/g, '');
      if (!/^[\d\+]+$/.test(cleaned)) return 'Nomor HP hanya boleh berisi angka.';
      if (cleaned.replace(/^\+/, '').length < 9)  return 'Nomor HP minimal 9 digit.';
      if (cleaned.replace(/^\+/, '').length > 20) return 'Nomor HP maksimal 20 digit.';
      return null;
    },
    'email': (val) => {
      if (!val) return 'Alamat email wajib diisi.';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val)) return 'Format alamat email tidak valid.';
      if (val.length > 120) return 'Alamat email terlalu panjang.';
      return null;
    },
    'password-reg': (val) => {
      if (!val) return 'Kata sandi wajib diisi.';
      if (val.length < 8) return 'Kata sandi minimal 8 karakter.';
      if (!/[a-zA-Z]/.test(val)) return 'Kata sandi harus mengandung minimal satu huruf.';
      if (!/\d/.test(val))        return 'Kata sandi harus mengandung minimal satu angka.';
      return null;
    },
    'password_confirmation': (val) => {
      if (!val) return 'Konfirmasi kata sandi wajib diisi.';
      const pw = document.getElementById('regPassword')?.value ?? '';
      if (val !== pw) return 'Konfirmasi kata sandi tidak cocok.';
      return null;
    },
    'terms': (_, el) => {
      if (!el.checked) return 'Anda harus menyetujui ketentuan penggunaan portal.';
      return null;
    },
  };

  // ── State & UI ─────────────────────────────────────────────────────────────
  const fieldStates = {};

  function setFieldState(input, errEl, rule, state, message) {
    fieldStates[rule] = state;
    const wrap = input.closest('.auth-field');
    wrap.classList.remove('is-valid', 'is-invalid', 'is-empty');
    if (state === 'valid')   wrap.classList.add('is-valid');
    if (state === 'invalid') wrap.classList.add('is-invalid');
    if (state === 'empty')   wrap.classList.add('is-empty');

    if (errEl) errEl.textContent = message ?? '';
    if (input.type !== 'checkbox') {
      input.setAttribute('aria-invalid', state === 'invalid' ? 'true' : 'false');
    }
    updateSubmitButton();
  }

  function validateField(input) {
    const rule = input.dataset.validate;
    if (!rule || !RULES[rule]) return;

    const val   = input.type === 'checkbox' ? input.value : input.value.trim();
    const errId = input.getAttribute('aria-describedby')?.split(' ')[0];
    const errEl = errId ? document.getElementById(errId) : null;

    // For password field, also re-validate confirmation
    if (rule === 'password-reg') {
      updateStrengthMeter(val);
      const confirmInput = document.querySelector('[data-validate="password_confirmation"]');
      if (confirmInput?.value) validateField(confirmInput);
    }

    if (!val && input.type !== 'checkbox') {
      setFieldState(input, errEl, rule, 'empty', null);
      return;
    }

    const error = RULES[rule](val, input);
    if (error) {
      setFieldState(input, errEl, rule, 'invalid', error);
    } else {
      setFieldState(input, errEl, rule, 'valid', null);
    }
  }

  function updateSubmitButton() {
    const btn = document.getElementById('registerSubmit');
    if (!btn) return;
    const allValid = Object.values(fieldStates).every(s => s === 'valid');
    btn.disabled = !allValid;
  }

  // ── Wire up all inputs ──────────────────────────────────────────────────────
  document.querySelectorAll('[data-validate]').forEach(input => {
    const event = input.type === 'checkbox' ? 'change' : 'input';
    input.addEventListener(event, () => validateField(input));
    input.addEventListener('blur',   () => validateField(input));
    if (input.value.trim() || input.checked) validateField(input);
  });

  // ── Form submit guard ───────────────────────────────────────────────────────
  const form = document.getElementById('registerForm');
  const btn  = document.getElementById('registerSubmit');

  form?.addEventListener('submit', (e) => {
    let hasError = false;
    document.querySelectorAll('[data-validate]').forEach(input => {
      validateField(input);
      if (fieldStates[input.dataset.validate] !== 'valid') hasError = true;
    });

    if (hasError) { e.preventDefault(); return; }

    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.btn-auth-label').textContent = 'Mendaftarkan...';
  });

})();
</script>
@endpush
