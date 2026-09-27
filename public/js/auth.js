(() => {
  'use strict';

  const $ = id => document.getElementById(id);
  const $$ = sel => [...document.querySelectorAll(sel)];

  let toastTimer;

  /* ── Toast Feedback ──────────────────────────────────────── */
  function notify(msg) {
    const toast = $('toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
  }

  /* ── Tab Switcher ────────────────────────────────────────── */
  const indicator = $('authTabIndicator');

  function switchTab(tab) {
    $$('.auth-tab').forEach(btn => {
      const active = btn.dataset.authTab === tab;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', String(active));
    });

    if (indicator) {
      indicator.classList.toggle('at-register', tab === 'register');
    }

    const loginPanel    = $('panel-login');
    const registerPanel = $('panel-register');

    if (tab === 'login') {
      if (loginPanel) {
        loginPanel.classList.add('active');
        loginPanel.hidden = false;
      }
      if (registerPanel) {
        registerPanel.classList.remove('active');
        registerPanel.hidden = true;
      }
    } else {
      if (registerPanel) {
        registerPanel.classList.add('active');
        registerPanel.hidden = false;
      }
      if (loginPanel) {
        loginPanel.classList.remove('active');
        loginPanel.hidden = true;
      }
    }
  }

  $$('[data-auth-tab]').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.authTab));
  });

  /* ── Password Visibility Toggles ─────────────────────────── */
  $$('[data-toggle-pw]').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.togglePw;
      const input = $(targetId);
      if (!input) return;

      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      btn.setAttribute('aria-label', isText ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');

      const iconUse = btn.querySelector('use');
      if (iconUse) {
        iconUse.setAttribute('href', isText ? '#i-eye' : '#i-eye-off');
      }
    });
  });

  /* ── Validation Helpers ──────────────────────────────────── */
  function markInvalid(input) {
    input.classList.add('is-invalid');
    input.focus();
    return false;
  }

  function checkField(input) {
    const ok = input.checkValidity();
    input.classList.toggle('is-invalid', !ok);
    return ok;
  }

  document.querySelectorAll('input').forEach(input => {
    input.addEventListener('input', () => input.classList.remove('is-invalid'));
  });

  /* ── Forgot Password Button ──────────────────────────────── */
  const forgotPwBtn = $('forgotPwBtn');
  if (forgotPwBtn) {
    forgotPwBtn.addEventListener('click', () => {
      notify('Untuk pemulihan akun, hubungi tim CS Mal Bali Galeria atau masuk tanpa akun.');
    });
  }

  /* ── Login Form (Design Prototype: no DB / no localStorage) ── */
  const loginForm = $('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', e => {
      e.preventDefault();
      const email    = $('loginEmail');
      const password = $('loginPassword');

      if (!email || !password) return;

      const ok = checkField(email) && checkField(password);
      if (!ok) {
        notify('Lengkapi alamat email dan kata sandi.');
        return;
      }

      notify('Berhasil masuk! Mengalihkan ke portal...');
      setTimeout(() => {
        window.location.href = loginForm.getAttribute('action') || '/';
      }, 500);
    });
  }

  /* ── Register Form (Design Prototype: no DB / no localStorage) ─ */
  const registerForm = $('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', e => {
      e.preventDefault();
      const name    = $('regName');
      const email   = $('regEmail');
      const pw      = $('regPassword');
      const pwc     = $('regPasswordConfirm');
      const agree   = $('agreeTerms');

      if (!name || !email || !pw || !pwc || !agree) return;

      let ok = checkField(name) && checkField(email) && checkField(pw) && checkField(pwc);

      if (!agree.checked) {
        notify('Setujui ketentuan penggunaan portal perizinan.');
        ok = false;
      }

      if (!ok) {
        notify('Lengkapi semua kolom formulir.');
        return;
      }

      if (pw.value.length < 6) {
        markInvalid(pw);
        notify('Kata sandi minimal 6 karakter.');
        return;
      }

      if (pw.value !== pwc.value) {
        markInvalid(pwc);
        notify('Konfirmasi kata sandi tidak cocok.');
        return;
      }

      notify('Pendaftaran berhasil! Mengalihkan ke portal...');
      setTimeout(() => {
        window.location.href = registerForm.getAttribute('action') || '/';
      }, 500);
    });
  }

})();
