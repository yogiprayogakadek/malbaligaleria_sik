@extends('layouts.master')

@push('head')
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="MBG Admin">
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('validator.css') }}?v={{ filemtime(public_path('validator.css')) }}">
  <link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
@endpush

@section('body')
<div class="app-shell admin-shell" id="appShell" data-desktop-sidebar
     data-validator-realtime="{{ Auth::id() }}"
     data-unread-count="{{ $unreadCount ?? 0 }}"
     data-pending-category="loading"
     data-latest-notification-id="{{ ($notifications ?? collect())->max('id') ?? 0 }}"
     data-notification-feed-url="{{ route('admin.notifications.feed') }}"
     data-validator-push
     data-push-public-key="{{ config('webpush.vapid.public_key') }}"
     data-push-store-url="{{ route('admin.push-subscriptions.store') }}"
     data-push-destroy-url="{{ route('admin.push-subscriptions.destroy') }}">
  <aside class="desktop-sidebar" id="desktopSidebar" aria-label="Navigasi Admin">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-logo" aria-label="Dashboard Admin">
      <img src="{{ asset('logo.png') }}" alt="MBG" class="sidebar-logo-img">
    </a>

    <nav class="sidebar-nav" aria-label="Menu admin">
      <x-admin-menu-groups variant="sidebar" :pending-count="$pendingTRCount ?? 0" />
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-user" title="{{ Auth::user()->name }} : Administrator">
        <span class="sidebar-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
        <span class="sidebar-user-copy"><strong>{{ Auth::user()->name }}</strong><small>Administrator</small></span>
      </div>
      <form action="{{ route('logout') }}" method="POST" class="staff-logout-form">
        @csrf
        <button type="submit" class="nav-item nav-item-logout" aria-label="Keluar" title="Keluar">
          <svg><use href="#i-logout"/></svg><span class="nav-label">Keluar</span>
        </button>
      </form>
    </div>
  </aside>

  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="topbar-btn topbar-sidebar-toggle" data-sidebar-toggle aria-controls="desktopSidebar" aria-expanded="false" aria-label="Buka menu samping" title="Buka menu">
          <svg><use href="#i-menu"/></svg>
        </button>
        <a href="{{ route('admin.dashboard') }}" class="brand-mobile" aria-label="Dashboard Admin">
          <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="topbar-logo-mobile">
        </a>
      </div>
      <div class="topbar-center"><span class="topbar-title">@yield('page-title', 'Administrasi Perizinan')</span></div>
      <div class="topbar-right">
        <span class="topbar-date" id="todayLabel"></span>
        <a href="{{ route('admin.search') }}" class="topbar-btn" aria-label="Cari data perizinan" title="Cari data perizinan"><svg><use href="#i-search"/></svg></a>
        <a href="{{ route('account.profile.edit') }}" class="topbar-btn" aria-label="Profil akun" title="Profil akun"><svg><use href="#i-user"/></svg></a>
        <a href="{{ route('account.security.index') }}" class="topbar-btn" aria-label="Keamanan akun" title="Keamanan akun"><svg><use href="#i-lock"/></svg></a>
        <div class="notif-wrapper">
          <button type="button" class="topbar-btn notif-btn" id="validatorNotifBtn" aria-label="Notifikasi" aria-expanded="false" title="Notifikasi">
            <svg><use href="#i-bell"/></svg>
            <span class="notif-badge" data-notification-badge @if(($unreadCount ?? 0) === 0) hidden @endif></span>
          </button>
          <div class="notif-dropdown" id="validatorNotifDropdown" hidden>
            <div class="notif-dropdown-header">
              <h3>Pemberitahuan Admin</h3>
              <div class="notif-header-actions">
                <span class="notif-count" data-notification-count @if(($unreadCount ?? 0) === 0) hidden @endif>{{ $unreadCount ?? 0 }} baru</span>
                <form action="{{ route('admin.notifications.clear') }}" method="POST" data-notification-clear data-confirm="Hapus semua notifikasi?" @if(($notifications ?? collect())->isEmpty()) hidden @endif>
                  @csrf
                  <button type="submit" class="notif-clear-button"><svg><use href="#i-trash"/></svg><span>Hapus semua</span></button>
                </form>
              </div>
            </div>
            <div class="notif-list" id="validatorNotifList">
              @forelse(($notifications ?? collect()) as $item)
                <form action="{{ route('admin.notifications.read', $item) }}" method="POST" class="validator-notif-form">
                  @csrf
                  <button type="submit" class="notif-item unread">
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
                <svg aria-hidden="true"><use href="#i-bell"/></svg><span>Aktifkan</span>
              </button>
            </div>
            <div class="notif-dropdown-footer"><a href="{{ route('admin.loading.index', ['status' => 'pending']) }}">Lihat data menunggu</a></div>
          </div>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="staff-logout-form">
          @csrf
          <button type="submit" class="topbar-btn" aria-label="Keluar" title="Keluar"><svg><use href="#i-logout"/></svg></button>
        </form>
      </div>
    </header>

    <main id="mainContent" tabindex="-1">@yield('content')</main>
  </div>
</div>

<nav class="mobile-nav admin-mobile-nav" aria-label="Navigasi Admin">
  <a href="{{ route('admin.dashboard') }}" class="mobile-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><svg><use href="#i-grid"/></svg><span>Ringkasan</span></a>
  <a href="{{ route('admin.loading.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.loading.*') ? 'active' : '' }}">
    <svg><use href="#i-truck"/></svg><span>Loading</span>
    <span class="validator-mobile-count" data-pending-count @if(($pendingTRCount ?? 0) === 0) hidden @endif>{{ $pendingTRCount > 99 ? '99+' : $pendingTRCount }}</span>
  </a>
  <a href="{{ route('admin.reports.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"><svg><use href="#i-file"/></svg><span>Laporan</span></a>
  <a href="{{ route('admin.validators.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.validators.*') ? 'active' : '' }}"><svg><use href="#i-user"/></svg><span>Validator</span></a>
  <button type="button" class="mobile-nav-item {{ request()->routeIs('admin.health.*', 'admin.audit.*', 'admin.scanner-logs.*', 'admin.loading-evidence.*', 'admin.security-officers.*', 'admin.settings.*', 'admin.schedule.*', 'admin.search', 'admin.email-logs.*', 'admin.failed-jobs.*', 'admin.guide.*', 'admin.announcements.*', 'admin.data-maintenance.*', 'admin.form-presence.*', 'account.*') ? 'active' : '' }}" data-admin-menu-open aria-controls="adminMobileMenu" aria-expanded="false"><svg><use href="#i-menu"/></svg><span>Menu</span></button>
</nav>

<div class="admin-mobile-menu" id="adminMobileMenu" hidden>
  <button type="button" class="admin-mobile-menu-backdrop" data-admin-menu-close aria-label="Tutup menu"></button>
  <section class="admin-mobile-menu-panel" role="dialog" aria-modal="true" aria-labelledby="adminMobileMenuTitle">
    <header><div><strong id="adminMobileMenuTitle">Menu Admin</strong><span>Seluruh fitur administrasi</span></div><button type="button" data-admin-menu-close aria-label="Tutup menu"><svg><use href="#i-x"/></svg></button></header>
    <div class="admin-mobile-menu-scroll">
      <x-admin-menu-groups variant="mobile" :pending-count="$pendingTRCount ?? 0" />
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  const todayLabel = document.getElementById('todayLabel');
  if (todayLabel) todayLabel.textContent = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

  document.querySelector('.admin-shell .sidebar-nav .nav-item.active')?.scrollIntoView({ block: 'nearest' });

  const validatorNotifBtn = document.getElementById('validatorNotifBtn');
  const validatorNotifDropdown = document.getElementById('validatorNotifDropdown');
  validatorNotifBtn?.addEventListener('click', event => {
    event.stopPropagation();
    const willOpen = validatorNotifDropdown.hidden;
    validatorNotifDropdown.hidden = !willOpen;
    validatorNotifBtn.setAttribute('aria-expanded', String(willOpen));
  });
  document.addEventListener('click', event => {
    if (validatorNotifDropdown && !validatorNotifDropdown.contains(event.target) && !validatorNotifBtn?.contains(event.target)) {
      validatorNotifDropdown.hidden = true;
      validatorNotifBtn?.setAttribute('aria-expanded', 'false');
    }
  });

  const adminMobileMenu = document.getElementById('adminMobileMenu');
  const adminMenuOpen = document.querySelector('[data-admin-menu-open]');
  const setAdminMenu = open => {
    if (!adminMobileMenu) return;
    adminMobileMenu.hidden = !open;
    adminMenuOpen?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('admin-mobile-menu-open', open);
    if (open) adminMobileMenu.querySelector('[data-admin-menu-close]')?.focus();
    else adminMenuOpen?.focus();
  };
  adminMenuOpen?.addEventListener('click', () => setAdminMenu(true));
  adminMobileMenu?.querySelectorAll('[data-admin-menu-close]').forEach(button => button.addEventListener('click', () => setAdminMenu(false)));
  document.addEventListener('keydown', event => {
    if (adminMobileMenu?.hidden) return;
    if (event.key === 'Escape') {
      setAdminMenu(false);
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...adminMobileMenu.querySelectorAll('a, button:not([disabled]), input:not([disabled])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
</script>
@endpush
