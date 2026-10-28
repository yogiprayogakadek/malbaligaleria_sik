@extends('layouts.admin')

@section('title', 'Daftar Akun : Admin MBG')
@section('page-title', 'Daftar Akun')

@php
  $roleLabels = [
    'admin' => 'Administrator',
    'tenant' => 'Tenant',
    'validator' => 'Validator',
    'secretary' => 'Secretary',
  ];
@endphp

@section('content')
<div class="page-wrap admin-page-wrap admin-account-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Akun</span></div>
      <h1 class="page-title">Daftar akun terdaftar</h1>
      <p class="page-subtitle">Kelola akses staf dan tenant dari satu daftar. Aktivitas penting tetap tercatat di audit log.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>
  @endif
  @if($errors->any())
    <div class="permit-alert permit-alert--danger" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <section class="account-summary" aria-label="Ringkasan akun">
    <div><span>Seluruh akun</span><strong>{{ $summary['total'] }}</strong></div>
    <div><span>Aktif</span><strong>{{ $summary['active'] }}</strong></div>
    <div><span>Nonaktif</span><strong>{{ $summary['inactive'] }}</strong></div>
    <div><span>Akun staf</span><strong>{{ $summary['staff'] }}</strong></div>
  </section>

  <div class="dt-card account-directory">
    <div class="account-directory-head">
      <div>
        <h2>Direktori akun</h2>
        <p>{{ $accounts->total() }} akun sesuai filter. Gunakan penghapusan permanen hanya untuk akun uji atau akun seeder yang tidak lagi diperlukan.</p>
      </div>
      <span>Halaman {{ $accounts->currentPage() }} dari {{ $accounts->lastPage() }}</span>
    </div>
    <form method="GET" action="{{ route('admin.accounts.index') }}" class="account-filters">
      <label class="account-search-field">
        <span>Cari akun</span>
        <span class="account-search-control"><svg><use href="#i-search"/></svg><input type="search" name="q" value="{{ $search }}" placeholder="Nama, email, telepon, atau tenant"></span>
      </label>
      <label>
        <span>Peran</span>
        <select name="role">
          <option value="">Semua peran</option>
          @foreach($roleLabels as $value => $label)<option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>@endforeach
        </select>
      </label>
      <label>
        <span>Divisi</span>
        <select name="division">
          <option value="">Semua divisi</option>
          @foreach(config('divisions') as $code => $label)<option value="{{ $code }}" @selected($division === $code)>{{ $code }} · {{ $label }}</option>@endforeach
        </select>
      </label>
      <label>
        <span>Status</span>
        <select name="status">
          <option value="">Semua status</option>
          <option value="active" @selected($status === 'active')>Aktif</option>
          <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
        </select>
      </label>
      <label>
        <span>Per halaman</span>
        <select name="per_page">
          @foreach([10, 20, 50, 100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} data</option>@endforeach
        </select>
      </label>
      <div class="account-filter-actions">
        <button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Terapkan</button>
        <a href="{{ route('admin.accounts.index') }}" class="admin-secondary-button">Reset</a>
      </div>
    </form>

    @if($accounts->isEmpty())
      <x-data-table-empty
        icon="search"
        title="Akun tidak ditemukan"
        message="Tidak ada akun yang sesuai dengan pencarian dan filter saat ini."
      />
    @else
      <div class="dt-table-responsive account-table-wrap">
        <table class="dt-table account-table">
          <thead><tr><th>AKUN</th><th>PERAN</th><th>KONTAK</th><th>STATUS</th><th>TERDAFTAR</th><th>TINDAKAN</th></tr></thead>
          <tbody>
            @foreach($accounts as $account)
              <tr>
                <td>
                  <div class="dt-user-cell">
                    <span class="dt-avatar admin-validator-dt-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                    <span class="dt-user-info"><strong class="dt-user-name">{{ $account->name }}</strong><small class="dt-user-sub">{{ $account->tenant_name ?: 'ID akun #'.$account->id }}</small></span>
                  </div>
                </td>
                <td><strong class="admin-contact-value">{{ $roleLabels[$account->role] ?? ucfirst($account->role) }}</strong><small class="dt-user-sub">{{ $account->division ? ($account->division.' · '.config("divisions.{$account->division}")) : 'Tanpa divisi' }}</small></td>
                <td><strong class="admin-contact-value">{{ $account->email }}</strong><small class="dt-user-sub">{{ $account->phone }}</small></td>
                <td><span class="dt-badge-status {{ $account->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td><strong class="admin-contact-value">{{ $account->created_at->format('d M Y') }}</strong><small class="dt-user-sub">{{ $account->created_at->format('H:i') }} WITA</small></td>
                <td>
                  @if(auth()->id() === $account->id)
                    <span class="account-current-label">Akun saat ini</span>
                  @else
                    <div class="account-row-actions">
                      <form action="{{ route('admin.accounts.status', $account) }}" method="POST" data-confirm="{{ $account->is_active ? 'Nonaktifkan '.$account->name.'? Seluruh sesi akun akan langsung dicabut.' : 'Aktifkan kembali akun '.$account->name.'?' }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $account->is_active ? 0 : 1 }}">
                        <button type="submit" class="admin-status-button {{ $account->is_active ? 'danger' : '' }}">{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                      </form>
                      @unless($account->is_active)
                        <form action="{{ route('admin.accounts.destroy', $account) }}" method="POST" data-confirm="Hapus permanen akun {{ $account->name }}? Tindakan ini tidak dapat dibatalkan.">
                          @csrf @method('DELETE')
                          <button type="submit" class="account-delete-button" aria-label="Hapus akun {{ $account->name }}"><svg><use href="#i-trash"/></svg><span>Hapus</span></button>
                        </form>
                      @endunless
                    </div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="dt-mobile-list account-mobile-list">
        @foreach($accounts as $account)
          <article class="dt-mobile-card account-mobile-card">
            <div class="dt-mobile-card-header">
              <span class="dt-avatar admin-validator-dt-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
              <div class="dt-mobile-card-main">
                <div class="dt-mobile-card-title-row"><strong class="dt-mobile-card-title">{{ $account->name }}</strong><span class="dt-badge-status {{ $account->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                <div class="dt-mobile-card-meta"><span>{{ $roleLabels[$account->role] ?? ucfirst($account->role) }}</span>@if($account->division)<span>{{ $account->division }}</span>@endif</div>
              </div>
            </div>
            <div class="account-mobile-details"><span>{{ $account->email }}</span><span>{{ $account->phone }}</span><small>{{ $account->tenant_name ?: 'Terdaftar '.$account->created_at->format('d M Y') }}</small></div>
            <div class="dt-mobile-card-footer account-mobile-action">
              @if(auth()->id() === $account->id)
                <span class="account-current-label">Akun saat ini</span>
              @else
                <div class="account-row-actions">
                  <form action="{{ route('admin.accounts.status', $account) }}" method="POST" data-confirm="{{ $account->is_active ? 'Nonaktifkan '.$account->name.'? Seluruh sesi akun akan langsung dicabut.' : 'Aktifkan kembali akun '.$account->name.'?' }}">
                    @csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $account->is_active ? 0 : 1 }}">
                    <button type="submit" class="admin-status-button {{ $account->is_active ? 'danger' : '' }}">{{ $account->is_active ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</button>
                  </form>
                  @unless($account->is_active)
                    <form action="{{ route('admin.accounts.destroy', $account) }}" method="POST" data-confirm="Hapus permanen akun {{ $account->name }}? Tindakan ini tidak dapat dibatalkan.">
                      @csrf @method('DELETE')
                      <button type="submit" class="account-delete-button"><svg><use href="#i-trash"/></svg><span>Hapus akun</span></button>
                    </form>
                  @endunless
                </div>
              @endif
            </div>
          </article>
        @endforeach
      </div>

      <div class="dt-footer">
        <div class="dt-footer-info">Menampilkan <strong>{{ $accounts->firstItem() }}</strong>–<strong>{{ $accounts->lastItem() }}</strong> dari <strong>{{ $accounts->total() }}</strong> akun</div>
        <div class="dt-pagination-nav" role="navigation" aria-label="Navigasi halaman akun">
          @if($accounts->onFirstPage())<span class="dt-page-btn disabled">‹ Prev</span>@else<a href="{{ $accounts->previousPageUrl() }}" class="dt-page-btn">‹ Prev</a>@endif
          @foreach($accounts->getUrlRange(max(1, $accounts->currentPage() - 2), min($accounts->lastPage(), $accounts->currentPage() + 2)) as $page => $url)
            @if($page === $accounts->currentPage())<span class="dt-page-btn active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" class="dt-page-btn">{{ $page }}</a>@endif
          @endforeach
          @if($accounts->hasMorePages())<a href="{{ $accounts->nextPageUrl() }}" class="dt-page-btn">Next ›</a>@else<span class="dt-page-btn disabled">Next ›</span>@endif
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
