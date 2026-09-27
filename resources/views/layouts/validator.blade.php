@extends('layouts.master')

@push('head')
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="MBG TR">
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('validator.css') }}">
@endpush

@section('body')
<div class="app-shell" id="appShell"
     data-validator-realtime="{{ Auth::id() }}"
     data-unread-count="{{ $unreadCount ?? 0 }}"
     data-latest-notification-id="{{ ($notifications ?? collect())->max('id') ?? 0 }}"
     data-notification-feed-url="{{ route('tr.notifications.feed') }}"
     data-validator-push
     data-push-public-key="{{ config('webpush.vapid.public_key') }}"
     data-push-store-url="{{ route('tr.push-subscriptions.store') }}"
     data-push-destroy-url="{{ route('tr.push-subscriptions.destroy') }}">
  <aside class="desktop-sidebar" aria-label="Navigasi Validator TR">
    <a href="{{ route('tr.index') }}" class="sidebar-logo" aria-label="Dashboard Validator TR">
      <img src="{{ asset('logo.png') }}" alt="MBG" class="sidebar-logo-img">
    </a>

    <nav class="sidebar-nav" aria-label="Menu validator">
      <a href="{{ route('tr.index', ['status' => 'pending']) }}"
         class="nav-item {{ (request()->routeIs('tr.index') && request('status', 'pending') === 'pending') || request()->routeIs('tr.show') ? 'active' : '' }}"
         aria-label="Antrean pemeriksaan">
        <svg><use href="#i-clock"/></svg>
        <span class="nav-badge" data-pending-count @if(($pendingTRCount ?? 0) === 0) hidden @endif>{{ $pendingTRCount }}</span>
        <span class="nav-tooltip" data-pending-tooltip>Antrean ({{ $pendingTRCount ?? 0 }})</span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'all']) }}"
         class="nav-item {{ request()->routeIs('tr.index') && request('status') === 'all' ? 'active' : '' }}"
         aria-label="Semua permohonan">
        <svg><use href="#i-file"/></svg>
        <span class="nav-tooltip">Semua permohonan</span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'approved']) }}"
         class="nav-item {{ request()->routeIs('tr.index') && request('status') === 'approved' ? 'active' : '' }}"
         aria-label="Permohonan disetujui">
        <svg><use href="#i-check"/></svg>
        <span class="nav-tooltip">Disetujui</span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'rejected']) }}"
         class="nav-item {{ request()->routeIs('tr.index') && request('status') === 'rejected' ? 'active' : '' }}"
         aria-label="Permohonan ditolak">
        <svg><use href="#i-info"/></svg>
        <span class="nav-tooltip">Ditolak</span>
      </a>
      <a href="{{ route('scanner.index') }}" class="nav-item" aria-label="Verifikasi surat">
        <svg><use href="#i-search"/></svg>
        <span class="nav-tooltip">Verifikasi surat</span>
      </a>
      <a href="{{ route('portal.help') }}" class="nav-item" aria-label="Panduan">
        <svg><use href="#i-help"/></svg>
        <span class="nav-tooltip">Panduan</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-user" title="{{ Auth::user()->name }} : Validator {{ Auth::user()->division }}">
        <span class="sidebar-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
      </div>
      <form action="{{ route('logout') }}" method="POST" class="logout-form">
        @csrf
        <button type="submit" class="nav-item nav-item-logout" aria-label="Keluar" title="Keluar">
          <svg><use href="#i-logout"/></svg><span class="nav-tooltip">Keluar</span>
        </button>
      </form>
    </div>
  </aside>

  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <a href="{{ route('tr.index') }}" class="brand-mobile" aria-label="Dashboard Validator TR">
          <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="topbar-logo-mobile">
        </a>
      </div>
      <div class="topbar-center">
        <span class="topbar-title">@yield('page-title', 'Dashboard Validator TR')</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-date" id="todayLabel"></span>
        <a href="{{ route('scanner.index') }}" class="topbar-btn" aria-label="Verifikasi surat" title="Verifikasi surat">
          <svg><use href="#i-search"/></svg>
        </a>
        <div class="notif-wrapper">
          <button type="button" class="topbar-btn notif-btn" id="validatorNotifBtn"
                  aria-label="Notifikasi" aria-expanded="false" title="Notifikasi">
            <svg><use href="#i-bell"/></svg>
            <span class="notif-badge" data-notification-badge @if(($unreadCount ?? 0) === 0) hidden @endif></span>
          </button>
          <div class="notif-dropdown" id="validatorNotifDropdown" hidden>
            <div class="notif-dropdown-header">
              <h3>Pemberitahuan TR</h3>
              <span class="notif-count" data-notification-count @if(($unreadCount ?? 0) === 0) hidden @endif>{{ $unreadCount ?? 0 }} baru</span>
            </div>
            <div class="notif-list" id="validatorNotifList">
              @forelse(($notifications ?? collect()) as $item)
                <form action="{{ route('tr.notifications.read', $item) }}" method="POST" class="validator-notif-form">
                  @csrf
                  <button type="submit" class="notif-item {{ $item->isUnread() ? 'unread' : '' }}">
                    <span class="notif-icon notif-icon--blue"><svg><use href="#i-clock"/></svg></span>
                    <span class="notif-body">
                      <span class="notif-title">{{ $item->title }}</span>
                      <span class="notif-desc">{{ $item->body }}</span>
                      <span class="notif-time">{{ $item->created_at->diffForHumans() }}</span>
                    </span>
                  </button>
                </form>
              @empty
                <div class="notif-empty" id="validatorNotifEmpty">
                  <svg width="24" height="24"><use href="#i-bell"/></svg>
                  <p>Belum ada pemberitahuan baru.</p>
                </div>
              @endforelse
            </div>
            <div class="push-settings" data-push-settings>
              <div class="push-settings-copy">
                <strong>Notifikasi perangkat</strong>
                <span data-push-status>Memeriksa dukungan perangkat...</span>
              </div>
              <button type="button" class="push-settings-button" data-push-toggle>
                <svg aria-hidden="true"><use href="#i-bell"/></svg>
                <span>Aktifkan</span>
              </button>
              <button type="button" class="push-install-button" data-pwa-install hidden>
                Pasang aplikasi
              </button>
            </div>
            <div class="notif-dropdown-footer">
              <a href="{{ route('tr.index', ['status' => 'pending']) }}">Lihat antrean pemeriksaan</a>
            </div>
          </div>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="logout-form">
          @csrf
          <button type="submit" class="topbar-btn" aria-label="Keluar" title="Keluar"><svg><use href="#i-logout"/></svg></button>
        </form>
      </div>
    </header>

    <main id="mainContent" tabindex="-1">
      @yield('content')
    </main>
  </div>
</div>

<nav class="mobile-nav" id="mobileNav" aria-label="Navigasi Validator TR">
  <a href="{{ route('tr.index', ['status' => 'pending']) }}"
     class="mobile-nav-item {{ (request()->routeIs('tr.index') && request('status', 'pending') === 'pending') || request()->routeIs('tr.show') ? 'active' : '' }}">
    <svg><use href="#i-clock"/></svg>
    <span>Antrean</span>
    <span class="validator-mobile-count" data-pending-count
          aria-label="{{ $pendingTRCount }} permohonan menunggu pemeriksaan"
          @if(($pendingTRCount ?? 0) === 0) hidden @endif>
      {{ $pendingTRCount > 99 ? '99+' : $pendingTRCount }}
    </span>
  </a>
  <a href="{{ route('tr.index', ['status' => 'all']) }}"
     class="mobile-nav-item {{ request()->routeIs('tr.index') && request('status') === 'all' ? 'active' : '' }}">
    <svg><use href="#i-file"/></svg>
    <span>Semua</span>
  </a>
  <a href="{{ route('scanner.index') }}" class="mobile-nav-fab" aria-label="Verifikasi surat">
    <svg><use href="#i-search"/></svg>
  </a>
  <a href="{{ route('tr.index', ['status' => 'approved']) }}"
     class="mobile-nav-item {{ request()->routeIs('tr.index') && request('status') === 'approved' ? 'active' : '' }}">
    <svg><use href="#i-check"/></svg>
    <span>Disetujui</span>
  </a>
  <a href="{{ route('tr.index', ['status' => 'rejected']) }}"
     class="mobile-nav-item {{ request()->routeIs('tr.index') && request('status') === 'rejected' ? 'active' : '' }}">
    <svg><use href="#i-info"/></svg>
    <span>Ditolak</span>
  </a>
</nav>
@endsection

@push('scripts')
<script>
  const todayLabel = document.getElementById('todayLabel');
  if (todayLabel) {
    todayLabel.textContent = new Intl.DateTimeFormat('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    }).format(new Date());
  }

  const validatorNotifBtn = document.getElementById('validatorNotifBtn');
  const validatorNotifDropdown = document.getElementById('validatorNotifDropdown');

  validatorNotifBtn?.addEventListener('click', event => {
    event.stopPropagation();
    const willOpen = validatorNotifDropdown.hidden;
    validatorNotifDropdown.hidden = !willOpen;
    validatorNotifBtn.setAttribute('aria-expanded', String(willOpen));
  });

  document.addEventListener('click', event => {
    if (validatorNotifDropdown && !validatorNotifDropdown.contains(event.target)) {
      validatorNotifDropdown.hidden = true;
      validatorNotifBtn?.setAttribute('aria-expanded', 'false');
    }
  });
</script>
@endpush
