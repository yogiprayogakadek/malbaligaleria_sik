@extends('layouts.master')

@push('head')
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="MBG Staff">
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('validator.css') }}">
@endpush

@section('body')
@php
  $isSecretary = Auth::user()->isSecretary();
  $validatorDivision = Auth::user()->division;
  $isTrWorkContext = $validatorDivision === 'TR' && (
    request()->routeIs('tr.work-permits.*') ||
    (isset($permit) && $permit instanceof \App\Models\WorkPermit && $permit->assigned_division === 'TR')
  );
  $validatorProfile = $isSecretary
    ? ['secretary', 'all', 'all', [['all', 'Semua', 'grid'], ['loading', 'Loading', 'truck'], ['work', 'Izin Kerja', 'wrench'], ['today', 'Hari Ini', 'clock']]]
    : match ($validatorDivision) {
    'MEP' => ['mep', 'work', 'action', [['action', 'Antrean', 'clock'], ['all', 'Semua', 'file'], ['approved', 'Disetujui', 'check'], ['refunded', 'Dikembalikan', 'info']]],
    'FIN' => ['finance', 'finance', 'payment_review', [['payment_review', 'Antrean', 'clock'], ['all', 'Semua', 'file'], ['verified', 'Terverifikasi', 'check'], ['payment_revision', 'Revisi', 'info']]],
    default => $isTrWorkContext
      ? ['tr', 'work', 'tr_review', [['tr_review', 'Antrean', 'clock'], ['all', 'Semua', 'file'], ['approved', 'Disetujui', 'check'], ['rejected', 'Ditolak', 'info']]]
      : ['tr', 'loading', 'pending', [['pending', 'Antrean', 'clock'], ['all', 'Semua', 'file'], ['approved', 'Disetujui', 'check'], ['rejected', 'Ditolak', 'info']]],
    };
  [$validatorPrefix, $pendingCategory, $defaultStatus, $validatorNavItems] = $validatorProfile;
  $validatorIndexRoute = $isTrWorkContext ? 'tr.work-permits.index' : $validatorPrefix . '.index';
  $validatorReadRoute = $validatorPrefix . '.notifications.read';
  $validatorClearRoute = $validatorPrefix . '.notifications.clear';
  $validatorFeedRoute = $validatorPrefix . '.notifications.feed';
  $validatorPushStoreRoute = $validatorPrefix . '.push-subscriptions.store';
  $validatorPushDestroyRoute = $validatorPrefix . '.push-subscriptions.destroy';
  $validatorRoutePattern = $isTrWorkContext ? 'tr.work-permits.*' : $validatorPrefix . '.*';
  $staffRoleLabel = $isSecretary ? 'Secretary' : 'Validator ' . $validatorDivision;
  $notificationHeading = $isSecretary ? 'Pemberitahuan Permohonan' : 'Pemberitahuan ' . $validatorDivision;
  $queueLinkLabel = $isSecretary ? 'Lihat semua permohonan' : 'Lihat antrean pemeriksaan';
@endphp
<div class="app-shell" id="appShell"
     data-validator-realtime="{{ Auth::id() }}"
     data-unread-count="{{ $unreadCount ?? 0 }}"
     data-pending-category="{{ $pendingCategory }}"
     data-latest-notification-id="{{ ($notifications ?? collect())->max('id') ?? 0 }}"
     data-notification-feed-url="{{ route($validatorFeedRoute, ['category' => $pendingCategory]) }}"
     data-validator-push
     data-push-public-key="{{ config('webpush.vapid.public_key') }}"
     data-push-store-url="{{ route($validatorPushStoreRoute) }}"
     data-push-destroy-url="{{ route($validatorPushDestroyRoute) }}">
  <aside class="desktop-sidebar" aria-label="Navigasi {{ $staffRoleLabel }}">
    <a href="{{ route($validatorIndexRoute) }}" class="sidebar-logo" aria-label="Dashboard {{ $staffRoleLabel }}">
      <img src="{{ asset('logo.png') }}" alt="MBG" class="sidebar-logo-img">
    </a>

    <nav class="sidebar-nav" aria-label="Menu {{ $staffRoleLabel }}">
      @foreach($validatorNavItems as [$statusKey, $statusLabel, $statusIcon])
        <a href="{{ route($validatorIndexRoute, ['status' => $statusKey]) }}"
           class="nav-item {{ request()->routeIs($validatorRoutePattern) && request('status', $defaultStatus) === $statusKey ? 'active' : '' }}"
           aria-label="{{ $statusLabel }}">
          <svg><use href="#i-{{ $statusIcon }}"/></svg>
          @if($loop->first)<span class="nav-badge" data-pending-count @if(($pendingValidatorCount ?? 0) === 0) hidden @endif>{{ $pendingValidatorCount }}</span>@endif
          <span class="nav-tooltip" @if($loop->first) data-pending-tooltip @endif>{{ $statusLabel }}@if($loop->first) ({{ $pendingValidatorCount ?? 0 }})@endif</span>
        </a>
      @endforeach
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
      <div class="sidebar-user" title="{{ Auth::user()->name }} : {{ $staffRoleLabel }}">
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
        <a href="{{ route($validatorIndexRoute) }}" class="brand-mobile" aria-label="Dashboard {{ $staffRoleLabel }}">
          <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="topbar-logo-mobile">
        </a>
      </div>
      <div class="topbar-center">
        <span class="topbar-title">@yield('page-title', 'Dashboard ' . $staffRoleLabel)</span>
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
              <h3>{{ $notificationHeading }}</h3>
              <div class="notif-header-actions">
                <span class="notif-count" data-notification-count @if(($unreadCount ?? 0) === 0) hidden @endif>{{ $unreadCount ?? 0 }} baru</span>
                <form action="{{ route($validatorClearRoute) }}" method="POST" data-notification-clear @if(($notifications ?? collect())->isEmpty()) hidden @endif onsubmit="return confirm('Hapus semua notifikasi?')">
                  @csrf
                  <button type="submit" class="notif-clear-button"><svg><use href="#i-trash"/></svg><span>Hapus semua</span></button>
                </form>
              </div>
            </div>
            <div class="notif-list" id="validatorNotifList">
              @forelse(($notifications ?? collect()) as $item)
                <form action="{{ route($validatorReadRoute, $item) }}" method="POST" class="validator-notif-form">
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
                <svg aria-hidden="true"><use href="#i-bell"/></svg>
                <span>Aktifkan</span>
              </button>
              <button type="button" class="push-install-button" data-pwa-install hidden>
                Pasang aplikasi
              </button>
            </div>
            <div class="notif-dropdown-footer">
              <a href="{{ route($validatorIndexRoute, ['status' => $defaultStatus]) }}">{{ $queueLinkLabel }}</a>
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

<nav class="mobile-nav" id="mobileNav" aria-label="Navigasi {{ $staffRoleLabel }}">
  @foreach(array_slice($validatorNavItems, 0, 2) as [$statusKey, $statusLabel, $statusIcon])
    <a href="{{ route($validatorIndexRoute, ['status' => $statusKey]) }}" class="mobile-nav-item {{ request()->routeIs($validatorRoutePattern) && request('status', $defaultStatus) === $statusKey ? 'active' : '' }}">
      <svg><use href="#i-{{ $statusIcon }}"/></svg><span>{{ $statusLabel }}</span>
      @if($loop->first)<span class="validator-mobile-count" data-pending-count aria-label="{{ $pendingValidatorCount }} {{ $isSecretary ? 'pemberitahuan baru' : 'permohonan menunggu pemeriksaan' }}" @if(($pendingValidatorCount ?? 0) === 0) hidden @endif>{{ $pendingValidatorCount > 99 ? '99+' : $pendingValidatorCount }}</span>@endif
    </a>
  @endforeach
  <a href="{{ route('scanner.index') }}" class="mobile-nav-fab" aria-label="Verifikasi surat">
    <svg><use href="#i-search"/></svg>
  </a>
  @foreach(array_slice($validatorNavItems, 2) as [$statusKey, $statusLabel, $statusIcon])
    <a href="{{ route($validatorIndexRoute, ['status' => $statusKey]) }}" class="mobile-nav-item {{ request()->routeIs($validatorRoutePattern) && request('status', $defaultStatus) === $statusKey ? 'active' : '' }}"><svg><use href="#i-{{ $statusIcon }}"/></svg><span>{{ $statusLabel }}</span></a>
  @endforeach
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
