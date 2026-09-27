@extends('layouts.master')

@section('body')
<div class="app-shell" id="appShell">

  <!-- Desktop Sidebar: icon-only -->
  <aside class="desktop-sidebar" aria-label="Navigasi utama">
    <a href="{{ route('portal.dashboard') }}" class="sidebar-logo" aria-label="Mal Bali Galeria beranda">
      <img src="{{ asset('logo.png') }}" alt="MBG" class="sidebar-logo-img">
    </a>

    <nav class="sidebar-nav" aria-label="Menu utama">
      <a href="{{ route('portal.dashboard') }}"
         class="nav-item {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}"
         aria-label="Beranda">
        <svg><use href="#i-grid"/></svg>
        <span class="nav-tooltip">Beranda</span>
      </a>

      <a href="{{ route('permits.index') }}"
         class="nav-item {{ request()->routeIs('permits.*') ? 'active' : '' }}"
         aria-label="Permohonan saya">
        <svg><use href="#i-file"/></svg>
        <span class="nav-tooltip">Permohonan</span>
      </a>

      <a href="{{ route('portal.track') }}"
         class="nav-item {{ request()->routeIs('portal.track') || request()->routeIs('loading.track') ? 'active' : '' }}"
         aria-label="Cek status">
        <svg><use href="#i-search"/></svg>
        <span class="nav-tooltip">Cek Status</span>
      </a>

      @if(Auth::check() && ((Auth::user()->role === 'validator' && Auth::user()->division === 'TR') || Auth::user()->role === 'admin'))
        <a href="{{ route('tr.index') }}"
           class="nav-item {{ request()->routeIs('tr.*') ? 'active' : '' }}"
           aria-label="Verifikasi TR">
          <svg><use href="#i-check"/></svg>
          @if(isset($pendingTRCount) && $pendingTRCount > 0)
            <span class="nav-badge">{{ $pendingTRCount }}</span>
          @endif
          <span class="nav-tooltip">Verifikasi TR ({{ $pendingTRCount ?? 0 }})</span>
        </a>
      @endif

      <a href="{{ route('scanner.index') }}"
         class="nav-item {{ request()->routeIs('scanner.*') ? 'active' : '' }}"
         aria-label="Scanner QR">
        <svg><use href="#i-box"/></svg>
        <span class="nav-tooltip">Scanner QR</span>
      </a>

      <a href="{{ route('portal.help') }}"
         class="nav-item {{ request()->routeIs('portal.help') ? 'active' : '' }}"
         aria-label="Bantuan">
        <svg><use href="#i-help"/></svg>
        <span class="nav-tooltip">Bantuan</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      @auth
        <div class="sidebar-user" title="{{ Auth::user()->name }} ({{ Auth::user()->role }})">
          <span class="sidebar-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="logout-form">
          @csrf
          <button type="submit" class="nav-item nav-item-logout" id="logoutBtn" aria-label="Keluar dari portal" title="Keluar"><svg><use href="#i-logout"/></svg><span class="nav-tooltip">Keluar</span></button>
        </form>
      @else
        <a href="{{ route('login') }}" class="nav-item" aria-label="Masuk" title="Masuk">
          <svg><use href="#i-user"/></svg>
          <span class="nav-tooltip">Masuk</span>
        </a>
      @endauth
    </div>
  </aside>

  <!-- Main Content Shell -->
  <div class="content-shell">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <a href="{{ route('portal.dashboard') }}" class="brand-mobile" aria-label="MBG Portal">
          <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="topbar-logo-mobile">
        </a>
      </div>
      <div class="topbar-center">
        <span class="topbar-title" id="topbarTitle">@yield('page-title', 'Beranda')</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-date" id="todayLabel"></span>
        <a href="{{ route('portal.track') }}" class="topbar-btn" aria-label="Cek status" title="Cek Status">
          <svg><use href="#i-search"/></svg>
        </a>

        <!-- Notification Bell with Dropdown -->
        <div class="notif-wrapper">
          <button type="button" class="topbar-btn notif-btn" id="notifBtn" aria-label="Notifikasi" aria-expanded="false" title="Pemberitahuan">
            <svg><use href="#i-bell"/></svg>
            @if(isset($unreadCount) && $unreadCount > 0)
              <span class="notif-badge">{{ $unreadCount }}</span>
            @endif
          </button>

          <!-- Notification Dropdown Panel -->
          <div class="notif-dropdown" id="notifDropdown" hidden>
            <div class="notif-dropdown-header">
              <h3>Pemberitahuan</h3>
              @if(isset($unreadCount) && $unreadCount > 0)
                <span class="notif-count">{{ $unreadCount }} Baru</span>
              @endif
            </div>

            <div class="notif-list">
              @auth
                @if(isset($notifications) && $notifications->count() > 0)
                  @foreach($notifications as $item)
                    @php
                      $targetUrl = route('permits.index');
                      if ($item->permit) {
                        $targetUrl = $item->type === 'approved'
                          ? route('loading.letter', $item->permit->permit_number)
                          : route('loading.show', $item->permit->permit_number);
                      }
                    @endphp
                    <a href="{{ $targetUrl }}" class="notif-item {{ $item->isUnread() ? 'unread' : '' }}">
                      <div class="notif-icon notif-icon--{{ $item->type === 'approved' ? 'green' : ($item->type === 'rejected' ? 'rose' : 'blue') }}">
                        <svg><use href="#i-{{ $item->type === 'approved' ? 'check' : ($item->type === 'rejected' ? 'info' : 'clock') }}"/></svg>
                      </div>
                      <div class="notif-body">
                        <p class="notif-title">{{ $item->title }}</p>
                        <p class="notif-desc">{{ $item->body }}</p>
                        <span class="notif-time">{{ $item->created_at->diffForHumans() }}</span>
                      </div>
                    </a>
                  @endforeach
                @else
                  <div class="notif-empty">
                    <svg width="24" height="24"><use href="#i-bell"/></svg>
                    <p>Belum ada pemberitahuan baru.</p>
                  </div>
                @endif
              @else
                <div class="notif-empty">
                  <p>Masuk ke akun tenant untuk melihat riwayat pemberitahuan permohonan Anda.</p>
                  <a href="{{ route('login') }}" class="btn-auth-sm">Masuk Akun</a>
                </div>
              @endauth
            </div>

            @auth
              <div class="notif-dropdown-footer">
                <a href="{{ route('permits.index') }}">Lihat semua permohonan &rarr;</a>
              </div>
            @endauth
          </div>
        </div>

        @auth
          <form action="{{ route('logout') }}" method="POST" class="logout-form">
            @csrf
            <button type="submit" class="topbar-btn" id="mobileLogoutBtn" aria-label="Keluar dari portal" title="Keluar"><svg><use href="#i-logout"/></svg></button>
          </form>
        @else
          <a href="{{ route('login') }}" class="topbar-btn" id="mobileLoginBtn" aria-label="Masuk ke portal" title="Masuk">
            <svg><use href="#i-user"/></svg>
          </a>
        @endauth
      </div>
    </header>

    <main id="mainContent" tabindex="-1">
      @yield('content')
      @yield('page-content')
    </main>

  </div><!-- /.content-shell -->

</div><!-- /#appShell -->

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav" id="mobileNav" aria-label="Navigasi utama">
  <a href="{{ route('portal.dashboard') }}"
     class="mobile-nav-item {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
    <svg><use href="#i-grid"/></svg>
    <span>Beranda</span>
  </a>
  <a href="{{ route('permits.index') }}"
     class="mobile-nav-item {{ request()->routeIs('permits.*') ? 'active' : '' }}">
    <svg><use href="#i-file"/></svg>
    <span>Permohonan</span>
  </a>
  <button type="button" class="mobile-nav-fab" id="createShortcut" aria-label="Buat permohonan baru">
    <svg><use href="#i-plus"/></svg>
  </button>
  @if(Auth::check() && ((Auth::user()->role === 'validator' && Auth::user()->division === 'TR') || Auth::user()->role === 'admin'))
    <a href="{{ route('tr.index') }}"
       class="mobile-nav-item {{ request()->routeIs('tr.*') ? 'active' : '' }}">
      <svg><use href="#i-check"/></svg>
      <span>TR ({{ $pendingTRCount ?? 0 }})</span>
    </a>
  @else
    <a href="{{ route('portal.track') }}"
       class="mobile-nav-item {{ request()->routeIs('portal.track') || request()->routeIs('loading.track') ? 'active' : '' }}">
      <svg><use href="#i-search"/></svg>
      <span>Status</span>
    </a>
  @endif
  <a href="{{ route('portal.help') }}"
     class="mobile-nav-item {{ request()->routeIs('portal.help') ? 'active' : '' }}">
    <svg><use href="#i-help"/></svg>
    <span>Bantuan</span>
  </a>
</nav>

<!-- Mobile Create Sheet Modal -->
<div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
<div class="create-sheet" id="createSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
  <div class="sheet-drag-handle"></div>
  <div class="sheet-header">
    <div>
      <p class="sheet-eyebrow">PERMOHONAN BARU</p>
      <h2 id="sheetTitle">Pilih Jenis Izin</h2>
    </div>
    <button type="button" class="sheet-close-btn" id="closeSheet" aria-label="Tutup panel">
      <svg><use href="#i-plus"/></svg>
    </button>
  </div>
  <div class="sheet-list">
    <a href="{{ route('loading.create') }}" class="sheet-option">
      <div class="sheet-option-icon sheet-option-icon--blue"><svg><use href="#i-box"/></svg></div>
      <div class="sheet-option-label">
        <span>Loading &amp; Unloading Barang</span>
        <small>Logistik keluar-masuk gedung</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </a>
    <a href="{{ route('permits.create', ['type' => 'work']) }}" class="sheet-option">
      <div class="sheet-option-icon sheet-option-icon--violet"><svg><use href="#i-wrench"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Kerja (SIK)</span>
        <small>Renovasi dan pekerjaan teknis</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </a>
    <a href="{{ route('permits.create', ['type' => 'exhibition']) }}" class="sheet-option">
      <div class="sheet-option-icon sheet-option-icon--rose"><svg><use href="#i-gallery"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Pameran</span>
        <small>Display dan aktivasi brand</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </a>
    <a href="{{ route('permits.create', ['type' => 'event']) }}" class="sheet-option">
      <div class="sheet-option-icon sheet-option-icon--amber"><svg><use href="#i-calendar"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Acara &amp; Kegiatan</span>
        <small>Event dan agenda khusus tenant</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </a>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Inisialisasi Tanggal Lokal Indonesia
  const todayLabel = document.getElementById('todayLabel');
  if (todayLabel) {
    todayLabel.textContent = new Intl.DateTimeFormat('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    }).format(new Date());
  }

  // Pengendali Mobile Sheet Modal
  const createShortcut = document.getElementById('createShortcut');
  const closeSheet = document.getElementById('closeSheet');
  const sheet = document.getElementById('createSheet');
  const backdrop = document.getElementById('sheetBackdrop');

  function openCreateSheet() {
    if (sheet) sheet.hidden = false;
    if (backdrop) backdrop.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeCreateSheet() {
    if (sheet) sheet.hidden = true;
    if (backdrop) backdrop.hidden = true;
    document.body.style.overflow = '';
  }

  if (createShortcut) createShortcut.addEventListener('click', openCreateSheet);
  if (closeSheet) closeSheet.addEventListener('click', closeCreateSheet);
  if (backdrop) backdrop.addEventListener('click', closeCreateSheet);

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sheet && !sheet.hidden) {
      closeCreateSheet();
    }
  });

  // Pengendali Dropdown Notifikasi
  const notifBtn = document.getElementById('notifBtn');
  const notifDropdown = document.getElementById('notifDropdown');

  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isHidden = notifDropdown.hidden;
      notifDropdown.hidden = !isHidden;
      notifBtn.setAttribute('aria-expanded', String(!isHidden));
    });

    document.addEventListener('click', (e) => {
      if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
        notifDropdown.hidden = true;
        notifBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
</script>
@endpush
