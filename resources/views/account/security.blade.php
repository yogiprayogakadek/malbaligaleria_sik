@php
  $accountLayout = auth()->user()->isAdmin()
    ? 'layouts.admin'
    : ((auth()->user()->isValidator() || auth()->user()->isSecretary()) ? 'layouts.validator' : 'layouts.portal');
@endphp
@extends($accountLayout)

@section('title', 'Keamanan Akun - Mal Bali Galeria')
@section('page-title', 'Keamanan Akun')

@section('content')
<section class="account-security-page">
  <header class="account-security-header">
    <div>
      <p class="account-security-eyebrow">AKUN DAN PERANGKAT</p>
      <h1>Sesi aktif</h1>
      <p>Periksa perangkat yang masih memiliki akses ke akun Anda. Cabut sesi yang tidak dikenali.</p>
    </div>
    @if($sessionManagementAvailable && $sessions->where('is_current', false)->isNotEmpty())
      <button type="button" class="account-security-danger" data-open-revoke-all>
        <svg><use href="#i-lock"/></svg><span>Cabut sesi lain</span>
      </button>
    @endif
  </header>

  @if(session('success'))
    <div class="account-security-alert account-security-alert--success" role="status">{{ session('success') }}</div>
  @endif
  @error('session')<div class="account-security-alert account-security-alert--error" role="alert">{{ $message }}</div>@enderror
  @error('current_password')<div class="account-security-alert account-security-alert--error" role="alert">{{ $message }}</div>@enderror

  @unless($sessionManagementAvailable)
    <div class="account-security-alert" role="status">Pengelolaan perangkat tersedia saat server menggunakan <strong>SESSION_DRIVER=database</strong>.</div>
  @endunless

  <div class="account-session-list">
    @forelse($sessions as $session)
      <article class="account-session-item">
        <span class="account-session-icon" aria-hidden="true"><svg><use href="#i-{{ str_contains($session['device'], 'iOS') || $session['device'] === 'Android' ? 'phone' : 'grid' }}"/></svg></span>
        <div class="account-session-copy">
          <div class="account-session-title">
            <strong>{{ $session['device'] }} · {{ $session['browser'] }}</strong>
            @if($session['is_current'])<span>Sesi ini</span>@endif
          </div>
          <p>IP {{ $session['ip_address'] }} · Aktif {{ $session['last_active_at']->diffForHumans() }}</p>
        </div>
        @unless($session['is_current'])
          <button type="button" class="account-session-revoke" data-open-session="{{ $session['id'] }}" aria-label="Cabut sesi {{ $session['device'] }}">Cabut</button>
        @endunless
      </article>
    @empty
      <div class="account-session-empty">
        <svg><use href="#i-lock"/></svg>
        <strong>Data sesi belum tersedia</strong>
        <p>Sesi aktif akan muncul setelah penyimpanan session database digunakan.</p>
      </div>
    @endforelse
  </div>

  @if(auth()->user()->isStaff())
    <section class="account-two-factor-summary">
      <div>
        <span class="account-session-icon"><svg><use href="#i-shield-check"/></svg></span>
        <div><strong>Autentikasi dua langkah</strong><p>{{ auth()->user()->hasConfirmedTwoFactor() ? 'Aktif untuk akun ini.' : 'Belum diaktifkan.' }}</p></div>
      </div>
      <a href="{{ route('two-factor.setup.show') }}">{{ auth()->user()->hasConfirmedTwoFactor() ? 'Kelola recovery code' : 'Aktifkan sekarang' }}</a>
    </section>
  @endif
</section>

<dialog class="account-security-dialog" id="revokeSessionDialog" aria-labelledby="revokeSessionTitle">
  <form method="POST" id="revokeSessionForm">
    @csrf
    @method('DELETE')
    <h2 id="revokeSessionTitle">Konfirmasi kata sandi</h2>
    <p id="revokeSessionDescription">Masukkan kata sandi saat ini untuk mencabut akses perangkat.</p>
    <label for="securityCurrentPassword">Kata sandi saat ini</label>
    <input id="securityCurrentPassword" type="password" name="current_password" required maxlength="255" autocomplete="current-password">
    <div class="account-security-dialog-actions">
      <button type="button" data-close-dialog>Batal</button>
      <button type="submit">Cabut sesi</button>
    </div>
  </form>
</dialog>
@endsection

@push('scripts')
<script>
  const securityDialog = document.getElementById('revokeSessionDialog');
  const securityForm = document.getElementById('revokeSessionForm');
  const sessionRouteTemplate = @json(route('account.security.sessions.destroy', ['sessionId' => '__SESSION__']));
  const otherSessionsRoute = @json(route('account.security.sessions.others.destroy'));

  document.querySelectorAll('[data-open-session]').forEach(button => {
    button.addEventListener('click', () => {
      securityForm.action = sessionRouteTemplate.replace('__SESSION__', encodeURIComponent(button.dataset.openSession));
      securityDialog.showModal();
    });
  });

  document.querySelector('[data-open-revoke-all]')?.addEventListener('click', () => {
    securityForm.action = otherSessionsRoute;
    securityDialog.showModal();
  });

  document.querySelector('[data-close-dialog]')?.addEventListener('click', () => securityDialog.close());
  securityDialog?.addEventListener('click', event => {
    if (event.target === securityDialog) securityDialog.close();
  });
</script>
@endpush
